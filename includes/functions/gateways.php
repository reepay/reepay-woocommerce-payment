<?php

use Reepay\Checkout\Gateways\ReepayCheckout;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_get_payment_method ' ) ) {
	/**
	 * Get Payment Method.
	 *
	 * @param WC_Order $order
	 *
	 * @return false|ReepayCheckout
	 */
	function rp_get_payment_method( WC_Order $order ) {
		$gateways = WC()->payment_gateways()->payment_gateways();

		return $gateways[ $order->get_payment_method() ] ?? false;
	}
}