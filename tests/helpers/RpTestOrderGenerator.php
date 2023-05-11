<?php

class RpTestOrderGenerator {
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

	public function order() {
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
		$this->order->add_product(
			( new RpTestProductGenerator( 'simple' ) )->product()
		);
	}

	public function add_variable_product() {
		$this->order->add_product(
			( new RpTestProductGenerator( 'variable' ) )->product()
		);
	}

	public function add_woocommerce_subscription_product() {
		$this->order->add_product(
			( new RpTestProductGenerator( 'woo_sub' ) )->product()
		);
	}

//	public function add_woo_subscription() {
//		$product = new WC_Product_Subscription();
//		$product->save();
//		$this->order->add_product( $product );
//	}
}
