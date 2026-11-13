<?php
/**
 * Class WebhookTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\OrderFlow\Webhook;
use Reepay\Checkout\OrderFlow\WebhookLock;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\ProductGenerator;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * WebhookTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\Webhook
 * @group orderflow_webhook
 */
class WebhookTest extends Reepay_UnitTestCase {

	/**
	 * Webhook instance.
	 *
	 * @var Webhook
	 */
	private Webhook $webhook;

	/**
	 * Shared valid HMAC secret used in all signature calculations.
	 *
	 * @var string
	 */
	private string $secret = 'test_secret_key';

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->webhook = new Webhook();

		// Store the test secret in the transient so Webhook::process() can use it
		// without making a live API call.
		set_transient( 'reepay_webhook_settings_secret', $this->secret );

		// Ensure status sync is disabled by default so status assertions are predictable.
		self::$options->set_options(
			array(
				'enable_sync' => 'yes',
			)
		);

		OrderStatuses::init_statuses();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		delete_transient( 'reepay_webhook_settings_secret' );
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	/**
	 * Build a valid signed webhook payload.
	 *
	 * @param string $event_type Reepay event type string.
	 * @param array  $extra      Extra fields to merge into the payload.
	 *
	 * @return array
	 */
	private function build_payload( string $event_type, array $extra = array() ): array {
		$timestamp = (string) time();
		$id        = 'evt_' . wp_generate_uuid4();
		$signature = bin2hex( hash_hmac( 'sha256', $timestamp . $id, $this->secret, true ) );

		return array_merge(
			array(
				'event_type' => $event_type,
				'id'         => $id,
				'timestamp'  => $timestamp,
				'signature'  => $signature,
				'transaction' => 'trans_test_001',
			),
			$extra
		);
	}

	// -----------------------------------------------------------------------
	// Authenticity checks (RSCC-003)
	// -----------------------------------------------------------------------

