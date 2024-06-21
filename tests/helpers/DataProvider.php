<?php
/**
 * Class DataProvider
 *
 * @package Reepay\Checkout\Tests\Helpers
 */

namespace Reepay\Checkout\Tests\Helpers;

use Billwerk\Sdk\Enum\AgreementTypeEnum;
use Reepay\Checkout\Gateways;
use WC_Gateway_COD;

/**
 * Class HELPERS
 *
 * @package Reepay\Checkout\Tests\Helpers
 */
abstract class DataProvider {
	/**
	 * Agreement types
	 *
	 * @return array
	 */
	public static function agreement_types(): array {
		return array_map( fn( $gateway ) => array( $gateway ), AgreementTypeEnum::getAll() );
	}

	/**
	 * Get payment methods for tests. Use as dataProvider.
	 *
	 * @return array
	 */
	public static function payment_methods(): array {
		$payment_methods = array();

		foreach ( Gateways::PAYMENT_METHODS as $key => $method_name ) {
			$payment_methods[ $method_name ] = array( $method_name, Gateways::PAYMENT_CLASSES[ $key ], true );
		}

		$payment_methods['cod']         = array( 'cod', WC_Gateway_COD::class, false );
		$payment_methods['unavailable'] = array( 'unavailable', false, false );

		return $payment_methods;
	}


	/**
	 * Get keys of orders statuses wit
	 *
	 * @return string[]
	 */
	public static function order_statuses(): array {
		return array_reduce(
			array_keys( wc_get_order_statuses() ),
			function ( array $carry, string $status ) {
				$status           = str_replace( 'wc-', '', $status );
				$carry[ $status ] = array( $status );

				return $carry;
			},
			array()
		);
	}
}
