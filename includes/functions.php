<?php

if ( ! function_exists( 'rp_get_payment_method ' ) ) {
	/**
	 * Get Payment Method.
	 *
	 * @param WC_Order $order
	 *
	 * @return false|WC_Gateway_Reepay_Checkout
	 */
	function rp_get_payment_method( WC_Order $order ) {
		$gateways = WC()->payment_gateways()->payment_gateways();

		return $gateways[ $order->get_payment_method() ] ?? false;
	}
}

if ( ! function_exists( 'order_contains_subscription ' ) ) {
	/**
	 * Checks an order to see if it contains a subscription.
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 * @see wcs_order_contains_subscription()
	 *
	 */
	function order_contains_subscription( $order ) {
		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}

		return wcs_order_contains_subscription( $order );
	}
}

if ( ! function_exists( 'wcs_is_subscription_product' ) ) {
	/**
	 * Checks if there's Subscription Product.
	 *
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	function wcs_is_subscription_product( $product ) {
		return class_exists( 'WC_Subscriptions_Product', false ) &&
		       WC_Subscriptions_Product::is_subscription( $product );
	}
}

if ( ! function_exists( 'wcr_is_subscription_product' ) ) {
	/**
	 * Checks if there's Subscription Product.
	 *
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	function wcr_is_subscription_product( $product ) {
		return class_exists( 'WC_Reepay_Checkout', false ) &&
		       WC_Reepay_Checkout::is_reepay_product( $product );
	}
}

if ( ! function_exists( 'wcs_is_payment_change' ) ) {
	/**
	 * WC Subscriptions: Is Payment Change.
	 *
	 * @return bool
	 */
	function wcs_is_payment_change() {
		return class_exists( 'WC_Subscriptions_Change_Payment_Gateway', false ) &&
		       WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
	}
}

if ( ! function_exists( 'wcs_cart_have_subscription' ) ) {
	/**
	 * Check is Cart have Subscription Products.
	 *
	 * @return bool
	 */
	function wcs_cart_have_subscription() {
		if ( class_exists( 'WC_Subscriptions_Product' ) ) {
			// Check is Recurring Payment
			if ( ! is_null( WC()->cart ) ) {
				$cart = WC()->cart->get_cart();
				foreach ( $cart as $key => $item ) {
					if ( is_object( $item['data'] ) && WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
						return true;
					}
				}
			}
		}

		return apply_filters( 'wcs_cart_have_subscription', false );
	}
}

if ( ! function_exists( 'wcs_cart_only_subscriptions' ) ) {
	/**
	 * Check is Cart have only Subscription Products.
	 *
	 * @return bool
	 */
	function wcs_cart_only_subscriptions() {
		$have_product = false;
		if ( class_exists( 'WC_Subscriptions_Product' ) ) {
			// Check is Recurring Payment
			$cart = WC()->cart->get_cart();
			if ( wcs_cart_have_subscription() ) {
				foreach ( $cart as $key => $item ) {
					if ( ! WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
						$have_product = true;
						break;
					}
				}
			} else {
				$have_product = true;
			}
		} else {
			$have_product = true;
		}

		return apply_filters( 'wcs_cart_only_subscriptions', ! $have_product );
	}
}

if ( ! function_exists( 'wc_cart_only_reepay_subscriptions' ) ) {
	/**
	 * Check is Cart have only Subscription Products.
	 *
	 * @return bool
	 */
	function wc_cart_only_reepay_subscriptions() {
		return apply_filters( 'wcs_cart_only_subscriptions', false );
	}
}

if ( ! function_exists( 'rp_prepare_amount' ) ) {
	/**
	 * Prepare amount.
	 *
	 * @param float $amount
	 * @param string $currency
	 *
	 * @return int
	 */
	function rp_prepare_amount( $amount, $currency ) {
		return round( $amount * rp_get_currency_multiplier( $currency ) );
	}
}

if ( ! function_exists( 'rp_make_initial_amount' ) ) {
	/**
	 * Convert amount from gateway to initial amount.
	 *
	 * @param $amount
	 * @param $currency
	 *
	 * @return int
	 */
	function rp_make_initial_amount( $amount, $currency ) {
		$denominator = rp_get_currency_multiplier( $currency );
		if ( ! $denominator ) {
			return 0;
		}

		return $amount / $denominator;
	}
}

if ( ! function_exists( 'rp_get_currency_multiplier' ) ) {
	/**
	 * Get count of minor units fof currency.
	 *
	 * @param string $currency
	 *
	 * @return int
	 */
	function rp_get_currency_multiplier( $currency ) {
		/**
		 * array for currencies that have different minor units that 100
		 * key is currency value is minor units
		 * for currencies that doesn't have minor units, value must be 1
		 *
		 * @var string[]
		 */
		$currency_minor_units = [ 'ISK' => 1 ];

		return array_key_exists( $currency, $currency_minor_units ) ?
			$currency_minor_units[ $currency ] : 100;
	}
}

