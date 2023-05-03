<?php

class Rp_Test_Order_Generator {
	/**
	 * @var WC_Order|null
	 */
	private $order;

	public function __construct( array $args = array() ) {
		$this->generate_order( $args );
	}

	public function generate_order( array $args = array() ) {
		$this->order = wc_create_order( wp_parse_args(
			$args,
			array(
				'status'      => 'completed',
				'created_via' => 'tests'
			)
		) );
	}

	public function get_order() {
		return $this->order;
	}

	public function delete_order() {
		$this->order->delete( true );
		$this->delete_order_products();
	}

	private function delete_order_products() {
		foreach ( $this->order->get_items() as $order_item ) {
			/**
			 * @var WC_Order_Item_Product $order_item
			 */
			$order_item->get_product()->delete( true );
		}
	}

	public function add_simple_product() {
		$product = new WC_Product_Simple();
		$product->save();
		$this->order->add_product( $product );
	}

	public function add_woo_subscription() {
		$product = new WC_Product_Subscription();
		$product->save();
		$this->order->add_product( $product );
	}
}
