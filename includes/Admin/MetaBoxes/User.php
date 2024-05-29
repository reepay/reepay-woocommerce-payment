<?php
/**
 * Register reepay user meta boxes
 *
 * @package Reepay\Checkout\Admin\MetaBoxes
 */

namespace Reepay\Checkout\Admin\MetaBoxes;

use WP_User;

defined( 'ABSPATH' ) || exit();

/**
 * Class
 *
 * @package Reepay\Checkout\Admin\MetaBoxes
 */
class User {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'edit_user_profile', array( $this, 'show_meta_fields' ) );
		add_action( 'show_user_profile', array( $this, 'show_meta_fields' ) );
	}

	/**
	 * Function to show meta box content
	 *
	 * @return void
	 */
	public function show_meta_fields() {
		if ( reepay()->get_setting( 'show_meta_fields_in_users' ) !== 'yes' ) {
			return;
		}
		$template_args = array(
			'post_type' => 'user',
		);
		reepay()->get_template(
			'meta-boxes/meta-fields.php',
			$template_args
		);
	}
}
