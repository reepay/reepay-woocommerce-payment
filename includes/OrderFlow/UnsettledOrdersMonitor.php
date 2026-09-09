<?php
/**
 * Throttled trigger that checks for unsettled orders and notifies the merchant.
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use DateTimeZone;
use Reepay\Checkout\Utils\LoggingTrait;

defined( 'ABSPATH' ) || exit();

/**
 * Class UnsettledOrdersMonitor
 *
 * @package Reepay\Checkout\OrderFlow
 */
class UnsettledOrdersMonitor {
	use LoggingTrait;

	/**
	 * Logging source.
	 *
	 * @var string
	 */
	protected string $logging_source = 'unsettled-orders-monitor';

	/**
	 * IANA timezone the daily check's calendar-day boundary aligns to. Hardcoded to Denmark
	 * regardless of each site's own configured WordPress timezone.
	 *
	 * @var string
	 */
	private const TIMEZONE = 'Europe/Copenhagen';

	/**
	 * Option (not an expiring transient) storing the Europe/Copenhagen calendar date (Y-m-d) the
	 * check last actually ran on. The check must run once per calendar day in that timezone — not
	 * once per rolling 24-hour period, which would drift across DST changes and wouldn't line up
	 * with the merchant's actual business day.
	 *
	 * @var string
	 */
	public const CHECK_DATE_OPTION = 'reepay_unsettled_orders_check_date';

	/**
	 * Option (not an expiring transient — must survive indefinitely between admin visits, however
	 * long the gap, so a real-time correction or the notice never reads a stale/expired cache
	 * during a multi-day gap with nobody visiting wp-admin) caching the last detection result for
	 * the notice to read cheaply without re-querying on every page load.
	 *
	 * @var string
	 */
	public const RESULT_OPTION = 'reepay_unsettled_orders_result';

	/**
	 * Option (not an expiring transient — must persist indefinitely between daily checks)
	 * storing the order-ID set from the last time run_check() actually ran, used to detect
	 * genuinely new orders rather than resending for an unchanged list.
	 *
	 * @var string
	 */
	public const LAST_CHECKED_OPTION = 'reepay_unsettled_orders_last_checked';

	/**
	 * Option storing an integer that increments every time run_check() finds at least one
	 * genuinely new order (the same condition that triggers the email — see run_check()).
	 * UnsettledOrdersNotice compares each user's last-dismissed generation against this value
	 * instead of hashing the current order-ID set, so a notice a user dismissed only reappears
	 * for that same reason (a new order showing up) — never merely because real-time removals
	 * happened to shrink the list back down to a shape that coincidentally matches an earlier
	 * dismissed set.
	 *
	 * @var string
	 */
	public const GENERATION_OPTION = 'reepay_unsettled_orders_generation';

	/**
	 * WP-Cron hook name for the daily check. `admin_init` alone only fires the check once
	 * someone visits wp-admin, which could be days after new orders actually broke. WP-Cron's
	 * default "pseudo-cron" still needs *some* page load (front-end or back-end) to fire — it does
	 * not run on a genuinely zero-traffic site without the host also configuring a real system
	 * cron against `wp-cron.php` (outside this plugin's control) — but it removes the "must
	 * specifically be an admin" requirement, since WP-Cron's own dispatch happens on any request.
	 * `admin_init` is deliberately kept as-is: if WP-Cron is disabled or broken on a given host
	 * the admin-visit path still works as a fallback.
	 *
	 * This hook's callback is run_check() directly, unconditionally — not the throttled
	 * maybe_check().
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'reepay_unsettled_orders_cron_check';

	/**
	 * Monitor constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_check' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_check' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
	}

	/**
	 * Schedule the daily WP-Cron event if it isn't already scheduled.
	 */
	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Run the detection check at most once per Europe/Copenhagen calendar day. Only guards
	 * admin_init (uncontrolled frequency — fires on every page load); CRON_HOOK calls run_check()
	 * directly and isn't routed through this method at all, since WP-Cron's own "daily" schedule
	 * already limits its automatic frequency, and a manual "Run now" click should always work.
	 */
	public function maybe_check() {
		$today = wp_date( 'Y-m-d', null, new DateTimeZone( self::TIMEZONE ) );

		if ( get_option( self::CHECK_DATE_OPTION, '' ) === $today ) {
			return;
		}

		$this->run_check();
	}

	/**
	 * Run the detection check unconditionally, cache the result, and email — and bump
	 * GENERATION_OPTION — only if the result contains at least one order that wasn't part of the
	 * last checked list. Also records today's date as the last-checked date, so a
	 * later admin_init the same day (via maybe_check()) correctly treats today as already done,
	 * regardless of whether this particular run happened via admin_init or CRON_HOOK.
	 *
	 * @return array{order_ids: int[], capped: bool}
	 */
	public function run_check(): array {
		update_option( self::CHECK_DATE_OPTION, wp_date( 'Y-m-d', null, new DateTimeZone( self::TIMEZONE ) ), false );

		$result = ( new UnsettledOrdersFinder() )->find();

		update_option( self::RESULT_OPTION, $result, false );

		$this->log(
			sprintf(
				'Unsettled orders check: %d found (capped: %s)',
				count( $result['order_ids'] ),
				$result['capped'] ? 'yes' : 'no'
			)
		);

		$previously_checked = get_option( self::LAST_CHECKED_OPTION, array() );
		$newly_detected     = array_diff( $result['order_ids'], $previously_checked );

		if ( ! empty( $newly_detected ) ) {
			( new UnsettledOrdersMailer() )->send( $result );
			update_option( self::GENERATION_OPTION, (int) get_option( self::GENERATION_OPTION, 0 ) + 1, false );
		}

		update_option( self::LAST_CHECKED_OPTION, $result['order_ids'], false );

		return $result;
	}

	/**
	 * Remove a single order from the cached result immediately, without a full re-query.
	 * Used for real-time list correction when an order is paid or its status changes away from
	 * Completed (see UnsettledOrdersRealtimeCorrection). Deliberately does not touch
	 * LAST_CHECKED_OPTION or send any email that would require removal alone to never trigger a
	 * notification.
	 *
	 * @param int $order_id order id to remove.
	 */
	public function remove_resolved_order( int $order_id ) {
		$result = self::get_last_result();

		if ( ! in_array( $order_id, $result['order_ids'], true ) ) {
			return;
		}

		$result['order_ids'] = array_values( array_diff( $result['order_ids'], array( $order_id ) ) );

		update_option( self::RESULT_OPTION, $result, false );
	}

	/**
	 * Get the last cached detection result.
	 *
	 * @return array{order_ids: int[], capped: bool}
	 */
	public static function get_last_result(): array {
		$result = get_option( self::RESULT_OPTION, false );

		return is_array( $result ) ? $result : array(
			'order_ids' => array(),
			'capped'    => false,
		);
	}
}
