<?php
/**
 * Class OrderItemsGenerator
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

/**
 * Class OrderItemsGenerator
 */
class OrderItemsGenerator {
	private bool $include_tax;

	private bool $only_not_settled;

	private OrderGenerator $order_generator;

	private array $order_items = array();

	public function __construct( OrderGenerator $order_generator, array $options ) {
		$this->order_generator = $order_generator;

		$this->set_options( $options );
	}

	public function get_order_items(): array {
		return $this->order_items;
	}

	public function set_options( array $options ) {
		$options = wp_parse_args( $options, array(
			'include_tax'      => false,
			'only_not_settled' => false,
		) );

		$this->include_tax = $options['include_tax'];
		update_option( 'woocommerce_calc_taxes', $options['include_tax'] ? 'yes' : 'no' );
		update_option( 'woocommerce_prices_include_tax', $options['include_tax'] ? 'yes' : 'no' );

		$this->only_not_settled = $options['only_not_settled'];
	}

	public function generate_line_item( array $options = array() ) {
		$options = wp_parse_args( $options, array(
			'type'            => 'simple',
			'name'            => 'Product #' . rand( 1000000, 9999999 ),
			'quantity'        => rand( 1, 10 ),
			'price'           => rand( 1, 99999 ) / 100,
			'tax'             => rand( 0, 9999 ) / 100,
			'product_meta'    => array(),
			'order_item_meta' => array(),
		) );

		if ( ! $this->include_tax ) {
			$options['tax'] = 0;
		}

		$order_item_id = $this->order_generator->add_product(
			$options['type'],
			array_merge(
				array(
					'name'          => $options['name'],
					'regular_price' => $options['price']
				),
				$options['product_meta']
			),
			array_merge(
				array(
					'quantity' => $options['quantity'],
					'taxes'    => array(
						'total'    => [ $options['tax'] * $options['quantity'] ],
						'subtotal' => [ $options['tax'] * $options['quantity'] ]
					)
				),
				$options['order_item_meta']
			)
		);

		$order_item = $this->order_generator->order()->get_item( $order_item_id, false );

		if ( ! $this->only_not_settled || empty( $order_item->get_meta( 'settled' ) ) ) {
			$this->order_items[] = array(
				'ordertext'       => $options['name'],
				'quantity'        => $options['quantity'],
				'amount'          => rp_prepare_amount(
					$options['price'] + $options['tax'],
					$this->order_generator->order()->get_currency()
				),
				'vat'             => round( $options['tax'] / $options['price'], 2 ),
				'amount_incl_vat' => $this->include_tax
			);
		}
	}

	/**
	 * @todo complete function
	 */
	public function generate_shipping_item( array $options = array() ) {
		$options = wp_parse_args( $options, array(
			'name'     => 'Shipping #' . rand( 1000000, 9999999 ),
			'price'    => rand( 1, 99999 ) / 100,
			'tax'      => rand( 0, 9999 ) / 100,
		) );

		if ( ! $this->include_tax ) {
			$options['tax'] = 0;
		}

		if ( ! empty( $options['tax'] ) ) {
			$this->order_generator->add_tax( $options['tax'] );
		}

		$order_item_id = $this->order_generator->add_shipping( array(
			'method_title' => $options['name'],
			'total'        => $options['price'],
			'taxes'    => array(
				'total'    => [ $options['tax'] ],
				'subtotal' => [ $options['tax'] ]
			)
		) );

		$order_item = $this->order_generator->order()->get_item( $order_item_id, false );

		if ( ! $this->only_not_settled || empty( $order_item->get_meta( 'settled' ) ) ) {
			$this->order_items[] = array(
				'ordertext'       => $options['name'],
				'quantity'        => 1,
				'amount'          => rp_prepare_amount(
					$options['price'] + $options['tax'],
					$this->order_generator->order()->get_currency()
				),
				'vat'             => round( $options['tax'] / $options['price'], 2 ),
				'amount_incl_vat' => $this->include_tax
			);
		}
	}
}
