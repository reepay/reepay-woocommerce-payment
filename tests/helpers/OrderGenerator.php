<?php
/**
 * Class OrderGenerator
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;

/**
 * Class OrderGenerator
 */
class OrderGenerator {
	/**
	 * Current order
	 *
	 * @var WC_Order|null
	 */
	private ?WC_Order $order;

	/**
	 * OrderGenerator constructor.
	 *
	 * @param array $args args to wc_create_order.
	 */
	public function __construct( array $args = array() ) {
		$this->generate( $args );
	}

	/**
	 * Create order
	 *
	 * @param array $args args to wc_create_order.
	 */
	public function generate( array $args = array() ) {
		$this->order = wc_create_order(
			wp_parse_args(
				$args,
				array(
					'status'      => 'completed',
					'created_via' => 'tests',
				)
			)
		);
	}

	/**
	 * Ger order
	 *
	 * @return WC_Order|null
	 */
	public function order(): ?WC_Order {
		return $this->order;
	}

	/**
	 * Update meta data by key or ID, if provided.
	 *
	 * @param string       $key   meta key.
	 * @param string|array $value meta value.
	 */
	public function set_meta( string $key, $value ) {
		$this->order->update_meta_data( $key, $value );
	}

	/**
	 * Get Meta Data by Key.
	 *
	 * @param string $key meta key.
	 *
	 * @return mixed
	 */
	public function get_meta( string $key ) {
		return $this->order->get_meta( $key );
	}

	/**
	 * Add product to order
	 *
	 * @param string $type product type.
	 * @param array  $data product meta data.
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
		$item->set_props(
			array(
				'name'      => $data['name'] ?? 'Test fee',
				'total'     => $data['amount'] ?? 0,
				'total_tax' => $data['tax'] ?? 0,
			)
		);
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
		$item->set_props(
			array(
				'method_title' => $data['method_title'] ?? 'Test fee',
			)
		);
		$item->save();

		$this->order->add_item( $item );

		return $item->get_id();
	}
}
