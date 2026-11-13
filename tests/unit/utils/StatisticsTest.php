<?php
/**
 * Class StatisticsTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Plugin\Statistics;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Tests for the statistics event sent to the external endpoint.
 *
 * The HTTP call is intercepted with `pre_http_request` and log lines are captured with
 * `woocommerce_logger_log_message`, so no request leaves the test and no key is written to a log file.
 *
 * @covers \Reepay\Checkout\Plugin\Statistics
 * @group statistics
 */
class StatisticsTest extends Reepay_UnitTestCase {

	const LIVE_KEY = 'priv_live_key_for_unit_tests';
	const TEST_KEY = 'priv_test_key_for_unit_tests';

	/**
	 * Requests seen by the HTTP mock.
	 *
	 * @var array
	 */
	private array $requests = array();

	/**
	 * Log messages written during the test.
	 *
	 * @var string[]
	 */
	private array $logs = array();

	/**
	 * Mocks the HTTP layer and the logger.
	 */
	public function set_up() {
		parent::set_up();

		$this->requests = array();
		$this->logs     = array();

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				$this->requests[] = array(
					'url'  => $url,
					'body' => (string) ( $parsed_args['body'] ?? '' ),
				);

				return array(
					'headers'  => array(),
					'body'     => 'accepted ' . self::LIVE_KEY . ' ' . self::TEST_KEY,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		add_filter(
			'woocommerce_logger_log_message',
			function ( $message ) {
				$this->logs[] = (string) $message;

				return $message;
			}
		);

		self::$options->set_options(
			array(
				'test_mode'        => 'no',
				'private_key'      => self::LIVE_KEY,
				'private_key_test' => self::TEST_KEY,
			)
		);
	}

	/**
	 * Removes the mocks.
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'woocommerce_logger_log_message' );

		parent::tear_down();
	}

	/**
	 * Asserts that neither key appears in the request body or in any log line.
	 */
	private function assert_no_key_anywhere() {
		$everything = implode( "\n", array_merge( array_column( $this->requests, 'body' ), $this->logs ) );

		$this->assertStringNotContainsString( self::LIVE_KEY, $everything );
		$this->assertStringNotContainsString( self::TEST_KEY, $everything );
	}

	/**
	 * Live mode: the live key is neither posted nor logged, the other fields are still sent.
	 */
	public function test_live_key_is_not_sent_or_logged() {
		$result = Statistics::get_instance()->send_event( 'activated' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $this->requests );
		$this->assert_no_key_anywhere();

		$body = json_decode( $this->requests[0]['body'], true );
		$this->assertSame( 'WOOCOMMERCE', $body['plugin'] );
		$this->assertSame( 'activated', $body['event'] );
		$this->assertSame( home_url(), $body['url'] );
		$this->assertArrayHasKey( 'version', $body );
		$this->assertArrayNotHasKey( 'privatekey', $body );
	}

	/**
	 * Test mode: the test key is neither posted nor logged.
	 */
	public function test_test_key_is_not_sent_or_logged() {
		self::$options->set_option( 'test_mode', 'yes' );

		Statistics::get_instance()->send_event( 'updated' );

		$this->assertCount( 1, $this->requests );
		$this->assert_no_key_anywhere();
	}

	/**
	 * The shop identifier is stable per key, differs between keys and is not the key.
	 */
	public function test_shop_id_is_stable_and_not_the_key() {
		Statistics::get_instance()->send_event( 'activated' );
		Statistics::get_instance()->send_event( 'deactivated' );
		self::$options->set_option( 'private_key', 'priv_other_key_for_unit_tests' );
		Statistics::get_instance()->send_event( 'activated' );

		$ids = array_map(
			function ( $request ) {
				return json_decode( $request['body'], true )['shop_id'];
			},
			$this->requests
		);

		$this->assertSame( $ids[0], $ids[1] );
		$this->assertNotSame( $ids[0], $ids[2] );
		$this->assertNotSame( self::LIVE_KEY, $ids[0] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $ids[0] );
	}

	/**
	 * A transport error is logged by code only and still returned to the caller.
	 */
	public function test_transport_error_is_returned_and_logged_without_body() {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'cURL error for ' . self::LIVE_KEY );
			}
		);

		$result = Statistics::get_instance()->send_event( 'activated' );

		$this->assertWPError( $result );
		$this->assertStringNotContainsString( self::LIVE_KEY, implode( "\n", $this->logs ) );
		$this->assertStringContainsString( 'http_request_failed', implode( "\n", $this->logs ) );
	}

	/**
	 * Runs Statistics::upgrade_completed() with the given upgrader options and returns the events that were sent.
	 *
	 * @param array $options Upgrader hook options.
	 *
	 * @return string[]
	 */
	private function upgrade_events( array $options ): array {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		Statistics::upgrade_completed( new WP_Upgrader(), $options );

		return array_map(
			function ( $request ) {
				return json_decode( $request['body'], true )['event'];
			},
			$this->requests
		);
	}

	/**
	 * The plugin's own basename as the upgrader reports it.
	 *
	 * @return string
	 */
	private function own_basename(): string {
		return plugin_basename( reepay()->get_setting( 'plugin_file' ) );
	}

	/**
	 * A bulk update that includes this plugin sends one 'updated' event.
	 */
	public function test_bulk_update_sends_updated_event() {
		$events = $this->upgrade_events( array( 'plugins' => array( 'other/other.php', $this->own_basename() ) ) );

		$this->assertSame( array( 'updated' ), $events );
	}

	/**
	 * A single-plugin update sends one 'updated' event.
	 */
	public function test_single_update_sends_updated_event() {
		$events = $this->upgrade_events( array( 'plugin' => $this->own_basename() ) );

		$this->assertSame( array( 'updated' ), $events );
	}

	/**
	 * Updating other plugins, or a path that merely contains this plugin's basename, sends nothing.
	 */
	public function test_update_of_other_plugins_sends_nothing() {
		$events = $this->upgrade_events(
			array(
				'plugins' => array( 'other/other.php', 'x/' . $this->own_basename() ),
				'plugin'  => 'another/another.php',
			)
		);

		$this->assertSame( array(), $events );
	}

	/**
	 * Without a key nothing is sent.
	 */
	public function test_empty_key_sends_nothing() {
		self::$options->set_option( 'private_key', '' );

		$this->assertFalse( Statistics::get_instance()->send_event( 'activated' ) );
		$this->assertCount( 0, $this->requests );
	}
}
