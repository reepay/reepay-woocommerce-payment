<?php
/**
 * Class ReepayCheckoutFinalizeLogTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Tests that reepay_finalize() keeps request secrets out of the log.
 *
 * @covers \Reepay\Checkout\Gateways\ReepayCheckout::reepay_finalize
 * @group gateways_checkout
 */
class ReepayCheckoutFinalizeLogTest extends Reepay_UnitTestCase {

	/**
	 * Log messages written during the test.
	 *
	 * @var string[]
	 */
	private array $logs = array();

	/**
	 * Captures log lines and turns the handler's final redirect into an exception, so exit() is not reached.
	 */
	public function set_up() {
		parent::set_up();

		$this->logs = array();

		add_filter(
			'woocommerce_logger_log_message',
			function ( $message ) {
				$this->logs[] = (string) $message;

				return $message;
			}
		);
		add_filter(
			'wp_redirect',
			function () {
				throw new RuntimeException( 'redirect' );
			}
		);
	}

	/**
	 * Removes the filters.
	 */
	public function tear_down() {
		remove_all_filters( 'woocommerce_logger_log_message' );
		remove_all_filters( 'wp_redirect' );

		parent::tear_down();
	}

	/**
	 * The order key, payment token and nonce are not written to the log; the invoice handle is.
	 */
	public function test_request_secrets_are_not_logged() {
		$nonce = wp_create_nonce( 'reepay_finalize' );
		$_GET  = array(
			'_wpnonce'       => $nonce,
			'payment_method' => 'ca_secret_token_value',
			'key'            => 'wc_order_secretkey123',
			'invoice'        => 'order-handle-42',
		);

		try {
			( new ReepayCheckout() )->reepay_finalize();
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$everything = implode( "\n", $this->logs );
		$this->assertStringContainsString( 'reepay_finalize', $everything );
		// The invoice handle and which parameters were present stay available for tracing.
		$this->assertStringContainsString( 'order-handle-42', $everything );
		$this->assertStringContainsString( '[has_key] => 1', $everything );
		$this->assertStringContainsString( '[has_token] => 1', $everything );
		$this->assertStringNotContainsString( 'secret', $everything );
		$this->assertStringNotContainsString( $nonce, $everything );
	}
}
