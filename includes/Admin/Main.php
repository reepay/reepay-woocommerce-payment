<?php
/**
 * Main admin class
 *
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
	/**
	 * Main constructor.
	 */
	public function __construct() {
		new PluginsPage();
		new Ajax();
		new MetaBoxes();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param string $hook current page hook.
	 */
	public function admin_enqueue_scripts( string $hook ) {
		if ( 'post.php' !== $hook ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wc-gateway-reepay-admin',
			reepay()->get_setting( 'css_url' ) . 'admin' . $suffix . '.css',
			array(),
			reepay()->get_setting( 'plugin_version' )
		);

		wp_register_script(
			'reepay-js-input-mask',
			reepay()->get_setting( 'js_url' ) . 'jquery.inputmask' . $suffix . '.js',
			array( 'jquery' ),
			'5.0.3',
			true
		);

		wp_enqueue_script(
			'reepay-admin-js',
			reepay()->get_setting( 'js_url' ) . 'admin' . $suffix . '.js',
			array(
				'jquery',
				'reepay-js-input-mask',
			),
			reepay()->get_setting( 'plugin_version' ),
			true
		);

		wp_localize_script(
			'reepay-admin-js',
			'Reepay_Admin',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'reepay-checkout-gateway' ),
				'nonce'     => wp_create_nonce( 'reepay' ),
			)
		);
	}
}

