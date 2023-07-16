<?php
/**
 * Class OrderGenerator
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use WC_Order;
use WC_Order_Factory;
use WC_Order_Item;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;
use WC_Order_Item_Tax;
use WC_Tax;

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
					'customer_id' => get_current_user_id() ?: 1,
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
	 * Set order, update data from db.
	 * For tests in which the order id is passed not the order instance
	 */
	public function reset_order() {
		$this->order = wc_get_order( $this->order->get_id() ) ?: null;
	}

	/**
	 * Update meta data by key
	 *
	 * @param string       $key   meta key.
	 * @param string|array $value meta value.
	 */
	public function set_meta( string $key, $value ) {
		$this->order->update_meta_data( $key, $value );
		$this->order->save();
	}

	/**
	 * Update property by key
	 *
	 * @param string $key   property key.
	 * @param mixed  $value property value.
	 */
	public function set_prop( string $key, $value ) {
		$this->set_props( array( $key => $value ) );
	}

	/**
	 * Set a collection of props in one go, collect any errors, and return the result.
	 * Only sets using public methods.
	 *
	 * @param array $props Key value pairs to set. Key is the prop and should map to a setter function name.
	 */
	public function set_props( array $props ) {
		$this->order->set_props( $props );
		$this->order->save();
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
	 * @param string $type            product type.
	 * @param array  $product_data    product meta data.
	 * @param array  $order_item_data order item meta data.
	 *
	 * @return int order item id
	 */
	public function add_product( string $type, array $product_data = array(), array $order_item_data = array() ): int {
		$product_generator = new ProductGenerator( $type, $product_data );

		$order_item_id = $this->order->add_product( $product_generator->product(), $order_item_data['quantity'] ?? 1 );

		$this->add_data_to_order_item( $order_item_id, $order_item_data );

		if ( 'woo_sub' === $type ) {
			$this->generate_woo_subscription( $product_generator->product() );
		}

		return $order_item_id;
	}

	private function generate_woo_subscription( $product ) {
		$sub = wcs_create_subscription(
			array(
				'order_id'         => $this->order->get_id(),
				'status'           => 'pending', // Status should be initially set to pending to match how normal checkout process goes.
				'billing_period'   => 'Day',
				'billing_interval' => 1,
			)
		);

		if ( is_wp_error( $sub ) ) {
			throw new Exception( $sub->get_error_message() );
		}

		// Add product to subscription.
		$sub->add_product( $product );

		$dates = array(
			'trial_end'    => gmdate( 'Y-m-d H:i:s', 0 ),
			'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
			'end'          => gmdate( 'Y-m-d H:i:s', strtotime( '+2 week' ) ),
		);

		$sub->update_dates( $dates );
		$sub->calculate_totals();

		$sub->update_status( 'active', '', true );

		return $sub;
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
		$item->save();

		$this->add_data_to_order_item(
			$item,
			wp_parse_args(
				$data,
				array(
					'name'      => 'Test fee',
					'total'     => 0,
					'total_tax' => 0,
				)
			)
		);

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
		$item->save();

		$this->add_data_to_order_item(
			$item,
			wp_parse_args(
				$data,
				array(
					'method_title' => 'Test shipping method',
					'total'        => 0,
				)
			)
		);

		$this->order->add_item( $item );

		$this->order->calculate_shipping();

		return $item->get_id();
	}

	/**
	 * Add shipping to order
	 *
	 * @param float  $tax_rate tax rate.
	 * @param string $tax_rate_name tax rate name.
	 *
	 * @return int order item id
	 */
	public function add_tax( float $tax_rate, string $tax_rate_name = 'test' ): int {
		$tax_rate_id = WC_Tax::_insert_tax_rate(
			array(
				'tax_rate'      => $tax_rate,
				'tax_rate_name' => $tax_rate_name,
			)
		);

		$item = new WC_Order_Item_Tax();

		$item->set_props(
			array(
				'rate'     => $tax_rate_id,
				'order_id' => $this->order->get_id(),
			)
		);

		$item->save();

		$this->order->add_item( $item );

		$this->order->calculate_taxes();

		return $item->get_id();
	}

	/**
	 * Add meta data to order item
	 *
	 * @param WC_Order_Item|int $order_item order item.
	 * @param array             $data data to add.
	 */
	protected function add_data_to_order_item( $order_item, array $data ) {
		if ( empty( $data ) ) {
			return;
		}

		if ( is_int( $order_item ) ) {
			$order_item = WC_Order_Factory::get_order_item( $order_item );
		}

		foreach ( $data as $key => $value ) {
			$function = 'set_' . ltrim( $key, '_' );

			if ( is_callable( array( $order_item, $function ) ) ) {
				$order_item->{$function}( $value );
			} else {
				$order_item->update_meta_data( $key, $value );
			}
		}

		$order_item->save();
	}

	/**
	 * Check if order note with such content exists
	 *
	 * @param string $note_content note content
	 *
	 * @return bool
	 */
	public function note_exists( string $note_content ): bool {
		return array_any(
			wc_get_order_notes( array( 'order_id' => $this->order->get_id() ) ),
			function ($comment) use ( $note_content ) {
				return $note_content === $comment->content;
			}
		);
	}
}
