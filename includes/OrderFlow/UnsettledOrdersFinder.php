<?php
/**
 * Finds completed orders that were never settled in Frisbii.
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Reepay\Checkout\Gateways;
use WC_Order;
use WC_Order_Query;

defined( 'ABSPATH' ) || exit();

/**
 * Class UnsettledOrdersFinder
 *
 * @package Reepay\Checkout\OrderFlow
 */
class UnsettledOrdersFinder {
	/**
	 * Orders completed before this date are outside the affected bug window (v1.8.14 release,
	 * 08/07/2026 in the ticket's DD/MM notation — 8 July 2026, i.e. 2026-07-08 in ISO form).
	 *
	 * @var string
	 */
	public const CUTOFF_DATE = '2026-07-08';

	/**
	 * Maximum number of order IDs returned in one run.
	 *
	 * @var int
	 */
	public const MAX_RESULTS = 500;

	/**
	 * Number of orders fetched per page while scanning candidates.
	 *
	 * @var int
	 */
	private const PAGE_SIZE = 200;

	/**
	 * Default maximum number of live Frisbii invoice checks (see live_check_single_order())
	 * performed in one find() run. Bounds how long a single run can take and avoids tripping the
	 * API's own rate limiting (Api.php sleeps and retries on a 429) across many orders in one
	 * request — a real risk on a site with a large backlog of affected orders (BWPM-281 found
	 * one site with 500+).
	 *
	 * @var int
	 */
	public const MAX_LIVE_VERIFICATIONS_PER_RUN = 20;

	/**
	 * Option storing the last checked order ID for round-robin live verification.
	 *
	 * The next run resumes after this order and wraps to the beginning when it
	 * reaches the end of the candidate list. This prevents older unresolved orders
	 * from blocking newer orders from being checked (BWPM-293).
	 *
	 * Ensures every candidate is checked at least once every
	 * ceil( candidate count / MAX_LIVE_VERIFICATIONS_PER_RUN ) runs.
	 *
	 * @var string
	 */
	public const LIVE_CHECK_CURSOR_OPTION = 'reepay_unsettled_orders_live_check_cursor';

	/**
	 * Find completed, non-zero-total, non-subscription Reepay orders with no settled-state meta,
	 * on/after the cutoff date.
	 *
	 * @return array{order_ids: int[], capped: bool}
	 */
	public function find(): array {
		$candidates = $this->get_candidates();

		$this->live_check_candidates( $candidates );

		$order_ids = array();
		$capped    = false;

		foreach ( $candidates as $order ) {
			if ( $order->get_meta( '_reepay_state_settled' ) ) {
				continue;
			}

			$order_ids[] = $order->get_id();

			if ( count( $order_ids ) > self::MAX_RESULTS ) {
				$capped = true;
				break;
			}
		}

		if ( $capped ) {
			$order_ids = array_slice( $order_ids, 0, self::MAX_RESULTS );
		}

		return array(
			'order_ids' => $order_ids,
			'capped'    => $capped,
		);
	}

	/**
	 * Gather the full candidate pool — every completed, non-zero-total, non-subscription Reepay
	 * order without the settled-state meta, on/after the cutoff date — oldest-completed-first.
	 * Shared by both reporting (find()) and live-verification rotation
	 * (live_check_candidates()), so the two always agree on what "the backlog" is.
	 *
	 * Sorted in PHP after fetching rather than via the query's `orderby`: WC_Order_Query (via
	 * WC_Order_Data_Store_CPT::get_wp_query_args()) only special-cases `orderby => 'total'` for
	 * mapping onto a meta-based sort — there is no equivalent mapping for `date_completed`, so
	 * WP_Query silently ignores it as an orderby key and the query falls back to its default (ID
	 * order) instead. The query still uses `orderby => 'ID'` for pagination purposes only (any
	 * stable order is fine there, since the real sort happens below), oldest-completed-first.
	 *
	 * @return WC_Order[]
	 */
	private function get_candidates(): array {
		$candidates = array();
		$page       = 1;
		$batch_size = self::PAGE_SIZE;

		do {
			$batch = ( new WC_Order_Query(
				array(
					'status'         => 'completed',
					'payment_method' => Gateways::PAYMENT_METHODS,
					'date_completed' => '>=' . self::CUTOFF_DATE,
					'limit'          => self::PAGE_SIZE,
					'page'           => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'return'         => 'ids',
				)
			) )->get_orders();

			$batch_size = count( $batch );

			foreach ( $batch as $order_id ) {
				$order = wc_get_order( $order_id );

				if ( ! $order
					|| $order->get_total() <= 0
					|| $order->get_meta( '_reepay_state_settled' )
					|| order_contains_subscription( $order )
					|| $this->is_frisbii_billing_subscription_order( $order )
				) {
					continue;
				}

				$candidates[] = $order;
			}

			++$page;
		} while ( self::PAGE_SIZE === $batch_size );

		usort(
			$candidates,
			static function ( WC_Order $a, WC_Order $b ): int {
				$date_comparison = $a->get_date_completed()->getTimestamp() <=> $b->get_date_completed()->getTimestamp();

				return 0 !== $date_comparison ? $date_comparison : $a->get_id() <=> $b->get_id();
			}
		);

		return $candidates;
	}

