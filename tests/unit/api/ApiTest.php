<?php
/**
 * Class ApiTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Tests for the real Api class.
 *
 * Unlike the other gateway tests, these do NOT use $this->api_mock — that mock
 * replaces the whole Api class via DI, which would hide the logic under test here
 * (Api::get_configurations, Api::recurring). Instead, a real Api instance is used
 * and the WordPress `pre_http_request` filter intercepts the HTTP call itself.
 *
 * @covers \Reepay\Checkout\Api
 * @group api
 */
class ApiTest extends Reepay_UnitTestCase {

	/**
	 * Real (non-mocked) Api instance under test.
	 *
	 * @var Api
	 */
	private Api $api;

	/**
	 * Sets up the real Api instance and a usable API key for each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->api = new Api();
		$this->api->set_logging_source( 'reepay_checkout' );

		self::$options->set_options(
			array(
				'test_mode'   => 'no',
				'private_key' => 'priv_test_key_for_unit_tests',
			)
		);
	}

	/**
	 * Removes any pre_http_request mock added by a test.
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );

		parent::tear_down();
	}

	/**
	 * Intercepts wp_remote_request() and returns a canned response, capturing the
	 * request args (method, url, body) that were sent into $captured_args.
	 *
	 * @param array $captured_args  Filled by reference with the last request's args + url.
	 * @param mixed $response_body  Value to json-encode as the response body.
	 * @param int   $http_code      HTTP status code to simulate.
	 */
	private function mock_http_request( array &$captured_args, $response_body, int $http_code = 200 ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$captured_args, $response_body, $http_code ) {
				$captured_args         = $parsed_args;
				$captured_args['url']  = $url;

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $response_body ),
					'response' => array(
						'code'    => $http_code,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	// -----------------------------------------------------------------------
	// get_configurations()
	// -----------------------------------------------------------------------

	/**
	 * Test @see Api::get_configurations returns the parsed list from the API
	 * and calls the correct endpoint/method.
	 */
	public function test_get_configurations_returns_parsed_list() {
		$configurations = array(
			array(
				'name'   => 'Default configuration',
				'handle' => 'default',
			),
			array(
				'name'   => 'Boyd test',
				'handle' => 'boyd-test',
			),
		);

		$captured_args = array();
		$this->mock_http_request( $captured_args, $configurations );

		$result = $this->api->get_configurations();

		$this->assertSame( $configurations, $result );
		$this->assertSame( 'GET', $captured_args['method'] );
		$this->assertSame( 'https://checkout-api.reepay.com/v1/configuration', $captured_args['url'] );
	}

	/**
	 * Test @see Api::get_configurations returns a WP_Error (not a fatal error)
	 * when the API call fails.
	 */
	public function test_get_configurations_returns_wp_error_on_api_failure() {
		$captured_args = array();
		$this->mock_http_request(
			$captured_args,
			array(
				'code'    => Api::ERROR_CODES['Unauthorized'],
				'error'   => 'unauthorized',
				'message' => 'Invalid credentials',
			),
			401
		);

		$result = $this->api->get_configurations();

		$this->assertWPError( $result );
	}

	// -----------------------------------------------------------------------
	// recurring() — "configuration" param injection
	// -----------------------------------------------------------------------

	/**
	 * Test @see Api::recurring includes the "configuration" param in the request
	 * body when a payment window configuration is set.
	 */
	public function test_recurring_includes_configuration_param_when_setting_present() {
		self::$options->set_option( 'payment_window_configuration', 'boyd-test' );

		$order = $this->order_generator->order();

		$captured_args = array();
		$this->mock_http_request(
			$captured_args,
			array(
				'id'  => 'session_1',
				'url' => 'https://checkout.reepay.com/pay/session_1',
			)
		);

		$this->api->recurring(
			array(),
			$order,
			array(
				'language'        => 'en_US',
				'test_mode'       => 'no',
				'customer_handle' => 'customer-1',
				'return_url'      => 'https://example.com/return',
			)
		);

		$body = json_decode( $captured_args['body'], true );

		$this->assertSame( 'boyd-test', $body['configuration'] ?? null );
	}

	/**
	 * Test @see Api::recurring falls back to the "default" configuration handle
	 * when no payment window configuration is set, so Reepay does not pick
	 * whichever checkout handle happened to be created last.
	 */
	public function test_recurring_defaults_configuration_param_when_setting_empty() {
		self::$options->set_option( 'payment_window_configuration', '' );

		$order = $this->order_generator->order();

		$captured_args = array();
		$this->mock_http_request(
			$captured_args,
			array(
				'id'  => 'session_1',
				'url' => 'https://checkout.reepay.com/pay/session_1',
			)
		);

		$this->api->recurring(
			array(),
			$order,
			array(
				'language'        => 'en_US',
				'test_mode'       => 'no',
				'customer_handle' => 'customer-1',
				'return_url'      => 'https://example.com/return',
			)
		);

		$body = json_decode( $captured_args['body'], true );

		$this->assertSame( 'default', $body['configuration'] ?? null );
	}
}
