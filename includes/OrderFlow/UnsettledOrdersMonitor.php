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
	 * WP-Cron hook name for the daily check. `admin_init` alone only fires the check once
	 * someone visits wp-admin, which could be days after new orders actually broke. WP-Cron's
	 * default "pseudo-cron" still needs *some* page load (front-end or back-end) to fire — it does
	 * not run on a genuinely zero-traffic site without the host also configuring a real system
	 * cron against `wp-cron.php` (outside this plugin's control) — but it removes the "must
	 * specifically be an admin" requirement, since WP-Cron's own dispatch happens on any request.
	 * `admin_init` is deliberately kept as-is: if WP-Cron is disabled or broken on a given host
	 *the admin-visit path still works as a fallback.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'reepay_unsettled_orders_cron_check';

	/**
	 * WP-Cron hook for forcing an out-of-schedule check on demand — deliberately separate from
	 * CRON_HOOK. 
	 *
	 * @var string
	 */
	public const FORCE_CHECK_HOOK = 'reepay_unsettled_orders_force_check';

	/**
	 * Monitor constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_check' ) );
		add_action( self::CRON_HOOK, array( $this, 'maybe_check' ) );
		add_action( self::FORCE_CHECK_HOOK, array( $this, 'run_check' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
	}

	/**
	 * Schedule the daily WP-Cron event if it isn't already scheduled, and keep a single
	 * far-future FORCE_CHECK_HOOK event registered so it always appears in WP Crontrol's event
	 * list as a "Run now" target.
	 */
	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}

		if ( ! wp_next_scheduled( self::FORCE_CHECK_HOOK ) ) {
			wp_schedule_single_event( time() + YEAR_IN_SECONDS, self::FORCE_CHECK_HOOK );
		}
	}

	/**
	 * Run the detection check at most once per Europe/Copenhagen calendar day. Shared by both
	 * triggers (admin_init and the WP-Cron event) so whichever fires first each day does the real
	 * work, and the other is a cheap no-op — no risk of a duplicate check or duplicate email.
	 */
	public function maybe_check() {
		$today = wp_date( 'Y-m-d', null, new DateTimeZone( self::TIMEZONE ) );

		if ( get_option( self::CHECK_DATE_OPTION, '' ) === $today ) {
			return;
		}

		update_option( self::CHECK_DATE_OPTION, $today, false );

		$this->run_check();
	}

	/**
	 * Run the detection check unconditionally, cache the result, and email only if the result
	 * contains at least one order that wasn't part of the last checked list (AC15/AC16).
	 *
	 * @return array{order_ids: int[], capped: bool}
	 */
	public function run_check(): array {
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
