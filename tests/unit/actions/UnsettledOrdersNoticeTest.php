<?php
/**
 * Class UnsettledOrdersNoticeTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Actions\UnsettledOrdersNotice;
use Reepay\Checkout\OrderFlow\UnsettledOrdersMonitor;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * UnsettledOrdersNoticeTest.
 *
 * @covers \Reepay\Checkout\Actions\UnsettledOrdersNotice
 */
class UnsettledOrdersNoticeTest extends Reepay_UnitTestCase {

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( UnsettledOrdersMonitor::RESULT_OPTION );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		delete_user_meta( $admin_id, UnsettledOrdersNotice::DISMISSED_META );
	}

	/**
	 * Test @see UnsettledOrdersNotice::render() prints nothing when there is no unresolved result.
	 */
	public function test_render_outputs_nothing_when_no_affected_orders() {
		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Test @see UnsettledOrdersNotice::render() prints the ticket's exact intro copy and a link per affected order.
	 */
	public function test_render_outputs_notice_with_intro_copy_and_order_links() {
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

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		// esc_url() HTML-entity-encodes "&" to "&#038;" — the raw get_edit_order_url() (which
		// contains "?post=X&action=edit") must be run through esc_url() here too, or this
		// assertion will never match the actually-escaped output (found the same issue in
		// UnsettledOrdersMailerTest.php during Phase 2 verification).
		$this->assertStringContainsString( 'reepay-unsettled-orders-notice', $output );
		$this->assertStringContainsString( 'The following orders have status as Completed but have no payment registered:', $output );
		$this->assertStringContainsString( '<a href="' . esc_url( $order->get_edit_order_url() ) . '"', $output );
		$this->assertStringContainsString( '#' . $order->get_order_number(), $output );
	}

	/**
	 * Test @see UnsettledOrdersNotice::render() lists every matching order, not just the first one
	 * (AC19 — multiple matching orders are reported together).
	 */
	public function test_render_lists_every_matching_order() {
		$this->order_generator->set_props(
			array(
				'status'         => 'completed',
				'payment_method' => reepay()->gateways()->checkout()->id,
			)
		);
		$order_one = $this->order_generator->order();

		$this->order_generator->generate( array( 'status' => 'completed' ) );
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$order_two = $this->order_generator->order();

		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( $order_one->get_id(), $order_two->get_id() ),
				'capped'    => false,
			),
			false
		);

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<a href="' . esc_url( $order_one->get_edit_order_url() ) . '"', $output );
		$this->assertStringContainsString( '<a href="' . esc_url( $order_two->get_edit_order_url() ) . '"', $output );
	}

	/**
	 * Test @see UnsettledOrdersNotice::render() notes the cap when the detection result is capped.
	 */
	public function test_render_notes_cap_when_capped() {
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
				'capped'    => true,
			),
			false
		);

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '500+', $output );
	}

	/**
	 * Test @see UnsettledOrdersNotice::render() suppresses the notice once dismissed for the current order-ID set.
	 */
	public function test_render_suppressed_after_dismiss_of_same_set() {
		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( 111, 222 ),
				'capped'    => false,
			),
			false
		);

		update_user_meta( get_current_user_id(), UnsettledOrdersNotice::DISMISSED_META, md5( wp_json_encode( array( 111, 222 ) ) ) );

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Test @see UnsettledOrdersNotice::render() reappears when the affected order-ID set changes after a dismiss.
	 */
	public function test_render_reappears_when_order_set_changes_after_dismiss() {
		update_user_meta( get_current_user_id(), UnsettledOrdersNotice::DISMISSED_META, md5( wp_json_encode( array( 111, 222 ) ) ) );

		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( 111, 222, 444 ),
				'capped'    => false,
			),
			false
		);

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertNotSame( '', $output );
	}
}
