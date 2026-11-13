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
				'debug'       => 'no',
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
	 * @param array                $captured_args               filled by reference with the
	 *                                                           args of whichever request's URL
	 *                                                           matches $capture_url_substring
	 *                                                           - useful when a flow makes
	 *                                                           multiple requests and only one
	 *                                                           needs to be asserted on.
	 * @param string|null          $capture_url_substring        URL substring to capture args for.
	 */
	private function mock_http_request_by_url( array $responses_by_url_substring, array &$captured_args = array(), ?string $capture_url_substring = null ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $responses_by_url_substring, &$captured_args, $capture_url_substring ) {
				if ( null !== $capture_url_substring && false !== strpos( $url, $capture_url_substring ) ) {
					$captured_args        = $parsed_args;
					$captured_args['url'] = $url;
				}

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
	// request() debug logging
	// -----------------------------------------------------------------------

	/**
	 * Test @see Api::request does not write secret values from the response to the log.
	 */
	public function test_request_debug_log_redacts_secret() {
		self::$options->set_options( array( 'debug' => 'yes' ) );

		$captured = array();
		$this->mock_http_request(
			$captured,
			array(
				'urls'   => array( 'https://example.test/' ),
				'secret' => 'super_secret_value_123',
			)
		);

		$logged = array();
		add_filter(
			'woocommerce_logger_log_message',
			function ( $message ) use ( &$logged ) {
				$logged[] = $message;

				return null;
			}
		);

		$result = $this->api->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
		remove_all_filters( 'woocommerce_logger_log_message' );

		$this->assertSame( 'super_secret_value_123', $result['secret'], 'The caller still receives the secret' );
		$this->assertNotEmpty( $logged );
		$this->assertStringNotContainsString( 'super_secret_value_123', implode( "\n", $logged ) );
		$this->assertStringContainsString( '***REDACTED***', implode( "\n", $logged ) );
	}

	/**
	 * Test @see Api::recurring does not write the customer's personal data to the log.
	 */
	public function test_recurring_log_has_no_customer_personal_data() {
		$order = $this->order_generator->order();
		$order->set_billing_email( 'pii-test@example.com' );
		$order->set_billing_phone( '+4512345678' );
		$order->set_billing_address_1( 'Secret Street 1' );
		$order->save();

		$captured = array();
		$this->mock_http_request( $captured, array( 'id' => 'session_1' ) );

		$logged = array();
		add_filter(
			'woocommerce_logger_log_message',
			function ( $message ) use ( &$logged ) {
				$logged[] = $message;

				return null;
			}
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
		remove_all_filters( 'woocommerce_logger_log_message' );

		$all = implode( "\n", $logged );
		$this->assertStringContainsString( 'session_recurring_request', $all );
		$this->assertStringContainsString( 'customer-1', $all );
		$this->assertStringNotContainsString( 'pii-test@example.com', $all );
		$this->assertStringNotContainsString( '+4512345678', $all );
		$this->assertStringNotContainsString( 'Secret Street 1', $all );

		$body = json_decode( $captured['body'], true );
		$this->assertSame( 'pii-test@example.com', $body['create_customer']['email'], 'The request to Frisbii still carries the data' );
	}

	/**
	 * Test @see Api::request keeps the live key on the retry after a rate-limit response
	 * when force_live is set while test mode is on. Takes about 10 seconds (the retry waits).
	 */
	public function test_request_retry_after_rate_limit_keeps_force_live() {
		self::$options->set_options(
			array(
				'test_mode'        => 'yes',
				'private_key_test' => 'priv_test_mode_key',
				'private_key'      => 'priv_live_mode_key',
			)
		);

		$authorizations = array();
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args ) use ( &$authorizations ) {
				$authorizations[] = $parsed_args['headers']['Authorization'];
				$limited          = 1 === count( $authorizations );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $limited ? array( 'error' => 'Request rate limit exceeded' ) : array( 'ok' => true ) ),
					'response' => array(
						'code'    => $limited ? 429 : 200,
						'message' => $limited ? 'Too Many Requests' : 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			2
		);

		$result = $this->api->request( 'GET', 'https://api.reepay.com/v1/account', array(), true );

		$this->assertSame( array( 'ok' => true ), $result );
		$this->assertCount( 2, $authorizations );
		$this->assertSame( $authorizations[0], $authorizations[1], 'The retry must use the same key as the first request' );
		$this->assertSame( 'Basic ' . base64_encode( 'priv_live_mode_key:' ), $authorizations[0] );
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
	// recurring() — VAT number
	// -----------------------------------------------------------------------

	/**
	 * Test @see Api::recurring includes the customer's VAT number from order meta
	 * in the create_customer payload.
	 */
	public function test_recurring_includes_vat_number_from_order_meta() {
		$this->order_generator->set_meta( '_billing_eu_vat_number', 'DK32097901' );
		$order = $this->order_generator->order();

		$captured_calls = array();
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$captured_calls ) {
				$captured_calls[] = array(
					'method' => $parsed_args['method'],
					'url'    => $url,
					'body'   => $parsed_args['body'] ?? null,
				);

				$response_body = str_contains( $url, 'session/recurring' )
					? array(
						'id'  => 'session_1',
						'url' => 'https://checkout.reepay.com/pay/session_1',
					)
					: array( 'handle' => 'customer-1' );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $response_body ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
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

		$recurring_call = null;
		foreach ( $captured_calls as $call ) {
			if ( str_contains( $call['url'], 'session/recurring' ) ) {
				$recurring_call = $call;
			}
		}

		$this->assertNotNull( $recurring_call, 'Expected a session/recurring request' );

		$body = json_decode( $recurring_call['body'], true );

		$this->assertSame( 'DK32097901', $body['create_customer']['vat'] ?? null );
	}

	/**
	 * Test @see Api::recurring sends an empty VAT value when the order has no
	 * VAT meta set.
	 */
	public function test_recurring_sends_empty_vat_when_no_vat_meta() {
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

		$this->assertSame( '', $body['create_customer']['vat'] ?? null );
	}

	// -----------------------------------------------------------------------
	// recurring() — VAT sync to existing customer
	// -----------------------------------------------------------------------

	/**
	 * Test @see Api::recurring fires a PUT /v1/customer/{handle} request to sync
	 * the VAT number onto the persisted Frisbii customer record.
	 */
	public function test_recurring_syncs_vat_to_existing_customer() {
		$this->order_generator->set_meta( '_billing_eu_vat_number', 'DK32097901' );
		$order = $this->order_generator->order();

		$captured_calls = array();
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$captured_calls ) {
				$captured_calls[] = array(
					'method' => $parsed_args['method'],
					'url'    => $url,
					'body'   => $parsed_args['body'] ?? null,
				);

				$response_body = str_contains( $url, 'session/recurring' )
					? array(
						'id'  => 'session_1',
						'url' => 'https://checkout.reepay.com/pay/session_1',
					)
					: array( 'handle' => 'customer-1' );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $response_body ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
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

		$vat_sync_call = null;
		foreach ( $captured_calls as $call ) {
			if ( 'PUT' === $call['method'] && str_contains( $call['url'], 'customer/customer-1' ) ) {
				$vat_sync_call = $call;
			}
		}

		$this->assertNotNull( $vat_sync_call, 'Expected a PUT request to sync the VAT number to the existing Frisbii customer' );

		$body = json_decode( $vat_sync_call['body'], true );
		$this->assertSame( 'DK32097901', $body['vat'] ?? null );
	}

	/**
	 * Test @see Api::recurring does not fire a customer-sync PUT request when the
	 * order has no VAT number — a previously-set VAT number on the customer must
	 * NOT be cleared just because one order omitted it, since Frisbii's customer
	 * record is shared across all of that customer's invoices.
	 */
	public function test_recurring_does_not_sync_vat_when_none_present() {
		$order = $this->order_generator->order();

		$captured_calls = array();
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$captured_calls ) {
				$captured_calls[] = array(
					'method' => $parsed_args['method'],
					'url'    => $url,
				);

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'id'  => 'session_1',
							'url' => 'https://checkout.reepay.com/pay/session_1',
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
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

		foreach ( $captured_calls as $call ) {
			$this->assertFalse(
				'PUT' === $call['method'] && str_contains( $call['url'], 'customer/customer-1' ),
				'Did not expect a customer-sync PUT request when there is no VAT number'
			);
		}
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

	// -----------------------------------------------------------------------
	// settle() - BWSM-114
	// -----------------------------------------------------------------------

	/**
	 * Regression test for BWSM-114: Api::settle() must respect an explicitly
	 * provided $amount even when $items_data is empty/false - exactly how
	 * OrderCapture::settle_amount() calls it for a manual "Capture Specified
	 * Amount". Previously, any falsy $items_data (regardless of $amount)
	 * triggered a full InstantSettle::calculate_instant_settle() recalculation
	 * that silently discarded the caller's $amount and sent a completely
	 * different value to Frisbii - e.g. attempting to capture the last 0.01 kr
	 * of a stuck order would instead resend the whole order's recalculated
	 * total, which the API correctly rejects as "Amount higher than authorized
	 * amount" - leaving merchants with no way to manually recover such orders.
	 *
	 * @see \Reepay\Checkout\Api::settle
	 */
	public function test_settle_respects_explicit_amount_when_items_data_empty() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => 257.63 ) );

		$order = $this->order_generator->order();
		$order->calculate_totals();
		$order->save();

		// settle() makes two calls on success: the POST to /settle itself, then a
		// GET to /invoice/{handle} afterwards to verify the transaction - capture
		// the former specifically, since both would otherwise return the same
		// canned response and the GET would overwrite the args we want to assert on.
		$captured_settle_args = array();
		$this->mock_http_request_by_url(
			array(
				'/settle'   => array(
					'state'             => 'settled',
					'transaction'       => 'transaction_1',
					'authorized_amount' => 25763,
					'amount'            => 25763,
				),
				'/invoice/' => array(
					'state'             => 'settled',
					'authorized_amount' => 25763,
					'settled_amount'    => 1,
				),
			),
			$captured_settle_args,
			'/settle'
		);

		// Mirrors OrderCapture::settle_amount()'s exact call: a flat 1-minor-unit
		// amount (0.01 kr) with no itemized order_lines.
		$result = $this->api->settle( $order, 1, false, false );

		$this->assertFalse( is_wp_error( $result ), 'settle() unexpectedly failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );

		$sent_body = json_decode( $captured_settle_args['body'], true );

		$this->assertSame( 'POST', $captured_settle_args['method'] ?? null );
		$this->assertSame( 'https://api.reepay.com/v1/charge/order-' . $order->get_id() . '/settle', $captured_settle_args['url'] ?? null );
		$this->assertSame( 1, $sent_body['amount'] ?? null, 'settle() must send the caller-provided amount (1), not a recalculated full-order amount' );
		$this->assertArrayNotHasKey( 'order_lines', $sent_body, 'A flat-amount settle (no items_data) must not include order_lines' );
	}

	/**
	 * BWPM-324 (Option A): with "Skip order lines" enabled, settle() sends only a flat amount,
	 * even when item data is passed, so Frisbii applies the account default VAT.
	 */
	public function test_settle_sends_flat_amount_when_skip_order_lines_enabled() {
		self::$options->set_option( 'skip_order_lines', 'yes' );

		try {
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
			$this->order_generator->add_product( 'simple', array( 'regular_price' => 100.00 ) );

			$order = $this->order_generator->order();
			$order->calculate_totals();
			$order->save();

			$captured_settle_args = array();
			$this->mock_http_request_by_url(
				array(
					'/settle'   => array(
						'state'       => 'settled',
						'transaction' => 'transaction_1',
					),
					'/invoice/' => array(
						'state'             => 'settled',
						'authorized_amount' => 10000,
						'settled_amount'    => 10000,
					),
				),
				$captured_settle_args,
				'/settle'
			);

			$items_data = array(
				array(
					'ordertext'       => __( 'Order Total', 'reepay-checkout-gateway' ),
					'quantity'        => 1,
					'amount'          => 10000,
					'vat'             => 0,
					'amount_incl_vat' => true,
				),
			);

			$result = $this->api->settle( $order, 10000, $items_data, $order->get_items() );

			$this->assertFalse( is_wp_error( $result ), 'settle() unexpectedly failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );

			$sent_body = json_decode( $captured_settle_args['body'], true );

			$this->assertSame( 10000, $sent_body['amount'] ?? null );
			$this->assertArrayNotHasKey( 'order_lines', $sent_body, 'Skip order lines must not send order_lines at settle' );
		} finally {
			self::$options->set_option( 'skip_order_lines', 'no' );
		}
	}

	/**
	 * Test @see \Reepay\Checkout\Api::settle falls back to a full
	 * InstantSettle::calculate_instant_settle() amount when the caller provides
	 * neither $amount nor $items_data - preserving the original default-settle
	 * behavior that other callers (e.g. webhook-driven settlement) rely on.
	 */
	public function test_settle_falls_back_to_instant_settle_when_both_missing() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product(
			'simple',
			array(
				'regular_price' => 20.00,
				'virtual'       => true,
			)
		);

		self::$options->set_option( 'settle', array( \Reepay\Checkout\OrderFlow\InstantSettle::SETTLE_VIRTUAL ) );

		$order = $this->order_generator->order();
		$order->calculate_totals();
		$order->save();

		$captured_settle_args = array();
		$this->mock_http_request_by_url(
			array(
				'/settle'   => array(
					'state'       => 'settled',
					'transaction' => 'transaction_1',
				),
				'/invoice/' => array(
					'state'             => 'settled',
					'authorized_amount' => 2000,
					'settled_amount'    => 2000,
				),
			),
			$captured_settle_args,
			'/settle'
		);

		$result = $this->api->settle( $order );

		$this->assertFalse( is_wp_error( $result ), 'settle() unexpectedly failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );

		$sent_body = json_decode( $captured_settle_args['body'], true );

		$this->assertSame( 2000, $sent_body['amount'] ?? null, 'With neither $amount nor $items_data provided, settle() must derive both from InstantSettle' );
	}

	/**
	 * Regression test for RSCC-005: a failed invoice lookup must make capture_payment()
	 * return the WP_Error instead of raising "Cannot use object of type WP_Error as array".
	 *
	 * @see \Reepay\Checkout\Api::capture_payment
	 */
	public function test_capture_payment_returns_wp_error_when_invoice_lookup_fails() {
		// Default order is not paid via Reepay, so get_invoice_data() returns a WP_Error.
		$order = $this->order_generator->order();

		$result = $this->api->capture_payment( $order, 20.00 );

		$this->assertTrue( is_wp_error( $result ) );
	}

	/**
	 * Regression test for RSCC-005: when settle() is rejected with "Amount higher than
	 * authorized amount" and the follow-up invoice lookup fails, the original WP_Error
	 * must be returned instead of a fatal error.
	 *
	 * @see \Reepay\Checkout\Api::settle
	 */
	public function test_settle_returns_wp_error_when_invoice_lookup_fails_during_retry() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => 20.00 ) );

		$order = $this->order_generator->order();
		$order->calculate_totals();
		$order->save();

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				$is_settle = false !== strpos( $url, '/settle' );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						$is_settle ?
							array(
								'code'    => 102,
								'error'   => 'Amount higher than authorized amount',
								'message' => 'Amount higher than authorized amount',
							) :
							array(
								'code'    => 2,
								'error'   => 'Internal error',
								'message' => 'Internal error',
							)
					),
					'response' => array(
						'code'    => $is_settle ? 400 : 500,
						'message' => 'Error',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$result = $this->api->settle(
			$order,
			2000,
			array(
				array(
					'ordertext' => 'Item',
					'amount'    => 2000,
					'vat'       => 0.25,
					'quantity'  => 1,
				),
			),
			true
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertStringContainsString( 'Amount higher than authorized amount', $result->get_error_message() );
	}

	/**
	 * Regression test for RSCC-006: on a transient miss get_customer_handle() used to fetch the
	 * invoice and then discard it. It must return null without calling the API.
	 *
	 * @see \Reepay\Checkout\Api::get_customer_handle
	 */
	public function test_get_customer_handle_makes_no_api_call_on_transient_miss() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$order = $this->order_generator->order();
		$order->set_status( 'processing' );
		$order->save();

		$requests = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt ) use ( &$requests ) {
				++$requests;

				return $preempt;
			}
		);

		$this->assertNull( $this->api->get_customer_handle( $order ) );
		$this->assertSame( 0, $requests );
	}

	/**
	 * A cached invoice with a customer is still used.
	 *
	 * @see \Reepay\Checkout\Api::get_customer_handle
	 */
	public function test_get_customer_handle_uses_cached_invoice_customer() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$order = $this->order_generator->order();
		$order->set_status( 'processing' );
		$order->save();

		set_transient( 'reepay_invoice_' . rp_get_order_handle( $order ), array( 'customer' => 'customer-cached' ), 60 );

		$this->assertSame( 'customer-cached', $this->api->get_customer_handle( $order ) );
	}

	/**
	 * Values that change a URL when they are concatenated unencoded (RSCC-007).
	 *
	 * @return array<string, array{0: string, 1: string}> raw value => expected path segment.
	 */
	public function special_handle_provider(): array {
		return array(
			'slash'     => array( 'a/b', 'a%2Fb' ),
			'query'     => array( 'a?x=1', 'a%3Fx%3D1' ),
			'fragment'  => array( 'a#b', 'a%23b' ),
			'ampersand' => array( 'a&size=1', 'a%26size%3D1' ),
			'traversal' => array( '../x', '..%2Fx' ),
			'space'     => array( 'a b', 'a%20b' ),
			'non-ascii' => array( 'å-ø', '%C3%A5-%C3%B8' ),
		);
	}

	/**
	 * A handle with special characters stays one path segment.
	 *
	 * @dataProvider special_handle_provider
	 *
	 * @param string $raw      handle as received.
	 * @param string $expected expected encoded segment.
	 */
	public function test_invoice_handle_is_one_path_segment( string $raw, string $expected ) {
		$captured_args = array();
		$this->mock_http_request( $captured_args, array( 'handle' => 'x' ) );

		$this->api->get_invoice_by_handle( $raw );

		$this->assertSame( 'https://api.reepay.com/v1/invoice/' . $expected, $captured_args['url'] ?? null );
	}

	/**
	 * A normal handle gives the same URL as before the change.
	 */
	public function test_normal_handle_url_is_unchanged() {
		$captured_args = array();
		$this->mock_http_request( $captured_args, array( 'handle' => 'x' ) );

		$this->api->get_invoice_by_handle( 'order-123' );

		$this->assertSame( 'https://api.reepay.com/v1/invoice/order-123', $captured_args['url'] ?? null );
	}

	/**
	 * Dot segments and empty segments are refused and no request is sent.
	 *
	 * @testWith ["."]
	 *           [".."]
	 *           [""]
	 *
	 * @param string $raw handle as received.
	 */
	public function test_dot_and_empty_segments_are_not_sent( string $raw ) {
		$captured_args = array();
		$this->mock_http_request( $captured_args, array( 'handle' => 'x' ) );

		$result = $this->api->delete_payment_method( $raw );

		$this->assertWPError( $result );
		$this->assertSame( array(), $captured_args, 'No HTTP request must be sent for an invalid path.' );
	}

	/**
	 * A URL that carries an already encoded dot segment is refused by request() itself.
	 */
	public function test_encoded_dot_segment_in_url_is_not_sent() {
		$captured_args = array();
		$this->mock_http_request( $captured_args, array() );

		$result = $this->api->request( 'GET', 'https://api.reepay.com/v1/customer/%2e%2e/account' );

		$this->assertWPError( $result );
		$this->assertSame( array(), $captured_args );
	}

	/**
	 * A raw value that already looks encoded is encoded again, so it stays one literal segment.
	 */
	public function test_percent_sequence_in_handle_is_encoded_again() {
		$captured_args = array();
		$this->mock_http_request( $captured_args, array() );

		$this->api->get_invoice_by_handle( '%2e%2e' );

		$this->assertSame( 'https://api.reepay.com/v1/invoice/%252e%252e', $captured_args['url'] ?? null );
	}

	/**
	 * Invoice and transaction ids from an event are encoded as separate segments.
	 */
	public function test_session_event_id_is_encoded() {
		$captured_args = array();
		$this->mock_http_request( $captured_args, array() );

		$this->api->get_session_events( 'cs_1/../../account' );

		$this->assertSame( 'https://checkout-api.reepay.com/v1/event/cs_1%2F..%2F..%2Faccount', $captured_args['url'] ?? null );
	}

	/**
	 * Regression test: Api::settle()'s flat-amount path (calculate_amount_with_vat())
	 * must round each VAT-derived line amount to a whole minor unit before summing and
	 * sending it to Frisbii. Previously, a line whose excl.-VAT amount x (1 + vat) landed
	 * on a fractional minor unit (19495 * 1.25 = 24368.75 - exactly the amount_incl_vat
	 * false shape ReepayGateway::get_order_items() sends for a store with prices entered
	 * exclusive of VAT) was sent to Frisbii unrounded. Frisbii truncates that fraction
	 * rather than rounding it, silently settling for one minor unit less than intended.
	 * Confirmed via a live reproduction against Frisbii's test environment: the actual
	 * request body was {"amount": 24368.75}, and the response settled for 24368 against
	 * an authorized_amount of 24369.
	 *
	 * @see \Reepay\Checkout\Api::settle
	 */
	public function test_settle_rounds_vat_derived_amount_to_whole_minor_unit() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => 194.95 ) );

		$order = $this->order_generator->order();
		$order->calculate_totals();
		$order->save();

		$captured_settle_args = array();
		$this->mock_http_request_by_url(
			array(
				'/settle'   => array(
					'state'       => 'settled',
					'transaction' => 'transaction_1',
				),
				'/invoice/' => array(
					'state'             => 'settled',
					'authorized_amount' => 24369,
					'settled_amount'    => 24369,
				),
			),
			$captured_settle_args,
			'/settle'
		);

		// Mirrors Api::capture_payment()'s exact call shape: a tax-exclusive line
		// amount (19495 = 194.95 excl. VAT) plus a 25% vat rate, amount_incl_vat
		// false.
		$item_data = array(
			'ordertext'       => 'Bookinabox regular',
			'quantity'        => 1,
			'amount'          => 19495,
			'vat'             => 0.25,
			'amount_incl_vat' => false,
		);

		$result = $this->api->settle( $order, 24369, array( $item_data ), false );

		$this->assertFalse( is_wp_error( $result ), 'settle() unexpectedly failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );

		$sent_body = json_decode( $captured_settle_args['body'], true );

		$this->assertSame(
			24369,
			$sent_body['amount'] ?? null,
			'settle() must round the VAT-derived amount to the nearest whole minor unit (24369), not send the raw fractional value (24368.75)'
		);
	}
}
