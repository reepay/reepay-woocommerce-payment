<?php
/**
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Class Main
 *
 * @package Reepay\Checkout\Admin
 */
class Main {
	public function __construct() {
		new PluginsPage();
		new Ajax();
		new MetaBoxes();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( $hook === 'post.php' ) {
			// Scripts
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_script(
				'reepay-js-input-mask',
				reepay()->get_setting( 'js_url' ) . 'jquery.inputmask' . $suffix . '.js',
				array( 'jquery' ),
				'5.0.3'
			);

			wp_register_script(
				'reepay-admin-js',
				reepay()->get_setting( 'js_url' ) . 'admin' . $suffix . '.js',
				array(
					'jquery',
					'reepay-js-input-mask',
				)
			);

			wp_enqueue_style(
				'wc-gateway-reepay-checkout',
				reepay()->get_setting( 'css_url' ) . 'style' . $suffix . '.css',
				array()
			);

			// Localize the script
			$translation_array = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'reepay-checkout-gateway' ),
				'nonce'     => wp_create_nonce( 'reepay' ),
			);
			wp_localize_script( 'reepay-admin-js', 'Reepay_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'reepay-admin-js' );
		}
	}
}

