<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;

/**
 * ApiTest.
 */
class GatewaysClassTest extends WP_UnitTestCase {
	public function payment_methods(): array {
		return RP_TEST_HELPERS::get_payment_methods();
	}

	/**
	 * @param string       $method_name
	 * @param string|false $class
	 * @param bool         $is_reepay
	 *
	 * @dataProvider payment_methods
	 */
	public function test_payment_methods_loaded( string $method_name, $class, bool $is_reepay ) {
		$gateway = reepay()->gateways()->get_gateway( $method_name );

		$this->assertSame(
			$is_reepay ? $class : null,
			$gateway ? get_class( $gateway ) : null
		);
	}

	public function test_reepay_checkout_gateway_loaded() {
		$this->assertSame(
			ReepayCheckout::class,
			get_class( reepay()->gateways()->checkout() )
		);
	}
}
