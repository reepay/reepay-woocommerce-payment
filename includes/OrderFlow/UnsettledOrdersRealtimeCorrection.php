<?php
/**
 * Immediately corrects the cached unsettled-orders list when an order is settled or leaves
 * Completed status, instead of waiting for the next daily batch check.
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use WC_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class UnsettledOrdersRealtimeCorrection
 *
 * @package Reepay\Checkout\OrderFlow
 */
class UnsettledOrdersRealtimeCorrection {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'reepay_order_settled', array( $this, 'on_order_settled' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 3 );
	}

	/**
	 * An order was genuinely settled — drop it from the cached list.
	 *
	 * @param WC_Order $order the settled order.
	 */
	public function on_order_settled( WC_Order $order ) {
		( new UnsettledOrdersMonitor() )->remove_resolved_order( $order->get_id() );
	}

	/**
	 * An order's status changed. If it moved away from "completed", drop it from the cached list —
	 * it's no longer a "Completed but unpaid" order regardless of whether it was ever settled.
	 *
	 * @param int    $order_id order id.
	 * @param string $from     previous status.
	 * @param string $to       new status.
	 */
	public function on_order_status_changed( int $order_id, string $from, string $to ) {
		if ( 'completed' === $from && 'completed' !== $to ) {
			( new UnsettledOrdersMonitor() )->remove_resolved_order( $order_id );
		}
	}
}
