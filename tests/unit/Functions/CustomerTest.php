<?php
/**
 * Unit Test
 *
 * @package Reepay\Checkout\Tests\Unit\Functions
 */

namespace Reepay\Checkout\Tests\Unit\Functions;

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Utils\TimeKeeper;
use WP_Error;

/**
 * Test Class
 *
 * @package Reepay\Checkout\Tests\Unit\Functions
 */
class CustomerTest extends Reepay_UnitTestCase {
	/**
	 * Set up before class
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		TimeKeeper::set( 1713387085 );
	}

	/**
	 * Test rp_get_customer_handle
	 *
	 * @see rp_get_customer_handle
	 * @group functions_customer
	 */
	public function test_rp_get_customer_handle() {
		$this->api_mock
			->method( 'request' )
			->willReturn( new WP_Error() );

		$user = self::factory()->user->create();

		self::assertSame(
			'customer-' . $user,
			rp_get_customer_handle( $user ),
			'Wrong customer handle'
		);
	}

	/**
	 * Test rp_get_customer_handle
	 *
	 * @see rp_get_customer_handle
	 * @group functions_customer
	 */
	public function test_rp_get_customer_handle_for_non_existent_user() {
		$this->api_mock
			->method( 'request' )
			->willReturn( new WP_Error() );

		$this::assertSame(
			'cust-' . TimeKeeper::get(),
			rp_get_customer_handle( 0 ),
			'Wrong customer handle for a non-existent user with id 0'
		);
		$this::assertSame(
			'cust-' . TimeKeeper::get(),
			rp_get_customer_handle( 1984 ),
			'Wrong customer handle for a non-existent user'
		);
	}

	/**
	 * Test rp_get_customer_handle
	 *
	 * @see rp_get_customer_handle
	 * @group functions_customer
	 */
	public function test_rp_get_customer_handle_exists() {
		$this->api_mock
			->method( 'request' )
			->willReturn( new WP_Error() );

		$user = self::factory()->user->create();

		rp_get_customer_handle( $user );

		$this::assertSame(
			'customer-' . $user,
			get_user_meta( $user, 'reepay_customer_id', true ),
			'Wrong customer handle in user meta'
		);
	}

	/**
	 * Test rp_get_customer_handle
	 *
	 * @see rp_get_customer_handle
	 * @group functions_customer
	 */
	public function test_rp_get_customer_exists_in_api() {
		$this->api_mock
			->method( 'request' )
			->willReturn(
				array(
					'content' => array(
						array(
							'handle' => 'rp-custom-handle-1',
						),
					),
				)
			);

		$user = self::factory()->user->create();

		$this::assertSame(
			'rp-custom-handle-1',
			rp_get_customer_handle( $user ),
			'Wrong customer handle from API'
		);
	}

	/**
	 * Test rp_get_user_id_by_handle with guest handle
	 *
	 * @see rp_get_user_id_by_handle
	 * @group functions_customer
	 */
	public function test_rp_get_user_id_by_handle_guest() {
		$this::assertSame(
			0,
			rp_get_user_id_by_handle( 'guest-0' ),
			'Wrong user id at guest by handle customer'
		);
	}

	/**
	 * Test rp_get_user_id_by_handle with customer handle
	 *
	 * @see rp_get_user_id_by_handle
	 * @group functions_customer
	 */
	public function test_rp_get_user_id_by_handle_customer() {
		$user = self::factory()->user->create();
		rp_get_customer_handle( $user );

		$this::assertSame(
			$user,
			rp_get_user_id_by_handle( "customer-$user" ),
			'Wrong user id by handle customer'
		);
	}
}
