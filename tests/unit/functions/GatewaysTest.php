<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * CurrencyTest.
 */
class GatewaysTest extends Reepay_UnitTestCase {
	/**
	 * Test @see rp_get_payment_method
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class payment method class name.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 */
	public function test_rp_get_payment_method( string $method_name, $class ) {
		$this->order_generator->set_prop( 'payment_method', $method_name );

		$payment_method = rp_get_payment_method( $this->order_generator->order() );

		$this->assertSame(
			$class,
			$payment_method ? get_class( $payment_method ) : false
		);
	}

	/**
	 * Test @see rp_is_reepay_payment_method
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class payment method class name.
	 * @param bool         $is_reepay is reepay gateway.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 */
	public function test_rp_is_reepay_payment_method( string $method_name, $class, bool $is_reepay ) {
		$this->assertSame(
			$is_reepay,
			rp_is_reepay_payment_method( $method_name )
		);
	}
}
