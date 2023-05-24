<?php
/**
 * Class MainClassTest
 *
 * @package Reepay\Checkout
 */

/**
 * MainClassTest.
 */
class MainClassTest extends WP_UnitTestCase {
	public function test_reepay_function() {
		$this->assertSame( get_class( reepay() ), WC_ReepayCheckout::class );
	}
}
