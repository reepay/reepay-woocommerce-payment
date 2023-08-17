<?php
/**
 * ApplePay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Reepay\Checkout\Frontend\Assets;

defined( 'ABSPATH' ) || exit();

/**
 * Class ApplePay
 *
 * @package Reepay\Checkout\Gateways
 */
class ApplePay extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'applepay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'applepay',
	);

	/**
	 * ApplePay constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_applepay';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Apple Pay', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'applepay' );

		parent::__construct();

		$this->apply_parent_settings();

		if ( 'yes' === $this->enabled ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_additional_assets' ), 10000 );
		}
	}

	/**
	 * Additional gateway assets
	 */
	public function enqueue_additional_assets() {
		wp_add_inline_script(
			Assets::SLUG_CHECKOUT_JS,
			"
			jQuery('body').on('updated_checkout', function () {
				if (true == Reepay.isApplePayAvailable()) {
					for (let element of document.getElementsByClassName('wc_payment_method payment_method_reepay_applepay')) {
						element.style.display = 'block';
					}
				}
			});
			"
		);
	}
}