	/**
	 * Live-verify up to MAX_LIVE_VERIFICATIONS_PER_RUN candidates against Frisbii, starting just
	 * after wherever the previous run left off (LIVE_CHECK_CURSOR_OPTION) and wrapping around to
	 * the start of the list once the end is reached — see that constant for why this matters.
	 *
	 * @param WC_Order[] $candidates full candidate pool, oldest-completed-first.
	 */
	private function live_check_candidates( array $candidates ): void {
		$total = count( $candidates );

		if ( 0 === $total ) {
			return;
		}

		$max_live_verifications = (int) apply_filters(
			'reepay_unsettled_orders_max_live_verifications',
			self::MAX_LIVE_VERIFICATIONS_PER_RUN
		);

		$start_index           = $this->get_cursor_start_index( $candidates );
		$checks_to_perform     = min( $max_live_verifications, $total );
		$last_checked_order_id = null;

		for ( $i = 0; $i < $checks_to_perform; $i++ ) {
			$order = $candidates[ ( $start_index + $i ) % $total ];

			$last_checked_order_id = $order->get_id();

			$this->live_check_single_order( $order );
		}

		if ( null !== $last_checked_order_id ) {
			update_option( self::LIVE_CHECK_CURSOR_OPTION, $last_checked_order_id, false );
		}
	}

	/**
	 * Find the index to resume live-verification from: the position right after the candidate
	 * matching the previous run's last-checked order ID, or 0 if that order is no longer a
	 * candidate (already healed/excluded by now) or this is the very first run.
	 *
	 * Matches by exact order ID rather than comparing IDs as a sort key, since $candidates is
	 * ordered by date_completed (ID only breaks ties) and WooCommerce order IDs do not increase
	 * with completion date — comparing IDs against the cursor would skip later-completed,
	 * lower-ID orders whenever a higher-ID order already advanced the cursor, starving them
	 * indefinitely (the exact problem this cursor exists to prevent).
	 *
	 * @param WC_Order[] $candidates full candidate pool, oldest-completed-first.
	 *
	 * @return int
	 */
	private function get_cursor_start_index( array $candidates ): int {
		$cursor_order_id = (int) get_option( self::LIVE_CHECK_CURSOR_OPTION, 0 );

		if ( ! $cursor_order_id ) {
			return 0;
		}

		foreach ( $candidates as $index => $order ) {
			if ( $order->get_id() === $cursor_order_id ) {
				return ( $index + 1 ) % count( $candidates );
			}
		}

		return 0;
	}

	/**
	 * Check whether the real Frisbii invoice is already settled even if our local settlement meta
	 * was not set, for example when a third-party integration settles the payment outside this
	 * plugin's own capture flow (BWPM-281), or a settlement webhook was dropped (BWPM-293).
	 *
	 * If settled, only update _reepay_state_settled locally. Do not trigger the normal settlement
	 * flow, add notes, change status, or fire events again.
	 *
	 * @param WC_Order $order order to check.
	 */
	private function live_check_single_order( WC_Order $order ): void {
		$invoice = reepay()->api( $order )->get_invoice_data( $order );

		if ( is_wp_error( $invoice ) || ! isset( $invoice['settled_amount'] ) ) {
			return;
		}

		// Compare against the order's net total, not the authorized or gross total.
		// This correctly handles discounts and refunds before or after settlement.
		$net_total_amount = rp_prepare_amount(
			(float) ( $order->get_total() - $order->get_total_refunded() ),
			$order->get_currency()
		);

		if ( $invoice['settled_amount'] <= 0 || $invoice['settled_amount'] < $net_total_amount ) {
			return;
		}

		$order->update_meta_data( '_reepay_state_settled', 1 );
		$order->save_meta_data();
	}

	/**
	 * Whether an order is part of a Frisbii Billing subscription (reepay/reepay-woocommerce-subscriptions
	 * — a separate plugin from official WooCommerce Subscriptions, with its own independent data
	 * model). Deliberately not folded into the shared `order_contains_subscription()` helper: that
	 * function is used in several other places in this codebase — including what gets sent to
	 * Frisbii's live payment API (`Api.php`, `Gateways/ReepayGateway.php`) — where broadening its
	 * behavior risks side effects unrelated to this feature. This check stays local to detection.
	 *
	 * @param WC_Order $order order to check.
	 *
	 * @return bool
	 */
	private function is_frisbii_billing_subscription_order( WC_Order $order ): bool {
		if ( $order->get_meta( '_reepay_is_subscription' ) ) {
			return true;
		}

		$parent_id = $order->get_parent_id();

		if ( ! $parent_id ) {
			return false;
		}

		$parent = wc_get_order( $parent_id );

		return $parent instanceof WC_Order && (bool) $parent->get_meta( '_reepay_is_subscription' );
	}
}
