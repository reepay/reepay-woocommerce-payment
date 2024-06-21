<?php
/**
 * PP Multibanco gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Billwerk\Sdk\Enum\AgreementTypeEnum;

defined( 'ABSPATH' ) || exit();

/**
 * Class PPMultibanco
 *
 * @package Reepay\Checkout\Gateways
 */
class PPMultibanco extends ReepayGateway {
	public const ID = 'reepay_' . AgreementTypeEnum::PP_MULTIBANCO;

	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'multibanco',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pp_multibanco',
	);

	/**
	 * PPMultibanco constructor.
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ - Multibanco', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

		parent::__construct();

		$this->apply_parent_settings();
	}
}
