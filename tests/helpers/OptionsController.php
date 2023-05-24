<?php

namespace Reepay\Checkout\Tests\Helpers;

use Reepay\Checkout\Gateways\ReepayCheckout;

class OptionsController {
	/**
	 * @var ReepayCheckout|null
	 */
	private ?ReepayCheckout $reepay_gateway;

	private bool $reset = true;

	/**
	 * RpTestOptions constructor.
	 */
	public function __construct() {
		$this->reepay_gateway = reepay()->gateways()->checkout();
	}

	public function set_option( string $key, $value ): OptionsController {
		$this->reepay_gateway->update_option( $key, $value );

		if ( $this->reset ) {
			reepay()->reset_settings();
		}

		return $this;
	}

	public function set_options( array $options ) {
		$this->reset = false;

		foreach ( $options as $key => $value ) {
			$this->set_option( $key, $value );
		}

		reepay()->reset_settings();
		$this->reset = true;
	}

	public function get_option( string $key ) {
		return reepay()->get_setting( $key );
	}
}
