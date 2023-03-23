<?php

namespace Reepay\Checkout\Integrations\WooBlocks;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Blocks\Registry\Container;
use Exception;
use Reepay\Checkout\Gateways;
use WC_Reepay_Log;

defined( 'ABSPATH' ) || exit;

class WooBlocksIntegration {
	use WC_Reepay_Log;

	/**
	 * @var string
	 */
	private $logging_source = 'WooBlocksIntegration';

	public function __construct() {
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_payment_method_integrations' ) );
	}

	/**
	 * Register payment method integrations bundled with blocks.
	 *
	 * @param PaymentMethodRegistry $payment_method_registry Payment method registry instance.
	 */
	public function register_payment_method_integrations( PaymentMethodRegistry $payment_method_registry ) {
		foreach (Gateways::PAYMENT_METHODS as $payment_method) {
			Package::container()->register(
				$payment_method,
				function( Container $container ) use ( $payment_method ){
					return new WooBlocksPaymentMethod( $payment_method );
				}
			);

			try {
				$payment_method_registry->register(
					Package::container()->get( $payment_method )
				);
			} catch (Exception $e) {
				//$this->log( $e->getMessage(), 'error' );
			}
		}
	}
}