<?php
/**
 * Mobilepay gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class Mobilepay
 *
 * @package Reepay\Checkout\Gateways
 */
class Mobilepay extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::MOBILEPAY;

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
		'mobilepay',
	);

	/**
	 * Mobilepay constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Mobilepay', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
