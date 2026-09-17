<?php
/**
 * Class UnsettledOrdersFinderTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\UnsettledOrdersFinder;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * UnsettledOrdersFinderTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\UnsettledOrdersFinder
 */
class UnsettledOrdersFinderTest extends Reepay_UnitTestCase {

	/**
	 * Test @see UnsettledOrdersFinder::find() finds a completed, unsettled Reepay order with a non-zero total.
	 */
	public function test_find_returns_completed_unsettled_reepay_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertContains( $order->get_id(), $result['order_ids'] );
		$this->assertFalse( $result['capped'] );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes orders that ARE settled.
	 */
	public function test_find_excludes_settled_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();
		$this->order_generator->set_meta( '_reepay_state_settled', 1 );

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
	}

	/**
	 * Regression test for BWPM-281: an order genuinely settled in Frisbii, but whose local
	 * _reepay_state_settled meta was never set (e.g. a third-party integration captured it
	 * directly, outside this plugin's own flow), must be excluded once find() checks Frisbii's
	 * real invoice state — and the local meta should get quietly written so future runs don't
	 * need to re-check this same order via the API again.
	 *
	 * @see UnsettledOrdersFinder::is_genuinely_settled_but_untracked
	 */
	public function test_find_excludes_and_heals_order_genuinely_settled_but_untracked() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 2000,
				'settled_amount'    => 2000,
			)
		);

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'], 'A genuinely settled order should have been excluded' );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 1, (int) $order->get_meta( '_reepay_state_settled' ), 'Local state was not healed after confirming the order is genuinely settled' );
	}

	/**
	 * Test @see UnsettledOrdersFinder::is_genuinely_settled_but_untracked does not exclude an
	 * order that the live invoice check confirms is genuinely still unpaid — the live check
	 * must not accidentally hide real unpaid orders.
	 */
	public function test_find_still_includes_order_confirmed_genuinely_unpaid_by_live_check() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 2000,
				'settled_amount'    => 0,
			)
		);

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertContains( $order->get_id(), $result['order_ids'] );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( '', $order->get_meta( '_reepay_state_settled' ) );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes orders not paid via a Reepay gateway.
	 */
	public function test_find_excludes_non_reepay_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => 'bacs',
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes zero-total orders (explicit AC).
	 */
	public function test_find_excludes_zero_total_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order = $this->order_generator->order();
		$order->set_total( 0 );
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes orders completed before the v1.8.14
	 * cutoff date (08/07/2026 in the ticket's DD/MM notation — 8 July 2026, i.e. 2026-07-08 in
	 * ISO form) — the bug window this feature is guarding against starts there.
	 */
	public function test_find_excludes_order_completed_before_cutoff() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-07-01 00:00:00' );
		$order->save();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes subscription orders (explicit AC3) — their
	 * parent order is typically 0-total already, and renewal orders settle through a separate
	 * flow, so including them here would misreport a not-yet-charged renewal as "unpaid Completed".
	 */
	public function test_find_excludes_subscription_order() {
		if ( ! Reepay\Checkout\Tests\Helpers\PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'woo_sub' );
		$order = $this->order_generator->order();
		$order->set_status( 'completed' );
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes a Frisbii Billing subscription's own
	 * parent/initial order (a separate plugin from official WooCommerce Subscriptions — flags
	 * itself via `_reepay_is_subscription` meta, confirmed against real live order data).
	 */
	public function test_find_excludes_frisbii_billing_subscription_parent_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->update_meta_data( '_reepay_is_subscription', '1' );
		$order->save();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes a Frisbii Billing subscription's renewal
	 * order. Confirmed against real live order data (2026-08-30): a renewal order never carries
	 * `_reepay_is_subscription` meta itself — only its parent order does, linked via the plain
	 * WordPress `post_parent` relationship (`WC_Order::get_parent_id()`), which is what this test
	 * reproduces. A renewal order's line item is also synthetic (no real linked product), so this
	 * scenario can't be detected via product-type checks — only via the parent-order lookup.
	 */
	public function test_find_excludes_frisbii_billing_subscription_renewal_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$parent_order = $this->order_generator->order();
		$parent_order->update_meta_data( '_reepay_is_subscription', '1' );
		$parent_order->save();

		$this->order_generator->generate( array( 'status' => 'completed' ) );
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$renewal_order = $this->order_generator->order();
		$renewal_order->set_parent_id( $parent_order->get_id() );
		$renewal_order->set_date_completed( '2026-08-10 00:00:00' );
		$renewal_order->save();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $renewal_order->get_id(), $result['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersFinder::find() excludes orders that are not status "completed".
	 */
	public function test_find_excludes_non_completed_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'processing',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
	}
}
