<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

/**
 * CurrencyTest.
 */
class SubscriptionsTest extends WP_UnitTestCase {
	public function test_order_contains_subscription() {
		$order_generator = new Rp_Test_Order_Generator();
		$order_generator->add_simple_product();

		if( function_exists( 'wcs_order_contains_subscription' ) ) {
			$order_generator->add_woo_subscription();
			$this->assertTrue( order_contains_subscription( $order_generator->get_order() ) );
		} else {
			$this->assertFalse( order_contains_subscription( $order_generator->get_order() ) );
		}

		$order_generator->delete_order();
	}

	public function test_wcs_is_subscription_product() {

	}
}