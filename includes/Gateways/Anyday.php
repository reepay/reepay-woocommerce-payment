<?php
/**
 * Anyday gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class Anyday
 *
 * @package Reepay\Checkout\Gateways
 */
class Anyday extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'anyday',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'anyday',
	);

	/**
	 * Anyday constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_anyday';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Anyday', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'anyday' );

		parent::__construct();

		$this->apply_parent_settings();
	}

	/**
	 * This payment method works only for cart with total more than 300 DKK
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		if ( ! empty( WC()->cart )
			 && WC()->cart->get_total( '' ) < 300
			 && get_option( 'woocommerce_currency' ) !== 'DKK' ) {
			return false;
		}

		return parent::is_available();
	}
}
