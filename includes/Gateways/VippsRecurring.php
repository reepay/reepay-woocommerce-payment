<?php
/**
 * VippsRecurring gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Reepay\Checkout\Frontend\Assets;
use Reepay\Checkout\Utils\MetaField;

defined( 'ABSPATH' ) || exit();

/**
 * Class VippsRecurring
 *
 * @package Reepay\Checkout\Gateways
 */
class VippsRecurring extends ReepayGateway {
	public const ID = 'reepay_vipps_recurring';

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'vipps',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'vipps_recurring',
	);

	/**
	 * VippsRecurring constructor.
	 */
	public function __construct() {
		$this->id                   = self::ID;
		$this->has_fields           = true;
		$this->method_title         = __( 'Frisbii Pay - Vipps MobilePay Recurring', 'reepay-checkout-gateway' );
		$this->supports             = array(
			'products',
			'refunds',
			'add_payment_method',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);
		$this->logos                = array( 'vipps' );
		$this->supported_currencies = array( 'DKK', 'EUR', 'NOK' );

		parent::__construct();

		$this->apply_parent_settings();

		add_action( 'wp_ajax_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
		add_action( 'wp_ajax_nopriv_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );

		// Add filter for dynamic icon change based on currency.
		add_filter( 'woocommerce_gateway_icon', array( $this, 'change_gateway_icon_based_on_currency' ), 10, 2 );

		// Add filter to exclude payment gateway based on currency.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'exclude_payment_gateway_based_on_currency' ) );
	}



	/**
	 * If There are no payment fields show the description if set.
	 *
	 * @return void
	 */
	public function payment_fields() {
		parent::payment_fields();

		$this->tokenization_script();

		// Don't show saved cards if there are age restricted products in the cart.
		if ( ! $this->has_age_restricted_products_in_cart() ) {
			$this->saved_payment_methods();
		}

		$this->save_payment_method_checkbox();
	}

	/**
	 * Check if there are age restricted products in the cart
	 *
	 * @return bool True if there are age restricted products in cart
	 */
	private function has_age_restricted_products_in_cart(): bool {
		// Check if global age verification is enabled.
		if ( ! MetaField::is_global_age_verification_enabled() ) {
			return false;
		}

		// Get age restricted products from cart.
		$age_restricted_products = MetaField::get_age_restricted_products_in_cart();

		return ! empty( $age_restricted_products );
	}

	/**
	 * Change payment method icon base on currency
	 *
	 * @param string $icon payment method icon.
	 * @param int    $gateway_id payment method id.
	 *
	 * @return string
	 */
	public function change_gateway_icon_based_on_currency( $icon, $gateway_id ) {
		if ( self::ID === $gateway_id ) {
			$currency = get_woocommerce_currency();

			// Use MobilePay logo for DKK and EUR, Vipps logo for NOK.
			$logo_type = 'vipps'; // default.
			if ( in_array( $currency, array( 'DKK', 'EUR' ), true ) ) {
				$logo_type = 'mobilepay';
			}

			$logos = array_map(
				function () use ( $logo_type ) {
					$logo_url = $this->get_logo( $logo_type );
					return array(
						'src' => $logo_url,
						// translators: %s gateway title.
						'alt' => esc_attr( sprintf( __( 'Pay with %s on Frisbii Pay', 'reepay-checkout-gateway' ), $this->get_title() ) ),
					);
				},
				array_filter( (array) $this->logos, 'strlen' )
			);

			$icon = reepay()->get_template(
				'checkout/gateway-logos.php',
				array(
					'logos' => $logos,
				),
				true
			);
		}
		return $icon;
	}
}
