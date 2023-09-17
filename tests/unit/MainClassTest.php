<?php
/**
 * Class MainClassTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Class MainClassTest
 */
class MainClassTest extends Reepay_UnitTestCase {
	/**
	 * Test main plugin function. Checks that there is no infinite recursion.
	 */
	public function test_reepay_function() {
		$this->assertSame( WC_ReepayCheckout::class, get_class( reepay() ) );
	}
}
