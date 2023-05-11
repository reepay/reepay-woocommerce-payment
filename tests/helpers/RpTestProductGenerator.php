<?php

class RpTestProductGenerator {
	/**
	 * @var WC_Product|null
	 */
	private $product;

	/**
	 * RpTestProductGenerator constructor.
	 *
	 * @param string $type
	 *
	 * @throws Exception
	 */
	public function __construct( string $type = '' ) {
		if ( ! empty( $type ) ) {
			$this->generate( $type );
		}
	}

	/**
	 * Generate new product and maybe remove previous
	 *
	 * @param string $type
	 * @param bool   $remove_previous
	 *
	 * @throws Exception
	 */
	public function generate( string $type, bool $remove_previous = true ) {
		if ( $remove_previous ) {
			$this->delete();
		}

		$products = array(
			'simple'   => WC_Product_Simple::class,
//			'variable' => WC_Product_Variable::class,
			'woo_sub' => WC_Product_Subscription::class,
			'rp_sub'  => WC_Product_Reepay_Simple_Subscription::class,
		);

		if ( isset( $products[ $type ] ) ) {
			$this->product = class_exists( $products[ $type ] ) ? new $products[ $type ] : null;

			if ( ! empty( $this->product ) ) {
				$this->product->set_regular_price( 12.23 );
				$this->product->save();
			}
		} else {
			throw new Exception( 'Wrong prodct type' );
		}

		return $this->product;
	}

	/**
	 * @return WC_Product|null
	 */
	public function product(): ?WC_Product {
		return $this->product;
	}

	public function delete() {
		$this->product && $this->product->delete( true );
	}
}
