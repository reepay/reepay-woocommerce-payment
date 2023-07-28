<?php
/**
 * Gateway wrapper for woocommerce checkout block integration
 *
 * @package Reepay\Checkout\Integrations\WooBlocks
 */

namespace Reepay\Checkout\Integrations\WooBlocks;

defined( 'ABSPATH' ) || exit();

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Exception;
use Reepay\Checkout\Frontend\Assets;
use Reepay\Checkout\Tokens\TokenReepay;
use Reepay\Checkout\Gateways\ReepayGateway;
use WC_Payment_Gateway;
use WC_Payment_Tokens;

/**
 * Reepay payment methods integration
 */
final class WooBlocksPaymentMethod extends AbstractPaymentMethodType {
	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Wrapped gateway
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Array of reepay gateways supporting card payment
	 *
	 * @var string[]
	 */
	protected array $support_cards = array(
		'reepay_checkout',
	);

	/**
	 * Constructor
	 *
	 * @param string $name Payment method name/id/slug.
	 *
	 * @throws Exception If gateway not exist.
	 */
	public function __construct( string $name ) {
		$this->name    = $name;
		$this->gateway = reepay()->gateways()->get_gateway( $this->name );

		if ( is_null( $this->gateway ) ) {
			throw new Exception( "Gateway '$this->name' not found" );
		}
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active(): bool {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$handle = "wc-reepay-blocks-payment-method-$this->name";

		/**
		 * Filters the list of script dependencies.
		 *
		 * @param array  $dependencies The list of script dependencies.
		 * @param string $handle       The script's handle.
		 *
		 * @return array
		 */
		$script_dependencies = apply_filters( 'woocommerce_blocks_register_script_dependencies', array( Assets::SLUG_CHECKOUT_JS ), $handle );

		wp_register_script(
			$handle,
			reepay()->get_setting( 'js_url' ) . "woo-blocks$suffix.js?name=$this->name",
			$script_dependencies,
			reepay()->get_setting( 'plugin_version' ),
			true
		);

		return array( $handle );
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features(): array {
		$gateway = reepay()->gateways()->get_gateway( $this->name );

		if ( ! empty( $gateway ) ) {
			$features = $gateway->supports;
		} else {
			$features = array( 'products' );
		}

		if ( in_array( $this->name, $this->support_cards, true ) ) {
			$features[] = 'cards';
		}

		return $features;
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$data = array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
			'cssPath'     => reepay()->get_setting( 'css_url' ) . 'woo_blocks' . $suffix . '.css?ver=' . reepay()->get_setting( 'plugin_version' ),
		);

		if ( in_array( 'cards', $data['supports'], true ) ) {
			$tokens = $this->gateway->get_tokens();

			if ( ! empty( $tokens ) ) {
				$data['tokens'] = array();

				foreach ( $this->gateway->get_tokens() as $token ) {
					if ( $token instanceof TokenReepay ) {
						$data['tokens'][] = array(
							'id'           => $token->get_id(),
							'expiry_month' => $token->get_expiry_month(),
							'expiry_year'  => $token->get_expiry_year(),
							'masked'       => $token->get_masked_card(),
							'type'         => $token->get_card_type(),
							'image'        => $token->get_card_image_url(),
							'image_alt'    => wc_get_credit_card_type_label( $token->get_card_type() ),
							'is_default'   => checked( $token->is_default(), true, false ),
						);
					}
				}

				if ( ! empty( WC()->cart ) && ! empty( WC()->cart->get_customer() ) ) {
					$default_token = WC_Payment_Tokens::get_customer_default_token( WC()->cart->get_customer()->get_id() );
				}

				if ( ! empty( $default_token ) ) {
					$data['default_token'] = $default_token->get_id();
				} elseif ( ! empty( $data['tokens'] ) ) {
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
