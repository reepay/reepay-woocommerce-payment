<?php
/**
 * Resurs gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class Resurs
 *
 * @package Reepay\Checkout\Gateways
 */
class Resurs extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::RESURS;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'resurs',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'resurs',
	);

	/**
	 * Resurs constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Resurs Bank', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
