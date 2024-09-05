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
		'vipps',
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
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Vipps MobilePay', 'reepay-checkout-gateway' );
		$this->method_description = '<span style="color:red">' . __( 'The new Vipps MobilePay payment method, which utilizes bank transfers instead of card payments, will replace the old MobilePay Online payment method. Please refer to Vipps MobilePay for more efficient transactions and a better conversion rate.', 'reepay-checkout-gateway' ) . '</span>';
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = $this->logos;
		$this->supported_currencies = array( 'DKK', 'EUR', 'NOK' );

		parent::__construct();

		$this->apply_parent_settings();

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'exclude_payment_gateway_based_on_currency' ) );
	}
}
