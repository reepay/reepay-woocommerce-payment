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
		delete_option( UnsettledOrdersMonitor::GENERATION_OPTION );

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
		$this->assertStringContainsString( 'The following orders for payment via the Frisbii gateway have status as Completed but have no payment registered:', $output );
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
	 * Test @see UnsettledOrdersNotice::render() suppresses the notice once dismissed for the
	 * current generation, regardless of the order-ID set's exact content.
	 */
	public function test_render_suppressed_after_dismiss_of_current_generation() {
		update_option( UnsettledOrdersMonitor::GENERATION_OPTION, 3, false );
		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( 111, 222 ),
				'capped'    => false,
			),
			false
		);

		update_user_meta( get_current_user_id(), UnsettledOrdersNotice::DISMISSED_META, 3 );

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Regression test for a bug found live on staging immediately after deploying the
	 * generation-based dismissal (2026-09-07): a site with a leftover dismissal value from the
	 * old hash-based design (a hex string) must not have that value misread as a real generation
	 * number. PHP's (int) cast on a hex string like "5d41402a..." silently reads only its leading
	 * digits (yielding 5 here), which could coincidentally match GENERATION_OPTION and wrongly
	 * hide the notice for that admin.
	 */
	public function test_render_ignores_leftover_hash_style_dismissed_value() {
		update_option( UnsettledOrdersMonitor::GENERATION_OPTION, 5, false );
		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( 111, 222 ),
				'capped'    => false,
			),
			false
		);

		// Old-format dismissed value: a hex string whose leading digits happen to cast to 5.
		update_user_meta( get_current_user_id(), UnsettledOrdersNotice::DISMISSED_META, '5d41402abc4b2a76b9719d911017c59' );

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertNotSame( '', $output );
	}

	/**
	 * Test @see UnsettledOrdersNotice::render() reappears once the generation advances past what
	 * the user dismissed (i.e. a genuinely new order was detected since then).
	 */
	public function test_render_reappears_when_generation_advances_after_dismiss() {
		update_user_meta( get_current_user_id(), UnsettledOrdersNotice::DISMISSED_META, 3 );
		update_option( UnsettledOrdersMonitor::GENERATION_OPTION, 4, false );

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

	/**
	 * Regression test for the 2026-09-07 bug Christian found: with the old content-hash
	 * dismissal, a real-time removal that happened to shrink the list back down to a shape
	 * matching an earlier dismissed set made the notice disappear even though nobody dismissed
	 * that state, and the generation never advanced. With generation-based dismissal, the notice
	 * must stay dismissed here purely because the generation hasn't changed — the order-ID
	 * content is irrelevant.
	 */
	public function test_render_stays_dismissed_after_removal_even_if_set_matches_old_dismissed_content() {
		update_option( UnsettledOrdersMonitor::GENERATION_OPTION, 2, false );
		update_user_meta( get_current_user_id(), UnsettledOrdersNotice::DISMISSED_META, 2 );

		// The current set happens to look exactly like some earlier, already-dismissed set would
		// have under the old design — this must not matter anymore.
		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( 111, 222 ),
				'capped'    => false,
			),
			false
		);

		$notice = new UnsettledOrdersNotice();

		ob_start();
		$notice->render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
