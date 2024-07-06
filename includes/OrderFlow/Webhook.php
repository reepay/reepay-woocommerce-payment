<?php
/**
 * Class for handling reepay hooks
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Billwerk\Sdk\Enum\CustomerEventEnum;
use Billwerk\Sdk\Enum\InvoiceEventEnum;
use Billwerk\Sdk\Exception\BillwerkApiException;
use Billwerk\Sdk\Model\Invoice\InvoiceGetModel;
use Billwerk\Sdk\Model\Transaction\TransactionGetModel;
use Exception;
use Reepay\Checkout\Utils\LoggingTrait;
use Reepay\Checkout\Tokens\ReepayTokens;
use Reepay\Checkout\Utils\TimeKeeper;
use WC_Data_Exception;
use WC_Order;
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

	public const LOG_SOURCE = 'webhook';

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
	 * @throws Exception Exception.
	 */
	public function return_handler() {
		http_response_code( 200 );

		$raw_body = file_get_contents( 'php://input' );
		$data     = json_decode( $raw_body, true );
		reepay()->log( self::LOG_SOURCE )->info(
			sprintf(
				'Request %s from %s',
				$_SERVER['REQUEST_URI'] ?? '',
				$_SERVER['REMOTE_ADDR'] ?? ''
			),
			array(
				'data' => $data,
			),
		);
		if ( ! $data ) {
			reepay()->log( self::LOG_SOURCE )->error(
				'Missing parameters',
			);

			return;
		}

		$secret = get_transient( 'reepay_webhook_settings_secret' );

		if ( empty( $secret ) ) {
			try {
				$webhook_settings = reepay()->sdk()->account()->getWebHookSettings();
				$secret           = $webhook_settings->getSecret();

				set_transient( 'reepay_webhook_settings_secret', $secret, HOUR_IN_SECONDS );
			} catch ( BillwerkApiException $e ) {
				return;
			}
		}

		$check = bin2hex( hash_hmac( 'sha256', $data['timestamp'] . $data['id'], $secret, true ) );
		if ( $check !== $data['signature'] ) {
			reepay()->log( self::LOG_SOURCE )->error(
				'Signature verification failed',
			);

			return;
		}

		try {
			$this->process( $data );
		} catch ( Exception $e ) {
			reepay()->log( self::LOG_SOURCE )->error(
				$e->getMessage(),
			);
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
	 */
	public function process( array $data ) {
		do_action( 'reepay_webhook', $data );

		reepay()->log( self::LOG_SOURCE )->info(
			sprintf( 'WebHook type %s', $data['event_type'] ),
		);

		switch ( $data['event_type'] ) {
			case InvoiceEventEnum::INVOICE_AUTHORIZED:
				$this->process_invoice_authorized( $data );
				break;
			case InvoiceEventEnum::INVOICE_SETTLED:
				$this->process_invoice_settled( $data );
				break;
			case InvoiceEventEnum::INVOICE_CANCELLED:
				$this->process_invoice_cancelled( $data );
				break;
			case InvoiceEventEnum::INVOICE_REFUND:
				$this->process_invoice_refund( $data );
				break;
			case InvoiceEventEnum::INVOICE_CREATED:
				$this->process_invoice_created( $data );
				break;
			case CustomerEventEnum::CUSTOMER_CREATED:
				$this->process_customer_created( $data );
				break;
			case CustomerEventEnum::CUSTOMER_PAYMENT_METHOD_ADDED:
				$this->process_customer_payment_method_added( $data );
				break;
			default:
				global $wp_filter;
				$base_hook_name    = 'reepay_webhook_raw_event';
				$current_hook_name = "{$base_hook_name}_{$data['event_type']}";

				if ( isset( $wp_filter[ $base_hook_name ] ) || isset( $wp_filter[ $current_hook_name ] ) ) {
					do_action( $base_hook_name, $data );
					do_action( $current_hook_name, $data );
				} else {
					reepay()->log( self::LOG_SOURCE )->error(
						sprintf(
							'Unknown event type: %s',
							$data['event_type'],
						),
					);
					http_response_code( 200 );

					return;
				}
		}
	}

	/**
	 * Process webhook type InvoiceEventEnum::CUSTOMER_PAYMENT_METHOD_ADDED
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function process_customer_payment_method_added( array $data ) {
		if ( ! empty( $data['payment_method_reference'] ) ) {
			$order = rp_get_order_by_session( $data['payment_method_reference'] );
			if ( $order && order_contains_subscription( $order ) ) {
				WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );

				if ( ! empty( $data['payment_method'] ) ) {
					try {
						$token = ReepayTokens::reepay_save_token( $order, $data['payment_method'] );
						ReepayTokens::assign_payment_token( $order, $token );
						ReepayTokens::save_card_info_to_order( $order, $token->get_token() );

						$order->payment_complete();
					} catch ( Exception $e ) {
						$order->add_order_note( $e->getMessage() );
						reepay()->log( self::LOG_SOURCE )->error(
							sprintf(
								'Token save error: %s',
								$e->getMessage(),
							),
						);
						return;
					}
				}
			}
		}

		do_action( 'reepay_webhook_customer_payment_method_added', $data );
	}

	/**
	 * Process webhook type InvoiceEventEnum::CUSTOMER_CREATED
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function process_customer_created( array $data ) {
		$customer = $data['customer'];
		$user_id  = rp_get_user_id_by_handle( $customer );
		if ( ! $user_id ) {
			if ( strpos( $customer, 'customer-' ) !== false ) {
				$user_id = (int) str_replace( 'customer-', '', $customer );
				if ( $user_id > 0 ) {
					update_user_meta( $user_id, 'reepay_customer_id', $customer );
					reepay()->log( self::LOG_SOURCE )->info(
						'Customer created',
						array(
							'customer' => $customer,
						)
					);
				}
			}

			if ( ! $user_id ) {
				reepay()->log( self::LOG_SOURCE )->warning(
					'Customer doesn\'t exists',
					array(
						'customer' => $customer,
					)
				);
			}
		}

		$data['user_id'] = $user_id;
		do_action( 'reepay_webhook_customer_created', $data );

		reepay()->log( self::LOG_SOURCE )->info(
			sprintf(
				'Event type: %s success',
				$data['event_type'],
			),
		);
	}

	/**
	 * Process webhook type InvoiceEventEnum::INVOICE_CREATED
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function process_invoice_created( array $data ) {
		if ( ! isset( $data['invoice'] ) || ! isset( $data['subscription'] ) ) {
			reepay()->log( self::LOG_SOURCE )->error(
				'Missing invoice or subscription parameter'
			);
			throw new Exception( 'Missing invoice or subscription parameter' );
		}

		$order = rp_get_order_by_subscription_handle( $data['subscription'] );
		if ( ! $order ) {
			reepay()->log( self::LOG_SOURCE )->error(
				sprintf( 'Order is not found. Invoice: %s', $data['invoice'] )
			);
			do_action( 'reepay_webhook_invoice_created', $data );

			return;
		} else {
			reepay()->log( self::LOG_SOURCE )->info(
				sprintf( 'Order is found. Order: %s', $order->get_id() )
			);
		}

		$data['order_id'] = $order->get_id();
		do_action( 'reepay_webhook_invoice_created', $data );

		reepay()->log( self::LOG_SOURCE )->info(
			sprintf(
				'Event type: %s success',
				$data['event_type'],
			),
		);
	}

	/**
	 * Process webhook type InvoiceEventEnum::INVOICE_REFUND
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function process_invoice_refund( array $data ) {
		$this->check_invoice_in_data( $data );

		$order = rp_get_order_by_handle( $data['invoice'] );
		if ( ! $order ) {
			reepay()->log( self::LOG_SOURCE )->warning(
				sprintf( 'Order is not found. Invoice: %s', $data['invoice'] ),
			);
			return;
		}

		$sub_order = $order->get_meta( '_reepay_subscription_handle' );
		if ( ! empty( $sub_order ) ) {
			return;
		}

		try {
			$invoice = reepay()->sdk()->invoice()->get(
				( new InvoiceGetModel() )
					->setId( $data['invoice'] )
			);
		} catch ( Exception $e ) {
			$invoice = null;
		}

		foreach ( $invoice->getCreditNotes() as $credit_note ) {
			// Get registered credit notes.
			$credit_note_ids = $order->get_meta( '_reepay_credit_note_ids' );
			if ( ! is_array( $credit_note_ids ) ) {
				$credit_note_ids = array();
			}

			// Check is refund already registered.
			$credit_note_id = $credit_note->getId();
			if ( in_array( $credit_note_id, $credit_note_ids, true ) ) {
				continue;
			}

			$amount = rp_make_initial_amount( $credit_note->getAmount(), $order->get_currency() );
			$reason = sprintf(
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

		reepay()->log( self::LOG_SOURCE )->info(
			sprintf(
				'Event type: %s success',
				$data['event_type'],
			),
		);
	}

	/**
	 * Process webhook type InvoiceEventEnum::INVOICE_CANCELLED
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function process_invoice_cancelled( array $data ) {
		$this->check_invoice_in_data( $data );

		$order = rp_get_order_by_handle( $data['invoice'] );
		if ( ! $order ) {
			reepay()->log( self::LOG_SOURCE )->warning(
				sprintf( 'Order is not found. Invoice: %s', $data['invoice'] ),
			);
			return;
		}

		$order->set_transaction_id( $data['transaction'] );
		$order->save();

		if ( $order->has_status( 'cancelled' ) ) {
			reepay()->log( self::LOG_SOURCE )->info(
				sprintf(
					'Event type: %s success. Order status: %s',
					$data['event_type'],
					$order->get_status()
				),
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

		reepay()->log( self::LOG_SOURCE )->info(
			sprintf(
				'Event type: %s success',
				$data['event_type'],
			),
		);
	}

	/**
	 * Process webhook type InvoiceEventEnum::INVOICE_SETTLED
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function process_invoice_settled( array $data ) {
		$this->check_invoice_in_data( $data );

		$order = $this->get_order_and_wait_unlock( $data );
		if ( ! $order ) {
			return;
		}

		if ( $order->has_status( OrderStatuses::$status_settled ) ) {
			reepay()->log( self::LOG_SOURCE )->warning(
				sprintf(
					'Event type: %s success. But the order had status early: %s',
					$data['event_type'],
					$order->get_status()
				),
			);

			http_response_code( 200 );

			return;
		}

		self::lock_order( $order->get_id() );

		try {
			$invoice = reepay()->sdk()->invoice()->get(
				( new InvoiceGetModel() )
					->setId( $data['invoice'] )
			);
		} catch ( Exception $e ) {
			$invoice = null;
		}
		reepay()->log( self::LOG_SOURCE )->info(
			sprintf( 'Invoice %s', $data['invoice'] ),
			array(
				'invoice' => $invoice ?? $invoice->toArray(),
			)
		);

		if ( ! empty( $invoice->getId() ) && ! empty( $data['transaction'] ) ) {
			$transaction = reepay()->sdk()->transaction()->get(
				( new TransactionGetModel() )
					->setId( $invoice->getId() )
					->setTransaction( $data['transaction'] )
			);
			reepay()->log( self::LOG_SOURCE )->info(
				sprintf( 'Transaction %s', $data['transaction'] ),
				array(
					'transaction' => $transaction ?? $transaction->toArray(),
				)
			);

			$card_transaction = $transaction->getCardTransaction();
			if ( $card_transaction && $card_transaction->getCard() ) {
				if ( $card_transaction->getError() ) {
					$order->add_order_note( 'Item settle error: ' . $card_transaction->getAcquirerMessage() );

					return;
				}
			}
		}

		if ( empty( $order->get_meta( '_reepay_subscription_handle' ) ) ) {
			OrderStatuses::set_settled_status(
				$order,
				false,
				$data['transaction']
			);
		}

		$order->update_meta_data( '_reepay_capture_transaction', $data['transaction'] );
		$order->save_meta_data();

		self::unlock_order( $order->get_id() );

		$data['order_id'] = $order->get_id();
		do_action( 'reepay_webhook_invoice_settled', $data );

		// Need for analytics.
		$order->set_date_paid( TimeKeeper::get() );
		reepay()->log( self::LOG_SOURCE )->info(
			sprintf(
				'Event type: %s success',
				$data['event_type'],
			),
		);
	}

	/**
	 * Process webhook type InvoiceEventEnum::INVOICE_AUTHORIZED
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function process_invoice_authorized( array $data ) {
		$this->check_invoice_in_data( $data );

		$order = $this->get_order_and_wait_unlock( $data );
		if ( ! $order ) {
			return;
		}

		if ( $order->has_status( OrderStatuses::$status_sync_enabled ? OrderStatuses::$status_authorized : 'on-hold' ) ) {
			reepay()->log( self::LOG_SOURCE )->warning(
				sprintf( 'WebHook type %s success. But the order had status early: %s', $data['event_type'], $order->get_status() ),
			);

			http_response_code( 200 );

			return;
		}

		self::lock_order( $order->get_id() );

		$order->set_transaction_id( $data['transaction'] );
		$order->save();

		try {
			$invoice = reepay()->sdk()->invoice()->get(
				( new InvoiceGetModel() )
					->setId( $data['invoice'] )
			);
		} catch ( Exception $e ) {
			$invoice = null;
		}

		reepay()->log( self::LOG_SOURCE )->info(
			sprintf( 'Invoice %s', $data['invoice'] ),
			array(
				'invoice' => $invoice ?? $invoice->toArray(),
			)
		);

		OrderStatuses::set_authorized_status(
			$order,
			sprintf(
			// translators: %1$s - order amount, %2$s - transaction id.
				__( 'Payment has been authorized. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
				wc_price(
					rp_make_initial_amount(
						$invoice->getAmount(),
						$order->get_currency()
					)
				),
				$data['transaction']
			),
			$data['transaction']
		);

		do_action( 'reepay_instant_settle', $order );

		self::unlock_order( $order->get_id() );

		$data['order_id'] = $order->get_id();
		do_action( 'reepay_webhook_invoice_authorized', $data );

		// Need for analytics.
		$order->set_date_paid( TimeKeeper::get() );

		reepay()->log( self::LOG_SOURCE )->info(
			sprintf( 'WebHook type %s success', $data['event_type'] ),
		);

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
	}

	/**
	 * Get order and wait unlock order
	 *
	 * @param array $data webhook data.
	 *
	 * @return WC_Order|null
	 * @throws Exception Exception.
	 */
	public function get_order_and_wait_unlock( array $data ): ?WC_Order {
		$order = rp_get_order_by_handle( $data['invoice'] );
		if ( ! $order ) {
			reepay()->log( self::LOG_SOURCE )->warning(
				sprintf( 'Order is not found. Invoice: %s', $data['invoice'] ),
			);

			return null;
		}

		$needs_reload = self::wait_for_unlock( $order->get_id() );
		if ( $needs_reload ) {
			$order = wc_get_order( $order->get_id() );
		}
		return $order;
	}

	/**
	 * Checks the existence of an order
	 *
	 * @param array $data webhook data.
	 *
	 * @return bool
	 * @throws Exception Exception.
	 */
	public function is_exist_order_by_handle( array $data ): bool {
		$order = rp_get_order_by_handle( $data['invoice'] );
		if ( ! $order ) {
			reepay()->log( self::LOG_SOURCE )->warning(
				sprintf( 'Order is not found. Invoice: %s', $data['invoice'] ),
			);
			return false;
		}
		return true;
	}

	/**
	 * Checks if there is an invoice in the webhook
	 *
	 * @param array $data webhook data.
	 *
	 * @return void
	 * @throws Exception Exception.
	 */
	public function check_invoice_in_data( array $data ) {
		if ( ! isset( $data['invoice'] ) ) {
			throw new Exception( 'Missing Invoice parameter' );
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
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_reepay_locked', '1' );
		$order->save_meta_data();
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
		$order = wc_get_order( $order_id );
		$order->delete_meta_data( '_reepay_locked' );
		$order->save_meta_data();
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

		$order        = wc_get_order( $order_id );
		$is_locked    = (bool) $order->get_meta( '_reepay_locked' );
		$needs_reload = false;
		$attempts     = 0;
		while ( $is_locked ) {
			usleep( 500 );
			++$attempts;
			if ( $attempts > 30 ) {
				break;
			}

			wp_cache_delete( $order_id, 'post_meta' );
			$is_locked = (bool) $order->get_meta( '_reepay_locked' );
			if ( $is_locked ) {
				$needs_reload = true;
				clean_post_cache( $order_id );
			}
		}

		return $needs_reload;
	}
}
