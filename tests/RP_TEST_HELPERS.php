<?php

use Reepay\Checkout\Gateways;

abstract class RP_TEST_HELPERS {
	static function rp_test_get_payment_methods(): array {
		$payment_methods = array();

		foreach ( Gateways::PAYMENT_METHODS as $key => $method_name ) {
			$payment_methods[ $method_name ] = array( $method_name, Gateways::PAYMENT_CLASSES[ $key ], true );
		}

		$payment_methods['cod']         = array( 'cod', WC_Gateway_COD::class, false );
		$payment_methods['unavailable'] = array( 'unavailable', false, false );

		return $payment_methods;
	}
}
