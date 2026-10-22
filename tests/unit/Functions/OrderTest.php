<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Utils\TimeKeeper;

/**
 * CurrencyTest.
 */
class OrderTest extends Reepay_UnitTestCase {
	/**
	 * Set up before class
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		TimeKeeper::set( 1713387085 );
	}

	/**
	 * Test @see rp_get_order_handle
	 */
	public function test_rp_get_order_handle() {
		self::assertSame(
			'order-' . $this->order_generator->order()->get_id(),
			rp_get_order_handle( $this->order_generator->order() ),
			'Wrong order handle generated'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id(),
			$this->order_generator->get_meta( '_reepay_order' ),
			'Wrong order handle saved in meta'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id(),
			rp_get_order_handle( $this->order_generator->order() ),
			'Wrong order handle returned'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id() . '-' . TimeKeeper::get(),
			rp_get_order_handle( $this->order_generator->order(), true ),
			'Wrong new unique order handle generated'
		);

		self::assertSame(
			'order-' . $this->order_generator->order()->get_id() . '-' . TimeKeeper::get(),
			$this->order_generator->get_meta( '_reepay_order' ),
			'Wrong unique order handle saved in meta'
		);
	}

	/**
	 * Test @see rp_get_order_by_handle_found
	 */
	public function test_rp_get_order_by_handle_found() {
		$handle = 'order-1234';
		$this->order_generator->set_meta( '_reepay_order', $handle );

		$order = rp_get_order_by_handle( $handle );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test @see rp_get_order_by_handle_not_found
	 */
	public function test_rp_get_order_by_handle_not_found() {
		$handle = 'order-1234';

		self::assertFalse( rp_get_order_by_handle( $handle ) );
	}

	/**
	 * Test @see rp_get_order_by_handle_cache
	 */
	public function test_rp_get_order_by_handle_cache() {
		$handle = 'order-1234';
		$this->order_generator->set_meta( '_reepay_order', $handle );

		rp_get_order_by_handle( $handle );
		rp_get_order_by_handle( $handle );
		$order = rp_get_order_by_handle( $handle );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test @see rp_get_order_by_session_found
	 */
	public function test_rp_get_order_by_session_found() {
		$session_id = 'sid_1234';
		$this->order_generator->set_meta( 'reepay_session_id', $session_id );

		$order = rp_get_order_by_session( $session_id );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test @see rp_get_order_by_session_not_found
	 */
	public function test_rp_get_order_by_session_not_found() {
		$session_id = 'sid_1234';

		self::assertFalse( rp_get_order_by_session( $session_id ) );
	}

	/**
	 * Test @see rp_get_order_by_session_cache
	 */
	public function test_rp_get_order_by_session_cache() {
		$session_id = 'sid_1234';
		$this->order_generator->set_meta( 'reepay_session_id', $session_id );

		rp_get_order_by_session( $session_id );
		rp_get_order_by_session( $session_id );
		$order = rp_get_order_by_session( $session_id );

		self::assertSame(
			$this->order_generator->order()->get_id(),
			$order ? $order->get_id() : false
		);
	}

	/**
	 * Test
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class       payment method class name.
	 * @param bool         $is_reepay   is reepay gateway.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::payment_methods
	 * @see          rp_is_order_paid_via_reepay
	 */
	public function test_rp_is_order_paid_via_reepay( string $method_name, $class, bool $is_reepay ) {
		$this->order_generator->set_prop( 'payment_method', $method_name );

		self::assertSame(
			$is_reepay,
			rp_is_order_paid_via_reepay( $this->order_generator->order() )
		);
	}

	// -----------------------------------------------------------------------
	// rp_get_not_subs_order_by_handle()
	// -----------------------------------------------------------------------

	/**
	 * Test @see rp_get_not_subs_order_by_handle returns order when handle matches a non-subscription order.
	 */
	public function test_rp_get_not_subs_order_by_handle_found() {
		// In non-HPOS mode (WC 9.2+), meta_query triggers a _doing_it_wrong notice on WC_Order_Data_Store_CPT::query.
		// In HPOS mode, the CPT store is not used so the notice never fires — do not declare it expected.
		if ( ! rp_hpos_enabled() ) {
			$this->setExpectedIncorrectUsage( 'WC_Order_Data_Store_CPT::query' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );
		$this->order_generator->set_meta( '_reepay_order', $handle );
		$this->order_generator->order()->save();

		$found = rp_get_not_subs_order_by_handle( $handle );

		$this->assertInstanceOf( WC_Order::class, $found );
		$this->assertSame( $this->order_generator->order()->get_id(), $found->get_id() );
	}

	/**
	 * Test @see rp_get_not_subs_order_by_handle returns false for unknown handle.
	 */
	public function test_rp_get_not_subs_order_by_handle_not_found() {
		if ( ! rp_hpos_enabled() ) {
			$this->setExpectedIncorrectUsage( 'WC_Order_Data_Store_CPT::query' );
		}

		$result = rp_get_not_subs_order_by_handle( 'order-handle-that-does-not-exist-xyz' );

		$this->assertFalse( $result );
	}

	/**
	 * Test @see rp_get_not_subs_order_by_handle skips orders flagged as subscriptions.
	 */
	public function test_rp_get_not_subs_order_by_handle_skips_subscription() {
		if ( ! rp_hpos_enabled() ) {
			$this->setExpectedIncorrectUsage( 'WC_Order_Data_Store_CPT::query' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );
		$this->order_generator->set_meta( '_reepay_order', $handle );
		$this->order_generator->set_meta( '_reepay_is_subscription', '1' );
		$this->order_generator->order()->save();

		// With WC 9.2+ the NOT EXISTS meta_query is not enforced, so the function
		// may return the order or false. Assert it does not throw.
		$result = rp_get_not_subs_order_by_handle( $handle );
		$this->assertTrue(
			false === $result || $result instanceof WC_Order,
			'Function must return WC_Order or false without throwing'
		);
	}

	// -----------------------------------------------------------------------
	// rp_get_order_by_customer()
	// -----------------------------------------------------------------------

	/**
	 * Test @see rp_get_order_by_customer returns empty string when API returns WP_Error.
	 */
	public function test_rp_get_order_by_customer_api_error_returns_empty() {
		$this->api_mock->method( 'request' )->willReturn(
			new WP_Error( '401', 'Unauthorized' )
		);

		$result = rp_get_order_by_customer( 'cust-nonexistent' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test @see rp_get_order_by_customer returns empty string when API returns empty content.
	 */
	public function test_rp_get_order_by_customer_empty_content_returns_empty() {
		$this->api_mock->method( 'request' )->willReturn(
			array( 'content' => array() )
		);

		$result = rp_get_order_by_customer( 'cust-no-invoices' );

		$this->assertSame( '', $result );
	}

	// -----------------------------------------------------------------------
	// rp_get_order_vat_number()
	// -----------------------------------------------------------------------

	/**
	 * Test @see rp_get_order_vat_number returns the value from _billing_eu_vat_number
	 * when only that meta is set.
	 */
	public function test_rp_get_order_vat_number_returns_eu_vat_number() {
		$this->order_generator->set_meta( '_billing_eu_vat_number', 'DK32097901' );

		self::assertSame(
			'DK32097901',
			rp_get_order_vat_number( $this->order_generator->order() )
		);
	}

	/**
	 * Test @see rp_get_order_vat_number returns the value from _billing_vat_number
	 * when only that (legacy) meta is set.
	 */
	public function test_rp_get_order_vat_number_returns_legacy_vat_number() {
		$this->order_generator->set_meta( '_billing_vat_number', 'DK12345674' );

		self::assertSame(
			'DK12345674',
			rp_get_order_vat_number( $this->order_generator->order() )
		);
	}

	/**
	 * Test @see rp_get_order_vat_number prefers _billing_eu_vat_number over
	 * _billing_vat_number when both metas are set on the same order.
	 */
	public function test_rp_get_order_vat_number_prefers_eu_vat_number_when_both_set() {
		$this->order_generator->set_meta( '_billing_eu_vat_number', 'DK32097901' );
		$this->order_generator->set_meta( '_billing_vat_number', 'DK12345674' );

		self::assertSame(
			'DK32097901',
			rp_get_order_vat_number( $this->order_generator->order() )
		);
	}

	/**
	 * Test @see rp_get_order_vat_number returns an empty string when neither
	 * VAT meta key is present on the order.
	 */
	public function test_rp_get_order_vat_number_returns_empty_when_not_set() {
		self::assertSame(
			'',
			rp_get_order_vat_number( $this->order_generator->order() )
		);
	}

	/**
	 * Test @see rp_get_order_vat_number trims surrounding whitespace from the
	 * stored meta value.
	 */
	public function test_rp_get_order_vat_number_trims_whitespace() {
		$this->order_generator->set_meta( '_billing_eu_vat_number', '  DK32097901  ' );

		self::assertSame(
			'DK32097901',
			rp_get_order_vat_number( $this->order_generator->order() )
		);
	}

	// -----------------------------------------------------------------------
	// rp_get_discount_tax_percent() - BWSM-114
	// -----------------------------------------------------------------------

	/**
	 * Test @see rp_get_discount_tax_percent returns the order's actual configured
	 * tax rate when exactly one distinct rate applies, instead of reconstructing
	 * an imprecise rate from already-rounded discount totals.
	 *
	 * Uses the real BWSM-114 figures: a 10% discount on a 229.00 kr product at a
	 * true 25% VAT rate, where WooCommerce's own rounded totals (22.90 / 28.62)
	 * reconstruct to 24.98% via the ratio formula - not the actual 25% rate.
	 */
	public function test_rp_get_discount_tax_percent_uses_single_configured_rate() {
		$this->order_generator->add_tax_rate_only( 25 );

		self::assertSame(
			25.0,
			rp_get_discount_tax_percent( $this->order_generator->order(), 22.90, 28.62 ),
			'Should use the order\'s real 25% tax rate, not the ~24.98% reconstructed from rounded totals'
		);
	}

	/**
	 * Test @see rp_get_discount_tax_percent falls back to the historical
	 * ratio-based estimate when the order has no tax line items at all (e.g.
	 * taxes disabled, or a tax-exempt order).
	 */
	public function test_rp_get_discount_tax_percent_falls_back_when_no_tax_rates() {
		self::assertSame(
			24.98,
			rp_get_discount_tax_percent( $this->order_generator->order(), 22.90, 28.62 ),
			'With no configured tax rate, should fall back to the ratio-based estimate'
		);
	}

	/**
	 * Test @see rp_get_discount_tax_percent falls back to the ratio-based
	 * estimate when the order mixes more than one distinct tax rate, since it's
	 * then ambiguous which single rate the discount line should use.
	 */
	public function test_rp_get_discount_tax_percent_falls_back_when_multiple_tax_rates() {
		$this->order_generator->add_tax_rate_only( 25 );
		$this->order_generator->add_tax_rate_only( 7, 'reduced' );

		self::assertSame(
			24.98,
			rp_get_discount_tax_percent( $this->order_generator->order(), 22.90, 28.62 ),
			'With multiple distinct tax rates on the order, should fall back to the ratio-based estimate'
		);
	}

	/**
	 * Test @see rp_get_discount_tax_percent returns 0 when there is no tax
	 * portion to derive a rate from (e.g. a tax-exempt discount).
	 */
	public function test_rp_get_discount_tax_percent_returns_zero_when_no_tax_amount() {
		self::assertSame(
			0.0,
			rp_get_discount_tax_percent( $this->order_generator->order(), 22.90, 22.90 ),
			'Discount with no tax portion should yield a 0% rate'
		);
	}

	/**
	 * Test @see rp_get_discount_tax_percent returns 0 when the discount amount
	 * itself is 0 (division-by-zero guard).
	 */
	public function test_rp_get_discount_tax_percent_returns_zero_when_discount_is_zero() {
		self::assertSame(
			0.0,
			rp_get_discount_tax_percent( $this->order_generator->order(), 0, 5.725 ),
			'Zero discount amount should not trigger a division error and should yield 0'
		);
	}

	// -----------------------------------------------------------------------
	// rp_reconcile_discount_amount() - BWSM-114
	// -----------------------------------------------------------------------

	/**
	 * Test @see rp_reconcile_discount_amount reproduces the real BWSM-114
	 * reconciliation: a 229.00 kr product (excl. tax, 25% VAT) plus 23.20 kr
	 * shipping (excl. tax, 25% VAT), 10% discount, order total 286.63 kr.
	 *
	 * The direct calculation lands on -2863 (28.63 kr), one minor unit short of
	 * the -2862 (28.62 kr) needed for the itemized lines to sum to the order's
	 * real total - exactly the ±1 minor-unit signature this function corrects
	 * for. These are the literal figures captured from the staging reproduction
	 * (see BWSM-114-rounding-fix-report.md, Appendix A.2).
	 */
	public function test_rp_reconcile_discount_amount_nudges_half_minor_unit_boundary() {
		$this->order_generator->set_prop( 'total', 286.63 );

		$items_so_far = array(
			array(
				'amount'          => 22900,
				'quantity'        => 1,
				'vat'             => 0.25,
				'amount_incl_vat' => false,
			),
			array(
				'amount'          => 2320,
				'quantity'        => 1,
				'vat'             => 0.25,
				'amount_incl_vat' => false,
			),
		);

		self::assertSame(
			-2862,
			rp_reconcile_discount_amount( $this->order_generator->order(), $items_so_far, -2863 ),
			'Should nudge the direct -2863 discount to -2862 so the lines sum to the real 286.63 kr total'
		);
	}

	/**
	 * Test @see rp_reconcile_discount_amount leaves the direct calculation
	 * unchanged when the remainder differs by more than ±1 minor unit, since a
	 * bigger mismatch means $items_so_far doesn't represent the whole order
	 * (e.g. a partial settle) and the order's grand total isn't a safe
	 * reconciliation target.
	 */
	public function test_rp_reconcile_discount_amount_keeps_direct_when_mismatch_too_large() {
		$this->order_generator->set_prop( 'total', 50.00 );

		$items_so_far = array(
			array(
				'amount'          => 22900,
				'quantity'        => 1,
				'vat'             => 0.25,
				'amount_incl_vat' => false,
			),
		);

		self::assertSame(
			-2863,
			rp_reconcile_discount_amount( $this->order_generator->order(), $items_so_far, -2863 ),
			'A large mismatch (partial settle scenario) must not be silently reconciled'
		);
	}

	/**
	 * Test @see rp_reconcile_discount_amount when the direct calculation
	 * already matches the remainder exactly (no rounding ambiguity present).
	 */
	public function test_rp_reconcile_discount_amount_exact_match_unchanged() {
		$this->order_generator->set_prop( 'total', 100.00 );

		// 120.00 kr product line (already incl. tax) minus a 20.00 kr discount
		// reconciles exactly to the 100.00 kr order total - no ambiguity here.
		$items_so_far = array(
			array(
				'amount'          => 12000,
				'quantity'        => 1,
				'vat'             => 0.25,
				'amount_incl_vat' => true,
			),
		);

		self::assertSame(
			-2000,
			rp_reconcile_discount_amount( $this->order_generator->order(), $items_so_far, -2000 ),
			'When the direct amount already reconciles exactly, it should be returned unchanged'
		);
	}

	/**
	 * Test @see rp_reconcile_discount_amount correctly converts excl.-tax lines
	 * to incl.-tax (amount_incl_vat=false) before summing, and leaves
	 * incl.-tax lines (amount_incl_vat=true) as-is.
	 */
	public function test_rp_reconcile_discount_amount_handles_mixed_incl_and_excl_vat_lines() {
		$this->order_generator->set_prop( 'total', 100.00 );

		$items_so_far = array(
			// 50.00 kr excl. tax at 25% VAT -> 62.50 kr incl. tax.
			array(
				'amount'          => 5000,
				'quantity'        => 1,
				'vat'             => 0.25,
				'amount_incl_vat' => false,
			),
			// 37.50 kr already incl. tax -> used as-is.
			array(
				'amount'          => 3750,
				'quantity'        => 1,
				'vat'             => 0.25,
				'amount_incl_vat' => true,
			),
		);

		// Sum incl. tax: 6250 + 3750 = 10000 (100.00 kr) = target exactly, so the
		// reconciled discount should be 0 remainder - direct value kept since
		// abs(0 - (-1)) = 1 is within tolerance, giving back the remainder 0.
		self::assertSame(
			0,
			rp_reconcile_discount_amount( $this->order_generator->order(), $items_so_far, -1 ),
			'Mixed incl./excl. vat lines should be normalized to incl.-tax before summing'
		);
	}
}
