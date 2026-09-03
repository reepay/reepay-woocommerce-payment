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
	 * Find completed, non-zero-total, non-subscription Reepay orders with no settled-state meta,
	 * on/after the cutoff date.
	 *
	 * @return array{order_ids: int[], capped: bool}
	 */
	public function find(): array {
		$order_ids  = array();
		$capped     = false;
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
					'orderby'        => 'date_completed',
					'order'          => 'DESC',
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
