<?php
/**
 * @package Reepay\Checkout\Functions
 */

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_prepare_amount' ) ) {
	/**
	 * Prepare amount.
	 *
	 * @param float  $amount
	 * @param string $currency
	 *
	 * @return float
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
		$currency_minor_units = array( 'ISK' => 1 );

		return array_key_exists( $currency, $currency_minor_units ) ?
			$currency_minor_units[ $currency ] : 100;
	}
}
