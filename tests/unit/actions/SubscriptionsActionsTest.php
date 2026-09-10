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
	 * Build a minimal subscription stub for @see Subscriptions::renewal_order_created(), which
	 * only ever calls get_payment_method() and get_parent() on its $subscription argument (it
	 * has no WC_Subscription type hint — see the method's own signature). Using a plain object
	 * instead of mocking the real WC_Subscription class means these tests run in every plugin
	 * combination, including when WooCommerce Subscriptions itself isn't loaded — mocking the
	 * real class would fail there, since PHPUnit's mock builder requires the target class to
	 * actually be loadable.
	 *
	 * @param string   $payment_method value get_payment_method() should return.
	 * @param WC_Order $parent         value get_parent() should return.
	 *
	 * @return object
	 */
	private function create_subscription_stub( string $payment_method, WC_Order $parent ) {
		return new class( $payment_method, $parent ) {
			/**
			 * @var string
			 */
			private string $payment_method;

			/**
			 * @var WC_Order
			 */
			private WC_Order $parent;

			/**
			 * @param string   $payment_method value get_payment_method() should return.
			 * @param WC_Order $parent         value get_parent() should return.
			 */
			public function __construct( string $payment_method, WC_Order $parent ) {
				$this->payment_method = $payment_method;
				$this->parent         = $parent;
			}

			/**
			 * @return string
			 */
			public function get_payment_method(): string {
				return $this->payment_method;
			}

			/**
			 * @return WC_Order
			 */
			public function get_parent(): WC_Order {
				return $this->parent;
			}
		};
	}

	/**
	 * Test @see Subscriptions::renewal_order_created copies the age verification
	 * result from the parent order to the renewal order (BWPM-264).
	 */
	public function test_renewal_order_created_copies_age_verification_result() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->update_meta_data(
			'_reepay_age_verification_result',
			wp_json_encode(
				array(
					'result'  => '18',
					'message' => 'Age verification result: 18',
				)
			)
		);
		$this->order_generator->order()->save();

		$parent_order  = $this->order_generator->order();
		$renewal_order = wc_create_order();
		$renewal_order->save();

		$subscription = $this->create_subscription_stub( reepay()->gateways()->checkout()->id, $parent_order );

		( new Subscriptions() )->renewal_order_created( $renewal_order, $subscription );

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame(
			wp_json_encode(
				array(
					'result'  => '18',
					'message' => 'Age verification result: 18',
				)
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

		$subscription = $this->create_subscription_stub( reepay()->gateways()->checkout()->id, $parent_order );

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
			wp_json_encode( array( 'result' => '18' ) )
		);
		$this->order_generator->order()->save();

		$parent_order  = $this->order_generator->order();
		$renewal_order = wc_create_order();
		$renewal_order->save();

		$subscription = $this->create_subscription_stub( 'bacs', $parent_order );

		( new Subscriptions() )->renewal_order_created( $renewal_order, $subscription );

		$renewal_order = wc_get_order( $renewal_order->get_id() );

		$this->assertSame( '', $renewal_order->get_meta( '_reepay_age_verification_result' ) );
	}
}
