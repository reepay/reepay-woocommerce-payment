<?php
/**
 * Class ReepayCustomerTest
 *
 * @package Reepay\Checkout
 */


use Reepay\Checkout\Actions\ReepayCustomer;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * ReepayCustomerTest.
 *
 * @covers \Reepay\Checkout\Actions\ReepayCustomer
 */
class ReepayCustomerTest extends Reepay_UnitTestCase {
	function test_set_reepay_handle_empty_email() {
		$this->assertSame(
			'',
			ReepayCustomer::set_reepay_handle(
				$this->factory()->user->create()
			)
		);
	}

	function test_set_reepay_handle_api_wp_error() {
		$this->api_mock->method( 'request' )->willReturn( new WP_Error() );

		$this->assertSame(
			'',
			ReepayCustomer::set_reepay_handle(
				$this->factory()->user->create( array(
					'meta_input' => array(
						'billing_email' => 'user@example.org'
					)
				) )
			)
		);
	}

	function test_set_reepay_handle_api_empty() {
		$this->api_mock->method( 'request' )->willReturn( array(
			'content' => array()
		) );

		$this->assertSame(
			'',
			ReepayCustomer::set_reepay_handle(
				$this->factory()->user->create( array(
					'meta_input' => array(
						'billing_email' => 'user@example.org'
					)
				) )
			)
		);
	}

	function test_set_reepay_handle() {
		$this->api_mock->method('request')->willReturn( array(
			'content' => array(
				array(
					'handle' => 'rp-custom-handle-2'
				)
			)
		) );

		$this->assertSame(
			'rp-custom-handle-2',
			ReepayCustomer::set_reepay_handle(
				$this->factory()->user->create( array(
					'meta_input' => array(
						'billing_email' => 'user@example.org'
					)
				) )
			)
		);
	}
}
