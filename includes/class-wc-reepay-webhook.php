<?php

class WC_Reepay_Webhook {
	use WC_Reepay_Log;

	/**
	 * @var string
	 */
	private $logging_source = 'reepay-webhook';

	/**
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * Process WebHook.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function process() {
		$data = $this->data;

		// Check invoice state
		switch ( $data['event_type'] ) {
			case 'invoice_authorized':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				// Check transaction is applied
				//if ( $order->get_transaction_id() === $data['transaction'] ) {
					//$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );

					//return;
				//}

				// Wait to be unlocked
				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				// Check if the order has been marked as authorized before
				if ( $order->has_status( REEPAY_STATUS_AUTHORIZED ) ) {
					$this->log( sprintf( 'WebHook: Event type: %s success. But the order had status early: %s',
						$data['event_type'],
						$order->get_status()
					) );

					http_response_code( 200 );

					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				// Add transaction ID
				$order->set_transaction_id( $data['transaction'] );
				$order->save();

				// Fetch the Invoice data at the moment
				$gateway      = rp_get_payment_method( $order );
				$invoice_data = $gateway->api->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					/** @var WP_Error $result */
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', json_encode( $invoice_data, JSON_PRETTY_PRINT ) ) );

				// set order as authorized
				WC_Reepay_Order_Statuses::set_authorized_status(
					$order,
					sprintf(
						__( 'Payment has been authorized. Amount: %s. Transaction: %s', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $invoice_data['amount'], $order->get_currency() ) ),
						$data['transaction']
					),
					$data['transaction']
				);

				// Settle an authorized payment instantly if possible
				do_action( 'reepay_instant_settle', $order );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_settled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				// Wait to be unlocked
				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				// Check transaction is applied
				//if ( $order->get_transaction_id() === $data['transaction'] ) {
					//$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );

					//return;
				//}

				// Check if the order has been marked as settled before
				if ( $order->has_status( REEPAY_STATUS_SETTLED ) ) {
					$this->log( sprintf( 'WebHook: Event type: %s success. But the order had status early: %s',
						$data['event_type'],
						$order->get_status()
					) );

					http_response_code( 200 );

					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				// Fetch the Invoice data at the moment
				$gateway      = rp_get_payment_method( $order );
				$invoice_data = $gateway->api->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					/** @var WP_Error $result */
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', var_export( $invoice_data, true ) ) );

				WC_Reepay_Order_Statuses::set_settled_status(
					$order,
					sprintf(
						__( 'Payment has been settled. Amount: %s. Transaction: %s',
							'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $invoice_data['amount'], $order->get_currency() ) ),
						$data['transaction']
					),
					$data['transaction']
				);

				update_post_meta( $order->get_id(), '_reepay_capture_transaction', $data['transaction'] );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_cancelled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				// Check transaction is applied
				//if ( $order->get_transaction_id() === $data['transaction'] ) {
					//$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );

					//return;
				//}

				// Add transaction ID
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
				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_refund':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				// Get Invoice data
				$gateway      = rp_get_payment_method( $order );
				$invoice_data = $gateway->api->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					/** @var WP_Error $result */
					$invoice_data = array();
				}

				$credit_notes = $invoice_data['credit_notes'];
				foreach ( $credit_notes as $credit_note ) {
					// Get registered credit notes
					$credit_note_ids = $order->get_meta( '_reepay_credit_note_ids' );
					if ( ! is_array( $credit_note_ids ) ) {
						$credit_note_ids = array();
					}

					// Check is refund already registered
					if ( in_array( $credit_note['id'], $credit_note_ids ) ) {
						continue;
					}

					$credit_note_id = $credit_note['id'];
					$amount         = rp_make_initial_amount( $credit_note['amount'], $order->get_currency() );
					$reason         = sprintf(
						__( 'Credit Note Id #%s.', 'reepay-checkout-gateway' ),
						$credit_note_id
					);

					// Create Refund
					$refund = wc_create_refund( array(
						'amount'   => $amount,
						'reason'   => '', // don't add Credit note to refund line
						'order_id' => $order->get_id()
					) );

					if ( $refund ) {
						// Save Credit Note ID
						$credit_note_ids = array_merge( $credit_note_ids, $credit_note_id );
						$order->update_meta_data( '_reepay_credit_note_ids', $credit_note_ids );
						$order->save_meta_data();

						$order->add_order_note(
							sprintf( __( 'Refunded: %s. Reason: %s', 'reepay-checkout-gateway' ),
								wc_price( $amount ),
								$reason
							)
						);
					}
				}

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_created':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$this->log( sprintf( 'WebHook: Invoice created: %s', var_export( $data['invoice'], true ) ) );

				// Get Order by handle
				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

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
							$this->log( sprintf( 'WebHook: Customer created: %s', var_export( $customer, true ) ) );
						}
					}

					if ( ! $user_id ) {
						$this->log( sprintf( 'WebHook: Customer doesn\'t exists: %s', var_export( $customer, true ) ) );
					}
				}

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_payment_method_added':
				// @todo
				$this->log( sprintf( 'WebHook: TODO: customer_payment_method_added: %s', var_export( $data, true ) ) );
				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			default:
				$this->log( sprintf( 'WebHook: Unknown event type: %s', $data['event_type'] ) );
				throw new Exception( sprintf( 'Unknown event type: %s', $data['event_type'] ) );
		}
	}

	/**
	 * Lock the order.
	 *
	 * @param mixed $order_id
	 *
	 * @return void
	 * @see wait_for_unlock()
	 */
	private static function lock_order( $order_id ) {
		update_post_meta( $order_id, '_reepay_locked', '1' );
	}

	/**
	 * Unlock the order.
	 *
	 * @param mixed $order_id
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
	 * @param $order_id
	 *
	 * @return bool
	 */
	private static function wait_for_unlock( $order_id ) {
		@set_time_limit( 0 );
		@ini_set( 'max_execution_time', '0' );

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
