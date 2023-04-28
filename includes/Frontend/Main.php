<?php
/**
 * Frontend main class
 *
 * @package Reepay\Checkout\Frontend
 */

namespace Reepay\Checkout\Frontend;

defined( 'ABSPATH' ) || exit();

/**
 * Class Main
 *
 * @package Reepay\Checkout\Frontend
 */
class Main {
	/**
	 * Main constructor.
	 */
	public function __construct() {
		new Assets();
	}
}
