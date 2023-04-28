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
		include_once dirname( __FILE__ ) . '/currency.php';
		include_once dirname( __FILE__ ) . '/customer.php';
		include_once dirname( __FILE__ ) . '/format.php';
		include_once dirname( __FILE__ ) . '/gateways.php';
		include_once dirname( __FILE__ ) . '/order.php';
		include_once dirname( __FILE__ ) . '/subscriptions.php';
	}
}
