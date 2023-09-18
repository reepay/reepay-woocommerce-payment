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
		$this->api_mock->method('request')->willReturn( new WP_Error() );

		$user = $this->factory()->user->create();

		$this->assertSame(
			'customer-' . $user,
			rp_get_customer_handle( $user )
		);
	}

	/**
	 * Test @see rp_get_customer_handle_generation
	 */
	public function test_rp_get_customer_handle_generation_zero_customer() {
		$this->api_mock->method('request')->willReturn( new WP_Error() );

		$this->assertSame(
			'cust-' . time(),
			rp_get_customer_handle( 0 )
		);
	}

	/**
	 * Test @see rp_get_customer_handle_exists
	 */
	public function test_rp_get_customer_handle_exists() {
		$this->api_mock->method('request')->willReturn( new WP_Error() );

		$user = $this->factory()->user->create();

		rp_get_customer_handle( $user );

		$this->assertSame(
			'customer-' . $user,
			rp_get_customer_handle( $user )
		);
	}

	/**
	 * Test @see rp_get_customer_handle_exists
	 */
	public function test_rp_get_customer_exists_in_api() {
		$this->api_mock->method('request')->willReturn( array(
			'content' => array(
				array(
					'handle' => 'rp-custom-handle-1'
				)
			)
		) );

		$user = $this->factory()->user->create();

		$this->assertSame(
			'rp-custom-handle-1',
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
		$user = $this->factory()->user->create();
		rp_get_customer_handle( $user );

		$this->assertSame(
			$user,
			rp_get_userid_by_handle( "customer-$user" )
		);
	}
}
