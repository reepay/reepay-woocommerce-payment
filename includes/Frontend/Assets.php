<?php

namespace Reepay\Checkout\Frontend;

defined( 'ABSPATH' ) || exit();

class Assets {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_payment_assets' ) );
	}

	/**
	 * enqueue_payment_assets function.
	 *
	 * Outputs scripts used for payment
	 *
	 * @return void
	 */
	public function enqueue_payment_assets() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wc-gateway-reepay-checkout',
			reepay()->get_setting( 'assets_url' ) . 'css/style' . $suffix . '.css',
			array()
		);

		$logo_height = reepay()->get_setting( 'logo_height' );
		if ( ! empty( $logo_height ) ) {

			if ( is_numeric( $logo_height ) ) {
				$logo_height .= 'px';
			}
			wp_add_inline_style(
				'wc-gateway-reepay-checkout',
				"
                    #payment .wc_payment_method > label:first-of-type img {
                        height: $logo_height;
                        max-height: $logo_height;
                        list-style: none;
                    }
                "
			);
		}

		wp_register_script(
			'reepay-checkout',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/dist/js/checkout-cdn.js',
			array()
		);

		wp_register_script(
			'wc-gateway-reepay-checkout',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/dist/js/checkout' . $suffix . '.js',
			array(
				'jquery',
				'wc-checkout',
				'reepay-checkout',
			),
			filemtime( REEPAY_CHECKOUT_PLUGIN_PATH . 'assets/dist/js/checkout' . $suffix . '.js' ),
			true
		);

		wp_localize_script(
			'wc-gateway-reepay-checkout',
			'WC_Gateway_Reepay_Checkout',
			reepay()->gateways()->checkout()->get_localize_script_data()
		);

		if ( ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() )
		     && ! is_order_received_page()
		) {
			wp_enqueue_script( 'reepay-checkout' );
			wp_enqueue_script( 'wc-gateway-reepay-checkout' );
		}
	}
}