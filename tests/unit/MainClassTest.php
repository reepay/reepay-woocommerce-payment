<?php
/**
 * Unit test
 *
 * @package Reepay\Checkout\Tests\Unit
 */

namespace Reepay\Checkout\Tests\Unit;

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use WC_ReepayCheckout;

/**
 * Test class
 */
class MainClassTest extends Reepay_UnitTestCase {
	/**
	 * Test main plugin function. Checks that there is no infinite recursion.
	 */
	public function test_reepay_function() {
		$this->assertSame( WC_ReepayCheckout::class, get_class( reepay() ) );
	}
}
