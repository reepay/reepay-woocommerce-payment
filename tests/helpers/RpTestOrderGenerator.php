<?php

class RpTestOrderGenerator {
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

	/**
	 * Add simple product to order
	 *
	 * @param array $data product meta data.
	 *
	 * @return int order item id
	 */
	public function add_simple_product( array $data = array() ): int {
		return $this->order->add_product(
			( new RpTestProductGenerator( 'simple', $data ) )->product()
		);
	}

	/**
	 * Add woocommerce subscription product to order
	 *
	 * @param array $data product meta data.
	 *
	 * @return int order item id
	 */
	public function add_woo_sub_product( array $data = array() ): int {
		return $this->order->add_product(
			( new RpTestProductGenerator( 'woo_sub', $data ) )->product()
		);
	}

	/**
	 * Add reepay subscription product to order
	 *
	 * @param array $data product meta data.
	 *
	 * @return int order item id
	 */
	public function add_rp_sub_product( array $data = array() ): int {
		return $this->order->add_product(
			( new RpTestProductGenerator( 'rp_sub', $data ) )->product()
		);
	}
}
