<?php

class RpTestCartGenerator {
	/**
	 * @var WC_Cart|null
	 */
	private ?WC_Cart $cart;

	/**
	 * @var RpTestProductGenerator[]
	 */
	private array $product_generators = array();

	/**
	 * RpTestCartGenerator constructor.
	 */
	public function __construct() {
		 $this->empty_cart();
	}

	/**
	 * @param string|string[] $product_types
	 *
	 * @throws Exception
	 */
	public function new_cart( $product_types ) {
		if ( ! is_array( $product_types ) ) {
			$product_types = array( $product_types );
		}

		$this->empty_cart();

		foreach ( $product_types as $product_type ) {
			$this->add_item( $product_type );
		}

		$this->replace_global_cart();
	}

	/**
	 * @throws Exception
	 */
	public function add_item( string $type ): RpTestCartGenerator {
		$product = ( new RpTestProductGenerator( $type ) )->product();

		$this->product_generators[] = $product;

		$this->cart->add_to_cart( $product );

		return $this;
	}

	public function replace_global_cart() {
		WC()->cart = $this->cart;

		return $this;
	}

	public function empty_cart() {
		$this->cart = new WC_Cart();

		foreach ( $this->product_generators as $product_generator ) {
			$product_generator->delete();
		}

		return $this;
	}

	public function restore_global_cart() {
		WC()->initialize_cart();
	}

	public function cart(): ?WC_Cart {
		return $this->cart;
	}
}
