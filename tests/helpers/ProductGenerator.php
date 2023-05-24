<?php

namespace Reepay\Checkout\Tests\Helpers;

use Exception;
use WC_Product;
use WC_Product_Reepay_Simple_Subscription;
use WC_Product_Simple;
use WC_Product_Subscription;

class ProductGenerator {
	/**
	 * @var WC_Product|null
	 */
	private ?WC_Product $product = null;

	/**
	 * RpTestProductGenerator constructor.
	 *
	 * @param string $type
	 * @param array       $data
	 *
	 * @throws Exception
	 */
	public function __construct( string $type = '', array $data = array() ) {
		if ( ! empty( $type ) ) {
			$this->generate( $type, $data );
		}
	}

	/**
	 * Generate new product and maybe remove previous
	 *
	 * @param string $type
	 * @param array  $data
	 *
	 * @return WC_Product|null
	 */
	public function generate( string $type, array $data = array() ): ?WC_Product {
		$products = array(
			'simple'  => WC_Product_Simple::class,
			'woo_sub' => WC_Product_Subscription::class,
			'rp_sub'  => WC_Product_Reepay_Simple_Subscription::class,
		);

		if ( isset( $products[ $type ] ) ) {
			$this->product = class_exists( $products[ $type ] ) ? new $products[ $type ] : null;

			if ( ! empty( $this->product ) ) {
				$this->product->set_regular_price( 12.23 );
				$this->product->set_props($data);

				$this->product->save();
			}
		} else {
			throw new Exception( 'Wrong product type' );
		}

		return $this->product;
	}

	/**
	 * @return WC_Product|null
	 */
	public function product(): ?WC_Product {
		return $this->product;
	}
}
