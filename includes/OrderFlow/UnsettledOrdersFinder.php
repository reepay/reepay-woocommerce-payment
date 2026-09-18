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
	 * Default maximum number of live Frisbii invoice checks (see is_genuinely_settled_but_untracked())
	 * performed in one find() run. Bounds how long a single run can take and avoids tripping the
	 * API's own rate limiting (Api.php sleeps and retries on a 429) across many orders in one
	 * request — a real risk on a site with a large backlog of affected orders (BWPM-281 found
	 * one site with 500+). An order skipped here for this reason is simply reconsidered,
	 * with a fresh live check, the next time find() runs, so a large backlog clears gradually
	 * over a few days rather than in one slow or timed-out run.
	 *
	 * @var int
	 */
	public const MAX_LIVE_VERIFICATIONS_PER_RUN = 20;

	/**
	 * Number of live Frisbii invoice checks performed so far in the current find() call.
	 *
	 * @var int
	 */
	private int $live_verifications_used = 0;

	/**
	 * Find completed, non-zero-total, non-subscription Reepay orders with no settled-state meta,
	 * on/after the cutoff date.
	 *
	 * Scans oldest-completed-first (not newest-first) deliberately: is_genuinely_settled_but_untracked()
	 * spends one unit of MAX_LIVE_VERIFICATIONS_PER_RUN per candidate regardless of what the live
	 * check finds, including genuinely still-unpaid ones. Newest-first would let an ever-refreshing
	 * pool of recent, correctly-unpaid orders permanently starve the live-check budget, so the
	 * actual (older) backlog this feature exists to clear would never get checked. Oldest-first
	 * ensures the backlog drains over successive runs instead.
	 *
	 * @return array{order_ids: int[], capped: bool}
	 */
	public function find(): array {
		$order_ids  = array();
		$capped     = false;
		$page       = 1;
		$batch_size = self::PAGE_SIZE;

		$this->live_verifications_used = 0;

		do {
			$batch = ( new WC_Order_Query(
				array(
					'status'         => 'completed',
					'payment_method' => Gateways::PAYMENT_METHODS,
					'date_completed' => '>=' . self::CUTOFF_DATE,
					'limit'          => self::PAGE_SIZE,
					'page'           => $page,
					'orderby'        => 'date_completed',
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
					|| $this->is_genuinely_settled_but_untracked( $order )
				) {
					continue;
				}

				$order_ids[] = $order_id;

				if ( count( $order_ids ) > self::MAX_RESULTS ) {
					$capped = true;
					break 2;
				}
			}

			++$page;
		} while ( self::PAGE_SIZE === $batch_size );

		if ( $capped ) {
			$order_ids = array_slice( $order_ids, 0, self::MAX_RESULTS );
		}

		return array(
			'order_ids' => $order_ids,
			'capped'    => $capped,
		);
	}

	/**
	 * Check whether the real Frisbii invoice is already settled even if our local
	 * settlement meta was not set, for example when a third-party integration
	 * settles the payment outside our normal capture flow (BWPM-281).
	 *
	 * If settled, only update _reepay_state_settled locally. Do not trigger the
	 * normal settlement flow, add notes, change status, or fire events again.
	 * This prevents the order from appearing in the unsettled list and avoids
	 * checking the same order again on future runs.
	 *
	 * Guards against an already-tracked order itself, not just relying on find()'s own check
	 * before calling this — so this method never spends live-verification budget or makes an
	 * API call for an order that doesn't need one, even if called some other way in future.
	 *
	 * @param WC_Order $order order to check.
	 *
	 * @return bool
	 */
	private function is_genuinely_settled_but_untracked( WC_Order $order ): bool {
		if ( $order->get_meta( '_reepay_state_settled' ) ) {
			return false;
		}

		$max_live_verifications = (int) apply_filters(
			'reepay_unsettled_orders_max_live_verifications',
			self::MAX_LIVE_VERIFICATIONS_PER_RUN
		);

		if ( $this->live_verifications_used >= $max_live_verifications ) {
			return false;
		}

		++$this->live_verifications_used;

		$invoice = reepay()->api( $order )->get_invoice_data( $order );

		if ( is_wp_error( $invoice ) || ! isset( $invoice['settled_amount'], $invoice['authorized_amount'] ) ) {
			return false;
		}

		if ( $invoice['settled_amount'] <= 0 || $invoice['settled_amount'] < $invoice['authorized_amount'] ) {
			return false;
		}

		$order->update_meta_data( '_reepay_state_settled', 1 );
		$order->save_meta_data();

		return true;
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
