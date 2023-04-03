<?php

class WC_Reepay_Order_Statuses {
	/**
	 * Constructor
	 */
	public function __construct() {
		$settings = get_option( 'woocommerce_reepay_checkout_settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		define( 'REEPAY_STATUS_SYNC', ! isset( $settings['enable_sync'] ) || $settings['enable_sync'] == 'yes' );
		define( 'REEPAY_STATUS_CREATED', isset( $settings['status_created'] ) ? str_replace( 'wc-', '', $settings['status_created'] ) : 'pending' );
		define( 'REEPAY_STATUS_AUTHORIZED', isset( $settings['status_authorized'] ) ? str_replace( 'wc-', '', $settings['status_authorized'] ) : 'on-hold' );
		define( 'REEPAY_STATUS_SETTLED', isset( $settings['status_settled'] ) ? str_replace( 'wc-', '', $settings['status_settled'] ) : 'processing' );

		add_filter(
			'reepay_checkout_form_fields',
			array(
				$this,
				'form_fields',
			),
			10,
			2
		);

		// Add statuses for payment complete
		add_filter(
			'woocommerce_valid_order_statuses_for_payment_complete',
			array(
				$this,
				'add_valid_order_statuses',
			),
			10,
			2
		);

		add_filter(
			'woocommerce_payment_complete_order_status',
			array(
				$this,
				'payment_complete_order_status',
			),
			10,
			3
		);

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 10 );

		add_action( 'woocommerce_payment_complete', array( $this, 'payment_complete' ), 10, 1 );

		add_filter(
			'reepay_authorized_order_status',
			array(
				$this,
				'reepay_authorized_order_status',
			),
			10,
			2
		);

		add_filter(
			'reepay_settled_order_status',
			array(
				$this,
				'reepay_settled_order_status',
			),
			10,
			2
		);

		add_filter(
			'wc_order_is_editable',
			array(
				$this,
				'is_editable',
			),
			10,
			2
		);

		add_filter(
			'woocommerce_order_is_paid',
			array(
				$this,
				'is_paid',
			),
			10,
			2
		);

		add_filter(
			'woocommerce_cancel_unpaid_order',
			array(
				$this,
				'cancel_unpaid_order',
			),
			10,
			2
		);
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return;
		}

