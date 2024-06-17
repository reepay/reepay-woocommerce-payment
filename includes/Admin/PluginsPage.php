<?php
/**
 * Add data to plugins page
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Class PluginsPage
 *
 * @package Reepay\Checkout\Admin
 */
class PluginsPage {
	/**
	 * PluginsPage constructor.
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . reepay()->get_setting( 'plugin_basename' ), array( $this, 'add_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'rollback_link' ), 20, 2 );
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param array $links default links.
	 *
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=reepay_checkout' ) . '">' . __( 'Settings', 'reepay-checkout-gateway' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param mixed $links Plugin Row Meta.
	 * @param mixed $file  Plugin Base file.
	 *
	 * @return array
	 */
	public function add_plugin_row_meta( $links, $file ): array {
		if ( reepay()->get_setting( 'plugin_basename' ) === $file ) {
			$links = array_merge(
				$links,
				array(
					'account' => '<a target="_blank" href="https://signup.reepay.com/?_gl=1*1iccm28*_gcl_aw*R0NMLjE2NTY1ODI3MTQuQ2p3S0NBandrX1dWQmhCWkVpd0FVSFFDbVJaNDJmVmVQWFc4LUlpVDRndE83bWRmaW5NNG5wZDhkaG12dVJFOEZkbDR4eXVMNlZpMTRSb0N1b2NRQXZEX0J3RQ..*_ga*MjA3MDA3MTk4LjE2NTM2MzgwNjY.*_ga_F82PFFEF3F*MTY2Mjk2NTEwNS4xOS4xLjE2NjI5NjUxODkuMC4wLjA.&_ga=2.98685660.319325710.1662963483-207007198.1653638066#/en">' . esc_html__( 'Get free test account', 'reepay-checkout-gateway' ) . '</a>',
					'pricing' => '<a target="_blank" href="https://reepay.com/pricing/">' . esc_html__( 'Pricing', 'reepay-checkout-gateway' ) . '</a>',
				)
			);
		}

		return $links;
	}

	/**
	 * Show Rollback plugin install, activate URL
	 *
	 * @param mixed $links Plugin Row Meta.
	 * @param mixed $file  Plugin Base file.
	 *
	 * @return links
	 */
	public function rollback_link( $links, $file ): array {
		if ( reepay()->get_setting( 'plugin_basename' ) === $file ) {
			$rollback_link    = null;
			$plugin_file_path = WP_PLUGIN_DIR . '/wp-rollback/wp-rollback.php';
			if ( ! file_exists( $plugin_file_path ) ) {
				/**
				 * Generate install WP-Rollback plguin URL
				 */
				$action        = 'install-plugin';
				$slug          = 'wp-rollback';
				$url           = wp_nonce_url(
					add_query_arg(
						array(
							'action' => $action,
							'plugin' => $slug,
						),
						admin_url( 'update.php' )
					),
					$action . '_' . $slug
				);
				$rollback_link = "<a href='$url'>" . esc_html__( 'Rollback using WP Rollback', 'reepay-checkout-gateway' ) . '</a>';
			} elseif ( ! in_array( 'wp-rollback/wp-rollback.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
				/**
				 * Generate activate WP-Rollback plguin URL
				 */
				$path          = 'wp-rollback/wp-rollback.php';
				$url           = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $path ), 'activate-plugin_' . $path );
				$rollback_link = "<a href='$url'>" . esc_html__( 'Activate Rollback', 'reepay-checkout-gateway' ) . '</a>';
			}

			if ( $rollback_link ) {
				array_push(
					$links,
					$rollback_link
				);
			}
		}

		return $links;
	}
}
