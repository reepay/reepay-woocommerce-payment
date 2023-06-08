<?php
/**
 * Class CartGenerator
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use WC_Cart;

/**
 * Class CartGenerator
 */
class CartGenerator {
	/**
	 * Current cart
	 *
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
	 * Create new cart with products
	 *
	 * @param string|string[] $product_types type of products to add to cart.
	 */
	public function new_cart( $product_types ) {
		if ( ! is_array( $product_types ) ) {
			$product_types = array( $product_types );
		}

		$this->empty_cart();

		foreach ( $product_types as $product_type ) {
			$this->add_product( $product_type );
		}

		$this->replace_global_cart();
	}

	/**
	 * Add product to cart
	 *
	 * @param string $type product type.
	 * @param array  $data product meta data.
	 */
	public function add_product( string $type, array $data = array() ) {
		$this->cart->add_to_cart( ( new ProductGenerator( $type, $data ) )->product()->get_id() );
	}

	/**
	 * Replace global cart with current
	 */
	public function replace_global_cart() {
		WC()->cart = $this->cart;
	}

	/**
	 * Clear cart
	 */
	public function empty_cart() {
		$this->cart = new WC_Cart();
	}

	/**
	 * Restore global cart
	 */
	public function restore_global_cart() {
		WC()->initialize_cart();
	}

	/**
	 * Get cart
	 *
	 * @return WC_Cart|null
	 */
	public function cart(): ?WC_Cart {
		return $this->cart;
	}
}
