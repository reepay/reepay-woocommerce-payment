<?php
/**
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

defined( 'ABSPATH' ) || exit();

/**
 * Class Main
 *
 * @package Reepay\Checkout\OrderFlow
 */
class Main {
	/**
	 * Main constructor.
	 */
	public function __construct() {
		new OrderStatuses();
		new OrderCapture();
		new InstantSettle();
		new ThankyouPage();
		new Webhook();
	}
}

