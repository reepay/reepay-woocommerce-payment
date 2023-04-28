<?php
/**
 * Class SampleTest
 *
 * @package ./reepay_Woocommerce_Payment
 */

/**
 * Sample test case.
 */
class MainClassTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_reepay_function() {
		$this->assertEquals(
			'WC_ReepayCheckout',
			get_class( reepay() )
		);
	}
}
