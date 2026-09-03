<?php
/**
 * Class UnsettledOrdersMailerTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\UnsettledOrdersMailer;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * UnsettledOrdersMailerTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\UnsettledOrdersMailer
 */
class UnsettledOrdersMailerTest extends Reepay_UnitTestCase {

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		self::$options->set_options(
			array(
				'failed_webhooks_email' => 'merchant@example.com',
			)
		);

		reset_phpmailer_instance();
	}

	/**
	 * Test @see UnsettledOrdersMailer::send() sends the exact ticket subject to the configured address, as HTML.
	 */
	public function test_send_emails_configured_address_with_exact_subject_and_html_content_type() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order = $this->order_generator->order();

		$sent = ( new UnsettledOrdersMailer() )->send(
			array(
				'order_ids' => array( $order->get_id() ),
				'capped'    => false,
			)
		);

		$this->assertTrue( $sent );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 1, $mailer->mock_sent );
		$this->assertSame( 'merchant@example.com', $mailer->mock_sent[0]['to'][0][0] );
		$this->assertSame( 'Some completed orders in your webshop are unpaid', $mailer->mock_sent[0]['subject'] );
		$this->assertStringContainsString( 'text/html', $mailer->mock_sent[0]['header'] );
	}

	/**
	 * Test @see UnsettledOrdersMailer::send() body includes the webshop name and a link to each order.
	 */
	public function test_send_body_includes_site_name_and_order_link() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order = $this->order_generator->order();

		( new UnsettledOrdersMailer() )->send(
			array(
				'order_ids' => array( $order->get_id() ),
				'capped'    => false,
			)
		);

		$mailer = tests_retrieve_phpmailer_instance();
		$body   = $mailer->mock_sent[0]['body'];

		// esc_url() HTML-entity-encodes "&" to "&#038;" — the raw get_edit_order_url() (which
		// contains "?post=X&action=edit") must be run through esc_url() here too, or this
		// assertion will never match the actually-escaped output.
		$this->assertStringContainsString( get_bloginfo( 'name' ), $body );
		$this->assertStringContainsString( '<a href="' . esc_url( $order->get_edit_order_url() ) . '"', $body );
		$this->assertStringContainsString( '#' . $order->get_order_number(), $body );
	}

	/**
	 * Test @see UnsettledOrdersMailer::send() does not send when there are no affected orders.
	 */
	public function test_send_does_nothing_when_no_orders() {
		$sent = ( new UnsettledOrdersMailer() )->send(
			array(
				'order_ids' => array(),
				'capped'    => false,
			)
		);

		$this->assertFalse( $sent );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 0, $mailer->mock_sent );
	}

	/**
	 * Test @see UnsettledOrdersMailer::send() does not send when no destination address is configured.
	 */
	public function test_send_does_nothing_without_configured_address() {
		self::$options->set_options(
			array(
				'failed_webhooks_email' => '',
			)
		);

		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);

		$sent = ( new UnsettledOrdersMailer() )->send(
			array(
				'order_ids' => array( $this->order_generator->order()->get_id() ),
				'capped'    => false,
			)
		);

		$this->assertFalse( $sent );
	}

	/**
	 * Test @see UnsettledOrdersMailer::send() notes the cap in the body when capped is true.
	 */
	public function test_send_notes_cap_in_body_when_capped() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order = $this->order_generator->order();

		( new UnsettledOrdersMailer() )->send(
			array(
				'order_ids' => array( $order->get_id() ),
				'capped'    => true,
			)
		);

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( '500+', $mailer->mock_sent[0]['body'] );
	}

	/**
	 * Test @see UnsettledOrdersMailer::send() includes every matching order, not just the first
	 * one (AC19 — multiple matching orders are reported together).
	 */
	public function test_send_body_includes_every_matching_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order_one = $this->order_generator->order();

		// generate() creates a genuinely new order and transitions it straight to "completed",
		// which fires WooCommerce's own order-completed notification email as a side effect —
		// reset the mock mailer after both orders exist so only our own send() call below is
		// captured (otherwise WooCommerce's email would land at mock_sent[0] instead of ours).
		$this->order_generator->generate( array( 'status' => 'completed' ) );
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$order_two = $this->order_generator->order();

		reset_phpmailer_instance();

		( new UnsettledOrdersMailer() )->send(
			array(
				'order_ids' => array( $order_one->get_id(), $order_two->get_id() ),
				'capped'    => false,
			)
		);

		$mailer = tests_retrieve_phpmailer_instance();
		$body   = $mailer->mock_sent[0]['body'];

		$this->assertStringContainsString( '<a href="' . esc_url( $order_one->get_edit_order_url() ) . '"', $body );
		$this->assertStringContainsString( '<a href="' . esc_url( $order_two->get_edit_order_url() ) . '"', $body );
	}
}
