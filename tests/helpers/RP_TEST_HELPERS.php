<?php

use Reepay\Checkout\Gateways;

abstract class RP_TEST_HELPERS {
	/**
	 * @param bool $only_reepay
	 *
	 * @return array
	 */
	static function get_payment_methods( bool $only_reepay = false ): array {
		$payment_methods = array();

		foreach ( Gateways::PAYMENT_METHODS as $key => $method_name ) {
			$payment_methods[ $method_name ] = array( $method_name, Gateways::PAYMENT_CLASSES[ $key ], true );
		}

		if ( ! $only_reepay ) {
			$payment_methods['cod']         = array( 'cod', WC_Gateway_COD::class, false );
			$payment_methods['unavailable'] = array( 'unavailable', false, false );
		}

		return $payment_methods;
	}
}
