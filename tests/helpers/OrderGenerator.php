<?php

namespace Reepay\Checkout\Tests\Helpers;

use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;

class OrderGenerator {
	/**
	 * @var WC_Order|null
	 */
	private ?WC_Order $order;

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

	public function order(): ?WC_Order {
		return $this->order;
	}

	public function set_meta( string $key, $value ) {
		$this->order->update_meta_data( $key, $value );
	}

	public function get_meta( string $key ) {
		return $this->order->get_meta( $key );
	}

	/**
	 * Add product to order
	 *
	 * @param array $data product meta data.
	 *
	 * @return int order item id
	 */
	public function add_product( string $type, array $data = array() ): int {
		return $this->order->add_product(
			( new ProductGenerator( $type, $data ) )->product()
		);
	}

	/**
	 * Add simple product to order
	 *
	 * @param array $data product meta data.
	 *
	 * @return int order item id
	 */
	public function add_simple_product( array $data = array() ): int {
		return $this->add_product( 'simple', $data );
	}

	/**
	 * Add woocommerce subscription product to order
	 *
	 * @param array $data product meta data.
	 *
	 * @return int order item id
	 */
	public function add_woo_sub_product( array $data = array() ): int {
		return $this->add_product( 'woo_sub', $data );
	}

	/**
	 * Add reepay subscription product to order
	 *
	 * @param array $data product meta data.
	 *
	 * @return int order item id
	 */
	public function add_rp_sub_product( array $data = array() ): int {
		return $this->add_product( 'rp_sub', $data );
	}

	/**
	 * Add fee to order
	 *
	 * @param array $data optional. Fee data.
	 *
	 * @return int order item id
	 */
	public function add_fee( array $data = array() ): int {
		$item = new WC_Order_Item_Fee();
		$item->set_props( array(
			'name'      => $data['name'] ?? 'Test fee',
			'total'     => $data['amount'] ?? 0,
			'total_tax' => $data['tax'] ?? 0,
		) );
		$item->save();

		$this->order->add_item( $item );

		return $item->get_id();
	}

	/**
	 * Add shipping to order
	 *
	 * @param array $data optional. Shipping data.
	 *
	 * @return int order item id
	 */
	public function add_shipping( array $data = array() ): int {
		$item = new WC_Order_Item_Shipping();
		$item->set_props( array(
			'method_title' => $data['method_title'] ?? 'Test fee',
		) );
		$item->save();

		$this->order->add_item( $item );

		return $item->get_id();
	}
}
