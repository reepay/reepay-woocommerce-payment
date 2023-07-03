<?php
/**
 * Activate order flow classes
 *
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
		OrderStatuses::init_statuses();

		new InstantSettle();
		InstantSettle::set_order_capture( new OrderCapture() );

		new ThankyouPage();
		new Webhook();
	}
}

