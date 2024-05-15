<?php
/**
 * Add hidden debug page
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Class
 *
 * @package Reepay\Checkout\Admin
 */
class DebugPage {
	public const SLUG = 'bw---debug';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'debug_page' ) );
		add_action( 'admin_head', array( $this, 'hide_debug_page' ) );
	}

	/**
	 * Add page.
	 *
	 * @return void
	 */
	public function debug_page() {
		add_menu_page(
			'Debug Page',
			'Debug Page',
			'manage_options',
			self::SLUG,
			array( $this, 'debug_page_content' ),
			'',
			99
		);
	}

	/**
	 * Hide page in menu
	 *
	 * @return void
	 */
	public function hide_debug_page() {
		remove_menu_page( self::SLUG );
	}

	/**
	 * Page content
	 *
	 * @return void
	 */
	public function debug_page_content() {
		$template_args = array();
		reepay()->get_template(
			'admin/debug-page.php',
			$template_args
		);
	}
}
