<?php
/**
 * Subscriptions functions
 *
 * @package Reepay\Checkout\Functions
 */

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'order_contains_subscription ' ) ) {
	/**
	 * Checks an order to see if it contains a woocommerce subscription.
	 *
	 * @param WC_Order $order order to check.
	 *
	 * @return bool
	 * @see wcs_order_contains_subscription()
	 */
	function order_contains_subscription( WC_Order $order ): bool {
		return function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order );
	}
}

if ( ! function_exists( 'wcs_is_subscription_product' ) ) {
	/**
	 * Checks if there's Woo Subscription Product.
	 *
	 * @param WC_Product $product product to check.
	 *
	 * @return bool
	 */
	function wcs_is_subscription_product( WC_Product $product ): bool {
		return class_exists( WC_Subscriptions_Product::class, false ) &&
			   WC_Subscriptions_Product::is_subscription( $product );
	}
}

if ( ! function_exists( 'wcr_is_subscription_product' ) ) {
	/**
	 * Checks if there's Reepay Subscription Product.
	 *
	 * @param WC_Product $product product to check.
	 *
	 * @return bool
	 */
	function wcr_is_subscription_product( WC_Product $product ): bool {
		return class_exists( WC_Reepay_Checkout::class, false ) &&
			   WC_Reepay_Checkout::is_reepay_product( $product );
	}
}

if ( ! function_exists( 'wcs_is_payment_change' ) ) {
	/**
	 * WC Subscriptions: Is Woo Payment Method Change.
	 *
	 * @return bool
	 */
	function wcs_is_payment_change(): bool {
		return class_exists( WC_Subscriptions_Change_Payment_Gateway::class, false ) &&
			   WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
	}
}

if ( ! function_exists( 'wcs_cart_have_subscription' ) ) {
	/**
	 * Check is Cart have woocommerce subscription products (and reepay subscription via filter)
	 *
	 * @see WC_Reepay_Checkout::is_reepay_product_in_cart()
	 *
	 * @return bool
	 */
	function wcs_cart_have_subscription(): bool {
		if (
			class_exists( WC_Subscriptions_Product::class )
			&& WC()->cart
		) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( is_object( $item['data'] ) && WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
					return true;
				}
			}
		}

		return apply_filters( 'wcs_cart_have_subscription', false );
	}
}

if ( ! function_exists( 'wcs_cart_only_subscriptions' ) ) {
	/**
	 * Check is Cart have only Subscription Products (Woocommerce or Reepay via filter).
	 *
	 * @see WC_Reepay_Checkout::only_reepay_products_in_cart
	 *
	 * @return bool
	 */
	function wcs_cart_only_subscriptions(): bool {
		$only_subscriptions = true;
		if (
			class_exists( WC_Subscriptions_Product::class )
			&& WC()->cart
		) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
					$only_subscriptions = false;
					break;
				}
			}
		} else {
			$only_subscriptions = false;
		}

		return apply_filters( 'wcs_cart_only_subscriptions', $only_subscriptions );
	}
}

if ( ! function_exists( 'wcr_cart_only_reepay_subscriptions' ) ) {
	/**
	 * Check is Cart have only Subscription Products.
	 *
	 * @return bool
	 */
	function wcr_cart_only_reepay_subscriptions(): bool {
		return apply_filters( 'wcr_cart_only_reepay_subscriptions', false );
	}
}

if ( ! function_exists( 'rp_get_order_by_subscription_handle' ) ) {
	/**
	 * Get order by subscription handle
	 *
	 * @see WC_Reepay_Renewals::get_order_by_subscription_handle
	 *
	 * @param string $handle reepay subscription handle.
	 *
	 * @return WC_Order|false
	 */
	function rp_get_order_by_subscription_handle( string $handle ) {
		return class_exists( WC_Reepay_Renewals::class, false ) ?
			WC_Reepay_Renewals::get_order_by_subscription_handle( $handle ) : false;
	}
}
