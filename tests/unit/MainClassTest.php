<?php
/**
 * Class MainClassTest
 *
 * @package Reepay\Checkout
 */

/**
 * Class MainClassTest
 */
class MainClassTest extends WP_UnitTestCase {
	/**
	 * Test main plugin function. Checks that there is no infinite recursion.
	 */
	public function test_reepay_function() {
		$this->assertSame( get_class( reepay() ), WC_ReepayCheckout::class );
	}
}
