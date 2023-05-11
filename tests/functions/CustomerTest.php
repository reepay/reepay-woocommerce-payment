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
	 * @var int
	 */
	private $user_id;

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = wp_create_user('test', 'test', 'test@test.com');
	}

	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down() {
		parent::tear_down();

		wp_delete_user( $this->user_id );
	}

	public function test_rp_get_customer_handle_generation() {
		$this->assertSame(
			"customer-$this->user_id",
			rp_get_customer_handle( $this->user_id )
		);
	}

	public function test_rp_get_customer_handle_exists() {
		rp_get_customer_handle( $this->user_id );

		$this->assertSame(
			"customer-$this->user_id",
			rp_get_customer_handle( $this->user_id )
		);
	}
}