	/**
	 * Call a private Webhook method.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 *
	 * @return mixed
	 */
	private function call_private( string $method, array $args ) {
		$ref = new ReflectionMethod( Webhook::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( $this->webhook, $args );
	}

	/**
	 * Test @see Webhook::is_signature_valid accepts a correct signature.
	 *
	 * @group orderflow_webhook
	 */
	public function test_signature_valid() {
		$data = $this->build_payload( 'invoice_settled' );
		$this->assertTrue( $this->call_private( 'is_signature_valid', array( $data, $this->secret ) ) );
	}

	/**
	 * Test @see Webhook::is_signature_valid rejects an empty secret even when the HMAC matches it.
	 *
	 * @group orderflow_webhook
	 */
	public function test_signature_rejected_with_empty_secret() {
		$data              = $this->build_payload( 'invoice_settled' );
		$data['signature'] = bin2hex( hash_hmac( 'sha256', $data['timestamp'] . $data['id'], '', true ) );
		$this->assertFalse( $this->call_private( 'is_signature_valid', array( $data, '' ) ) );
	}

	/**
	 * Test @see Webhook::is_signature_valid rejects a wrong signature.
	 *
	 * @group orderflow_webhook
	 */
	public function test_signature_rejected_when_wrong() {
		$data              = $this->build_payload( 'invoice_settled' );
		$data['signature'] = str_repeat( '0', 64 );
		$this->assertFalse( $this->call_private( 'is_signature_valid', array( $data, $this->secret ) ) );
	}

	/**
	 * Test @see Webhook::is_fresh_and_new rejects an old timestamp.
	 *
	 * @group orderflow_webhook
	 */
	public function test_old_timestamp_rejected() {
		$data              = $this->build_payload( 'invoice_settled' );
		$data['timestamp'] = (string) ( time() - HOUR_IN_SECONDS );
		$this->assertFalse( $this->call_private( 'is_fresh_and_new', array( $data ) ) );
	}

	/**
	 * Test @see Webhook::is_fresh_and_new accepts a Frisbii retry that arrives 7 minutes after the event.
	 *
	 * @group orderflow_webhook
	 */
	public function test_retry_seven_minutes_old_accepted() {
		$data              = $this->build_payload( 'invoice_settled' );
		$data['timestamp'] = (string) ( time() - 7 * MINUTE_IN_SECONDS );
		$this->assertTrue( $this->call_private( 'is_fresh_and_new', array( $data ) ) );
	}

	/**
	 * Test @see Webhook::is_fresh_and_new accepts an ISO 8601 timestamp.
	 *
	 * @group orderflow_webhook
	 */
	public function test_iso_timestamp_accepted() {
		$data              = $this->build_payload( 'invoice_settled' );
		$data['timestamp'] = gmdate( 'Y-m-d\TH:i:s.000+00:00' );
		$this->assertTrue( $this->call_private( 'is_fresh_and_new', array( $data ) ) );
	}

	/**
	 * Test @see Webhook::is_fresh_and_new processes a replayed event only once.
	 *
	 * @group orderflow_webhook
	 */
	public function test_replayed_event_rejected() {
		$data = $this->build_payload( 'invoice_settled' );
		$this->assertTrue( $this->call_private( 'is_fresh_and_new', array( $data ) ) );
		$this->assertFalse( $this->call_private( 'is_fresh_and_new', array( $data ) ) );
	}

	// -----------------------------------------------------------------------
	// Pre-authentication hardening (RSCC-004)
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::sanitize_log_value removes line breaks and bounds the length.
	 *
	 * @group orderflow_webhook
	 */
	public function test_sanitize_log_value_single_bounded_line() {
		$value  = "abc\r\nWebHook: fake line\n" . str_repeat( 'x', 500 );
		$result = $this->call_private( 'sanitize_log_value', array( $value, 100 ) );

		$this->assertSame( 0, preg_match( '/[\r\n]/', $result ) );
		$this->assertLessThanOrEqual( 100, strlen( $result ) );
		$this->assertSame( '', $this->call_private( 'sanitize_log_value', array( array( 'a' ), 100 ) ) );
	}

	/**
	 * Test @see Webhook::may_refresh_secret allows only the configured number of refreshes per minute.
	 *
	 * @group orderflow_webhook
	 */
	public function test_secret_refresh_is_limited() {
		delete_transient( 'reepay_webhook_secret_refresh' );
		add_filter(
			'reepay_webhook_secret_refresh_limit',
			function () {
				return 2;
			}
		);

		$this->assertTrue( $this->call_private( 'may_refresh_secret', array() ) );
		$this->assertTrue( $this->call_private( 'may_refresh_secret', array() ) );
		$this->assertFalse( $this->call_private( 'may_refresh_secret', array() ) );

		remove_all_filters( 'reepay_webhook_secret_refresh_limit' );
		delete_transient( 'reepay_webhook_secret_refresh' );
	}

	/**
	 * Test @see Webhook::may_refresh_secret limits the first secret fetch separately from the refresh.
	 *
	 * @group orderflow_webhook
	 */
	public function test_secret_fetch_is_limited_separately() {
		delete_transient( 'reepay_webhook_secret_fetch' );
		delete_transient( 'reepay_webhook_secret_refresh' );
		add_filter(
			'reepay_webhook_secret_fetch_limit',
			function () {
				return 2;
			}
		);

		$this->assertTrue( $this->call_private( 'may_refresh_secret', array( true ) ) );
		$this->assertTrue( $this->call_private( 'may_refresh_secret', array( true ) ) );
		$this->assertFalse( $this->call_private( 'may_refresh_secret', array( true ) ) );
		$this->assertTrue( $this->call_private( 'may_refresh_secret', array() ), 'The refresh quota is not consumed by fetches' );

		remove_all_filters( 'reepay_webhook_secret_fetch_limit' );
		delete_transient( 'reepay_webhook_secret_fetch' );
		delete_transient( 'reepay_webhook_secret_refresh' );
	}

	// -----------------------------------------------------------------------
	// process() — invoice_authorized
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process with invoice_authorized event sets authorized status.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'    => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'currency'    => get_woocommerce_currency(),
				'state'     => 'authorized',
				'handle'    => $handle,
				'order_lines' => array(),
			)
		);

		$payload = $this->build_payload(
			'invoice_authorized',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );

		// When status_sync enabled the order should move to the configured authorized status.
		$this->assertContains(
			$order->get_status(),
			array( OrderStatuses::$status_authorized, 'on-hold' ),
			'Order should be in authorized/on-hold status after invoice_authorized webhook'
		);
	}

	/**
	 * Test @see Webhook::process with invoice_authorized event when order is not found.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_order_not_found() {
		$payload = $this->build_payload(
			'invoice_authorized',
			array(
				'invoice'     => 'order-non-existent-handle',
				'transaction' => 'trans_test_999',
			)
		);

		// Should return early without throwing.
		$this->webhook->process( $payload );

		// No assertion needed — the method must not throw an exception.
		$this->assertTrue( true );
	}

	/**
	 * Test @see Webhook::process invoice_authorized throws when invoice param missing.
	 *
	 * The subscription plugin also hooks into reepay_webhook and accesses $data['invoice']
	 * before our own guard can run, so a PHP warning (converted to an exception by PHPUnit)
	 * or our own Exception can both be thrown. Assert that SOMETHING is thrown.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_missing_invoice_param() {
		$payload = $this->build_payload( 'invoice_authorized' );
		unset( $payload['invoice'] );

		$threw = false;
		try {
			$this->webhook->process( $payload );
		} catch ( \Throwable $e ) {
			$threw = true;
		}

		$this->assertTrue( $threw, 'An exception or error must be thrown when invoice key is missing' );
	}

	// -----------------------------------------------------------------------
	// process() — invoice_authorized: age verification result (BWPM-264)
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process stores the EXTERNAL_AGE_VERIFICATION_RESULT
	 * event's data as order meta when the session log contains it.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_stores_age_verification_result() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->update_meta_data( 'reepay_session_id', 'cs_test_001' );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'      => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'currency'    => get_woocommerce_currency(),
				'state'       => 'authorized',
				'handle'      => $handle,
				'order_lines' => array(),
			)
		);

		$this->api_mock->method( 'get_session_events' )->willReturn(
			array(
				array(
					'name' => 'GET',
					'type' => 'SUCCESS',
					'data' => array( 'ip' => '127.0.0.1' ),
				),
				array(
					'name' => 'EXTERNAL_AGE_VERIFICATION_RESULT',
					'type' => 'SUCCESS',
					'data' => array(
						'result'  => '18',
						'message' => 'Age verification result: 18',
					),
				),
			)
		);

		$payload = $this->build_payload(
			'invoice_authorized',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order  = wc_get_order( $this->order_generator->order()->get_id() );
		$result = json_decode( $order->get_meta( '_reepay_age_verification_result' ), true );

		$this->assertSame( '18', $result['result'] ?? null );
	}

	/**
	 * Test @see Webhook::process AC3: no matching event in the session log
	 * results in no meta stored and no exception.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_age_verification_missing_event() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->update_meta_data( 'reepay_session_id', 'cs_test_002' );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'      => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'currency'    => get_woocommerce_currency(),
				'state'       => 'authorized',
				'handle'      => $handle,
				'order_lines' => array(),
			)
		);

		$this->api_mock->method( 'get_session_events' )->willReturn(
			array(
				array(
					'name' => 'GET',
					'type' => 'SUCCESS',
					'data' => array( 'ip' => '127.0.0.1' ),
				),
			)
		);

		$payload = $this->build_payload(
			'invoice_authorized',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );

		$this->assertSame( '', $order->get_meta( '_reepay_age_verification_result' ) );
	}

	/**
	 * Test @see Webhook::process AC3: a failed session-events API call results
	 * in no meta stored and no exception, webhook processing still succeeds.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_age_verification_api_error() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->update_meta_data( 'reepay_session_id', 'cs_test_003' );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'      => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'currency'    => get_woocommerce_currency(),
				'state'       => 'authorized',
				'handle'      => $handle,
				'order_lines' => array(),
			)
		);

		$this->api_mock->method( 'get_session_events' )->willReturn(
			new WP_Error( 0, 'Frisbii Pay: Request timed out' )
		);

		$payload = $this->build_payload(
			'invoice_authorized',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );

		$this->assertSame( '', $order->get_meta( '_reepay_age_verification_result' ) );
		$this->assertContains(
			$order->get_status(),
			array( OrderStatuses::$status_authorized, 'on-hold' ),
			'Webhook processing must still succeed even if the age verification lookup fails'
		);
	}

	/**
	 * Test @see Webhook::process AC4: when the session log contains multiple
	 * EXTERNAL_AGE_VERIFICATION_RESULT events, only the last occurrence is stored.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_age_verification_multiple_events_uses_last() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->update_meta_data( 'reepay_session_id', 'cs_test_004' );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'      => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'currency'    => get_woocommerce_currency(),
				'state'       => 'authorized',
				'handle'      => $handle,
				'order_lines' => array(),
			)
		);

		$this->api_mock->method( 'get_session_events' )->willReturn(
			array(
				array(
					'name' => 'EXTERNAL_AGE_VERIFICATION_RESULT',
					'type' => 'SUCCESS',
					'data' => array( 'result' => 'first-attempt' ),
				),
				array(
					'name' => 'EXTERNAL_AGE_VERIFICATION_RESULT',
					'type' => 'SUCCESS',
					'data' => array( 'result' => 'second-attempt' ),
				),
			)
		);

		$payload = $this->build_payload(
			'invoice_authorized',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order  = wc_get_order( $this->order_generator->order()->get_id() );
		$result = json_decode( $order->get_meta( '_reepay_age_verification_result' ), true );

		$this->assertSame( 'second-attempt', $result['result'] ?? null );
	}

	/**
	 * Test @see Webhook::process skips the age-verification lookup entirely
	 * when the order has no reepay_session_id meta.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_no_session_id_skips_age_verification_lookup() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'      => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'currency'    => get_woocommerce_currency(),
				'state'       => 'authorized',
				'handle'      => $handle,
				'order_lines' => array(),
			)
		);

		$this->api_mock->expects( $this->never() )->method( 'get_session_events' );

		$payload = $this->build_payload(
			'invoice_authorized',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );

		$this->assertSame( '', $order->get_meta( '_reepay_age_verification_result' ) );
	}

	// -----------------------------------------------------------------------
	// process() — invoice_settled
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process with invoice_settled event sets settled status.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_settled() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'      => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'state'       => 'settled',
				'handle'      => $handle,
				'order_lines' => array(),
				'credit_notes' => array(),
			)
		);

		// OrderStatuses::set_settled_status() calls get_invoice_data() internally.
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'amount'            => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'authorized_amount' => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'settled_amount'    => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'state'             => 'settled',
				'handle'            => $handle,
			)
		);

		$this->api_mock->method( 'request' )->willReturn( array() );

		$payload = $this->build_payload(
			'invoice_settled',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );

		$this->assertContains(
			$order->get_status(),
			array( OrderStatuses::$status_settled, 'processing', 'completed' ),
			'Order should be in settled/processing/completed status after invoice_settled webhook'
		);
	}

	/**
	 * A failed transaction lookup does not stop the invoice_settled handling (RSCC-001 of run 20261105_1438).
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_settled_when_transaction_lookup_fails() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'id'           => 'inv_1',
				'amount'       => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'state'        => 'settled',
				'handle'       => $handle,
				'order_lines'  => array(),
				'credit_notes' => array(),
			)
		);
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'amount'            => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'authorized_amount' => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'settled_amount'    => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'state'             => 'settled',
				'handle'            => $handle,
			)
		);
		$this->api_mock->method( 'request' )->willReturn( new WP_Error( 500, 'Transaction lookup failed' ) );

		$this->webhook->process( $this->build_payload( 'invoice_settled', array( 'invoice' => $handle ) ) );

		$order = wc_get_order( $this->order_generator->order()->get_id() );

		$this->assertContains(
			$order->get_status(),
			array( OrderStatuses::$status_settled, 'processing', 'completed' ),
			'The order is settled although the transaction lookup failed'
		);
	}

	/**
	 * Test @see Webhook::process with invoice_settled event when order is not found.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_settled_order_not_found() {
		$this->api_mock->method( 'request' )->willReturn( array() );

		$payload = $this->build_payload(
			'invoice_settled',
			array( 'invoice' => 'order-non-existent-settled' )
		);

		// Should return early without throwing.
		$this->webhook->process( $payload );

		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// process() — invoice_cancelled
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process with invoice_cancelled event cancels the order.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_cancelled() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$payload = $this->build_payload(
			'invoice_cancelled',
			array( 'invoice' => $handle )
		);

		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );

		$this->assertSame( 'cancelled', $order->get_status() );
	}

	/**
	 * Test @see Webhook::process invoice_cancelled is idempotent when order is already cancelled.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_cancelled_already_cancelled() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->update_status( 'cancelled' );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$payload = $this->build_payload(
			'invoice_cancelled',
			array( 'invoice' => $handle )
		);

		// Should return early without re-cancelling / throwing.
		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );
		$this->assertSame( 'cancelled', $order->get_status() );
	}

	/**
	 * Test @see Webhook::process with invoice_cancelled event when order not found.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_cancelled_order_not_found() {
		$payload = $this->build_payload(
			'invoice_cancelled',
			array( 'invoice' => 'order-does-not-exist-canc' )
		);

		$this->webhook->process( $payload );

		$this->assertTrue( true );
	}

	/**
	 * Test @see Webhook::process invoice_cancelled throws when invoice param missing.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_cancelled_missing_invoice_param() {
		$this->expectException( Exception::class );

		$payload = $this->build_payload( 'invoice_cancelled' );
		unset( $payload['invoice'] );

		$this->webhook->process( $payload );
	}

	// -----------------------------------------------------------------------
	// process() — invoice_refund
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process with invoice_refund event registers a WC refund.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_refund() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '10.00' ) );
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'       => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'state'        => 'settled',
				'handle'       => $handle,
				'credit_notes' => array(
					array(
						'id'     => 'cn_001',
						'amount' => 500,
					),
				),
			)
		);

		$payload = $this->build_payload(
			'invoice_refund',
			array( 'invoice' => $handle )
		);

		$refunds_before = count( $this->order_generator->order()->get_refunds() );
		$this->webhook->process( $payload );

		$order          = wc_get_order( $this->order_generator->order()->get_id() );
		$refunds_after  = count( $order->get_refunds() );

		$this->assertGreaterThan( $refunds_before, $refunds_after, 'A refund should have been created by the webhook' );
	}

	/**
	 * Test @see Webhook::process invoice_refund is idempotent — same credit note not registered twice.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_refund_idempotent() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '10.00' ) );
		$this->order_generator->order()->calculate_totals();
		$this->order_generator->order()->update_meta_data( '_reepay_credit_note_ids', array( 'cn_already' ) );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'       => rp_prepare_amount( $this->order_generator->order()->get_total(), get_woocommerce_currency() ),
				'handle'       => $handle,
				'credit_notes' => array(
					array(
						'id'     => 'cn_already',
						'amount' => 500,
					),
				),
			)
		);

		$payload = $this->build_payload(
			'invoice_refund',
			array( 'invoice' => $handle )
		);

		$refunds_before = count( $this->order_generator->order()->get_refunds() );
		$this->webhook->process( $payload );

		$order         = wc_get_order( $this->order_generator->order()->get_id() );
		$refunds_after = count( $order->get_refunds() );

		$this->assertSame( $refunds_before, $refunds_after, 'Duplicate credit note should not create a second refund' );
	}

	/**
	 * Test @see Webhook::process invoice_refund does not fail when the invoice lookup returns an error.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_refund_invoice_lookup_error() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn( new WP_Error( 0, 'Frisbii Pay: Request timed out' ) );

		$payload = $this->build_payload(
			'invoice_refund',
			array( 'invoice' => $handle )
		);

		// A PHP warning would be converted to an exception by PHPUnit.
		$this->webhook->process( $payload );

		$order = wc_get_order( $this->order_generator->order()->get_id() );
		$this->assertCount( 0, $order->get_refunds(), 'No refund should be created when the invoice lookup fails' );
	}

	// -----------------------------------------------------------------------
	// process() — unknown event type
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process with an unknown event type returns without throwing.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_unknown_event_type() {
		$payload = $this->build_payload( 'totally_unknown_event_xyz' );

		// Must not throw, just log and return.
		$this->webhook->process( $payload );

		$this->assertTrue( true );
	}

	/**
	 * Test @see Webhook::process fires the generic raw-event action for unknown events
	 * when a listener is registered.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_unknown_event_fires_raw_action_when_hooked() {
		$fired   = false;
		$handler = function() use ( &$fired ) {
			$fired = true;
		};

		add_action( 'reepay_webhook_raw_event', $handler );

		$payload = $this->build_payload( 'custom_partner_event' );
		$this->webhook->process( $payload );

		remove_action( 'reepay_webhook_raw_event', $handler );

		$this->assertTrue( $fired, 'reepay_webhook_raw_event action should have fired' );
	}

	// -----------------------------------------------------------------------
	// process() — customer_created
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process customer_created saves handle to user meta.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_customer_created_known_handle() {
		$user_id = $this->factory()->user->create();

		$customer_handle = 'customer-' . $user_id;

		$payload = $this->build_payload(
			'customer_created',
			array( 'customer' => $customer_handle )
		);

		$this->webhook->process( $payload );

		$this->assertSame(
			$customer_handle,
			get_user_meta( $user_id, 'reepay_customer_id', true ),
			'customer_created webhook should save handle to user meta'
		);
	}

	// -----------------------------------------------------------------------
	// process() — invoice_created (subscription)
	// -----------------------------------------------------------------------

	/**
	 * Test @see Webhook::process invoice_created without subscription param is skipped gracefully.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_created_missing_subscription_skipped() {
		$payload = $this->build_payload(
			'invoice_created',
			array( 'invoice' => 'order-test-created' )
			// 'subscription' key intentionally omitted
		);

		// Should log and break without throwing.
		$this->webhook->process( $payload );

		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// Order lock (RSCC-006)
	// -----------------------------------------------------------------------

	/**
	 * Set the seconds a request waits for a lock held by another request.
	 *
	 * @param int $seconds seconds.
	 */
	private function set_lock_wait( int $seconds ) {
		$property = new ReflectionProperty( Webhook::class, 'lock_wait' );
		$property->setAccessible( true );
		$property->setValue( $this->webhook, $seconds );
	}

	/**
	 * Only one request holds the lock of an order, and the owner can release it.
	 *
	 * @group orderflow_webhook
	 */
	public function test_order_lock_is_exclusive_and_released_by_owner() {
		$first  = new WebhookLock();
		$second = new WebhookLock();

		$this->assertTrue( $first->acquire( 1001 ) );
		$this->assertFalse( $second->acquire( 1001 ) );
		$this->assertTrue( $second->acquire( 1002 ), 'Locks are per order' );

		$first->release();
		$this->assertTrue( ( new WebhookLock() )->acquire( 1001 ) );
	}

	/**
	 * A lock left behind by a dead request is taken over after it expires, a live one is not,
	 * and the previous owner cannot release the new lock.
	 *
	 * @group orderflow_webhook
	 */
	public function test_expired_order_lock_is_taken_over() {
		global $wpdb;

		$dead = new WebhookLock();
		$this->assertTrue( $dead->acquire( 1003 ) );

		$this->assertFalse( ( new WebhookLock() )->acquire( 1003 ), 'A live lock is not taken over' );

		$wpdb->update( WebhookLock::table_name(), array( 'expires_at' => time() - 1 ), array( 'order_id' => 1003 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$new = new WebhookLock();
		$this->assertTrue( $new->acquire( 1003 ) );

		$dead->release();
		$this->assertFalse( ( new WebhookLock() )->acquire( 1003 ), 'The old owner did not release the new lock' );
	}

	/**
	 * A lock that is not expired blocks others until the wait ends, then the event continues without it.
	 *
	 * @group orderflow_webhook
	 */
	public function test_order_lock_wait_ends_and_continues_without_lock() {
		$this->assertTrue( ( new WebhookLock() )->acquire( 1004 ) );

		$this->set_lock_wait( 0 );

		$this->assertTrue( $this->call_private( 'lock_order', array( 1004 ) ), 'The request had to wait' );
		$this->assertFalse( ( new WebhookLock() )->acquire( 1004 ), 'The holder still has the lock' );
	}

	/**
	 * A lock table that is missing while the version option says it is installed is created again.
	 *
	 * @group orderflow_webhook
	 */
	public function test_missing_lock_table_is_recreated_when_version_option_is_set() {
		global $wpdb;

		$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . WebhookLock::table_name() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		update_option( WebhookLock::DB_VERSION_OPTION, WebhookLock::DB_VERSION );

		$this->assertTrue( ( new WebhookLock() )->acquire( 1005 ), 'The table is created again and the lock is taken' );
		$this->assertFalse( ( new WebhookLock() )->acquire( 1005 ), 'The lock now works' );
	}

	/**
	 * A lock table that cannot be created does not make the request wait.
	 *
	 * @group orderflow_webhook
	 */
	public function test_unusable_lock_table_continues_without_lock() {
		global $wpdb;

		$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . WebhookLock::table_name() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		update_option( WebhookLock::DB_VERSION_OPTION, WebhookLock::DB_VERSION );

		$block = function ( $query ) {
			return preg_match( '/^\s*CREATE\s+(TEMPORARY\s+)?TABLE/i', $query ) ? 'SELECT 1' : $query;
		};
		add_filter( 'query', $block, 20 );

		$wpdb->suppress_errors( true );
		$start  = microtime( true );
		$waited = $this->call_private( 'lock_order', array( 1007 ) );
		$wpdb->suppress_errors( false );

		remove_filter( 'query', $block, 20 );

		$this->assertFalse( $waited );
		$this->assertLessThan( 2, microtime( true ) - $start );
	}

	/**
	 * Build an invoice_settled payload whose transaction has a card error, so the handler returns early.
	 *
	 * @return array
	 */
	private function settle_error_payload(): array {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn( array( 'id' => 'inv_1' ) );
		$this->api_mock->method( 'request' )->willReturn(
			array(
				'card_transaction' => array(
					'card'             => 'cc_1',
					'error'            => 'declined',
					'acquirer_message' => 'Declined',
				),
			)
		);

		return $this->build_payload( 'invoice_settled', array( 'invoice' => $handle ) );
	}

	/**
	 * The lock is released after the early return on an item settle error.
	 *
	 * @group orderflow_webhook
	 */
	public function test_order_lock_released_after_item_settle_error() {
		$payload = $this->settle_error_payload();

		$this->webhook->process( $payload );

		$order_id = $this->order_generator->order()->get_id();
		$this->assertEmpty( wc_get_order( $order_id )->get_meta( '_reepay_locked' ) );
		$this->assertTrue( ( new WebhookLock() )->acquire( $order_id ), 'The order lock is free' );
	}

	/**
	 * The lock is released when processing throws.
	 *
	 * @group orderflow_webhook
	 */
	public function test_order_lock_released_after_exception() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willThrowException( new Exception( 'API down' ) );

		try {
			$this->webhook->process( $this->build_payload( 'invoice_settled', array( 'invoice' => $handle ) ) );
			$this->fail( 'The exception must reach the caller' );
		} catch ( Exception $e ) {
			$this->assertSame( 'API down', $e->getMessage() );
		}

		$order_id = $this->order_generator->order()->get_id();
		$this->assertTrue( ( new WebhookLock() )->acquire( $order_id ), 'The order lock is free' );
	}

	/**
	 * A failed table creation is not recorded as installed, and the next call creates the table.
	 *
	 * @group orderflow_webhook
	 */
	public function test_install_failure_is_retried() {
		global $wpdb;

		$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . WebhookLock::table_name() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		delete_option( WebhookLock::DB_VERSION_OPTION );

		$block = function ( $query ) {
			// The test suite has already turned CREATE TABLE into CREATE TEMPORARY TABLE.
			return preg_match( '/^\\s*CREATE\\s+(TEMPORARY\\s+)?TABLE/i', $query ) ? 'SELECT 1' : $query;
		};
		add_filter( 'query', $block, 20 );
		WebhookLock::install();
		remove_filter( 'query', $block, 20 );

		$this->assertFalse( get_option( WebhookLock::DB_VERSION_OPTION ), 'A failed creation is not recorded' );

		$this->assertTrue( ( new WebhookLock() )->acquire( 1006 ), 'The next request creates the table and gets the lock' );
		$this->assertSame( WebhookLock::DB_VERSION, get_option( WebhookLock::DB_VERSION_OPTION ) );
	}

	/**
	 * The order is read again after the lock is taken, also when the lock was free at once.
	 *
	 * @group orderflow_webhook
	 */
	public function test_order_is_reloaded_after_lock() {
		global $wpdb;

		if ( rp_hpos_enabled() ) {
			$this->markTestSkipped( 'The test changes the order row directly in the posts table.' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();
		$stale    = wc_get_order( $order_id );

		// Another request changed the order after this one read it.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'wc-completed' ), array( 'ID' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$fresh = $this->call_private( 'lock_and_reload_order', array( $stale ) );

		$this->assertSame( 'completed', $fresh->get_status() );
	}

	/**
	 * Data for the events that must run under the order lock.
	 *
	 * @return array
	 */
	public function locked_events(): array {
		return array(
			'cancelled' => array( 'invoice_cancelled', 'reepay_webhook_invoice_cancelled' ),
			'refund'    => array( 'invoice_refund', 'reepay_webhook_invoice_refund' ),
		);
	}

	/**
	 * The cancel and refund events hold the order lock while they work.
	 *
	 * @dataProvider locked_events
	 * @group orderflow_webhook
	 *
	 * @param string $event event type.
	 * @param string $hook  action fired at the end of the event.
	 */
	public function test_cancel_and_refund_run_under_order_lock( string $event, string $hook ) {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$order_id = $this->order_generator->order()->get_id();
		$handle   = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn( array( 'credit_notes' => array() ) );

		$locked = null;
		add_action(
			$hook,
			function () use ( &$locked, $order_id ) {
				$locked = false === ( new WebhookLock() )->acquire( $order_id );
			}
		);

		$this->webhook->process( $this->build_payload( $event, array( 'invoice' => $handle ) ) );

		$this->assertTrue( $locked, 'Another request cannot take the lock while the event is processed' );
		$this->assertTrue( ( new WebhookLock() )->acquire( $order_id ), 'The lock is released afterwards' );
	}

	// -----------------------------------------------------------------------
	// process() — customer_payment_method_added
	// -----------------------------------------------------------------------

	/**
	 * Add a WooCommerce Subscriptions product to the current test order and
	 * create its linked WC_Subscription left in 'pending' status — the real
	 * state a subscription is in before the customer has ever paid for it.
	 *
	 * OrderGenerator::add_product('woo_sub') activates the subscription
	 * immediately, and WC Subscriptions does not allow transitioning an
	 * 'active' subscription back to 'pending', so it is built directly here
	 * instead.
	 *
	 * @param array $product_data product meta data, e.g. regular_price.
	 *
	 * @return WC_Subscription
	 */
	private function add_pending_woo_subscription( array $product_data = array() ): WC_Subscription {
		$product = ( new ProductGenerator( 'woo_sub', $product_data ) )->product();

		$order = $this->order_generator->order();
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->save();

		$subscription = wcs_create_subscription(
			array(
				'order_id'         => $order->get_id(),
				'status'           => 'pending',
				'billing_period'   => 'Day',
				'billing_interval' => 1,
				'date_created'     => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			)
		);

		if ( is_wp_error( $subscription ) ) {
			throw new Exception( $subscription->get_error_message() );
		}

		$subscription->add_product( $product );
		$subscription->update_dates(
			array(
				'trial_end'    => gmdate( 'Y-m-d H:i:s', 0 ),
				'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
				'end'          => gmdate( 'Y-m-d H:i:s', strtotime( '+2 week' ) ),
			)
		);
		$subscription->calculate_totals();

		return $subscription;
	}

	/**
	 * Test @see Webhook::process customer_payment_method_added must NOT
	 * activate a subscription whose order was only matched through the
	 * customer-handle fallback (rp_get_order_by_customer()) when that order's
	 * own invoice is not authorized/settled. Without this check, adding a
	 * payment method lets a customer freely activate an unrelated pending
	 * subscription order of theirs for free.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_customer_payment_method_added_fallback_match_unverified_invoice_does_not_activate_subscription() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->set_prop( 'status', 'pending' );

		$subscription_id = $this->add_pending_woo_subscription( array( 'regular_price' => '20.00' ) )->get_id();
		$order           = $this->order_generator->order();

		$handle = rp_get_order_handle( $order );
		$this->order_generator->set_meta( '_reepay_order', $handle );
		// Deliberately no reepay_session_id meta on this order — the exact
		// session match must fail and fall through to rp_get_order_by_customer().

		$this->api_mock->method( 'request' )->willReturn(
			array( 'content' => array( array( 'handle' => $handle ) ) )
		);
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'state'             => 'created',
				'authorized_amount' => 0,
				'settled_amount'    => 0,
			)
		);

		$payload = $this->build_payload(
			'customer_payment_method_added',
			array(
				'customer'                 => 'cust-unrelated',
				'payment_method'           => '',
				'payment_method_reference' => 'cs_unrelated_session',
			)
		);

		$this->webhook->process( $payload );

		$subscription = wcs_get_subscription( $subscription_id );

		$this->assertSame(
			'pending',
			$subscription->get_status(),
			'Subscription must stay pending — adding an unrelated payment method must not activate it'
		);
	}

	/**
	 * Test @see Webhook::process customer_payment_method_added DOES activate a
	 * subscription matched through the customer-handle fallback once its
	 * order's invoice is genuinely settled for the full amount — the
	 * legitimate "missing payment_method_reference" recovery path must keep
	 * working.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_customer_payment_method_added_fallback_match_verified_invoice_activates_subscription() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->set_prop( 'status', 'pending' );

		$subscription_id = $this->add_pending_woo_subscription( array( 'regular_price' => '20.00' ) )->get_id();
		$order           = $this->order_generator->order();

		$handle = rp_get_order_handle( $order );
		$this->order_generator->set_meta( '_reepay_order', $handle );

		$expected_amount = rp_prepare_amount( (float) $order->get_total(), $order->get_currency() );

		$this->api_mock->method( 'request' )->willReturn(
			array( 'content' => array( array( 'handle' => $handle ) ) )
		);
		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'state'             => 'settled',
				'authorized_amount' => $expected_amount,
				'settled_amount'    => $expected_amount,
			)
		);

		$payload = $this->build_payload(
			'customer_payment_method_added',
			array(
				'customer'                 => 'cust-unrelated',
				'payment_method'           => '',
				'payment_method_reference' => 'cs_unrelated_session',
			)
		);

		$this->webhook->process( $payload );

		$subscription = wcs_get_subscription( $subscription_id );

		$this->assertSame(
			'active',
			$subscription->get_status(),
			'Subscription with a genuinely settled order invoice must still be activated via the fallback match'
		);
	}

	/**
	 * Test @see Webhook::process customer_payment_method_added activates a
	 * zero-total order's subscription via the fallback without requiring
	 * invoice verification — e.g. a free trial subscription sign-up.
	 *
	 * @group orderflow_webhook
	 */
	public function test_process_customer_payment_method_added_zero_total_order_activates_subscription_via_fallback() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->set_prop( 'status', 'pending' );

		$subscription_id = $this->add_pending_woo_subscription( array( 'regular_price' => '0.00' ) )->get_id();
		$order           = $this->order_generator->order();

		$handle = rp_get_order_handle( $order );
		$this->order_generator->set_meta( '_reepay_order', $handle );

		$this->api_mock->method( 'request' )->willReturn(
			array( 'content' => array( array( 'handle' => $handle ) ) )
		);
		$this->api_mock->expects( $this->never() )->method( 'get_invoice_data' );

		$payload = $this->build_payload(
			'customer_payment_method_added',
			array(
				'customer'                 => 'cust-unrelated',
				'payment_method'           => '',
				'payment_method_reference' => 'cs_unrelated_session',
			)
		);

		$this->webhook->process( $payload );

		$subscription = wcs_get_subscription( $subscription_id );

		$this->assertSame(
			'active',
			$subscription->get_status(),
			'A zero-total order subscription must still activate without needing invoice verification'
		);
	}
}
