<?php
/**
 * Class ApiTest
 *
 * @package Reepay\Checkout
 */

/**
 * ApiTest.
 */
class ApiTest extends WP_UnitTestCase {
	public function test_is_webhook_configured() {
		$this->assertTrue( reepay()->gateways()->checkout()->is_webhook_configured() );
	}
}
