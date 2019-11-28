<?php

class WC_Reepay_Order_Statuses {
	/**
	 * Constructor
	 */
	public function __construct() {
		$settings = get_option( 'woocommerce_reepay_checkout_settings' );
		if ( !is_array( $settings ) ) {
			$settings = array();
		}

		define('REEPAY_STATUS_CREATED', isset( $settings['status_created'] ) ? str_replace( 'wc-', '', $settings['status_created'] ) : 'pending' );
		define('REEPAY_STATUS_AUTHORIZED', isset( $settings['status_authorized'] ) ? str_replace( 'wc-', '', $settings['status_authorized'] ) : 'on-hold' );
		define('REEPAY_STATUS_SETTLED', isset( $settings['status_settled'] ) ? str_replace( 'wc-', '', $settings['status_settled'] ) : 'processing' );

		add_filter( 'woocommerce_settings_api_form_fields_reepay_checkout', array(
			$this,
			'form_fields'
		), 10, 2 );

		add_filter( 'woocommerce_create_order', array(
			$this,
			'woocommerce_create_order'
		), 10, 2 );

		// Add statuses for payment complete
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array(
			$this,
			'add_valid_order_statuses'
		), 10, 2 );

		add_filter( 'woocommerce_payment_complete_order_status', array(
			$this,
			'woocommerce_payment_complete_order_status'
		), 10, 3 );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 10 );

		add_action( 'woocommerce_payment_complete', array($this, 'woocommerce_payment_complete'), 10, 1 );

		add_filter( 'reepay_authorized_order_status', array(
			$this,
			'reepay_authorized_order_status'
		), 10, 3 );

		// Status Change Actions
		add_action( 'woocommerce_order_status_changed', __CLASS__ . '::order_status_changed', 10, 4 );

		// Woocommerce Subscriptions
		//add_filter( 'woocommerce_can_subscription_be_updated_to', array($this, 'subscription_be_updated_to'), 10, 3 );

		add_filter( 'wc_order_is_editable', array(
			$this,
			'is_editable'
		), 10, 2 );

		add_filter( 'woocommerce_order_is_paid', array(
			$this,
			'is_paid'
		), 10, 2 );
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		// Add actions for complete statuses
		$statuses = wc_get_order_statuses();
		foreach ($statuses as $status => $label) {
			$status = str_replace('wc-', '', $status);
			add_action( 'woocommerce_payment_complete_order_status_' . $status, array($this, 'woocommerce_payment_complete'), 10, 1 );
		}
	}

	/**
	 * Add Settings
	 * @param $form_fields
	 *
	 * @return mixed
	 */
	public function form_fields($form_fields) {
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
			'title'       => __( 'Status: Reepay Created', 'woocommerce-gateway-reepay-checkout' ),
			'type'        => 'select',
			'options'     => $pending_statuses,
			'default'     => 'wc-pending'
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
			'title'       => __( 'Status: Reepay Authorized', 'woocommerce-gateway-reepay-checkout' ),
			'type'        => 'select',
			'options'     => $authorized_statuses,
			'default'     => 'wc-on-hold'
		);

		$settled_statuses = wc_get_order_statuses();
		unset(
			$settled_statuses['wc-pending'],
			$settled_statuses['wc-cancelled'],
			$settled_statuses['wc-refunded'],
			$settled_statuses['wc-failed']
		);

		$form_fields['status_settled'] = array(
			'title'       => __( 'Status: Reepay Settled', 'woocommerce-gateway-reepay-checkout' ),
			'type'        => 'select',
			'options'     => $settled_statuses,
			'default'     => 'wc-processing'
		);

		return $form_fields;
	}

	/**
	 * @see WC_Checkout::create_order()
	 * @param int $order_id
	 * @param WC_Checkout $checkout
	 *
	 * @return int|WP_Error
	 */
	public function woocommerce_create_order($order_id, $checkout)
	{
		$data = $checkout->get_posted_data();

		try {
			$order_id           = absint( WC()->session->get( 'order_awaiting_payment' ) );
			$cart_hash          = md5( wp_json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$order              = $order_id ? wc_get_order( $order_id ) : null;

			/**
			 * If there is an order pending payment, we can resume it here so
			 * long as it has not changed. If the order has changed, i.e.
			 * different items or cost, create a new order. We use a hash to
			 * detect changes which is based on cart items + order total.
			 */
			if ( $order && $order->has_cart_hash( $cart_hash ) && $order->has_status( array( REEPAY_STATUS_CREATED, 'failed' ) ) ) {
				// Action for 3rd parties.
				do_action( 'woocommerce_resume_order', $order_id );

				// Remove all items - we will re-add them later.
				$order->remove_order_items();
			} else {
				$order = new WC_Order();
			}

			$fields_prefix = array(
				'shipping'  => true,
				'billing'   => true,
			);
			$shipping_fields = array(
				'shipping_method'   => true,
				'shipping_total'    => true,
				'shipping_tax'      => true,
			);
			foreach ( $data as $key => $value ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );
					// Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
				} elseif ( isset( $fields_prefix[ current( explode( '_', $key ) ) ] ) ) {
					if ( ! isset( $shipping_fields[ $key ] ) ) {
						$order->update_meta_data( '_' . $key, $value );
					}
				}
			}

			$order->set_created_via( 'checkout' );
			$order->set_cart_hash( $cart_hash );
			$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
			$order->set_currency( get_woocommerce_currency() );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
			$order->set_customer_user_agent( wc_get_user_agent() );
			$order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
			$order->set_payment_method( isset( $available_gateways[ $data['payment_method'] ] ) ? $available_gateways[ $data['payment_method'] ] : $data['payment_method'] );
			$order->set_shipping_total( WC()->cart->get_shipping_total() );
			$order->set_discount_total( WC()->cart->get_discount_total() );
			$order->set_discount_tax( WC()->cart->get_discount_tax() );
			$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
			$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
			$order->set_total( WC()->cart->get_total( 'edit' ) );

			$checkout->create_order_line_items( $order, WC()->cart );
			$checkout->create_order_fee_lines( $order, WC()->cart );
			$checkout->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping->get_packages() );
			$checkout->create_order_tax_lines( $order, WC()->cart );
			$checkout->create_order_coupon_lines( $order, WC()->cart );

			/**
			 * Action hook to adjust order before save.
			 *
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order', $order, $data );

			// Save the order.
			$order_id = $order->save();

			do_action( 'woocommerce_checkout_update_order_meta', $order_id, $data );

			return $order_id;
		} catch ( Exception $e ) {
			return new WP_Error( 'checkout-error', $e->getMessage() );
		}
	}

	/**
	 * Allow processing/completed statuses for capture
	 *
	 * @param array $statuses
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function add_valid_order_statuses( $statuses, $order ) {
		if ( $order->get_payment_method() === 'reepay_checkout' ) {
			$statuses = array_merge( $statuses, array( REEPAY_STATUS_AUTHORIZED, REEPAY_STATUS_SETTLED ) );
		}

		return $statuses;
	}

	/**
	 * Get Status For Payment Complete
	 * @param string $status
	 * @param int $order_id
	 * @param WC_Order $order
	 *
	 * @return mixed|string
	 */
	public function woocommerce_payment_complete_order_status( $status, $order_id, $order ) {
		if ( $order->get_payment_method() === 'reepay_checkout' ) {
			$status = REEPAY_STATUS_SETTLED;
		}

		return $status;
	}

	/**
	 * Payment Complete
	 * @param $order_id
	 */
	public function woocommerce_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_payment_method() === 'reepay_checkout' && ! $order->has_status( REEPAY_STATUS_SETTLED ) ) {
			if ( WC_Payment_Gateway_Reepay::order_contains_subscription( $order ) ) {
				$order->add_order_note( __( 'Payment completed', 'woocommerce-gateway-reepay-checkout' ) );
			} else {
				$order->set_status( REEPAY_STATUS_SETTLED );
				$order->save();
			}
		}
	}

	/**
	 * Get Status For Payment Complete
	 * @param string $status
	 * @param int $order_id
	 * @param WC_Order $order
	 *
	 * @return mixed|string
	 */
	public function reepay_authorized_order_status( $status, $order_id, $order ) {
		if ( $order->get_payment_method() === 'reepay_checkout' ) {
			if ( WC_Payment_Gateway_Reepay::order_contains_subscription( $order ) ) {
				$status = 'on-hold';
			} else {
				$status = REEPAY_STATUS_AUTHORIZED;
			}
		}

		return $status;
	}

	/**
	 * Order Status Change: Capture/Cancel
	 *
	 * @param $order_id
	 * @param $from
	 * @param $to
	 * @param WC_Order $order
	 */
	public static function order_status_changed( $order_id, $from, $to, $order ) {
		$payment_method = $order->get_payment_method();
		if ( ! in_array( $payment_method, WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			return;
		}

		if ( WC_Payment_Gateway_Reepay::order_contains_subscription( $order ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_Reepay_Checkout $gateway */
		$gateway = 	$gateways[ $payment_method ];

		if ( ! $gateway ) {
			return;
		}

		switch ( $to ) {
			case 'cancelled':
				// Cancel payment
				if ( $gateway->can_cancel( $order ) ) {
					try {
						$gateway->cancel_payment( $order );
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error( $message );

						// Rollback
						$order->update_status( $from, sprintf( __( 'Order status rollback. %s', 'woocommerce-gateway-reepay-checkout' ), $message ) );
					}
				}
				break;
			case REEPAY_STATUS_SETTLED:
				// Capture payment
				if ( $gateway->can_capture( $order ) ) {
					try {
						$gateway->capture_payment( $order );
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error( $message );

						// Rollback
						$order->update_status( $from, sprintf( __( 'Order status rollback. %s', 'woocommerce-gateway-reepay-checkout' ), $message ) );
					}
				}
				break;
			default:
				// no break
		}
	}

	/**
	 * Allow statuses for update.
	 *
	 * @param $can_be_updated
	 * @param $new_status
	 * @param WC_Subscription $subscription
	 *
	 * @return bool
	 */
	public function subscription_be_updated_to($can_be_updated, $new_status, $subscription)
	{
		if ( $subscription->get_payment_method() === 'reepay_checkout' && $new_status === 'processing' ) {
			$can_be_updated = true;
		}

		return $can_be_updated;
	}

	/**
	 * Checks if an order can be edited, specifically for use on the Edit Order screen.
	 *
	 * @param $is_editable
	 * @param WC_Order $order
	 * @return bool
	 */
	public function is_editable( $is_editable, $order ) {
		if ( $order->get_payment_method() === 'reepay_checkout' ) {
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
	 * @return bool
	 */
	public function is_paid( $is_paid, $order ) {
		if ( $order->get_payment_method() === 'reepay_checkout' ) {
			if ( in_array( $order->get_status(), array( REEPAY_STATUS_SETTLED ) ) ) {
				$is_paid = true;
			}
		}

		return $is_paid;
	}

}

new WC_Reepay_Order_Statuses();
