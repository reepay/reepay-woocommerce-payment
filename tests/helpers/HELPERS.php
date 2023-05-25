<?php
/**
 * Class HELPERS
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use Reepay\Checkout\Gateways;
use WC_Gateway_COD;

/**
 * Class HELPERS
 */
abstract class HELPERS {
	/**
	 * Get payment methods for tests. Use as dataProvider.
	 *
	 * @param bool $only_reepay should add non reepay payment methods.
	 *
	 * @return array
	 */
	public static function get_payment_methods( bool $only_reepay = false ): array {
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
