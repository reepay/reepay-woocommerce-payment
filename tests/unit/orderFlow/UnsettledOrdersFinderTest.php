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
	 * Regression test for BWPM-281: find() must not perform an unbounded number of live Frisbii
	 * API checks in one run — a large backlog of affected orders (found live: 500+ on one real
	 * site) should be verified gradually across several runs, not all in one potentially slow or
	 * rate-limited request. Creates more untracked-but-settled candidates than the per-run cap
	 * allows, and confirms only the cap's worth get excluded/healed in a single find() call.
	 *
	 * @see UnsettledOrdersFinder::MAX_LIVE_VERIFICATIONS_PER_RUN
	 */
	public function test_find_caps_live_verifications_per_run() {
		$cap    = UnsettledOrdersFinder::MAX_LIVE_VERIFICATIONS_PER_RUN;
		$orders = array();

		for ( $i = 0; $i < $cap + 3; $i++ ) {
			$this->order_generator->generate( array( 'status' => 'completed' ) );
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
			$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
			$this->order_generator->order()->calculate_totals();
			$order = $this->order_generator->order();
			$order->set_date_completed( '2026-08-10 00:00:00' );
			$order->save();
			$orders[] = $order;
		}

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 2000,
				'settled_amount'    => 2000,
			)
		);

		$result = ( new UnsettledOrdersFinder() )->find();

		$healed_count = 0;
		foreach ( $orders as $order ) {
			$order = wc_get_order( $order->get_id() );
			if ( '' !== $order->get_meta( '_reepay_state_settled' ) ) {
				++$healed_count;
			}
		}

		$this->assertSame( $cap, $healed_count, 'Exactly the per-run cap of orders should have been live-checked and healed' );
		$this->assertCount( 3, $result['order_ids'], 'The remaining orders beyond the cap should still show as unsettled for this run' );
	}

	/**
	 * Regression test (Cursor Bugbot finding, BWPM-281): is_genuinely_settled_but_untracked()
	 * spends one unit of the live-check budget on every candidate it inspects, including ones the
	 * live check confirms are genuinely still unpaid. If find() scanned newest-completed-first, an
	 * ever-refreshing pool of recent, correctly-unpaid orders would permanently consume the whole
	 * budget every run, and the actual (older) backlog this feature exists to clear would never
	 * get a live check at all. Creates exactly MAX_LIVE_VERIFICATIONS_PER_RUN recent genuinely-
	 * unpaid orders plus one older genuinely-settled-but-untracked order, and confirms the older
	 * order still gets checked and healed within the same run.
	 *
	 * @see UnsettledOrdersFinder::find
	 */
	public function test_find_prioritizes_oldest_orders_for_live_verification_budget() {
		$cap = UnsettledOrdersFinder::MAX_LIVE_VERIFICATIONS_PER_RUN;

		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$old_order = $this->order_generator->order();
		$old_order->set_date_completed( '2026-07-10 00:00:00' );
		$old_order->save();
		$old_order_id = $old_order->get_id();

		for ( $i = 0; $i < $cap; $i++ ) {
			$this->order_generator->generate( array( 'status' => 'completed' ) );
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
			$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
			$this->order_generator->order()->calculate_totals();
			$order = $this->order_generator->order();
			$order->set_date_completed( '2026-08-20 00:00:00' );
			$order->save();
		}

		$this->api_mock->method( 'get_invoice_data' )->willReturnCallback(
			function ( $order ) use ( $old_order_id ) {
				$settled = ( $order->get_id() === $old_order_id ) ? 2000 : 0;

				return array(
					'authorized_amount' => 2000,
					'settled_amount'    => $settled,
				);
			}
		);

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertNotContains( $old_order_id, $result['order_ids'], 'The oldest, genuinely settled order should have been prioritized for a live check and excluded' );

		$old_order = wc_get_order( $old_order_id );
		$this->assertSame( 1, (int) $old_order->get_meta( '_reepay_state_settled' ), 'The oldest order should have been healed within the per-run budget instead of being starved out by newer orders' );
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

	/**
	 * Test @see UnsettledOrdersFinder::find() caps results at MAX_RESULTS and reports capped=true.
	 */
	public function test_find_caps_results_and_reports_capped() {
		for ( $i = 0; $i < UnsettledOrdersFinder::MAX_RESULTS + 2; $i++ ) {
			// wc_create_order() (used internally by generate()) only recognizes a fixed whitelist
			// of args (status, customer_id, customer_note, parent, created_via, cart_hash,
			// order_id) — 'payment_method' is silently dropped, so it must be set afterwards via
			// set_props(), same as every other test in this file.
			$this->order_generator->generate( array( 'status' => 'completed' ) );
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
			$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
			$this->order_generator->order()->calculate_totals();
			$order = $this->order_generator->order();
			$order->set_date_completed( '2026-08-10 00:00:00' );
			$order->save();
		}

		$result = ( new UnsettledOrdersFinder() )->find();

		$this->assertCount( UnsettledOrdersFinder::MAX_RESULTS, $result['order_ids'] );
		$this->assertTrue( $result['capped'] );
	}
}
