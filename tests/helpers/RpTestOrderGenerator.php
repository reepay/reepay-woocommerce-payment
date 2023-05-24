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

	public function order(): ?WC_Order {
		return $this->order;
	}

	public function add_simple_product( array $data = array() ) {
		$this->order->add_product(
			( new RpTestProductGenerator( 'simple', $data ) )->product()
		);
	}

	public function add_variable_product( array $data = array() ) {
		$this->order->add_product(
			( new RpTestProductGenerator( 'variable', $data ) )->product()
		);
	}

	public function add_woocommerce_subscription_product( array $data = array() ) {
		$this->order->add_product(
			( new RpTestProductGenerator( 'woo_sub', $data ) )->product()
		);
	}
}