if ( ! function_exists( 'rp_get_order_handle' ) ) {
	/**
	 * Get Reepay Order Handle.
	 *
	 * @param WC_Order $order
	 * @param bool $unique
	 *
	 * @return string
	 */
	function rp_get_order_handle( WC_Order $order, $unique = false ) {
		$handle = $order->get_meta( '_reepay_order' );

		if ( $unique ) {
			$handle = null;
			$order->delete_meta_data( '_reepay_order' );
		}

		if ( empty( $handle ) ) {
			$handle = $unique ? 'order-' . $order->get_order_number() . '-' . time() : 'order-' . $order->get_order_number();

			$order->add_meta_data( '_reepay_order', $handle );
		}

		$order->save_meta_data();

		return $handle;
	}
}

if ( ! function_exists( 'rp_get_order_by_handle' ) ) {
	/**
	 * Get Order By Reepay Order Handle.
	 *
	 * @param string $handle
	 *
	 * @return false|WC_Order
	 */
	function rp_get_order_by_handle( $handle ) {
		global $wpdb;

		$query    = "
			SELECT post_id FROM {$wpdb->prefix}postmeta 
			LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
			WHERE meta_key = %s AND meta_value = %s;";
		$sql      = $wpdb->prepare( $query, '_reepay_order', $handle );
		$order_id = $wpdb->get_var( $sql );
		if ( ! $order_id ) {
			return false;
		}

		// Get Order
		clean_post_cache( $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return $order;
	}
}

if ( ! function_exists( 'rp_get_order_by_session' ) ) {
	/**
	 * Get Order By Reepay Order Session.
	 *
	 * @param string $handle
	 *
	 * @return false|WC_Order
	 */
	function rp_get_order_by_session( $session_id ) {
		global $wpdb;

		$query    = "
			SELECT post_id FROM {$wpdb->prefix}postmeta 
			LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
			WHERE meta_key = %s AND meta_value = %s;";
		$sql      = $wpdb->prepare( $query, 'reepay_session_id', $session_id );
		$order_id = $wpdb->get_var( $sql );
		if ( ! $order_id ) {
			throw new Exception( sprintf( __( 'Session #%s isn\'t exists in store.', 'reepay-checkout-gateway' ), $session_id ) );
		}

		// Get Order
		clean_post_cache( $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return $order;
	}
}

if ( ! function_exists( 'rp_get_customer_handle' ) ) {
	/**
	 * Get Customer handle by User ID.
	 *
	 * @param $user_id
	 *
	 * @return string
	 */
	function rp_get_customer_handle( $user_id ) {
		if ( ! $user_id ) {
			// Workaround: Allow to pay exist orders by guests
			if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
				if ( $order_id = wc_get_order_id_by_order_key( $_GET['key'] ) ) {
					$order = wc_get_order( $order_id );

					// Get customer handle by order
					$gateway = rp_get_payment_method( $order );
					$handle  = $gateway->api->get_customer_handle_online( $order );
					if ( $handle ) {
						return $handle;
					}
				}
			}
		}

		$handle = get_user_meta( $user_id, 'reepay_customer_id', true );
		if ( empty( $handle ) ) {
			$handle = 'customer-' . $user_id;
			update_user_meta( $user_id, 'reepay_customer_id', $handle );
		}

		return $handle;
	}
}

if ( ! function_exists( 'rp_get_userid_by_handle' ) ) {
	/**
	 * @param $handle
	 *
	 * @return bool|int
	 */
	function rp_get_userid_by_handle( $handle ) {
		if ( strpos( $handle, 'guest-' ) !== false ) {
			return 0;
		}

		$users = get_users( array(
			'meta_key'    => 'reepay_customer_id',
			'meta_value'  => $handle,
			'number'      => 1,
			'count_total' => false
		) );
		if ( count( $users ) > 0 ) {
			$user = array_shift( $users );

			return $user->ID;
		}

		return false;
	}
}

if ( ! function_exists( 'rp_format_price_decimals' ) ) {
	/**
	 * Formats a minor unit value into float with two decimals
	 *
	 * @param string $price_minor is the amount to format
	 *
	 * @return string the nicely formatted value
	 */
	function rp_format_price_decimals( $price_minor ) {
		return number_format( $price_minor / 100, 2, wc_get_price_decimal_separator(), '' );
	}
}

if ( ! function_exists( 'rp_format_credit_card' ) ) {
	/**
	 * Formats a credit card nicely
	 *
	 * @param string $cc is the card number to format nicely
	 *
	 * @return false|string the nicely formatted value
	 */
	function rp_format_credit_card( $cc ) {
		$cc        = str_replace( array( '-', ' ' ), '', $cc );
		$cc_length = strlen( $cc );
		$new_cc    = substr( $cc, - 4 );

		for ( $i = $cc_length - 5; $i >= 0; $i -- ) {
			if ( ( ( $i + 1 ) - $cc_length ) % 4 == 0 ) {
				$new_cc = ' ' . $new_cc;
			}
			$new_cc = $cc[ $i ] . $new_cc;
		}

		for ( $i = 7; $i < $cc_length - 4; $i ++ ) {
			if ( $new_cc[ $i ] == ' ' ) {
				continue;
			}
			$new_cc[ $i ] = 'X';
		}

		return $new_cc;
	}
}
