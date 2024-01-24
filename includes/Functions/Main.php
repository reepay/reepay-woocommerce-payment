<?php
/**
 * Include functions files
 *
 * @package Reepay\Checkout\Functions
 */

namespace Reepay\Checkout\Functions;

defined( 'ABSPATH' ) || exit();

/**
 * Class Main
 *
 * @package Reepay\Checkout\Functions
 */
class Main {
	/**
	 * Main constructor.
	 */
	public function __construct() {
		include_once __DIR__ . '/currency.php';
		include_once __DIR__ . '/customer.php';
		include_once __DIR__ . '/format.php';
		include_once __DIR__ . '/gateways.php';
		include_once __DIR__ . '/hpos.php';
		include_once __DIR__ . '/order.php';
		include_once __DIR__ . '/subscriptions.php';
	}
}
