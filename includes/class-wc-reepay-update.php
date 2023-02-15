<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Reepay_Update {

	/** @var array DB updates that need to be run */
	private static $db_updates = array(
		'1.1.0' => 'updates/update-1.1.0.php',
		'1.2.0' => 'updates/update-1.2.0.php',
		'1.2.1' => 'updates/update-1.2.0.php',
		'1.2.2' => 'updates/update-1.2.2.php',
		'1.2.3' => 'updates/update-1.2.3.php',
		'1.2.9' => 'updates/update-1.2.9.php',
		'1.4.54' => 'updates/update-1.4.54.php',
	);

	/**
	 * Handle updates
	 */
	public static function update() {
		$current_version = get_option( 'woocommerce_reepay_version' );
		foreach ( self::$db_updates as $version => $updater ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				include dirname( __FILE__ ) . '/../' . $updater;
				self::update_db_version( $version );
			}
		}
	}

	/**
	 * Update DB version.
	 *
	 * @param string $version
	 */
	private static function update_db_version( $version ) {
		delete_option( 'woocommerce_reepay_version' );
		add_option( 'woocommerce_reepay_version', $version );
	}
}