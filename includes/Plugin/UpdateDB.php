<?php
/**
 * Class to update database along with plugin update
 *
 * @package Reepay\Checkout\Plugin
 */

namespace Reepay\Checkout\Plugin;

defined( 'ABSPATH' ) || exit();

/**
 * Class UpdateDB
 *
 * @package Reepay\Checkout\Plugin
 */
class UpdateDB {
	/**
	 * The latest update that requires a database changes
	 *
	 * @var string
	 */
	const DB_VERSION = '1.4.54';

	/**
	 * DB updates that need to be run
	 *
	 * @var array
	 */
	const DB_UPDATES = array(
		'1.1.0'  => 'updates/update-1.1.0.php',
		'1.2.0'  => 'updates/update-1.2.0.php',
		'1.2.1'  => 'updates/update-1.2.0.php',
		'1.2.2'  => 'updates/update-1.2.2.php',
		'1.2.3'  => 'updates/update-1.2.3.php',
		'1.2.9'  => 'updates/update-1.2.9.php',
		'1.4.54' => 'updates/update-1.4.54.php',
	);

	const USER_CAPABILITY = 'update_plugins';

	const UPDATE_PAGE_SLUG = 'reepay_update_db';

	/**
	 * UpdateDB constructor.
	 */
	public function __construct() {
		if ( version_compare( get_option( 'woocommerce_reepay_version', self::DB_VERSION ), self::DB_VERSION, '<' )
			 && $this->user_can_update()
		) {
			add_action( 'admin_notices', array( $this, 'update_notice' ) );
		}

		add_action( 'admin_menu', array( &$this, 'add_admin_menu' ), 99 );
	}

	/**
	 * Provide Admin Menu items
	 */
	public function add_admin_menu() {
		add_submenu_page(
			null,
			'Reepay update db',
			'Reepay update db',
			self::USER_CAPABILITY,
			self::UPDATE_PAGE_SLUG,
			array( $this, 'update_page_content' )
		);
	}

	/**
	 * Update Page
	 */
	public function update_page_content() {
		$this->update();

		echo esc_html__( 'Update finished.', 'reepay-checkout-gateway' );
	}

	/**
	 * Update Notice
	 */
	public function update_notice() {
		reepay()->get_template(
			'admin/notices/update-db.php',
			array(
				'update_page_url' => menu_page_url( self::UPDATE_PAGE_SLUG, false ),
			)
		);
	}


	/**
	 * Check if the user has the ability to update database
	 *
	 * @param null $user_id user id to check.
	 *
	 * @return bool
	 */
	public function user_can_update( $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		return user_can( $user_id, self::USER_CAPABILITY );
	}

	/**
	 * Handle updates
	 */
	public function update() {
		$current_version = get_option( 'woocommerce_reepay_version' );
		foreach ( self::DB_UPDATES as $version => $updater ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				include reepay()->get_setting( 'plugin_path' ) . 'updates/' . $updater;
				self::update_db_version( $version );
			}
		}
	}

	/**
	 * Update DB version.
	 *
	 * @param string $version version to set.
	 */
	private function update_db_version( string $version ) {
		update_option( 'woocommerce_reepay_version', $version );
	}
}
