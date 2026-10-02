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

	/**
	 * Intercepts wp_remote_request() and returns different canned responses depending on
	 * which URL substring matches, so a single real Api instance can be driven through a
	 * multi-request flow (e.g. get_invoice_data() followed by settle()).
	 *
	 * @param array<string, mixed> $responses_by_url_substring map of URL substring => response body.
	 */
	private function mock_http_request_by_url( array $responses_by_url_substring ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $responses_by_url_substring ) {
				foreach ( $responses_by_url_substring as $needle => $body ) {
					if ( false !== strpos( $url, $needle ) ) {
						return array(
							'headers'  => array(),
							'body'     => wp_json_encode( $body ),
							'response' => array(
								'code'    => 200,
								'message' => 'OK',
							),
							'cookies'  => array(),
							'filename' => null,
						);
					}
				}

				return $preempt;
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

	// -----------------------------------------------------------------------
	// capture_payment() — 'settled' item meta tracking (BWPM-286)
	// -----------------------------------------------------------------------

	/**
	 * Regression test for BWPM-286: modified orders got settled for their full original
	 * amount. When capture_payment() is told it captured the full remaining balance
	 * ($mark_items_settled = true), it must mark the order's not-yet-settled items as
	 * 'settled'. This is what lets a second, independent auto-settle handler
	 * (OrderCapture::multi_settle(), hooked to the same woocommerce_order_status_changed
	 * event as OrderStatuses::order_status_changed()) recognize the balance is already
	 * captured and skip it, instead of resending the same amount again - which the API
	 * rejects, triggering a fallback that silently settles whatever remains authorized
	 * and overcharges the customer.
	 *
	 * @see \Reepay\Checkout\Api::capture_payment
	 */
	public function test_capture_payment_marks_items_settled_when_requested() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array( 'regular_price' => 20.00 )
		);

		$order = $this->order_generator->order();
		$order->calculate_totals();
		$order->save();

		$this->mock_http_request_by_url(
			array(
				'/invoice/' => array(
					'state'             => 'authorized',
					'authorized_amount' => 2000,
					'settled_amount'    => 0,
					'refunded_amount'   => 0,
				),
				'/settle'   => array(
					'state'       => 'settled',
					'transaction' => 'transaction_1',
				),
			)
		);

		$result = $this->api->capture_payment( $order, 20.00, true );

		$this->assertFalse( is_wp_error( $result ), 'capture_payment unexpectedly failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		$this->assertNotEmpty( WC_Order_Factory::get_order_item( $order_item_id )->get_meta( 'settled' ) );
	}

	/**
	 * Regression test for BWPM-286: without $mark_items_settled, capture_payment() must
	 * keep its original behavior and leave order items untouched. This covers the
	 * existing Admin\Ajax call sites - in particular capture_partly(), which genuinely
	 * captures a partial amount and must not have items falsely marked as fully settled.
	 *
	 * @see \Reepay\Checkout\Api::capture_payment
	 */
	public function test_capture_payment_does_not_mark_items_settled_by_default() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$order_item_id = $this->order_generator->add_product(
			'simple',
			array( 'regular_price' => 20.00 )
		);

		$order = $this->order_generator->order();
		$order->calculate_totals();
		$order->save();

		$this->mock_http_request_by_url(
			array(
				'/invoice/' => array(
					'state'             => 'authorized',
					'authorized_amount' => 2000,
					'settled_amount'    => 0,
					'refunded_amount'   => 0,
				),
				'/settle'   => array(
					'state'       => 'settled',
					'transaction' => 'transaction_1',
				),
			)
		);

		$result = $this->api->capture_payment( $order, 20.00 );

		$this->assertFalse( is_wp_error( $result ), 'capture_payment unexpectedly failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		$this->assertEmpty( WC_Order_Factory::get_order_item( $order_item_id )->get_meta( 'settled' ) );
	}
}
