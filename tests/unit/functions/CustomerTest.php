<?php
/**
 * Class CustomerTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * CurrencyTest.
 */
class CustomerTest extends Reepay_UnitTestCase {
	/**
	 * Test @see rp_get_customer_handle_generation
	 */
	public function test_rp_get_customer_handle_generation() {
		$user = wp_create_user( 'test', 'test', 'test@test.com' );

		$this->assertSame(
			'customer-' . $user,
			rp_get_customer_handle( $user )
		);
	}

	/**
	 * Test @see rp_get_customer_handle_exists
	 */
	public function test_rp_get_customer_handle_exists() {
		$user = wp_create_user( 'test', 'test', 'test@test.com' );

		rp_get_customer_handle( $user );

		$this->assertSame(
			'customer-' . $user,
			rp_get_customer_handle( $user )
		);
	}

	/**
	 * Test rp_get_userid_by_handle with guest handle
	 */
	public function test_rp_get_userid_by_handle_guest() {
		$this->assertSame(
			0,
			rp_get_userid_by_handle( 'guest-0' )
		);
	}

	/**
	 * Test rp_get_userid_by_handle with customer handle
	 */
	public function test_rp_get_userid_by_handle_customer() {
		$user = wp_create_user( 'test', 'test', 'test@test.com' );
		rp_get_customer_handle( $user );

		$this->assertSame(
			$user,
			rp_get_userid_by_handle( "customer-$user" )
		);
	}
}
