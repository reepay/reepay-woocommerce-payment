<?php
/**
 * Unit test
 *
 * @package Reepay\Checkout\Tests\Unit\Functions
 */

namespace Reepay\Checkout\Tests\Unit\Functions;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Reepay\Checkout\Tests\Helpers\HPOS_STATE;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Test class
 */
class HposTest extends Reepay_UnitTestCase {
	/**
	 * Test @see rp_hpos_enabled
	 */
	public function test_rp_hpos_enabled() {
		$this->assertSame( OrderUtil::custom_orders_table_usage_is_enabled(), rp_hpos_enabled() );
	}

	/**
	 * Test @see rp_hpos_is_order_page
	 *
	 * @testWith
	 * [false, false, false]
	 * [false, true, false]
	 * [true, false, false]
	 * [true, true, true]
	 */
	public function test_rp_hpos_is_order_page(bool $is_admin, bool $order_id_in_url, bool $result) {
		set_current_screen( $is_admin ? 'edit-post' : 'front' );

		if($order_id_in_url) {
			$this->order_generator->order()->save();
			$_GET['id'] = $this->order_generator->order()->get_id();
		} else {
			unset( $_GET['id'] );
		}

		$this->assertSame( $result && rp_hpos_enabled(), rp_hpos_is_order_page() );
	}
}
