<?php
/**
 * Gateway functions
 *
 * @package Reepay\Checkout\Functions
 */

use Reepay\Checkout\Gateways;
use Reepay\Checkout\Gateways\ReepayCheckout;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_get_payment_method ' ) ) {
	/**
	 * Get payment method from order.
	 *
	 * @param WC_Order $order order to get payment method.
	 *
	 * @return false|ReepayCheckout
	 */
	function rp_get_payment_method( WC_Order $order ) {
		$gateways = WC()->payment_gateways()->payment_gateways();

		return $gateways[ $order->get_payment_method() ] ?? false;
	}
}

if ( ! function_exists( 'is_reepay_payment_method' ) ) {
	/**
	 * Check if payment method is reepay payment method
	 *
	 * @param string $payment_method payment method to check.
	 */
	function rp_is_reepay_payment_method( string $payment_method ): bool {
		return in_array( $payment_method, Gateways::PAYMENT_METHODS, true );
	}
}
