<?php
/**
 * Class UnsettledOrdersMonitorTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\UnsettledOrdersMonitor;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * UnsettledOrdersMonitorTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\UnsettledOrdersMonitor
 */
class UnsettledOrdersMonitorTest extends Reepay_UnitTestCase {

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

		delete_option( UnsettledOrdersMonitor::CHECK_DATE_OPTION );
		delete_option( UnsettledOrdersMonitor::RESULT_OPTION );
		delete_option( UnsettledOrdersMonitor::LAST_CHECKED_OPTION );

		// Reset WP-Cron state so scheduling assertions start from "nothing scheduled" and don't
		// leak between tests.
		_set_cron_array( array() );

		reset_phpmailer_instance();
	}

	/**
	 * Create one completed, unsettled, non-zero-total Reepay order and return it.
	 *
	 * Creating a brand-new order that transitions straight to "completed" fires WooCommerce's
	 * own transactional notification emails as a side effect (found during Phase 3 verification
	 * — the same issue already fixed in UnsettledOrdersMailerTest.php). Reset the mock mailer
	 * after the order is fully created so those stray emails never leak into a caller's own
	 * mock_sent assertions.
	 *
	 * @return WC_Order
	 */
	private function create_matching_order(): WC_Order {
		$this->order_generator->generate( array( 'status' => 'completed' ) );
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->add_product( 'simple', array( 'regular_price' => '20.00' ) );
		$this->order_generator->order()->calculate_totals();
		$order = $this->order_generator->order();
		$order->set_date_completed( '2026-08-10 00:00:00' );
		$order->save();

		reset_phpmailer_instance();

		return $order;
	}

	/**
	 * Test @see UnsettledOrdersMonitor::run_check() stores the detection result in a transient.
	 */
	public function test_run_check_stores_result_transient() {
		$order = $this->create_matching_order();

		$monitor = new UnsettledOrdersMonitor();
		$result  = $monitor->run_check();

		$this->assertContains( $order->get_id(), $result['order_ids'] );
		$this->assertSame( $result, UnsettledOrdersMonitor::get_last_result() );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::get_last_result() defaults to an empty result before any check has run.
	 */
	public function test_get_last_result_defaults_to_empty() {
		$this->assertSame(
			array(
				'order_ids' => array(),
				'capped'    => false,
			),
			UnsettledOrdersMonitor::get_last_result()
		);
	}

	/**
	 * Test @see UnsettledOrdersMonitor::run_check() sends an email the first time a matching order
	 * is detected (AC12/AC18 — nothing was previously checked, so the order is "new").
	 */
	public function test_run_check_sends_email_for_newly_detected_order() {
		$this->create_matching_order();

		( new UnsettledOrdersMonitor() )->run_check();

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 1, $mailer->mock_sent );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::run_check() does not resend when the list is unchanged
	 * from the last check (AC16).
	 */
	public function test_run_check_does_not_resend_email_when_list_unchanged() {
		$this->create_matching_order();

		$monitor = new UnsettledOrdersMonitor();
		$monitor->run_check();

		reset_phpmailer_instance();

		$monitor->run_check();

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 0, $mailer->mock_sent );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::run_check() sends a new email when a genuinely new order
	 * appears, even though the previously-checked list already existed (AC15).
	 */
	public function test_run_check_sends_email_for_order_not_in_previously_checked_list() {
		update_option( UnsettledOrdersMonitor::LAST_CHECKED_OPTION, array( 999999 ), false );

		$this->create_matching_order();

		( new UnsettledOrdersMonitor() )->run_check();

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 1, $mailer->mock_sent );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::maybe_check() is a no-op when it already ran today
	 * (Europe/Copenhagen calendar date).
	 */
	public function test_maybe_check_skips_when_already_checked_today() {
		update_option(
			UnsettledOrdersMonitor::CHECK_DATE_OPTION,
			wp_date( 'Y-m-d', null, new DateTimeZone( 'Europe/Copenhagen' ) ),
			false
		);
		update_option(
			UnsettledOrdersMonitor::RESULT_OPTION,
			array(
				'order_ids' => array( 999999 ),
				'capped'    => false,
			),
			false
		);

		( new UnsettledOrdersMonitor() )->maybe_check();

		$this->assertSame( array( 999999 ), UnsettledOrdersMonitor::get_last_result()['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::maybe_check() runs again once the Europe/Copenhagen
	 * calendar date has changed since the last check, even if less than 24 rolling hours have
	 * elapsed in another timezone.
	 */
	public function test_maybe_check_runs_when_copenhagen_date_has_changed() {
		update_option( UnsettledOrdersMonitor::CHECK_DATE_OPTION, '2000-01-01', false );

		$order = $this->create_matching_order();

		( new UnsettledOrdersMonitor() )->maybe_check();

		$this->assertContains( $order->get_id(), UnsettledOrdersMonitor::get_last_result()['order_ids'] );
		$this->assertSame(
			wp_date( 'Y-m-d', null, new DateTimeZone( 'Europe/Copenhagen' ) ),
			get_option( UnsettledOrdersMonitor::CHECK_DATE_OPTION )
		);
	}

	/**
	 * Test @see UnsettledOrdersMonitor::maybe_schedule_cron() schedules the daily WP-Cron event
	 * when nothing is scheduled yet.
	 */
	public function test_maybe_schedule_cron_schedules_when_not_already_scheduled() {
		$this->assertFalse( wp_next_scheduled( UnsettledOrdersMonitor::CRON_HOOK ) );

		( new UnsettledOrdersMonitor() )->maybe_schedule_cron();

		$this->assertNotFalse( wp_next_scheduled( UnsettledOrdersMonitor::CRON_HOOK ) );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::maybe_schedule_cron() does not schedule a second event
	 * (and does not reschedule the existing one) when the cron event is already scheduled.
	 */
	public function test_maybe_schedule_cron_does_not_double_schedule() {
		( new UnsettledOrdersMonitor() )->maybe_schedule_cron();
		$first_scheduled_time = wp_next_scheduled( UnsettledOrdersMonitor::CRON_HOOK );

		( new UnsettledOrdersMonitor() )->maybe_schedule_cron();

		$this->assertSame( $first_scheduled_time, wp_next_scheduled( UnsettledOrdersMonitor::CRON_HOOK ) );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::CRON_HOOK firing runs run_check() directly.
	 */
	public function test_cron_hook_triggers_the_daily_check() {
		$order = $this->create_matching_order();

		new UnsettledOrdersMonitor();

		do_action( UnsettledOrdersMonitor::CRON_HOOK );

		$this->assertContains( $order->get_id(), UnsettledOrdersMonitor::get_last_result()['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::CRON_HOOK bypasses the once-per-day throttle entirely,
	 * unlike admin_init's maybe_check() — a manual "Run now" in WP Crontrol on this exact event
	 * must always force a real check, per Christian's 2026-09-07 request ("There should not be
	 * additional code that blocks it from running manually").
	 */
	public function test_cron_hook_runs_even_when_already_checked_today() {
		update_option(
			UnsettledOrdersMonitor::CHECK_DATE_OPTION,
			wp_date( 'Y-m-d', null, new DateTimeZone( 'Europe/Copenhagen' ) ),
			false
		);

		$order = $this->create_matching_order();

		new UnsettledOrdersMonitor();

		do_action( UnsettledOrdersMonitor::CRON_HOOK );

		$this->assertContains( $order->get_id(), UnsettledOrdersMonitor::get_last_result()['order_ids'] );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::run_check() bumps GENERATION_OPTION when a genuinely new
	 * order is detected — the same condition that triggers the email. UnsettledOrdersNotice reads
	 * this to decide whether a dismissed notice should reappear.
	 */
	public function test_run_check_increments_generation_for_newly_detected_order() {
		$this->assertSame( 0, (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 ) );

		$this->create_matching_order();

		( new UnsettledOrdersMonitor() )->run_check();

		$this->assertSame( 1, (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 ) );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::run_check() does not bump GENERATION_OPTION when the list
	 * is unchanged from the last check (mirrors the no-email case, AC16).
	 */
	public function test_run_check_does_not_increment_generation_when_list_unchanged() {
		$this->create_matching_order();

		$monitor = new UnsettledOrdersMonitor();
		$monitor->run_check();

		$generation_after_first_run = (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 );

		$monitor->run_check();

		$this->assertSame( $generation_after_first_run, (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 ) );
	}

	/**
	 * Test @see UnsettledOrdersMonitor::run_check() does not bump GENERATION_OPTION when the list
	 * only shrinks (a real-time removal) — only genuinely new orders should advance it, per
	 * Christian's 2026-09-07 request that removals must never affect notice dismissal.
	 */
	public function test_run_check_does_not_increment_generation_when_list_only_shrinks() {
		$order = $this->create_matching_order();

		$monitor = new UnsettledOrdersMonitor();
		$monitor->run_check();

		$generation_after_first_run = (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 );

		$monitor->remove_resolved_order( $order->get_id() );
		$monitor->run_check();

		$this->assertSame( $generation_after_first_run, (int) get_option( UnsettledOrdersMonitor::GENERATION_OPTION, 0 ) );
	}
}
