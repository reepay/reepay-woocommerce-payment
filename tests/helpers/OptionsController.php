<?php
/**
 * Class OptionsController
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\OrderFlow\OrderStatuses;

/**
 * Class OptionsController
 */
class OptionsController {
	/**
	 * ReepayCheckout gateway to get options
	 *
	 * @var ReepayCheckout|null
	 */
	private ?ReepayCheckout $reepay_gateway;

	/**
	 * Settings reset after set_option
	 *
	 * @var bool
	 */
	private bool $reset = true;

	/**
	 * RpTestOptions constructor.
	 */
	public function __construct() {
		$this->reepay_gateway = reepay()->gateways()->checkout();
	}

	/**
	 * Set option and reset settings
	 *
	 * @param string $key option key.
	 * @param mixed  $value option value.
	 *
	 * @return $this
	 */
	public function set_option( string $key, $value ): OptionsController {
		$this->reepay_gateway->update_option( $key, $value );

		if ( $this->reset ) {
			reepay()->reset_settings();
		}

		return $this;
	}

	/**
	 * Set multiple options and reset settings
	 *
	 * @param array $options options to set.
	 */
	public function set_options( array $options ) {
		$this->reset = false;

		foreach ( $options as $key => $value ) {
			$this->set_option( $key, $value );
		}

		reepay()->reset_settings();
		OrderStatuses::init_statuses();

		$this->reset = true;
	}

	/**
	 * Get Reepay Checkout option
	 *
	 * @param string $key setting key.
	 *
	 * @return string|string[]|null
	 */
	public function get_option( string $key ) {
		return reepay()->get_setting( $key );
	}
}
