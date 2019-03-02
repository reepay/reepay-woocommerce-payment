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

		add_filter( 'reepay_authorized_order_status', array(
			$this,
			'reepay_authorized_order_status'
		), 10, 3 );
	}

	/**
	 * Add Settings
	 * @param $form_fields
	 *
	 * @return mixed
	 */
	public function form_fields($form_fields) {
		$form_fields['status_created'] = array(
			'title'       => __( 'Status: Reepay Created', 'woocommerce-gateway-reepay-checkout' ),
			'type'        => 'select',
			'options'     => wc_get_order_statuses(),
			'default'     => 'wc-pending'
		);

		$form_fields['status_authorized'] = array(
			'title'       => __( 'Status: Reepay Authorized', 'woocommerce-gateway-reepay-checkout' ),
			'type'        => 'select',
			'options'     => wc_get_order_statuses(),
			'default'     => 'wc-on-hold'
		);

		$form_fields['status_settled'] = array(
			'title'       => __( 'Status: Reepay Settled', 'woocommerce-gateway-reepay-checkout' ),
			'type'        => 'select',
			'options'     => wc_get_order_statuses(),
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
	 * Get Status For Payment Complete
	 * @param string $status
	 * @param int $order_id
	 * @param WC_Order $order
	 *
	 * @return mixed|string
	 */
	public function reepay_authorized_order_status( $status, $order_id, $order ) {
		if ( $order->get_payment_method() === 'reepay_checkout' ) {
			$status = REEPAY_STATUS_AUTHORIZED;
		}

		return $status;
	}
}

new WC_Reepay_Order_Statuses();
