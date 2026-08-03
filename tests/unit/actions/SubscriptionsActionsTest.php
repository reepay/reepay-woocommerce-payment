<?php
/**
 * Class SubscriptionsActionsTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Actions\Subscriptions;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * SubscriptionsActionsTest.
 *
 * @covers \Reepay\Checkout\Actions\Subscriptions
 */
class SubscriptionsActionsTest extends Reepay_UnitTestCase {

	/**
	 * Test @see Subscriptions::renewal_order_created copies the age verification
	 * result from the parent order to the renewal order (BWPM-264).
	 */
	public function test_renewal_order_created_copies_age_verification_result() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->update_meta_data(
			'_reepay_age_verification_result',
			array(
				'result'  => '18',
				'message' => 'Age verification result: 18',
			)
		);
		$this->order_generator->order()->save();

		$parent_order  = $this->order_generator->order();
		$renewal_order = wc_create_order();
		$renewal_order->save();

		$subscription = $this->createMock( WC_Subscription::class );
		$subscription->method( 'get_payment_method' )->willReturn( reepay()->gateways()->checkout()->id );
		$subscription->method( 'get_parent' )->willReturn( $parent_order );

		( new Subscriptions() )->renewal_order_created( $renewal_order, $subscription );

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame(
			array(
				'result'  => '18',
				'message' => 'Age verification result: 18',
			),
			$renewal_order->get_meta( '_reepay_age_verification_result' )
		);
	}

	/**
	 * Test @see Subscriptions::renewal_order_created skips the copy when the
	 * parent order has no age verification result stored.
	 */
	public function test_renewal_order_created_skips_when_parent_has_no_age_verification_result() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$parent_order  = $this->order_generator->order();
		$renewal_order = wc_create_order();
		$renewal_order->save();

		$subscription = $this->createMock( WC_Subscription::class );
		$subscription->method( 'get_payment_method' )->willReturn( reepay()->gateways()->checkout()->id );
		$subscription->method( 'get_parent' )->willReturn( $parent_order );

		( new Subscriptions() )->renewal_order_created( $renewal_order, $subscription );

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( '', $renewal_order->get_meta( '_reepay_age_verification_result' ) );
	}

	/**
	 * Test @see Subscriptions::renewal_order_created does nothing for a
	 * non-Reepay payment method.
	 */
	public function test_renewal_order_created_skips_for_non_reepay_payment_method() {
		$this->order_generator->order()->update_meta_data(
			'_reepay_age_verification_result',
			array( 'result' => '18' )
		);
		$this->order_generator->order()->save();

		$parent_order  = $this->order_generator->order();
		$renewal_order = wc_create_order();
		$renewal_order->save();

		$subscription = $this->createMock( WC_Subscription::class );
		$subscription->method( 'get_payment_method' )->willReturn( 'bacs' );
		$subscription->method( 'get_parent' )->willReturn( $parent_order );

		( new Subscriptions() )->renewal_order_created( $renewal_order, $subscription );

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( '', $renewal_order->get_meta( '_reepay_age_verification_result' ) );
	}
}
