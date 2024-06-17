<?php
/**
 * Add logs page
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

/**
 * Class
 *
 * @package Reepay\Checkout\Admin
 */
class LogsPage {
	public const SLUG = 'bw-logs';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_status_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_admin_status_content_' . self::SLUG, array( $this, 'tab_content' ) );
	}

	/**
	 * Add tab.
	 *
	 * @param array $tabs slugs and tab names.
	 * @return array
	 */
	public function add_tab( array $tabs ): array {
		$tabs[ self::SLUG ] = __( 'Billwerk logs', 'reepay-checkout-gateway' );
		return $tabs;
	}

	/**
	 * Tab content
	 *
	 * @return void
	 */
	public function tab_content() {
		$template_args = array();
		reepay()->get_template(
			'admin/logs-page.php',
			$template_args
		);
	}
}
