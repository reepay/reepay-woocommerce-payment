<?php
/**
 * VippsMobilepay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class VippsMobilepay
 *
 * @package Reepay\Checkout\Gateways
 */
class VippsMobilepay extends ReepayGateway {
	public const ID = 'reepay_vipps_epayment';

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'mobilepay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'vipps_epayment',
	);

	/**
	 * VippsMobilepay constructor.
	 */
	public function __construct() {
		$this->id                   = self::ID;
		$this->has_fields           = true;
		$this->method_title         = __( 'Billwerk+ Pay - Vipps MobilePay', 'reepay-checkout-gateway' );
		$this->method_description   = '<span style="color:red">' . __( 'The new Vipps MobilePay payment method, which utilizes bank transfers instead of card payments, will replace the old MobilePay Online payment method. Please refer to Vipps MobilePay for more efficient transactions and a better conversion rate.', 'reepay-checkout-gateway' ) . '</span>';
		$this->supports             = array(
			'products',
			'refunds',
		);
		$this->logos                = $this->logos;
		$this->supported_currencies = array( 'DKK', 'EUR', 'NOK' );

		$this->init_form_fields();
		$this->check_is_active();

		parent::__construct();

		$this->apply_parent_settings();

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'exclude_payment_gateway_based_on_currency' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'change_gateway_title_based_on_currency' ), 10, 2 );
		add_filter( 'woocommerce_gateway_description', array( $this, 'change_gateway_description_based_on_currency' ), 10, 2 );
		add_filter( 'woocommerce_gateway_icon', array( $this, 'change_gateway_icon_based_on_currency' ), 10, 2 );
	}

	/**
	 * Check if payment method activated in reepay
	 *
	 * @return bool
	 */
	public function check_is_active(): bool {

		$current_name = str_replace( 'reepay_', '', self::ID );

		if ( 'vipps_epayment' === $current_name ) {
			return true;
		}

		return false;
	}

	/**
	 * Initialise default settings form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in Billwerk+ Pay', 'reepay-checkout-gateway' ),
				'type'    => 'gateway_status',
				'label'   => __( 'Status in Billwerk+ Pay', 'reepay-checkout-gateway' ),
				'default' => $this->test_mode,
			),
			'enabled'              => array(
				'title'    => __( 'Enable/Disable', 'reepay-checkout-gateway' ),
				'type'     => 'checkbox',
				'label'    => __( 'Enable plugin', 'reepay-checkout-gateway' ),
				'default'  => 'no',
				'disabled' => $this->is_gateway_settings_page() && ! $this->check_is_active(), // Check calls api, so use it only on gateway page.
			),
			'title'                => array(
				'title'       => __( 'DKK, EUR Title', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title that the user sees during checkout when using currency DKK or EUR.', 'reepay-checkout-gateway' ),
				'default'     => __( 'Mobilepay', 'reepay-checkout-gateway' ),
			),
			'description'          => array(
				'title'       => __( 'DKK, EUR Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title that the user sees during checkout when using currency DKK or EUR.', 'reepay-checkout-gateway' ),
				'default'     => __( 'Mobilepay', 'reepay-checkout-gateway' ),
			),
			'title_nok'            => array(
				'title'       => __( 'NOK Title', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title that the user sees during checkout when using currency NOK.', 'reepay-checkout-gateway' ),
				'default'     => __( 'Vipps', 'reepay-checkout-gateway' ),
			),
			'description_nok'      => array(
				'title'       => __( 'NOK Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title that the user sees during checkout when using currency NOK.', 'reepay-checkout-gateway' ),
				'default'     => __( 'Vipps', 'reepay-checkout-gateway' ),
			),
		);
	}

	/**
	 * Change payment method title base on currency
	 *
	 * @param string $title payment method title.
	 * @param int    $gateway_id payment method id.
	 *
	 * @return string
	 */
	public function change_gateway_title_based_on_currency( $title, $gateway_id ) {
		if ( self::ID === $gateway_id ) {
			$currency         = get_woocommerce_currency();
			$gateway_settings = get_option( 'woocommerce_' . $gateway_id . '_settings' );
			if ( 'NOK' === $currency ) {
				$title = $gateway_settings['title_nok'];
			}
		}
		return $title;
	}

	/**
	 * Change payment method description base on currency
	 *
	 * @param string $description payment method description.
	 * @param int    $gateway_id payment method id.
	 *
	 * @return string
	 */
	public function change_gateway_description_based_on_currency( $description, $gateway_id ) {
		if ( self::ID === $gateway_id ) {
			$currency         = get_woocommerce_currency();
			$gateway_settings = get_option( 'woocommerce_' . $gateway_id . '_settings' );
			if ( 'NOK' === $currency ) {
				$description = $gateway_settings['description_nok'];
			}
		}
		return $description;
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
			if ( 'NOK' === $currency ) {
				$logos = array_map(
					function () {
						$logo_url         = $this->get_logo( 'vipps' );
						$gateway_settings = get_option( 'woocommerce_' . self::ID . '_settings' );
						return array(
							'src' => $logo_url,
							// translators: %s gateway title.
							'alt' => esc_attr( sprintf( __( 'Pay with %s on Billwerk+ Pay', 'reepay-checkout-gateway' ), $gateway_settings['title_nok'] ) ),
						);
					},
					array_filter( (array) $this->logos, 'strlen' )
				);
				$icon  = reepay()->get_template(
					'checkout/gateway-logos.php',
					array(
						'logos' => $logos,
					),
					true
				);
			}
		}
		return $icon;
	}
}
