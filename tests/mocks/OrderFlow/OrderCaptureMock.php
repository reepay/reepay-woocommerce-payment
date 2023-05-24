<?php

namespace Reepay\Checkout\Tests\Mocks\OrderFlow;

use Reepay\Checkout\OrderFlow\OrderCapture;
use WC_Order;
use WC_Order_Item;

class OrderCaptureMock extends OrderCapture {
	public function __construct() {
	}

	public function settle_items( WC_Order $order, array $items_data, float $total_all, array $line_items ): bool {
		foreach ( $line_items as $item ) {
			$item_data = $this->get_item_data( $item, $order );
			$total     = $item_data['amount'] * $item_data['quantity'];
			$this->complete_settle( $item, $order, $total );
		}

		return true;
	}

	public function complete_settle( WC_Order_Item $item, WC_Order $order, $total ) {
		$item->update_meta_data( 'settled', $total / 100 );
		$item->save();
	}
}