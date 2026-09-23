<?php
/**
 * Klarna gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Reepay\Checkout\Utils\MetaField;

defined( 'ABSPATH' ) || exit();

/**
 * Class Klarna
 *
 * @package Reepay\Checkout\Gateways
 */
class Klarna extends ReepayGateway {
	public const ID = 'reepay_klarna';

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'klarna',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'klarna',
	);

	/**
	 * Klarna constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Frisbii Pay - Klarna', 'reepay-checkout-gateway' );
		$this->supports     = array(
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
		$this->logos        = array( 'klarna' );

		parent::__construct();

		$this->apply_parent_settings();

		add_action( 'wp_ajax_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
		add_action( 'wp_ajax_nopriv_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
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
	 * Whether the "Enable/Disable" checkbox is unlocked on the gateway settings page.
	 *
	 * Parent implementation calls the Frisbii `/agreement` API and disables the
	 * checkbox unless the merchant account has an active Klarna agreement. On
	 * localhost that account-level activation isn't available for testing, so
	 * bypass the check for localhost only — this must not reach production.
	 *
	 * @return bool
	 */
	public function check_is_active(): bool {
		if ( $this->is_localhost() ) {
			return true;
		}

		return parent::check_is_active();
	}

	/**
	 * Detect development environment by host name.
	 *
	 * @return bool
	 */
	private function is_localhost(): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return in_array( $host, array( 'localhost', '127.0.0.1', 'frisbii-paydev.radarsofthouse.com','frisbii-woodev.radarsofthouse.com' ), true );
	}
}
