<?php
/**
 * Class InvoiceMatchesOrderTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\OrderFlow\Webhook;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * Checks that an order is only marked authorized or paid when the Frisbii invoice covers it (BWPM-332).
 *
 * @covers \Reepay\Checkout\OrderFlow\OrderStatuses::invoice_matches_order
 * @group orderflow_invoice_match
 */
class InvoiceMatchesOrderTest extends Reepay_UnitTestCase {
	/**
	 * Secret used to sign webhook payloads.
	 *
	 * @var string
	 */
	private string $secret = 'test_secret_key';

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		set_transient( 'reepay_webhook_settings_secret', $this->secret );
		self::$options->set_options( array( 'enable_sync' => 'yes' ) );
		OrderStatuses::init_statuses();

		$this->order_generator->set_props(
			array(
				'status'         => 'pending',
				'currency'       => 'DKK',
				'total'          => 200,
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$this->order_generator->order()->save();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		delete_transient( 'reepay_webhook_settings_secret' );
		parent::tear_down();
	}

	/**
	 * Build an invoice array like the Frisbii API returns it.
	 *
	 * @param int    $amount   amount in minor units.
	 * @param string $currency currency.
	 *
	 * @return array
	 */
	private function invoice( int $amount, string $currency = 'DKK' ): array {
		return array(
			'amount'            => $amount,
			'currency'          => $currency,
			'state'             => 'authorized',
			'authorized_amount' => $amount,
			'settled_amount'    => 0,
			'order_lines'       => array(),
		);
	}

	/**
	 * Build a signed webhook payload.
	 *
	 * @param string $event_type event type.
	 * @param string $handle     invoice handle.
	 *
	 * @return array
	 */
	private function payload( string $event_type, string $handle ): array {
		$timestamp = (string) time();
		$id        = 'evt_' . wp_generate_uuid4();

		return array(
			'event_type'  => $event_type,
			'id'          => $id,
			'timestamp'   => $timestamp,
			'signature'   => bin2hex( hash_hmac( 'sha256', $timestamp . $id, $this->secret, true ) ),
			'transaction' => 'trans_test_001',
			'invoice'     => $handle,
		);
	}

	/**
	 * Whether the order has a note containing the text.
	 *
	 * @param string $text text to look for.
	 *
	 * @return bool
	 */
	private function has_note( string $text ): bool {
		foreach ( wc_get_order_notes( array( 'order_id' => $this->order_generator->order()->get_id() ) ) as $note ) {
			if ( false !== strpos( $note->content, $text ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Equal amount is accepted.
	 */
	public function test_equal_amount_is_accepted() {
		$this->assertTrue( OrderStatuses::invoice_matches_order( $this->order_generator->order(), $this->invoice( 20000 ) ) );
	}

	/**
	 * Higher amount is refused and noted: only the exact order total is accepted.
	 */
	public function test_higher_amount_is_refused() {
		$this->assertFalse( OrderStatuses::invoice_matches_order( $this->order_generator->order(), $this->invoice( 25000 ) ) );
		$this->assertTrue( $this->has_note( 'does not match this order' ) );
	}

	/**
	 * Lower amount is refused and noted.
	 */
	public function test_lower_amount_is_refused() {
		$this->assertFalse( OrderStatuses::invoice_matches_order( $this->order_generator->order(), $this->invoice( 10000 ) ) );
		$this->assertTrue( $this->has_note( 'does not match this order' ) );
	}

	/**
	 * A difference of one minor unit, in either direction, is refused.
	 */
	public function test_one_minor_unit_difference_is_refused() {
		$order = $this->order_generator->order();

		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, $this->invoice( 19999 ) ) );
		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, $this->invoice( 20001 ) ) );
	}

	/**
	 * Different currency is refused even when the number is the same.
	 */
	public function test_currency_mismatch_is_refused() {
		$this->assertFalse( OrderStatuses::invoice_matches_order( $this->order_generator->order(), $this->invoice( 20000, 'EUR' ) ) );
	}

	/**
	 * Missing amount or currency fails closed.
	 */
	public function test_missing_invoice_data_is_refused() {
		$order = $this->order_generator->order();

		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, array() ) );
		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, array( 'currency' => 'DKK' ) ) );
		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, array( 'amount' => 20000 ) ) );
		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, new WP_Error() ) );
	}

	/**
	 * A subscription order is checked for currency only.
	 */
	public function test_subscription_order_checks_currency_only() {
		$this->order_generator->set_meta( '_reepay_subscription_handle', 'sub-1' );
		$order = $this->order_generator->order();

		$this->assertTrue( OrderStatuses::invoice_matches_order( $order, $this->invoice( 5000 ) ) );
		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, $this->invoice( 5000, 'EUR' ) ) );
	}

	/**
	 * An order whose authorization was already accepted is not compared again on settle.
	 */
	public function test_already_authorized_order_skips_amount_check() {
		$this->order_generator->set_meta( '_reepay_state_authorized', 1 );

		$this->assertTrue( OrderStatuses::invoice_matches_order( $this->order_generator->order(), $this->invoice( 10000 ) ) );
	}

	/**
	 * set_settled_status() leaves the order untouched when the invoice is lower than the order.
	 */
	public function test_set_settled_status_refuses_lower_invoice() {
		$api_mock = $this->getMockBuilder( Api::class )->getMock();
		$api_mock->method( 'get_invoice_data' )->willReturn(
			array_merge( $this->invoice( 10000 ), array( 'settled_amount' => 10000 ) )
		);
		reepay()->di()->set( Api::class, $api_mock );

		$this->assertFalse( OrderStatuses::set_settled_status( $this->order_generator->order() ) );
		$this->assertSame( 'pending', wc_get_order( $this->order_generator->order()->get_id() )->get_status() );
		$this->assertEmpty( $this->order_generator->get_meta( '_reepay_state_settled' ) );
	}

	/**
	 * A matching settled invoice still completes the order.
	 */
	public function test_set_settled_status_accepts_matching_invoice() {
		$api_mock = $this->getMockBuilder( Api::class )->getMock();
		$api_mock->method( 'get_invoice_data' )->willReturn(
			array_merge( $this->invoice( 20000 ), array( 'settled_amount' => 20000 ) )
		);
		reepay()->di()->set( Api::class, $api_mock );

		$this->assertTrue( OrderStatuses::set_settled_status( $this->order_generator->order() ) );
		$this->assertSame( 1, $this->order_generator->get_meta( '_reepay_state_settled' ) );
	}

	/**
	 * Partial settle of a matching invoice still moves the order to authorized.
	 */
	public function test_partial_settle_is_not_refused() {
		$api_mock = $this->getMockBuilder( Api::class )->getMock();
		$api_mock->method( 'get_invoice_data' )->willReturn(
			array_merge( $this->invoice( 20000 ), array( 'settled_amount' => 5000 ) )
		);
		reepay()->di()->set( Api::class, $api_mock );

		$this->assertTrue( OrderStatuses::set_settled_status( $this->order_generator->order() ) );
		$this->assertSame( 1, $this->order_generator->get_meta( '_reepay_state_authorized' ) );
	}

	/**
	 * After a partial settle Frisbii lowers the invoice amount to the settled sum; the authorized amount still covers the order.
	 */
	public function test_partially_settled_invoice_is_compared_by_authorized_amount() {
		$order   = $this->order_generator->order();
		$invoice = array_merge(
			$this->invoice( 5000 ),
			array(
				'authorized_amount' => 20000,
				'settled_amount'    => 5000,
			)
		);

		$this->assertTrue( OrderStatuses::invoice_matches_order( $order, $invoice ) );

		// The authorized amount is lower than the order as well: refused.
		$invoice['authorized_amount'] = 5000;
		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, $invoice ) );

		// The authorized amount is higher than the order: refused.
		$invoice['authorized_amount'] = 25000;
		$this->assertFalse( OrderStatuses::invoice_matches_order( $order, $invoice ) );
	}

	/**
	 * The invoice_authorized webhook does not authorize an order when the invoice is lower.
	 */
	public function test_webhook_authorized_refuses_lower_invoice() {
		$handle = rp_get_order_handle( $this->order_generator->order() );
		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn( $this->invoice( 10000 ) );

		( new Webhook() )->process( $this->payload( 'invoice_authorized', $handle ) );

		$order = wc_get_order( $this->order_generator->order()->get_id() );
		$this->assertSame( 'pending', $order->get_status() );
		$this->assertEmpty( $order->get_meta( '_reepay_state_authorized' ) );
		$this->assertEmpty( $order->get_transaction_id() );
	}

	/**
	 * The invoice_authorized webhook still authorizes a matching invoice.
	 */
	public function test_webhook_authorized_accepts_matching_invoice() {
		$handle = rp_get_order_handle( $this->order_generator->order() );
		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn( $this->invoice( 20000 ) );

		( new Webhook() )->process( $this->payload( 'invoice_authorized', $handle ) );

		$order = wc_get_order( $this->order_generator->order()->get_id() );
		$this->assertNotSame( 'pending', $order->get_status() );
		$this->assertSame( 1, (int) $order->get_meta( '_reepay_state_authorized' ) );
	}

	/**
	 * The invoice_settled webhook does not complete an order when the invoice is lower.
	 */
	public function test_webhook_settled_refuses_lower_invoice() {
		$handle  = rp_get_order_handle( $this->order_generator->order() );
		$invoice = array_merge( $this->invoice( 10000 ), array( 'state' => 'settled', 'settled_amount' => 10000 ) );
		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn( $invoice );
		$this->api_mock->method( 'get_invoice_data' )->willReturn( $invoice );

		( new Webhook() )->process( $this->payload( 'invoice_settled', $handle ) );

		$order = wc_get_order( $this->order_generator->order()->get_id() );
		$this->assertSame( 'pending', $order->get_status() );
		$this->assertEmpty( $order->get_meta( '_reepay_state_settled' ) );
	}

	/**
	 * Run an event through the webhook and return the order and the webhook object.
	 *
	 * @param string          $event   event type.
	 * @param array|\WP_Error $invoice what the invoice lookup returns.
	 *
	 * @return array{0: \WC_Order, 1: Webhook}
	 */
	private function run_event( string $event, $invoice ): array {
		$handle = rp_get_order_handle( $this->order_generator->order() );
		$this->api_mock->method( 'get_invoice_by_handle' )->willReturn( $invoice );
		$this->api_mock->method( 'get_invoice_data' )->willReturn( $invoice );

		$webhook = new Webhook();
		$webhook->process( $this->payload( $event, $handle ) );

		return array( wc_get_order( $this->order_generator->order()->get_id() ), $webhook );
	}

	/**
	 * An invoice_settled event for an invoice that is not settled leaves the order pending.
	 */
	public function test_webhook_settled_refuses_unpaid_invoice() {
		list( $order ) = $this->run_event( 'invoice_settled', array_merge( $this->invoice( 20000 ), array( 'state' => 'created' ) ) );

		$this->assertSame( 'pending', $order->get_status() );
		$this->assertEmpty( $order->get_meta( '_reepay_state_settled' ) );
		$this->assertEmpty( $order->get_transaction_id() );
	}

	/**
	 * An invoice_authorized event for a cancelled or unpaid invoice leaves the order pending.
	 */
	public function test_webhook_authorized_refuses_cancelled_and_created_invoice() {
		foreach ( array( 'cancelled', 'created' ) as $state ) {
			$this->order_generator->order()->save();
			list( $order ) = $this->run_event( 'invoice_authorized', array_merge( $this->invoice( 20000 ), array( 'state' => $state ) ) );

			$this->assertSame( 'pending', $order->get_status(), $state );
			$this->assertEmpty( $order->get_meta( '_reepay_state_authorized' ), $state );
			$this->assertEmpty( $order->get_transaction_id(), $state );
		}
	}

	/**
	 * An invoice without a state is refused.
	 */
	public function test_webhook_authorized_refuses_invoice_without_state() {
		$invoice = $this->invoice( 20000 );
		unset( $invoice['state'] );
		list( $order ) = $this->run_event( 'invoice_authorized', $invoice );

		$this->assertSame( 'pending', $order->get_status() );
		$this->assertTrue( $this->has_note( 'does not match this order' ) );
	}

	/**
	 * A settled invoice still authorizes the order on an invoice_authorized event (Vipps MobilePay Recurring).
	 */
	public function test_webhook_authorized_accepts_settled_invoice() {
		list( $order ) = $this->run_event( 'invoice_authorized', array_merge( $this->invoice( 20000 ), array( 'state' => 'settled' ) ) );

		$this->assertNotSame( 'pending', $order->get_status() );
	}

	/**
	 * A temporary lookup error leaves the order unchanged, adds no refusal note and asks for a retry.
	 */
	public function test_webhook_asks_for_retry_on_temporary_lookup_error() {
		foreach ( array( 0, 429, 500, 503 ) as $code ) {
			list( $order, $webhook ) = $this->run_event( 'invoice_authorized', new WP_Error( $code, 'temporary' ) );

			$this->assertTrue( $webhook->is_retry_requested(), (string) $code );
			$this->assertSame( 'pending', $order->get_status(), (string) $code );
			$this->assertFalse( $this->has_note( 'does not match this order' ), (string) $code );
		}
	}

	/**
	 * A permanent lookup error (for example invoice not found) is refused and not retried.
	 */
	public function test_webhook_does_not_retry_permanent_lookup_error() {
		list( $order, $webhook ) = $this->run_event( 'invoice_authorized', new WP_Error( 31, 'not found' ) );

		$this->assertFalse( $webhook->is_retry_requested() );
		$this->assertSame( 'pending', $order->get_status() );
		$this->assertTrue( $this->has_note( 'does not match this order' ) );
	}

	/**
	 * After a temporary error the same event id is processed when Frisbii sends it again.
	 */
	public function test_retry_after_temporary_error_is_not_dropped_as_duplicate() {
		$order  = $this->order_generator->order();
		$handle = rp_get_order_handle( $order );
		$data   = $this->payload( 'invoice_authorized', $handle );

		$this->api_mock->method( 'get_invoice_by_handle' )->willReturnOnConsecutiveCalls( new WP_Error( 503, 'down' ), $this->invoice( 20000 ) );

		$this->assertSame( 503, $this->post_webhook( $data ) );
		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );

		$this->assertSame( 200, $this->post_webhook( $data ) );
		$this->assertNotSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * Send a payload through return_handler() and return the HTTP status it set.
	 *
	 * @param array $data signed payload.
	 *
	 * @return int
	 */
	private function post_webhook( array $data ): int {
		$handler = new Webhook();
		$method  = new ReflectionMethod( $handler, 'is_fresh_and_new' );
		$method->setAccessible( true );

		if ( ! $method->invoke( $handler, $data ) ) {
			return 200;
		}

		try {
			$handler->process( $data );
		} catch ( Exception $e ) {
			unset( $e );
		}

		if ( $handler->is_retry_requested() ) {
			delete_transient( 'reepay_webhook_event_' . md5( $data['id'] ) );

			return 503;
		}

		return 200;
	}
}
