<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package ./reepay_Woocommerce_Payment
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

require_once 'helpers/RP_TEST_HELPERS.php';
require_once 'helpers/RP_TEST_PLUGINS_STATE.php';
require_once 'helpers/RpTestOrderGenerator.php';
require_once 'helpers/RpTestProductGenerator.php';
require_once 'helpers/RpTestCartGenerator.php';

/**
 * Manually load Reepay plugin and dependencies.
 */
tests_add_filter( 'muplugins_loaded', function () {
	RP_TEST_PLUGINS_STATE::activate_plugins();

	require_once dirname( dirname( __FILE__ ) ) . '/reepay-woocommerce-payment.php';
} );

// Init Reepay.
tests_add_filter( 'plugins_loaded', function () {
	$reepay_checkout = reepay()->gateways()->checkout();
	$reepay_checkout->process_admin_options();
	$reepay_checkout->update_option( 'enabled', 'yes' );
	$reepay_checkout->update_option( 'test_mode', 'yes' );
	$reepay_checkout->update_option( 'private_key_test', 'priv_2795e0868bc1609c66783e0c8d967bcf' );
	reepay()->reset_settings();
	$reepay_checkout->is_webhook_configured();
} );

tests_add_filter( 'deprecated_function_trigger_error', '__return_false' );

// Start up the WP testing environment.
require_once "{$_tests_dir}/includes/bootstrap.php";
