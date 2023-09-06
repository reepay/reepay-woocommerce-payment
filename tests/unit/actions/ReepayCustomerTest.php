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
	public function test_user_register_hook() {
		$this->api_mock->method('request')->willReturn( array(
			'content' => array(
				array(
					'handle' => 'rp-custom-handle-3'
				)
			)
		) );

		$this->_backup_hooks();

		new ReepayCustomer();

		$user = $this->factory()->user->create();

		$this->assertSame(
			'rp-custom-handle-3',
			get_user_meta($user, 'reepay_customer_id', true)
		);

		$this->_restore_hooks();
	}

	public function test_user_register() {
		$this->api_mock->method('request')->willReturn( array(
			'content' => array(
				array(
					'handle' => 'rp-custom-handle-3'
				)
			)
		) );

		$user = $this->factory()->user->create();

		ReepayCustomer::set_reepay_handle( $user );

		$this->assertSame(
			'rp-custom-handle-3',
			get_user_meta($user, 'reepay_customer_id', true)
		);
	}

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
