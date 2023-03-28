<?php

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'order_contains_subscription ' ) ) {
	/**
	 * Checks an order to see if it contains a subscription.
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 * @see wcs_order_contains_subscription()
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
				foreach ( $cart as $item ) {
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
				foreach ( $cart as $item ) {
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