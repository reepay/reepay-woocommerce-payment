<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

/**
 * CurrencyTest.
 */
class GatewaysTest extends WP_UnitTestCase {
	/**
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order = wc_create_order( array(
			'status'      => 'completed',
			'created_via' => 'tests',
			'cart_hash'   => 'cart_hash',
		) );
	}

	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->order->delete( true );
	}

	public function payment_methods(): array {
		return RP_TEST_HELPERS::get_payment_methods();
	}

	/**
	 * @param string       $method_name
	 * @param string|false $class
	 *
	 * @dataProvider payment_methods
	 */
	public function test_rp_get_payment_method( string $method_name, $class ) {
		$this->order->set_payment_method( $method_name );

		$payment_method = rp_get_payment_method( $this->order );

		$this->assertSame(
			$class,
			$payment_method ? get_class( $payment_method ) : false
		);

		$this->order->set_payment_method( '' );
	}

	/**
	 * @param string       $method_name
	 * @param string|false $class
	 * @param bool         $is_reepay
	 *
	 * @dataProvider payment_methods
	 */
	public function test_rp_is_reepay_payment_method( string $method_name, $class, bool $is_reepay ) {
		$this->assertSame(
			$is_reepay,
			rp_is_reepay_payment_method( $method_name )
		);
	}
}