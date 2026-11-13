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
	public function test_user_register_hook_does_not_bind_email_match() {
		$this->api_mock->expects( $this->never() )->method( 'request' );

		$this->_backup_hooks();

		new ReepayCustomer();

		$user = $this->factory()->user->create(
			array(
				'meta_input' => array( 'billing_email' => 'victim@example.org' ),
			)
		);

		$this->assertSame( '', get_user_meta( $user, 'reepay_customer_id', true ) );

		$this->_restore_hooks();
	}

	public function test_set_reepay_handle_ignores_email_lookup() {
		$this->api_mock->expects( $this->never() )->method( 'request' );

		$user = $this->factory()->user->create(
			array(
				'meta_input' => array( 'billing_email' => 'victim@example.org' ),
			)
		);

		$this->assertSame( '', ReepayCustomer::set_reepay_handle( $user ) );
		$this->assertSame( '', get_user_meta( $user, 'reepay_customer_id', true ) );
	}

	public function test_set_reepay_handle_reuses_handle_from_own_order() {
		$user  = $this->factory()->user->create();
		$order = wc_create_order( array( 'customer_id' => $user ) );
		$order->update_meta_data( '_reepay_customer', 'customer-own-1' );
		$order->save();

		$this->assertSame( 'customer-own-1', ReepayCustomer::set_reepay_handle( $user ) );
		$this->assertSame( 'customer-own-1', get_user_meta( $user, 'reepay_customer_id', true ) );
	}

	public function test_set_reepay_handle_ignores_other_users_orders() {
		$owner = $this->factory()->user->create();
		$user  = $this->factory()->user->create();
		$order = wc_create_order( array( 'customer_id' => $owner ) );
		$order->update_meta_data( '_reepay_customer', 'customer-owner' );
		$order->save();

		$this->assertSame( '', ReepayCustomer::set_reepay_handle( $user ) );
	}
}
