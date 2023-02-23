<?php

use Automattic\WooCommerce\Blocks\Assets\Api as AssetApi;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Reepay payment methods integration
 *
 */
final class Reepay_Woo_Blocks_Payment_Method extends AbstractPaymentMethodType {
	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * An instance of the Asset Api
	 *
	 * @var AssetApi
	 */
	private $asset_api;

	/**
	 * Constructor
	 *
	 * @param  string    $name       Payment method name/id/slug.
	 * @param  AssetApi  $asset_api  An instance of assets Api.
	 */
	public function __construct( string $name, AssetApi $asset_api ) {
		$this->name      = $name;
		$this->asset_api = $asset_api;
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		if ( ! $this->is_active() ) {
			return [];
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$handle = "wc-payment-method-{$this->name}";

		/**
		 * Filters the list of script dependencies.
		 *
		 * @param array $dependencies The list of script dependencies.
		 * @param string $handle The script's handle.
		 * @return array
		 */
		$script_dependencies = apply_filters( 'woocommerce_blocks_register_script_dependencies', [], $handle );

		wp_register_script(
			$handle,
			plugin_dir_url( __FILE__ ) . "../../../assets/js/woo-blocks-register-payment-method$suffix.js?name={$this->name}",
			$script_dependencies,
			false,
			true
		);

		return [ $handle ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		];
	}
}
