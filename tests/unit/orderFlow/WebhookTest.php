<?php
/**
 * Class WebhookTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\OrderFlow\Webhook;
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
				'amount'    => 1000,
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
	 * @group orderflow_webhook
	 */
	public function test_process_invoice_authorized_missing_invoice_param() {
		$this->expectException( Exception::class );

		$payload = $this->build_payload( 'invoice_authorized' );
		// Remove the 'invoice' key so the guard condition triggers.
		unset( $payload['invoice'] );

		$this->webhook->process( $payload );
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
				'amount'      => 1000,
				'state'       => 'settled',
				'handle'      => $handle,
				'order_lines' => array(),
				'credit_notes' => array(),
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
		$this->order_generator->order()->add_product( WC_Helper_Product::create_simple_product() );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'       => 1000,
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
		$this->order_generator->order()->add_product( WC_Helper_Product::create_simple_product() );
		$this->order_generator->order()->update_meta_data( '_reepay_credit_note_ids', array( 'cn_already' ) );
		$this->order_generator->order()->save();

		$handle = rp_get_order_handle( $this->order_generator->order() );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn(
			array(
				'amount'       => 1000,
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
}
