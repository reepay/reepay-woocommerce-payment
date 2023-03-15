<?php

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
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	protected $support_cards = array(
		'reepay_checkout',
	);

	/**
	 * Constructor
	 *
	 * @param  string  $name  Payment method name/id/slug.
	 *
	 * @throws Exception
	 */
	public function __construct( string $name ) {
		$this->name    = $name;
		$this->gateway = WC()->payment_gateways()->get_available_payment_gateways()[ $this->name ] ?? null;

		if ( is_null( $this->gateway ) ) {
			throw new Exception( "Gateway '{$this->name}' not found" );
		}
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
		if ( ! $this->is_active() || is_admin() ) {
			return [];
		}

		static $gateway_scripts_initialized = false;

		if ( ! $gateway_scripts_initialized ) {
			/** @var WC_Gateway_Reepay $gateway */
			$gateway = WC()->payment_gateways()->get_available_payment_gateways()[ $this->name ] ?? null;

			if ( empty( $gateway ) ) {
				return [];
			}

			$gateway->enqueue_payment_scripts();

			$gateway_scripts_initialized = true;

			wp_enqueue_style(
				'wc-reepay-blocks',
				plugin_dir_url( __FILE__ ) . "../../../assets/dist/css/woo_blocks.css",
			);
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$handle = "wc-reepay-blocks-payment-method-{$this->name}";

		/**
		 * Filters the list of script dependencies.
		 *
		 * @param  array   $dependencies  The list of script dependencies.
		 * @param  string  $handle        The script's handle.
		 *
		 * @return array
		 */
		$script_dependencies = apply_filters( 'woocommerce_blocks_register_script_dependencies', [ 'wc-gateway-reepay-checkout' ], $handle );

		wp_register_script(
			$handle,
			plugin_dir_url( __FILE__ ) . "../../../assets/dist/js/woo-blocks$suffix.js?name={$this->name}",
			$script_dependencies,
			false,
			true
		);

		return [ $handle ];
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		/** @var WC_Gateway_Reepay $gateway */
		$gateway = WC()->payment_gateways()->get_available_payment_gateways()[ $this->name ] ?? null;

		if ( !empty( $gateway ) ) {
			$features= $gateway->supports;
		} else {
			$features = [ 'products' ];
		}

		if ( in_array( $this->name, $this->support_cards ) ) {
			$features[] = 'cards';
		}

		return $features;
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$data = [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		];


		if ( in_array( 'cards', $data['supports'] ) ) {
			$tokens = $this->gateway->get_tokens();

			if ( ! empty( $tokens ) ) {
				$data['tokens'] = [];

				foreach ( $this->gateway->get_tokens() as $token ) {
					if ( $token instanceof WC_Payment_Token_Reepay ) {
						/** @var WC_Payment_Token_Reepay $token */
						$data['tokens'][] = [
							'id'           => $token->get_id(),
							'expiry_month' => $token->get_expiry_month(),
							'expiry_year'  => $token->get_expiry_year(),
							'masked'       => $token->get_masked_card(),
							'type'         => $token->get_card_type(),
							'image'        => $token->get_card_image_url(),
							'image_alt'    => wc_get_credit_card_type_label( $token->get_card_type() ),
							'is_default'   => checked( $token->is_default(), true, false ),
						];
					}
				}

				$default_token = WC_Payment_Tokens::get_customer_default_token( WC()->cart->get_customer()->get_id() );

				if(!empty($default_token)) {
					$data['default_token'] = $default_token->get_id();
				} else {
					$data['default_token'] = $data['tokens'][0]['id'];
				}
			} else {
				$data['default_token'] = 'new';
			}
		}

		$data = apply_filters( 'reepay_blocks_payment_method_data', $data, $this );
		return apply_filters( 'reepay_blocks_payment_method_data_' . $this->name, $data, $this );
	}
}
