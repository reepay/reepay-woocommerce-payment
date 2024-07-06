<?php
/**
 * Paypal gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class Paypal
 *
 * @package Reepay\Checkout\Gateways
 */
class Paypal extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PAYPAL;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'paypal',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'paypal',
	);

	/**
	 * Paypal constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - PayPal', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
