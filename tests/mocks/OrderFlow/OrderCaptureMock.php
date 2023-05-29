<?php
/**
 * Class OrderCaptureMock
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Mocks\OrderFlow;

use Reepay\Checkout\OrderFlow\OrderCapture;
use WC_Order;
use WC_Order_Item;

/**
 * Class OrderCaptureMock
 */
class OrderCaptureMock extends OrderCapture {
	/**
	 * OrderCaptureMock constructor. Disable default construct
	 */
	public function __construct() {
	}

	/**
	 * Settle order items.
	 *
	 * @param WC_Order        $order      order to settle.
	 * @param array[]         $items_data items data from self::get_item_data.
	 * @param float           $total_all  order total amount ot settle.
	 * @param WC_Order_Item[] $line_items order line items.
	 *
	 * @return bool
	 */
	public function settle_items( WC_Order $order, array $items_data, float $total_all, array $line_items ): bool {
		foreach ( $line_items as $item ) {
			$item_data = $this->get_item_data( $item, $order );
			$total     = $item_data['amount'] * $item_data['quantity'];
			$this->complete_settle( $item, $order, $total );
		}

		return true;
	}

	/**
	 * Complete settle for order item, activate associated subscription and save data to meta
	 *
	 * @param WC_Order_Item $item  order item to set 'settled' meta.
	 * @param WC_Order      $order order to activate woo subscription (if it is possible).
	 * @param float|int     $total settled total to set to 'settled' meta.
	 */
	public function complete_settle( WC_Order_Item $item, WC_Order $order, $total ) {
		$item->update_meta_data( 'settled', $total / 100 );
		$item->save();
	}

	/**
	 * Check if capture is allowed
	 *
	 * @param WC_Order $order order to check.
	 *
	 * @return bool
	 */
	public function check_capture_allowed( WC_Order $order ): bool {
		return true;
	}
}
