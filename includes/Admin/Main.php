<?php
/**
 * Main admin class
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

use Reepay\Checkout\Utils\MetaField;
use Reepay\Checkout\Utils\ViteAssetsLoader;

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
		new DebugPage();
		new Ajax();
		new MetaBoxes\Order();
		new MetaBoxes\User();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param string $hook current page hook.
	 */
	public function admin_enqueue_scripts( string $hook ) {
		$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		if ( 'post.php' === $hook || rp_hpos_is_order_page() ) {
			$suffix = $debug ? '' : '.min';

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

		$vite_entry_points = array(
			array(
				'nested_path' => '/meta-fields/',
				'file'        => 'src/admin/meta-fields/main.tsx',
			),
			array(
				'nested_path' => '/debug-page/',
				'file'        => 'src/admin/debug-page/main.tsx',
			),
		);
		if ( $debug ) {
			ViteAssetsLoader::dev( $vite_entry_points );
		} else {
			ViteAssetsLoader::production(
				$vite_entry_points,
				reepay()->get_setting( 'vite_path' ),
				reepay()->get_setting( 'vite_url' ),
				reepay()->get_setting( 'plugin_version' )
			);
			foreach ( $vite_entry_points as $vite_entry_point ) {
				wp_set_script_translations(
					$vite_entry_point['file'],
					'reepay-checkout-gateway',
					reepay()->get_setting( 'languages_path' )
				);
			}
		}
		wp_localize_script(
			'jquery',
			'BILLWERK_SETTINGS',
			array(
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'metaFieldKeys' => MetaField::BILLWERK_FIELD_KEYS,
				'urlViteAssets' => $debug ? ViteAssetsLoader::HMR_HOST . '/' : reepay()->get_setting( 'vite_url' ),
			)
		);
	}
}
