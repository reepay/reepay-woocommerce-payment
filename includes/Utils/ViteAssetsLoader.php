<?php
/**
 * Helper for vite in WordPress.
 *
 * @package Reepay\Checkout\Utils
 */

namespace Reepay\Checkout\Utils;

/**
 * Class
 *
 * @package Reepay\Checkout\Utils
 */
class ViteAssetsLoader {
	public const HMR_HOST           = 'http://localhost:5173';
	public const MANIFEST_FILE_PATH = '.vite/manifest.json';
	public const WP_DEPS            = array( 'wp-i18n', 'wp-element', 'wp-html-entities', 'moment', 'lodash' );
	public const WC_DEPS            = array( 'wc-blocks-registry', 'wc-settings' );

	/**
	 * Displays vite scripts for development in the footer
	 *
	 * @param bool $admin_footer Display in the admin panel or on the website.
	 *
	 * @return void
	 */
	public static function output_vite_dev( bool $admin_footer = true ): void {
		add_action(
			$admin_footer ? 'admin_footer' : 'wp_footer',
			function () {
				$host = self::HMR_HOST;
				// phpcs:disable
				$scripts = <<<HTML
					<script type="module">
						import RefreshRuntime from '{$host}/@react-refresh'
						RefreshRuntime.injectIntoGlobalHook(window)
						window.\$RefreshReg$ = () => {}
						window.\$RefreshSig$ = () => (type) => type
						window.__vite_plugin_react_preamble_installed__ = true
					</script>
					<script src="{$host}/@vite/client" type="module"></script>
				HTML;
				// phpcs:enable
				echo $scripts;
			}
		);
	}

	/**
	 * Displays Vite entry point for development in the footer.
	 *
	 * @param string $entry_point entry point vite.
	 * @param bool   $admin_footer Display in the admin panel or on the website.
	 *
	 * @return void
	 */
	public static function output_vite_dev_entry_point( string $entry_point, bool $admin_footer = true ): void {
		add_action(
			$admin_footer ? 'admin_footer' : 'wp_footer',
			function () use ( $entry_point ) {
				$host = self::HMR_HOST;
				// phpcs:disable
				$scripts = <<<HTML
					<script src="{$host}/{$entry_point}" type="module"></script>
				HTML;
				// phpcs:enable
				echo $scripts;
			}
		);
	}

	/**
	 * Show error on site
	 *
	 * @param string $error_title error.
	 *
	 * @return void
	 */
	public static function show_error(
		string $error_title
	): void {
		wp_die(
			wp_kses(
				$error_title,
				array(
					'b' => array(),
				)
			)
		);
	}

	/**
	 * Gets the full manifest vite file
	 *
	 * @param string $build_path path to compiled vite scripts.
	 *
	 * @return mixed
	 */
	public static function get_manifest_config(
		string $build_path
	) {
		$file_path   = $build_path . '/' . self::MANIFEST_FILE_PATH;
		$error_title = '<b>Error Billwerk+ Payments:</b> ';
		if ( ! file_exists( $file_path ) ) {
			$error_title = $error_title . __( 'Vite scripts manifest file not found', 'reepay-checkout-gateway' );
			self::show_error( $error_title );
		}
		$json_data = file_get_contents( $file_path ); // phpcs:ignore
		if ( false === $json_data ) {
			$error_title = $error_title . __( 'Vite scripts manifest file not read', 'reepay-checkout-gateway' );
			self::show_error( $error_title );
		}
		$data = json_decode( $json_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_title = $error_title . __( 'Vite scripts manifest file is not correct', 'reepay-checkout-gateway' );
			self::show_error( $error_title );
		}

		return $data;
	}

	/**
	 * Receives the entrypoint config or false
	 *
	 * @param array  $manifest_config main config.
	 * @param string $entry_point entry point.
	 *
	 * @return false|mixed
	 */
	public static function get_entry_point_config(
		array $manifest_config,
		string $entry_point
	) {
		if ( array_key_exists( $entry_point, $manifest_config ) ) {
			return $manifest_config[ $entry_point ];
		}

		return false;
	}

	/**
	 * Connect script to WordPress
	 *
	 * @param array       $entry_point_config entry point config.
	 * @param string      $build_url vite build url.
	 * @param bool|string $plugin_version version.
	 * @param array|null  $dependencies dependencies instead of default ones.
	 *
	 * @return void
	 */
	public static function connecting_entry_point_script(
		array $entry_point_config,
		string $build_url,
		$plugin_version = false,
		?array $dependencies = null
	): void {
		if ( ! $entry_point_config['isEntry'] ) {
			return;
		}
		wp_enqueue_script(
			$entry_point_config['src'],
			$build_url . $entry_point_config['file'],
			$dependencies ? $dependencies : self::WP_DEPS,
			$plugin_version,
			true
		);
	}

	/**
	 * Displays all scripts for development devs
	 *
	 * @param array $vite_entry_points entry points from manifest.
	 * @param bool  $admin_footer Display in the admin panel or on the website.
	 *
	 * @return void
	 */
	public static function dev( array $vite_entry_points, bool $admin_footer = true ): void {
		self::output_vite_dev( $admin_footer );
		foreach ( $vite_entry_points as $vite_entry_point ) {
			self::output_vite_dev_entry_point( $vite_entry_point['file'], $admin_footer );
		}
	}

	/**
	 * Fake script registration in dev mode, where it is very necessary
	 *
	 * @param array $vite_entry_points entry points from manifest.
	 *
	 * @return void
	 */
	public static function fake_register_scripts(
		array $vite_entry_points
	) {
		foreach ( $vite_entry_points as $vite_entry_point ) {
			// phpcs:disable
			wp_register_script(
				$vite_entry_point['file'],
				'',
			);
			// phpcs:enable
		}
	}

	/**
	 * Displays all scripts for production
	 *
	 * @param array       $vite_entry_points entry points from manifest.
	 * @param string      $build_path path to compiled vite scripts.
	 * @param string      $build_url vite build url.
	 * @param bool|string $plugin_version version.
	 * @param array|null  $dependencies dependencies instead of default ones.
	 *
	 * @return void
	 */
	public static function production(
		array $vite_entry_points,
		string $build_path,
		string $build_url,
		$plugin_version = false,
		?array $dependencies = null
	): void {
		foreach ( $vite_entry_points as $vite_entry_point ) {
			$config             = self::get_manifest_config( $build_path . $vite_entry_point['nested_path'] );
			$entry_point_config = self::get_entry_point_config( $config, $vite_entry_point['file'] );
			if ( $entry_point_config ) {
				self::connecting_entry_point_script( $entry_point_config, $build_url . $vite_entry_point['nested_path'], $plugin_version, $dependencies );
			}
		}
	}
}
