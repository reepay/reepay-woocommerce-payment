<?php
/**
 * Class AnalyticsSyncTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Analytics\AnalyticsSync;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * AnalyticsSyncTest.
 *
 * @covers \Reepay\Checkout\Analytics\AnalyticsSync
 * @group analytics_sync
 */
class AnalyticsSyncTest extends Reepay_UnitTestCase {

	/**
	 * AnalyticsSync instance under test.
	 *
	 * @var AnalyticsSync
	 */
	private AnalyticsSync $analytics_sync;

	/**
	 * Runs before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->analytics_sync = new AnalyticsSync();
	}

	// -------------------------------------------------------------------------
	// is_order_in_analytics
	// -------------------------------------------------------------------------

	/**
	 * Test @see AnalyticsSync::is_order_in_analytics returns false for an order not in analytics.
	 */
	public function test_is_order_in_analytics_false() {
		$order_id = $this->order_generator->order()->get_id();

		// No row exists in wc_order_stats for this order in the test environment.
		$this->assertFalse( $this->analytics_sync->is_order_in_analytics( $order_id ) );
	}

	/**
	 * Test @see AnalyticsSync::is_order_in_analytics returns true when a row is present.
	 */
	public function test_is_order_in_analytics_true() {
		global $wpdb;

		$order_id = $this->order_generator->order()->get_id();

		// Insert a stub row into the analytics table.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'wc_order_stats',
			array(
				'order_id'         => $order_id,
				'parent_id'        => 0,
				'date_created'     => current_time( 'mysql' ),
				'date_created_gmt' => current_time( 'mysql', true ),
				'num_items_sold'   => 1,
				'total_sales'      => 10.00,
				'tax_total'        => 0.00,
				'shipping_total'   => 0.00,
				'net_total'        => 10.00,
				'returning_customer' => 0,
				'status'           => 'wc-processing',
				'customer_id'      => 0,
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Invalidate the cache populated by a previous is_order_in_analytics call.
		wp_cache_delete( 'reepay_order_in_analytics_' . $order_id, 'reepay_analytics' );

		$this->assertTrue( $this->analytics_sync->is_order_in_analytics( $order_id ) );

		// Cleanup.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'wc_order_stats', array( 'order_id' => $order_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	// -------------------------------------------------------------------------
	// handle_order_status_change
	// -------------------------------------------------------------------------

	/**
	 * Test @see AnalyticsSync::handle_order_status_change does not call sync for non-Reepay orders.
	 */
	public function test_handle_order_status_change_skips_non_reepay() {
		$this->order_generator->set_prop( 'payment_method', 'cod' );
		$order = $this->order_generator->order();

		// Track whether woocommerce_update_order is fired.
		$fired = false;
		add_action(
			'woocommerce_update_order',
			function() use ( &$fired ) {
				$fired = true;
			}
		);

		$this->analytics_sync->handle_order_status_change( $order->get_id(), 'pending', 'processing', $order );

		$this->assertFalse( $fired, 'Analytics sync should not run for non-Reepay orders.' );
	}

	/**
	 * Test @see AnalyticsSync::handle_order_status_change triggers sync for Reepay orders.
	 */
	public function test_handle_order_status_change_triggers_sync_for_reepay_order() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$order = $this->order_generator->order();

		$fired = false;
		add_action(
			'woocommerce_update_order',
			function() use ( &$fired ) {
				$fired = true;
			}
		);

		$this->analytics_sync->handle_order_status_change( $order->get_id(), 'pending', 'processing', $order );

		$this->assertTrue( $fired, 'Analytics sync should run when a Reepay order status changes to a reporting status.' );
	}

	/**
	 * Test @see AnalyticsSync::handle_order_status_change skips irrelevant status transitions.
	 */
	public function test_handle_order_status_change_skips_irrelevant_transition() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$order = $this->order_generator->order();

		$fired = false;
		add_action(
			'woocommerce_update_order',
			function() use ( &$fired ) {
				$fired = true;
			}
		);

		// 'pending' → 'checkout-draft' is not a reporting status transition.
		$this->analytics_sync->handle_order_status_change( $order->get_id(), 'pending', 'checkout-draft', $order );

		$this->assertFalse( $fired, 'Analytics sync should not run for non-reporting status transitions.' );
	}

	// -------------------------------------------------------------------------
	// sync_order_analytics
	// -------------------------------------------------------------------------

	/**
	 * Test @see AnalyticsSync::sync_order_analytics does nothing when order_id is missing from data.
	 */
	public function test_sync_order_analytics_skips_empty_data() {
		$fired = false;
		add_action(
			'woocommerce_update_order',
			function() use ( &$fired ) {
				$fired = true;
			}
		);

		$this->analytics_sync->sync_order_analytics( array() );

		$this->assertFalse( $fired, 'sync_order_analytics should do nothing when order_id is absent.' );
	}

	/**
	 * Test @see AnalyticsSync::sync_order_analytics fires woocommerce_update_order for a valid order.
	 */
	public function test_sync_order_analytics_fires_update_hook() {
		$order    = $this->order_generator->order();
		$order_id = $order->get_id();

		$fired = false;
		add_action(
			'woocommerce_update_order',
			function( $id ) use ( $order_id, &$fired ) {
				if ( $id === $order_id ) {
					$fired = true;
				}
			}
		);

		$this->analytics_sync->sync_order_analytics( array( 'order_id' => $order_id ) );

		$this->assertTrue( $fired, 'sync_order_analytics should fire woocommerce_update_order for a valid order.' );
	}
}
