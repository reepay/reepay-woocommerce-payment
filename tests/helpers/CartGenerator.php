<?php

namespace Reepay\Checkout\Tests\Helpers;

use WC_Cart;

class CartGenerator {
	/**
	 * @var WC_Cart|null
	 */
	private ?WC_Cart $cart;

	/**
	 * RpTestCartGenerator constructor.
	 */
	public function __construct() {
		 $this->empty_cart();
	}

	/**
	 * @param string|string[] $product_types
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

	public function add_item( string $type ): CartGenerator {
		$product_generator = ( new ProductGenerator( $type ) );

		$this->cart->add_to_cart( $product_generator->product()->get_id() );

		return $this;
	}

	public function replace_global_cart() {
		WC()->cart = $this->cart;

		return $this;
	}

	public function empty_cart() {
		$this->cart = new WC_Cart();

		return $this;
	}

	public function restore_global_cart() {
		WC()->initialize_cart();
	}

	public function cart(): ?WC_Cart {
		return $this->cart;
	}
}
