<?php
/**
 * Class ApiTest
 *
 * @package Reepay\Checkout
 */

/**
 * Class ApiConfiguredTest
 */
class ApiConfiguredTest extends WP_UnitTestCase {

	/**
	 * Checks if api is configured
	 */
	public function test_is_webhook_configured() {
		$this->assertTrue( reepay()->gateways()->checkout()->is_webhook_configured() );
	}
}
