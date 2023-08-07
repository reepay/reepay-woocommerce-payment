<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package ./reepay_Woocommerce_Payment
 */

use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\OptionsController;

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
require_once __DIR__ . '/../helpers/functions.php';

/**
 * Manually load Reepay plugin and dependencies.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		PLUGINS_STATE::activate_plugins();

		require_once __DIR__ . '/../../reepay-woocommerce-payment.php';
	}
);

// Init Reepay.
tests_add_filter(
	'plugins_loaded',
	function () {
		( new OptionsController() )->set_option( 'enabled', 'yes' );
	}
);

tests_add_filter( 'deprecated_function_trigger_error', '__return_false' );

require_once __DIR__ . '/../../vendor/autoload.php';

// Start up the WP testing environment.
require_once "{$_tests_dir}/includes/bootstrap.php";
