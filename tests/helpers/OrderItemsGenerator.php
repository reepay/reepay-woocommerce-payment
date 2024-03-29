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

	public function add_order_item( array $order_item ) {
		$this->order_items[] = $order_item;
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

		if ( 'rp_sub' !== $options['type'] &&
			 ( ! $this->only_not_settled || empty( $order_item->get_meta( 'settled' ) ) )
		) {
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
	 * @todo add tax
	 */
	public function generate_shipping_item( array $options = array() ) {
		$options = wp_parse_args( $options, array(
			'name'     => 'Shipping #' . rand( 1000000, 9999999 ),
			'price'    => rand( 1, 99999 ) / 100,
			'meta'	   => array()
		) );

		$order_item_id = $this->order_generator->add_shipping(
			array(
				'method_title' => $options['name'],
				'total'        => $options['price']
			),
			$options['meta']
		);

		$order_item = $this->order_generator->order()->get_item( $order_item_id, false );

		if ( ! $this->only_not_settled || empty( $order_item->get_meta( 'settled' ) ) ) {
			$this->order_items[] = array(
				'ordertext'       => $options['name'],
				'quantity'        => 1,
				'amount'          => rp_prepare_amount(
					$options['price'],
					$this->order_generator->order()->get_currency()
				),
				'vat'             => .0,
				'amount_incl_vat' => $this->include_tax
			);
		}
	}

	public function generate_fee_item( array $options = array() ) {
		$options = wp_parse_args( $options, array(
			'name'            => 'Fee #' . rand( 1000000, 9999999 ),
			'price'           => rand( 1, 99999 ) / 100,
			'tax'             => rand( 0, 9999 ) / 100,
			'meta'	          => array()
		) );

		if ( ! $this->include_tax ) {
			$options['tax'] = 0;
		}

		$order_item_id = $this->order_generator->add_fee(
			array(
				'name'      => $options['name'],
				'total'     => $options['price'],
				'total_tax' => $options['tax']
			),
			$options['meta']
		);

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

	public function add_total_discount( array $options = array() ) {
		$options = wp_parse_args( $options, array(
			'amount' => rand(20, 100),
			'tax' => rand( 10, 20 )
		) );

		if ( ! $this->include_tax ) {
			$options['tax'] = 0;
		}

		$this->order_generator->order()->set_discount_total( $options['amount'] );
		$this->order_generator->order()->set_discount_tax( $options['tax'] );

		$this->order_items[] = array(
			'ordertext'       => __( 'Discount', 'reepay-checkout-gateway' ),
			'quantity'        => 1,
			'amount'          => -1 * rp_prepare_amount(
				$options['amount'] + $options['tax'],
				$this->order_generator->order()->get_currency()
			),
			'vat'             => round( $options['tax'] / $options['amount'], 2 ),
			'amount_incl_vat' => $this->include_tax
		);
	}
}
