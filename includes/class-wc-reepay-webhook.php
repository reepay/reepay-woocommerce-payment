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

		do_action( 'reepay_webhook', $data );

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

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_authorized', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );

				if ( ! empty( $invoice_data['order_lines'] ) ) {
					foreach ( $invoice_data['order_lines'] as $invoice_lines ) {
						$is_exist = false;
						foreach ( $order->get_items( 'fee' ) as $item ) {
							if ( $item['name'] == $invoice_lines['ordertext'] ) {
								$is_exist = true;
							}
						}

						if ( ! $is_exist ) {
							if ( $invoice_lines['origin'] == 'surcharge_fee' ) {
								$fees_item = new WC_Order_Item_Fee();
								$fees_item->set_name( $invoice_lines['ordertext'] );
								$fees_item->set_amount( floatval( $invoice_lines['unit_amount'] ) / 100 );
								$fees_item->set_total( floatval( $invoice_lines['amount'] ) / 100 );
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

				$this->log( sprintf( 'WebHook: Invoice settled: %s', var_export( $data['invoice'], true ) ) );

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


				if ( ! empty( $invoice_data['id'] ) && ! empty( $data['transaction'] ) ) {
					$transaction = $gateway->api->request( 'GET', 'https://api.reepay.com/v1/invoice/' . $invoice_data['id'] . '/transaction/' . $data['transaction'] );
					$this->log( sprintf( 'WebHook: Transaction data: %s', var_export( $transaction, true ) ) );

					if ( ! empty( $transaction['card_transaction']['card'] ) ) {
						if ( ! empty( $transaction['card_transaction']['error'] ) && ! empty( $transaction['card_transaction']['acquirer_message'] ) ) {
							$order->add_order_note( 'Item settle error: ' . $transaction['card_transaction']['acquirer_message'] );

							return;
						}
					}
				}


				WC_Reepay_Order_Statuses::set_settled_status(
					$order,
					false,
					$data['transaction']
				);

				update_post_meta( $order->get_id(), '_reepay_capture_transaction', $data['transaction'] );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_settled', $data );

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

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_cancelled', $data );

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

				$sub_order = get_post_meta( $order->get_id(), '_reepay_subscription_handle', true );
				if ( ! empty( $sub_order ) ) {
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

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_refund', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_created':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$this->log( sprintf( 'WebHook: Invoice created: %s', var_export( $data, true ) ) );

				// Get Order by handle
				$order = rp_get_order_by_handle( $data['invoice'] );
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
							$this->log( sprintf( 'WebHook: Customer created: %s', var_export( $customer, true ) ) );
						}
					}

					if ( ! $user_id ) {
						$this->log( sprintf( 'WebHook: Customer doesn\'t exists: %s', var_export( $customer, true ) ) );
					}
				}

				$data['user_id'] = $user_id;
				do_action( 'reepay_webhook_customer_created', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_payment_method_added':
				// @todo
				$this->log( sprintf( 'WebHook: TODO: customer_payment_method_added: %s', var_export( $data, true ) ) );
				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );

				if ( ! empty( $data['payment_method_reference'] ) ) {
					$order = rp_get_order_by_session( $data['payment_method_reference'] );
					if ( order_contains_subscription( $order ) ) {
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					}
				}

				do_action( 'reepay_webhook_customer_payment_method_added', $data );

				break;
			default:
				global $wp_filter;
				$this->log( sprintf( 'WebHook: %s', $data['event_type'] ) );
				$base_hook_name    = "reepay_webhook_raw_event";
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
