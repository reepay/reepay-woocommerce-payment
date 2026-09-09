<?php
/**
 * Class UnsettledOrdersRealtimeCorrectionTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\OrderFlow\UnsettledOrdersMonitor;
use Reepay\Checkout\OrderFlow\UnsettledOrdersRealtimeCorrection;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * UnsettledOrdersRealtimeCorrectionTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\UnsettledOrdersRealtimeCorrection
 */
class UnsettledOrdersRealtimeCorrectionTest extends Reepay_UnitTestCase {

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		reset_phpmailer_instance();
	}

	/**
	 * Test @see UnsettledOrdersRealtimeCorrection removes an order from the cached list once it is
	 * settled, and does NOT send a notification email for that removal alone (AC7).
	 */
	public function test_order_removed_from_list_when_settled() {
		$this->order_generator->set_props(
			array(
				'status'         => 'on-hold',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order = $this->order_generator->order();

		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( $order->get_id(), 999999 ),
				'capped'    => false,
			),
			false
		);

		new UnsettledOrdersRealtimeCorrection();

		self::$options->set_options( array( 'enable_sync' => 'yes' ) );

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'authorized_amount' => 100,
				'settled_amount'    => 100,
			)
		);

		OrderStatuses::set_settled_status( $order );

		$result = UnsettledOrdersMonitor::get_last_result();
		$this->assertNotContains( $order->get_id(), $result['order_ids'] );
		$this->assertContains( 999999, $result['order_ids'] );

		$this->assert_no_unsettled_orders_notification_sent();
	}

	/**
	 * Test @see UnsettledOrdersRealtimeCorrection removes an order from the cached list once its
	 * status changes away from Completed, and does NOT send a notification email for that removal
	 * alone (AC8).
	 */
	public function test_order_removed_from_list_when_status_changes_away_from_completed() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order = $this->order_generator->order();

		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( $order->get_id() ),
				'capped'    => false,
			),
			false
		);

		new UnsettledOrdersRealtimeCorrection();

		$order->set_status( 'cancelled' );
		$order->save();

		$result = UnsettledOrdersMonitor::get_last_result();
		$this->assertNotContains( $order->get_id(), $result['order_ids'] );

		$this->assert_no_unsettled_orders_notification_sent();
	}

	/**
	 * Test @see UnsettledOrdersRealtimeCorrection does not remove an order that changes between
	 * two non-completed statuses (only leaving Completed matters).
	 */
	public function test_order_not_removed_when_transition_does_not_involve_completed() {
		$this->order_generator->set_props(
			array(
				'status'         => 'on-hold',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order = $this->order_generator->order();

		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( 555555 ),
				'capped'    => false,
			),
			false
		);

		new UnsettledOrdersRealtimeCorrection();

		$order->set_status( 'processing' );
		$order->save();

		$result = UnsettledOrdersMonitor::get_last_result();
		$this->assertContains( 555555, $result['order_ids'] );
	}

	/**
	 * Assert that no unsettled-orders notification email was sent, without assuming zero emails
	 * of any kind — WooCommerce's own transactional emails (e.g. order-completed,
	 * order-cancelled notifications) legitimately fire as a side effect of the order transitions
	 * these tests exercise, and are unrelated to this feature.
	 */
	private function assert_no_unsettled_orders_notification_sent(): void {
		$mailer = tests_retrieve_phpmailer_instance();

		foreach ( $mailer->mock_sent as $sent ) {
			$this->assertNotSame( 'Some completed orders in your webshop are unpaid', $sent['subject'] );
		}
	}
}
