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
	 * Fake third-party gateway registered by a test.
	 *
	 * @var WC_Payment_Gateway|null
	 */
	private ?WC_Payment_Gateway $foreign_gateway = null;

	/**
	 * Remove the fake gateway registered by a test.
	 */
	public function tear_down() {
		if ( null !== $this->foreign_gateway ) {
			$gateways = WC()->payment_gateways();

			foreach ( $gateways->payment_gateways as $key => $gateway ) {
				if ( $gateway === $this->foreign_gateway ) {
					unset( $gateways->payment_gateways[ $key ] );
				}
			}

			$this->foreign_gateway = null;
		}

		parent::tear_down();
	}

	/**
	 * Register a gateway like Quickpay's MainGateway: a WC_Payment_Gateway without a log() method.
	 *
	 * @param string $id gateway id.
	 */
	private function register_foreign_gateway( string $id ) {
		$this->foreign_gateway = new class( $id ) extends WC_Payment_Gateway {
			/**
			 * @param string $id gateway id.
			 */
			public function __construct( string $id ) {
				$this->id = $id;
			}
		};

		WC()->payment_gateways()->payment_gateways[] = $this->foreign_gateway;
	}

	/**
	 * Create a pending renewal order paid via the given payment method.
	 *
	 * @param string $payment_method payment method id.
	 *
	 * @return WC_Order
	 */
	private function create_renewal_order( string $payment_method ): WC_Order {
		$order = wc_create_order( array( 'status' => 'pending' ) );
		$order->set_payment_method( $payment_method );
		$order->set_total( 100 );
		$order->update_meta_data( '_subscription_renewal', 1 );
		$order->save();

		return $order;
	}

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

	/**
	 * Test @see Subscriptions::scheduled_subscription_payment ignores an order paid via another
	 * gateway instead of calling log() on it (BWPM-318).
	 */
	public function test_scheduled_subscription_payment_skips_non_reepay_gateway() {
		$this->register_foreign_gateway( 'quickpay' );
		$renewal_order = $this->create_renewal_order( 'quickpay' );

		$this->api_mock->expects( $this->never() )->method( 'charge' );

		( new Subscriptions() )->scheduled_subscription_payment( 100.0, $renewal_order, true );

		$this->assertSame( 'pending', wc_get_order( $renewal_order->get_id() )->get_status() );
	}

	/**
	 * Test @see Subscriptions::create_sub_invoice ignores a renewal order paid via another gateway
	 * when it moves from pending to processing, as Quickpay's callback does (BWPM-318).
	 */
	public function test_create_sub_invoice_skips_non_reepay_gateway() {
		$this->register_foreign_gateway( 'quickpay' );
		$renewal_order = $this->create_renewal_order( 'quickpay' );

		$this->api_mock->expects( $this->never() )->method( 'get_invoice_data' );
		$this->api_mock->expects( $this->never() )->method( 'charge' );

		( new Subscriptions() )->create_sub_invoice( $renewal_order->get_id(), 'pending', 'processing', $renewal_order );

		$this->assertSame( 'pending', wc_get_order( $renewal_order->get_id() )->get_status() );
	}

	/**
	 * Test the real status change hook does not fatal for an order paid via another gateway (BWPM-318).
	 */
	public function test_status_change_to_processing_does_not_fatal_for_non_reepay_gateway() {
		$this->register_foreign_gateway( 'quickpay' );
		$renewal_order = $this->create_renewal_order( 'quickpay' );

		$this->api_mock->method( 'get_invoice_data' )->willReturn( new WP_Error( 'not_found', 'Invoice not found' ) );
		$this->api_mock->expects( $this->never() )->method( 'charge' );

		new Subscriptions();

		$renewal_order->update_status( 'processing' );

		$this->assertSame( 'processing', wc_get_order( $renewal_order->get_id() )->get_status() );
	}

	/**
	 * Test @see Subscriptions::create_sub_invoice still handles a renewal order paid via Frisbii.
	 */
	public function test_create_sub_invoice_still_handles_reepay_gateway() {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions is not loaded.' );
		}

		$renewal_order = $this->create_renewal_order( reepay()->gateways()->checkout()->id );

		// No token exists, so the order is marked failed and the registered hook looks up the invoice a second time.
		$this->api_mock->expects( $this->atLeastOnce() )
			->method( 'get_invoice_data' )
			->willReturn( new WP_Error( 'not_found', 'Invoice not found' ) );

		( new Subscriptions() )->create_sub_invoice( $renewal_order->get_id(), 'pending', 'processing', $renewal_order );
	}
}