		// Add actions for complete statuses
		$statuses = wc_get_order_statuses();
		foreach ( $statuses as $status => $label ) {
			$status = str_replace( 'wc-', '', $status );
			add_action(
				'woocommerce_payment_complete_order_status_' . $status,
				array(
					$this,
					'payment_complete',
				),
				10,
				1
			);
		}
	}

	/**
	 * Add Settings
	 *
	 * @param $form_fields
	 *
	 * @return mixed
	 */
	public function form_fields( $form_fields ) {

		$form_fields['hr_sync'] = array(
			'type' => 'separator',
		);

		$form_fields['enable_sync'] = array(
			'title'       => __( 'Sync statuses', 'reepay-checkout-gateway' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable sync', 'reepay-checkout-gateway' ),
			'description' => __( '2-way synchronization of order statuses in Woocommerce with invoice statuses in Reepay', 'reepay-checkout-gateway' ),
			'default'     => 'yes',
		);

		$pending_statuses = wc_get_order_statuses();
		unset(
			$pending_statuses['wc-processing'],
			$pending_statuses['wc-on-hold'],
			$pending_statuses['wc-completed'],
			$pending_statuses['wc-cancelled'],
			$pending_statuses['wc-refunded'],
			$pending_statuses['wc-failed']
		);

		$form_fields['status_created'] = array(
			'title'   => __( 'Status: Reepay Created', 'reepay-checkout-gateway' ),
			'type'    => 'select',
			'options' => $pending_statuses,
			'default' => 'wc-pending',
		);

		/**
		 * wc-cancelled, wc-refunded, wc-failed
		 */

		$authorized_statuses = wc_get_order_statuses();
		unset(
			$authorized_statuses['wc-pending'],
			$authorized_statuses['wc-cancelled'],
			$authorized_statuses['wc-refunded'],
			$authorized_statuses['wc-failed']
		);

		$form_fields['status_authorized'] = array(
			'title'   => __( 'Status: Reepay Authorized', 'reepay-checkout-gateway' ),
			'type'    => 'select',
			'options' => $authorized_statuses,
			'default' => 'wc-on-hold',
		);

		$settled_statuses = wc_get_order_statuses();
		unset(
			$settled_statuses['wc-pending'],
			$settled_statuses['wc-cancelled'],
			$settled_statuses['wc-refunded'],
			$settled_statuses['wc-failed']
		);

		$form_fields['status_settled'] = array(
			'title'   => __( 'Status: Reepay Settled', 'reepay-checkout-gateway' ),
			'type'    => 'select',
			'options' => $settled_statuses,
			'default' => 'wc-processing',
		);

		return $form_fields;
	}

	/**
	 * Allow processing/completed statuses for capture
	 *
	 * @param array    $statuses
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function add_valid_order_statuses( $statuses, $order ) {
		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			$statuses = array_merge( $statuses, array( REEPAY_STATUS_AUTHORIZED, REEPAY_STATUS_SETTLED ) );
		}

		return $statuses;
	}

	/**
	 * Get Status For Payment Complete.
	 *
	 * @param string   $status
	 * @param int      $order_id
	 * @param WC_Order $order
	 *
	 * @return mixed|string
	 */
	public function payment_complete_order_status( $status, $order_id, $order ) {
		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			$status = apply_filters(
				'reepay_settled_order_status',
				$order->needs_processing() ? 'processing' : 'completed',
				$order
			);
		}

		return $status;
	}

	/**
	 * Payment Complete.
	 *
	 * @param $order_id
	 */
	public function payment_complete( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			$status = apply_filters(
				'reepay_settled_order_status',
				REEPAY_STATUS_SETTLED,
				$order
			);

			if ( ! $order->has_status( $status ) ) {
				$order->set_status( $status );
				$order->save();
			}
		}
	}

	/**
	 * Get a status for Authorized payments.
	 *
	 * @param string   $status
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function reepay_authorized_order_status( $status, $order ) {
		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			if ( order_contains_subscription( $order ) ) {
				$status = 'on-hold';
			} elseif ( REEPAY_STATUS_SYNC ) {
				$status = REEPAY_STATUS_AUTHORIZED;
			}
		}

		return $status;
	}

	/**
	 * Get a status for Settled payments.
	 *
	 * @param string   $status
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function reepay_settled_order_status( $status, $order ) {
		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) && REEPAY_STATUS_SYNC ) {

			$status = REEPAY_STATUS_SETTLED;
		}

		return $status;
	}

	/**
	 * Set Authorized Status.
	 *
	 * @param  WC_Order    $order
	 * @param  string|null $note
	 * @param  string|null $transaction_id
	 *
	 * @return void
	 * @throws WC_Data_Exception Throws exception when invalid data sent to update_order_status.
	 */
	public static function set_authorized_status( WC_Order $order, $note, $transaction_id ) {
		$authorized_status = apply_filters( 'reepay_authorized_order_status', 'on-hold', $order );

		if ( ! empty( $order->get_meta( '_reepay_state_authorized' ) ) || $order->get_status() == $authorized_status ) {
			return;
		}

		// Reduce stock
		$order_stock_reduced = $order->get_meta( '_order_stock_reduced' );
		if ( ! $order_stock_reduced ) {
			wc_reduce_stock_levels( $order->get_id() );
		}

		self::update_order_status(
			$order,
			$authorized_status,
			$note,
			$transaction_id
		);

		$order->update_meta_data( '_reepay_state_authorized', 1 );
		$order->save_meta_data();
	}

	/**
	 * Set Settled Status.
	 *
	 * @param  WC_Order    $order
	 * @param  string|null $note
	 * @param  string|null $transaction_id
	 *
	 * @return void
	 * @throws WC_Data_Exception If cannot change order status.
	 */
	public static function set_settled_status( WC_Order $order, $note, $transaction_id ) {
		// Check if the payment has been settled fully

		$payment_method = $order->get_payment_method();
		if ( ! in_array( $payment_method, WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			return;
		}

		if ( '1' === $order->get_meta( '_reepay_state_settled' ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->payment_gateways();

		/** @var WC_Gateway_Reepay_Checkout $gateway */
		$gateway = $gateways[ $payment_method ];

		$invoice = $gateway->api->get_invoice_data( $order );
		if ( $invoice['settled_amount'] < $invoice['authorized_amount'] ) {
			// Use the authorized status if order has been settled partially
			self::set_authorized_status( $order, $note, $transaction_id );
		} else {
			// Settled status should be assigned using the hook defined there
			// @see WC_Reepay_Order_Statuses::payment_complete()
			// @see WC_Reepay_Order_Statuses::payment_complete_order_status()
			if ( ! empty( $order->get_meta( '_reepay_state_settled' ) ) ) {
				return;
			}

			$order->payment_complete( $transaction_id );

			if ( $note ) {
				$order->add_order_note( $note );
			}

			$order->update_meta_data( '_reepay_state_settled', 1 );
			$order->save_meta_data();
		}
	}

	/**
	 * Update Order Status.
	 *
	 * @param  WC_Order    $order
	 * @param  string      $new_status
	 * @param  string      $note
	 * @param  string|null $transaction_id
	 * @param  bool        $manual
	 *
	 * @return void
	 * @throws WC_Data_Exception Throws exception when invalid data sent to set_transaction_id.
	 */
	public static function update_order_status( $order, $new_status, $note = '', $transaction_id = null, $manual = false ) {
		remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40 );

		// Disable status change hook
		remove_action( 'woocommerce_order_status_changed', 'WC_Reepay_Admin::order_status_changed' );

		if ( ! empty( $transaction_id ) ) {
			$order->set_transaction_id( $transaction_id );
		}

		// Update status
		$order->set_status( $new_status, $note, $manual );
		$order->save();

		// Enable status change hook
		add_action( 'woocommerce_order_status_changed', 'WC_Reepay_Admin::order_status_changed', 10, 4 );
	}

	/**
	 * Checks if an order can be edited, specifically for use on the Edit Order screen.
	 *
	 * @param $is_editable
	 * @param WC_Order    $order
	 *
	 * @return bool
	 */
	public function is_editable( $is_editable, $order ) {
		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			if ( in_array( $order->get_status(), array( REEPAY_STATUS_CREATED, REEPAY_STATUS_AUTHORIZED ) ) ) {
				$is_editable = true;
			}
		}

		return $is_editable;
	}

	/**
	 * Returns if an order has been paid for based on the order status.
	 *
	 * @param $is_paid
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public function is_paid( $is_paid, $order ) {
		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			if ( in_array( $order->get_status(), array( REEPAY_STATUS_SETTLED ) ) ) {
				$is_paid = true;
			}
		}

		return $is_paid;
	}

	/**
	 * Prevent the pending cancellation for Reepay Orders if allowed
	 *
	 * @param bool     $maybe_cancel
	 * @param WC_Order $order
	 *
	 * @return bool
	 * @see wc_cancel_unpaid_orders()
	 */
	public function cancel_unpaid_order( $maybe_cancel, $order ) {
		if ( in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			$gateways       = WC()->payment_gateways()->get_available_payment_gateways();
			$payment_method = $order->get_payment_method();
			if ( isset( $gateways[ $payment_method ] ) ) {
				/** @var WC_Gateway_Reepay $gateway */
				$gateway = $gateways[ $payment_method ];

				// Now set the flag if auto-cancel is enabled or not
				if ( 'yes' !== $gateway->enable_order_autocancel ) {
					return false;
				}
			}
		}

		return $maybe_cancel;
	}

}

new WC_Reepay_Order_Statuses();
