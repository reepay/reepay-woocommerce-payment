<?php
/**
 * Class for handling reepay hooks
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use Reepay\Checkout\LoggingTrait;
use WC_Order_Item_Fee;
use WC_Subscriptions_Manager;
use WP_Error;

defined( 'ABSPATH' ) || exit();

/**
 * Class Webhook
 *
 * @package Reepay\Checkout\OrderFlow
 */
class Webhook {
	use LoggingTrait;

	/**
	 * Logging source
	 *
	 * @var string
	 */
	private string $logging_source = 'reepay-webhook';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_' . strtolower( 'WC_Gateway_Reepay' ), array( $this, 'return_handler' ) );
	}

	/**
	 * WebHook Callback
	 *
	 * @return void
	 */
	public function return_handler() {
		http_response_code( 200 );

		$raw_body = file_get_contents( 'php://input' );
		$this->log( sprintf( 'WebHook: Initialized %s from %s', $_SERVER['REQUEST_URI'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$this->log( sprintf( 'WebHook: Post data: %s', var_export( $raw_body, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$data = json_decode( $raw_body, true );
		if ( ! $data ) {
			$this->log( 'WebHook: Error: Missing parameters' );

			return;
		}

		$secret = get_transient( 'reepay_webhook_settings_secret' );

		if ( empty( $secret ) ) {
			$result = reepay()->api( $this->logging_source )->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
			if ( is_wp_error( $result ) ) {
				$this->log( 'WebHook: Error: ' . $result->get_error_message() );
				return;
			}

			$secret = $result['secret'];

			set_transient( 'reepay_webhook_settings_secret', $secret, HOUR_IN_SECONDS );
		}

		$check = bin2hex( hash_hmac( 'sha256', $data['timestamp'] . $data['id'], $secret, true ) );
		if ( $check !== $data['signature'] ) {
			$this->log( 'WebHook: Error: Signature verification failed' );

			return;
		}

		try {
			$this->process( $data );
		} catch ( Exception $e ) {
			$this->log( 'WebHook: Error:' . $e->getMessage() );
		}

		http_response_code( 200 );
	}

	/**
	 * Process WebHook.
	 *
	 * @param array $data data from Reepay.
	 *
	 * @return void
	 * @throws Exception If invalid data.
	 *
	 * @todo split switch into methods
	 * @todo remove code duplication
	 */
	public function process( array $data ) {
		do_action( 'reepay_webhook', $data );

		$this->log(
			sprintf(
				'WebHook %1$s: Data: %2$s',
				$data['event_type'],
				var_export( $data, true ) //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			)
		);

		switch ( $data['event_type'] ) {
			case 'invoice_authorized':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				if ( $order->has_status( OrderStatuses::$status_sync_enabled ? OrderStatuses::$status_authorized : 'on-hold' ) ) {
					$this->log(
						sprintf(
							'WebHook: Event type: %s success. But the order had status early: %s',
							$data['event_type'],
							$order->get_status()
						)
					);

					http_response_code( 200 );

					return;
				}

				self::lock_order( $order->get_id() );

				$order->set_transaction_id( $data['transaction'] );
				$order->save();

				$invoice_data = reepay()->api( $order )->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', wp_json_encode( $invoice_data, JSON_PRETTY_PRINT ) ) );

				OrderStatuses::set_authorized_status(
					$order,
					sprintf(
					// translators: %1$s - order amount, %2$s - transaction id.
						__( 'Payment has been authorized. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $invoice_data['amount'], $order->get_currency() ) ),
						$data['transaction']
					),
					$data['transaction']
				);

				do_action( 'reepay_instant_settle', $order );

				self::unlock_order( $order->get_id() );

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_authorized', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );

				if ( ! empty( $invoice_data['order_lines'] ) ) {
					foreach ( $invoice_data['order_lines'] as $invoice_line ) {
						$is_exist = false;
						foreach ( $order->get_items( 'fee' ) as $item ) {
							if ( $item['name'] === $invoice_line['ordertext'] ) {
								$is_exist = true;
							}
						}

						if ( ! $is_exist ) {
							if ( 'surcharge_fee' === $invoice_line['origin'] ) {
								$fees_item = new WC_Order_Item_Fee();
								$fees_item->set_name( $invoice_line['ordertext'] );
								$fees_item->set_amount( floatval( $invoice_line['unit_amount'] ) / 100 );
								$fees_item->set_total( floatval( $invoice_line['amount'] ) / 100 );
								$fees_item->add_meta_data( '_is_card_fee', true );
								$order->add_item( $fees_item );
							}

							$order->calculate_totals();
							$order->save();
							$order->calculate_totals();
						}
					}
				}

				break;
			case 'invoice_settled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				if ( $order->has_status( OrderStatuses::$status_settled ) ) {
					$this->log(
						sprintf(
							'WebHook: Event type: %s success. But the order had status early: %s',
							$data['event_type'],
							$order->get_status()
						)
					);

					http_response_code( 200 );

					return;
				}

				self::lock_order( $order->get_id() );

				$invoice_data = reepay()->api( $order )->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', var_export( $invoice_data, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

				if ( ! empty( $invoice_data['id'] ) && ! empty( $data['transaction'] ) ) {
					$transaction = reepay()->api( $order )->request( 'GET', 'https://api.reepay.com/v1/invoice/' . $invoice_data['id'] . '/transaction/' . $data['transaction'] );
					$this->log( sprintf( 'WebHook: Transaction data: %s', var_export( $transaction, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

					if ( ! empty( $transaction['card_transaction']['card'] ) ) {
						if ( ! empty( $transaction['card_transaction']['error'] ) && ! empty( $transaction['card_transaction']['acquirer_message'] ) ) {
							$order->add_order_note( 'Item settle error: ' . $transaction['card_transaction']['acquirer_message'] );

							return;
						}
					}
				}

				OrderStatuses::set_settled_status(
					$order,
					false,
					$data['transaction']
				);

				update_post_meta( $order->get_id(), '_reepay_capture_transaction', $data['transaction'] );

				self::unlock_order( $order->get_id() );

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_settled', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_cancelled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$order->set_transaction_id( $data['transaction'] );
				$order->save();

				if ( $order->has_status( 'cancelled' ) ) {
					$this->log(
						sprintf(
							'WebHook: Event type: %s success. Order status: %s',
							$data['event_type'],
							$order->get_status()
						)
					);

					http_response_code( 200 );

					return;
				}

				$order->update_status(
					'cancelled',
					__( 'Cancelled by WebHook.', 'reepay-checkout-gateway' )
				);

				$order->update_meta_data( '_reepay_cancel_transaction', $data['transaction'] );
				$order->save_meta_data();

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_cancelled', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_refund':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$order = rp_get_order_by_handle( $data['invoice'] );

				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$sub_order = get_post_meta( $order->get_id(), '_reepay_subscription_handle', true );
				if ( ! empty( $sub_order ) ) {
					return;
				}

				$invoice_data = reepay()->api( $order )->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					$invoice_data = array();
				}

				$credit_notes = $invoice_data['credit_notes'];
				foreach ( $credit_notes as $credit_note ) {
					// Get registered credit notes.
					$credit_note_ids = $order->get_meta( '_reepay_credit_note_ids' );
					if ( ! is_array( $credit_note_ids ) ) {
						$credit_note_ids = array();
					}

					// Check is refund already registered.
					if ( in_array( $credit_note['id'], $credit_note_ids, true ) ) {
						continue;
					}

					$credit_note_id = $credit_note['id'];
					$amount         = rp_make_initial_amount( $credit_note['amount'], $order->get_currency() );
					$reason         = sprintf(
					// translators: #%s credit note id.
						__( 'Credit Note Id #%s.', 'reepay-checkout-gateway' ),
						$credit_note_id
					);

					$refund = wc_create_refund(
						array(
							'amount'   => $amount,
							'reason'   => '', // don't add Credit note to refund line.
							'order_id' => $order->get_id(),
						)
					);

					if ( $refund ) {
						$credit_note_ids[] = $credit_note_id;
						$order->update_meta_data( '_reepay_credit_note_ids', $credit_note_ids );
						$order->save_meta_data();

						$order->add_order_note(
							sprintf(
							// translators: %1$s refund amount, %2$s refund reason.
								__( 'Refunded: %1$s. Reason: %2$s', 'reepay-checkout-gateway' ),
								wc_price( $amount ),
								$reason
							)
						);
					}
				}

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_refund', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_created':
				if ( ! isset( $data['invoice'] ) || ! isset( $data['subscription'] ) ) {
					$this->log( $data );
					throw new Exception( 'Missing invoice or subscription parameter' );
				}

				$order = rp_get_order_by_subscription_handle( $data['subscription'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );
					do_action( 'reepay_webhook_invoice_created', $data );

					return;
				} else {
					$this->log( sprintf( 'WebHook: Order is found. Order: %s', $order ) );
				}

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_created', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_created':
				$customer = $data['customer'];
				$user_id  = rp_get_userid_by_handle( $customer );
				if ( ! $user_id ) {
					if ( strpos( $customer, 'customer-' ) !== false ) {
						$user_id = (int) str_replace( 'customer-', '', $customer );
						if ( $user_id > 0 ) {
							update_user_meta( $user_id, 'reepay_customer_id', $customer );
							$this->log( sprintf( 'WebHook: Customer created: %s', var_export( $customer, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
						}
					}

					if ( ! $user_id ) {
						$this->log( sprintf( 'WebHook: Customer doesn\'t exists: %s', var_export( $customer, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					}
				}

				$data['user_id'] = $user_id;
				do_action( 'reepay_webhook_customer_created', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_payment_method_added':
				if ( ! empty( $data['payment_method_reference'] ) ) {
					$order = rp_get_order_by_session( $data['payment_method_reference'] );
					if ( $order && order_contains_subscription( $order ) ) {
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					}
				}

				do_action( 'reepay_webhook_customer_payment_method_added', $data );

				break;
			default:
				global $wp_filter;
				$this->log( sprintf( 'WebHook: %s', $data['event_type'] ) );
				$base_hook_name    = 'reepay_webhook_raw_event';
				$current_hook_name = "{$base_hook_name}_{$data['event_type']}";

				if ( isset( $wp_filter[ $base_hook_name ] ) || isset( $wp_filter[ $current_hook_name ] ) ) {
					do_action( $base_hook_name, $data );
					do_action( $current_hook_name, $data );
				} else {
					$this->log( sprintf( 'WebHook: Unknown event type: %s', $data['event_type'] ) );
					http_response_code( 200 );

					return;
				}
		}
	}

	/**
	 * Lock the order.
	 *
	 * @param int $order_id order id to lock.
	 *
	 * @return void
	 * @see wait_for_unlock()
	 */
	private static function lock_order( int $order_id ) {
		update_post_meta( $order_id, '_reepay_locked', '1' );
	}

	/**
	 * Unlock the order.
	 *
	 * @param mixed $order_id order id to unlock.
	 *
	 * @return void
	 * @see wait_for_unlock()
	 */
	private static function unlock_order( $order_id ) {
		delete_post_meta( $order_id, '_reepay_locked' );
	}

	/**
	 * Wait for unlock.
	 *
	 * @param int $order_id order id to wait.
	 *
	 * @return bool
	 */
	private static function wait_for_unlock( int $order_id ): bool {
		set_time_limit( 0 );

		$is_locked    = (bool) get_post_meta( $order_id, '_reepay_locked', true );
		$needs_reload = false;
		$attempts     = 0;
		while ( $is_locked ) {
			usleep( 500 );
			$attempts ++;
			if ( $attempts > 30 ) {
				break;
			}

			wp_cache_delete( $order_id, 'post_meta' );
			$is_locked = (bool) get_post_meta( $order_id, '_reepay_locked', true );
			if ( $is_locked ) {
				$needs_reload = true;
				clean_post_cache( $order_id );
			}
		}

		return $needs_reload;
	}
}
