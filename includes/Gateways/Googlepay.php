<?php
/**
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Reepay\Checkout\Frontend\Assets;

defined( 'ABSPATH' ) || exit();

/**
 * Class Googlepay
 *
 * @package Reepay\Checkout\Gateways
 */
class Googlepay extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public $logos = array(
		'googlepay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'googlepay',
	);

	public function __construct() {
		$this->id           = 'reepay_googlepay';
		$this->has_fields   = true;
		$this->method_title = __( 'Reepay - Google Pay', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'googlepay' );

		parent::__construct();

		// Load setting from parent method
		$this->apply_parent_settings();

		if ( 'yes' === $this->enabled ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_additional_assets' ), 10000 );
		}
	}

	/**
	 * Initialise Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
				'type'    => 'gateway_status',
				'label'   => __( 'Status in reepay', 'reepay-checkout-gateway' ),
				'default' => $this->test_mode,
			),
			'enabled'              => array(
				'title'    => __( 'Enable/Disable', 'reepay-checkout-gateway' ),
				'type'     => 'checkbox',
				'label'    => __( 'Enable plugin', 'reepay-checkout-gateway' ),
				'default'  => 'no',
				'disabled' => ! $this->is_configured(),
			),
			'title'                => array(
				'title'       => __( 'Title', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => __( 'Reepay - Google Pay', 'reepay-checkout-gateway' ),
			),
			'description'          => array(
				'title'       => __( 'Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => __( 'Reepay - Google Pay', 'reepay-checkout-gateway' ),
			),
		);
	}

	/**
	 * Additional gateway assets
	 */
	public function enqueue_additional_assets() {
		wp_add_inline_script(
			Assets::SLUG_CHECKOUT_JS,
			"
			jQuery('body').on('updated_checkout', function () {
				Reepay.isGooglePayAvailable().then(isAvailable => {
					if (true == isAvailable) {
						for (let element of document.getElementsByClassName('wc_payment_method payment_method_reepay_googlepay')) {
							element.style.display = 'block';
						}
					}
				});
			});
			"
		);
	}
}
