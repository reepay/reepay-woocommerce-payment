<?php
/**
 * Class CustomerTest
 *
 * @package Reepay\Checkout
 */

/**
 * CurrencyTest.
 */
class CustomerTest extends WP_UnitTestCase {
	/**
	 * Current user id
	 *
	 * @var int
	 */
	private static int $user;

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user = wp_create_user( 'test', 'test', 'test@test.com' );
	}

	/**
	 * Test function rp_get_customer_handle_generation
	 */
	public function test_rp_get_customer_handle_generation() {
		$this->assertSame(
			'customer-' . self::$user,
			rp_get_customer_handle( self::$user )
		);
	}

	/**
	 * Test function rp_get_customer_handle_exists
	 */
	public function test_rp_get_customer_handle_exists() {
		rp_get_customer_handle( self::$user );

		$this->assertSame(
			'customer-' . self::$user,
			rp_get_customer_handle( self::$user )
		);
	}
}
