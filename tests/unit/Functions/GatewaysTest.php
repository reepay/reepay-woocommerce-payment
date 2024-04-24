<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout\Tests\Unit\Functions
 */

namespace Reepay\Checkout\Tests\Unit\Functions;

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Test Class
 *
 * @package Reepay\Checkout\Tests\Unit\Functions
 */
class GatewaysTest extends Reepay_UnitTestCase {
	/**
	 * Test rp_get_payment_method
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class_name payment method class name.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 * @see rp_get_payment_method
	 * @group functions_gateways
	 */
	public function test_rp_get_payment_method( string $method_name, $class_name ) {
		$this->order_generator->set_prop( 'payment_method', $method_name );

		$payment_method = rp_get_payment_method( $this->order_generator->order() );

		self::assertSame(
			$class_name,
			$payment_method ? get_class( $payment_method ) : false
		);
	}

	/**
	 * Test rp_is_reepay_payment_method
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class_name payment method class name.
	 * @param bool         $is_reepay is reepay gateway.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 *  @see rp_is_reepay_payment_method
	 * @group functions_gateways
	 */
	public function test_rp_is_reepay_payment_method( string $method_name, $class_name, bool $is_reepay ) {
		self::assertSame(
			$is_reepay,
			rp_is_reepay_payment_method( $method_name )
		);
	}
}
