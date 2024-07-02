<?php
/**
 * PP WeChatPay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPWeChatPay
 *
 * @package Reepay\Checkout\Gateways
 */
class PPWeChatPay extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'wechatpay',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_wechatpay',
	);

	/**
	 * PPWeChatPay constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pp_wechatpay';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - WeChat Pay', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
