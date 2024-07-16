<?php
/**
 * PE Santander gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Reepay\Checkout\Frontend\Assets;

defined( 'ABSPATH' ) || exit();

/**
 * Class PESantander
 *
 * @package Reepay\Checkout\Gateways
 */
class PESantander extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'santander',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'pe_santander',
	);

	/**
	 * PESantander constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_pe_santander';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Santander', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
		);

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
			(function($) {
            	$(document).ready(function() {
					let payment_type = WC_Gateway_Reepay_Checkout.payment_type;

					setTimeout(function() {
						$('[name=radio-control-wc-payment-method-options], [name=payment_method]').each(function() {
							if( '" . $this->id . "' === $(this).val() && $(this).is(':checked') ){
								WC_Gateway_Reepay_Checkout.payment_type = 'WINDOW';
							}
						});
					}, 3000);

					// Script for WC Block
					$('body').on('click', '[name=radio-control-wc-payment-method-options]', function() {
						let option_value = $(this).val();
						if( '" . $this->id . "' === option_value ){
							WC_Gateway_Reepay_Checkout.payment_type = 'WINDOW';
						}else{
							WC_Gateway_Reepay_Checkout.payment_type = payment_type;
						}
					});

					// Script for short code check out
					$('body').on('click', '.wc_payment_method', function() {
						let option_value = $(this).find('.input-radio').val();
						if( '" . $this->id . "' === option_value ){
							WC_Gateway_Reepay_Checkout.payment_type = 'WINDOW';
						}else{
							WC_Gateway_Reepay_Checkout.payment_type = payment_type;
						}
					});
				});
			})(jQuery);
			"
		);
	}
}
