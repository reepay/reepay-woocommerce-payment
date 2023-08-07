<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Class Gateways
 *
 * @covers \Reepay\Checkout\Gateways
 */
class Gateways extends Reepay_UnitTestCase {
	/**
	 * Test get_gateway
	 *
	 * @param string       $method_name method name.
	 * @param string|false $class method class name.
	 * @param bool         $is_reepay is reepay gateway.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 */
	public function test_payment_methods_loaded( string $method_name, $class, bool $is_reepay ) {
		$gateway = reepay()->gateways()->get_gateway( $method_name );

		$this->assertSame(
			$is_reepay ? $class : null,
			$gateway ? get_class( $gateway ) : null
		);
	}

	/**
	 * Test checkout
	 */
	public function test_reepay_checkout_gateway_loaded() {
		$this->assertSame(
			ReepayCheckout::class,
			get_class( reepay()->gateways()->checkout() )
		);
	}
}
