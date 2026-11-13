<?php
/**
 * Class ReepayCheckoutLogUnsafeRedirectTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Tests\Helpers\Reepay_Ajax_UnitTestCase;

/**
 * Tests for the logged-out reepay_log_unsafe_redirect endpoint.
 *
 * Log lines are captured with `woocommerce_logger_log_message`, so nothing is written to a log file.
 *
 * @covers \Reepay\Checkout\Gateways\ReepayCheckout::ajax_log_unsafe_redirect
 * @group gateways_checkout
 */
class ReepayCheckoutLogUnsafeRedirectTest extends Reepay_Ajax_UnitTestCase {

	/**
	 * Log messages written during the test.
	 *
	 * @var string[]
	 */
	private array $logs = array();

	/**
	 * Runs in the main process: the handler is registered per test and nothing is shared.
	 *
	 * @var bool
	 */
	protected $runTestInSeparateProcess = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	/**
	 * Registers the handler and the log capture for a logged-out visitor.
	 */
	public function set_up() {
		parent::set_up();

		// _handleAjax() fires admin_init; WooCommerce hooks into it and needs its admin helpers,
		// which are not loaded when the test runs in the main process.
		if ( ! function_exists( 'wc_get_page_screen_id' ) ) {
			require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		}

		$this->logs = array();
		wp_set_current_user( 0 );
		new ReepayCheckout();

		add_filter(
			'woocommerce_logger_log_message',
			function ( $message ) {
				// The WooCommerce logger runs this filter more than once per entry, and other code logs too.
				if ( false !== strpos( (string) $message, 'checkout_js_blocked_unsafe_accept_url' ) && ! in_array( (string) $message, $this->logs, true ) ) {
					$this->logs[] = (string) $message;
				}

				return $message;
			}
		);
	}

	/**
	 * Posts a report as a logged-out visitor and returns the decoded JSON response.
	 *
	 * @param array  $params Extra POST values, merged over a valid nonce.
	 * @param string $ip     Client address.
	 *
	 * @return array
	 */
	private function report( array $params, string $ip = '203.0.113.7' ): array {
		$_SERVER['REMOTE_ADDR'] = $ip;
		$_POST                  = array_merge( array( 'nonce' => wp_create_nonce( 'reepay' ) ), $params );
		$_REQUEST               = $_POST;
		$this->_last_response   = '';

		try {
			$this->_handleAjax( 'reepay_log_unsafe_redirect' );
		} catch ( WPAjaxDieContinueException | WPAjaxDieStopException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true ) ?? array();
	}

	/**
	 * A genuine report is logged with the id and the host only, and succeeds.
	 */
	public function test_valid_report_is_logged_with_host_only() {
		$response = $this->report(
			array(
				'rid' => 'rid_123',
				'url' => 'https://evil.example/path?key=secret&x=1',
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $this->logs );
		$this->assertStringContainsString( 'rid_123', $this->logs[0] );
		$this->assertStringContainsString( 'https://evil.example', $this->logs[0] );
		$this->assertStringNotContainsString( 'secret', $this->logs[0] );
		$this->assertStringNotContainsString( '/path', $this->logs[0] );
	}

	/**
	 * Oversize values are cut and control characters never reach the log.
	 */
	public function test_oversize_and_control_characters_are_neutralised() {
		$this->report(
			array(
				'rid' => "rid\r\n[2026-01-01] forged line " . str_repeat( 'A', 10000 ),
				'url' => 'https://' . str_repeat( 'b', 10000 ) . '.example/',
			)
		);

		$this->assertCount( 1, $this->logs );
		$this->assertLessThan( 600, strlen( $this->logs[0] ) );
		// The log has the array keys on their own lines; the client values add none.
		$this->assertSame( 6, substr_count( $this->logs[0], "\n" ) );
		$this->assertStringNotContainsString( "\r", $this->logs[0] );
	}

	/**
	 * Array values are rejected and not logged.
	 */
	public function test_array_values_are_rejected() {
		$response = $this->report(
			array(
				'rid' => array( 'a' => 'b' ),
				'url' => 'https://shop.example/',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertCount( 0, $this->logs );
	}

	/**
	 * A second report from the same client inside the window is refused and not logged.
	 */
	public function test_second_report_from_same_client_is_rate_limited() {
		$params = array(
			'rid' => 'rid_1',
			'url' => 'https://shop.example/',
		);

		$this->assertTrue( $this->report( $params )['success'] );
		$this->assertFalse( $this->report( $params )['success'] );
		$this->assertCount( 1, $this->logs );

		// Another client is not affected.
		$params['rid'] = 'rid_2';
		$this->assertTrue( $this->report( $params, '203.0.113.8' )['success'] );
		$this->assertCount( 2, $this->logs );
	}

	/**
	 * A wrong nonce is refused and not logged.
	 */
	public function test_invalid_nonce_is_rejected() {
		$this->report(
			array(
				'nonce' => 'bad',
				'rid'   => 'rid_1',
				'url'   => 'https://shop.example/',
			)
		);

		$this->assertCount( 0, $this->logs );
	}
}
