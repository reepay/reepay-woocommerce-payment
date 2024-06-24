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
	 * Gets the full manifest vite file
	 *
	 * @param string $build_path path to compiled vite scripts.
	 *
	 * @return mixed
	 */
	public static function get_manifest_config( string $build_path ) {
		$file_path   = $build_path . '/' . self::MANIFEST_FILE_PATH;
		$error_title = '<b>Error Billwerk+ Pay:</b> ';
		if ( ! file_exists( $file_path ) ) {
			wp_die( $error_title . __( 'Vite scripts manifest file not found', 'reepay-checkout-gateway' ), $error_title );
		}
		$json_data = file_get_contents( $file_path ); // phpcs:ignore
		if ( false === $json_data ) {
			wp_die( $error_title . __( 'Vite scripts manifest file not read', 'reepay-checkout-gateway' ), $error_title );
		}
		$data = json_decode( $json_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_die( $error_title . __( 'Vite scripts manifest file is not correct', 'reepay-checkout-gateway' ), $error_title );
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
	public static function get_entry_point_config( array $manifest_config, string $entry_point ) {
		if ( array_key_exists( $entry_point, $manifest_config ) ) {
			return $manifest_config[ $entry_point ];
		}

		return false;
	}

	/**
	 * Connects scripts to WordPress
	 *
	 * @param array       $entry_point_config entry point config.
	 * @param string      $build_url vite build url.
	 * @param bool|string $plugin_version version.
	 *
	 * @return void
	 */
	public static function connecting_entry_point_scripts( array $entry_point_config, string $build_url, $plugin_version = false ): void {
		if ( ! $entry_point_config['isEntry'] ) {
			return;
		}
		wp_enqueue_script(
			$entry_point_config['src'],
			$build_url . $entry_point_config['file'],
			array( 'wp-i18n', 'wp-element', 'moment' ),
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
	public static function dev( array $vite_entry_points, bool $admin_footer = true ) {
		self::output_vite_dev();
		foreach ( $vite_entry_points as $vite_entry_point ) {
			self::output_vite_dev_entry_point( $vite_entry_point['file'], $admin_footer );
		}
	}

	/**
	 * Displays all scripts for production
	 *
	 * @param array       $vite_entry_points entry points from manifest.
	 * @param string      $build_path path to compiled vite scripts.
	 * @param string      $build_url vite build url.
	 * @param bool|string $plugin_version version.
	 *
	 * @return void
	 */
	public static function production( array $vite_entry_points, string $build_path, string $build_url, $plugin_version = false ) {
		foreach ( $vite_entry_points as $vite_entry_point ) {
			$config             = self::get_manifest_config( $build_path . $vite_entry_point['nested_path'] );
			$entry_point_config = self::get_entry_point_config( $config, $vite_entry_point['file'] );
			if ( $entry_point_config ) {
				self::connecting_entry_point_scripts( $entry_point_config, $build_url . $vite_entry_point['nested_path'], $plugin_version );
			}
		}
	}
}
