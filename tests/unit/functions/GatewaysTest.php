<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\DataProvider;

/**
 * CurrencyTest.
 */
class GatewaysTest extends WP_UnitTestCase {
	/**
	 * ProductGenerator instance
	 *
	 * @var WC_Order
	 */
	private static WC_Order $order;

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$order = wc_create_order(
			array(
				'status'      => 'completed',
				'created_via' => 'tests',
				'cart_hash'   => 'cart_hash',
			)
		);
	}

	/**
	 * Test @see rp_get_payment_method
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class payment method class name.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 */
	public function test_rp_get_payment_method( string $method_name, $class ) {
		self::$order->set_payment_method( $method_name );

		$payment_method = rp_get_payment_method( self::$order );

		$this->assertSame(
			$class,
			$payment_method ? get_class( $payment_method ) : false
		);

		self::$order->set_payment_method( '' );
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
