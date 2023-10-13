<?php
/**
 * Class HposTest
 *
 * @package Reepay\Checkout
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * HposTest.
 */
class HposTest extends Reepay_UnitTestCase {

	public function tear_down() {
		parent::tear_down();

		delete_option( CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION );
	}

	/**
	 * Test @see rp_hpos_enabled
	 */
	public function test_rp_hpos_enabled() {
		update_option( CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, 'yes' );

		$this->assertSame( OrderUtil::custom_orders_table_usage_is_enabled(), rp_hpos_enabled() );
		$this->assertTrue( rp_hpos_enabled() );

		update_option( CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, 'no' );

		$this->assertSame( OrderUtil::custom_orders_table_usage_is_enabled(), rp_hpos_enabled()  );
		$this->assertFalse( rp_hpos_enabled() );
	}

	/**
	 * Test @see rp_hpos_is_order_page
	 *
	 * @testWith
	 * [false, false, false, false]
	 * [false, false, true, false]
	 * [false, true, false, false]
	 * [false, true, true, false]
	 * [true, false, false, false]
	 * [true, false, true, false]
	 * [true, true, false, false]
	 * [true, true, true, true]
	 */
	public function test_rp_hpos_is_order_page(bool $is_admin, bool $hpos_enabled, bool $order_id_in_url, bool $result) {
		set_current_screen( $is_admin ? 'edit-post' : 'front' );
		update_option( CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, $hpos_enabled ? 'yes' : 'no' );

		if($order_id_in_url) {
			if ( $is_admin && $hpos_enabled ) {
				$this->markTestSkipped( 'Possible only with really enabled hpos' );
			}

			$this->order_generator->order()->save();
			$_GET['id'] = $this->order_generator->order()->get_id();
		} else {
			unset( $_GET['id'] );
		}

		$this->assertSame( $result, rp_hpos_is_order_page() );
	}
}
