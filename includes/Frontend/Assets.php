<?php
/**
 * @package Reepay\Checkout\Frontend
 */

namespace Reepay\Checkout\Frontend;

defined( 'ABSPATH' ) || exit();

/**
 * Class Assets
 *
 * @package Reepay\Checkout\Frontend
 */
class Assets {
	const SLUG_CHECKOUT_JS = 'wc-gateway-reepay-checkout';

	const SLUG_REEPAY_CDN_JS = 'reepay-checkout';

	const SLUG_GLOBAL_CSS = 'wc-gateway-reepay-checkout';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_payment_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_payment_assets' ) );
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
			self::SLUG_GLOBAL_CSS,
			reepay()->get_setting( 'css_url' ) . 'style' . $suffix . '.css',
			array()
		);

		$logo_height = reepay()->get_setting( 'logo_height' );
		if ( ! empty( $logo_height ) ) {

			if ( is_numeric( $logo_height ) ) {
				$logo_height .= 'px';
			}
			wp_add_inline_style(
				self::SLUG_GLOBAL_CSS,
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
			self::SLUG_REEPAY_CDN_JS,
			reepay()->get_setting( 'js_url' ) . 'checkout-cdn.js',
			array()
		);

		wp_register_script(
			self::SLUG_CHECKOUT_JS,
			reepay()->get_setting( 'js_url' ) . 'checkout' . $suffix . '.js',
			array(
				'jquery',
//				'wc-checkout',
				self::SLUG_REEPAY_CDN_JS,
			),
			filemtime( reepay()->get_setting( 'js_path' ) . 'checkout' . $suffix . '.js' ),
			true
		);

		wp_localize_script(
			self::SLUG_CHECKOUT_JS,
			'WC_Gateway_Reepay_Checkout',
			reepay()->gateways()->checkout()->get_localize_script_data()
		);

		if ( ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() )
			 && ! is_order_received_page()
		) {
			wp_enqueue_script( self::SLUG_REEPAY_CDN_JS );
			wp_enqueue_script( self::SLUG_CHECKOUT_JS );
		}
	}
}
