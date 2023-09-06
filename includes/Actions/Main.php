<?php
/**
 * Actions
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Actions;

defined( 'ABSPATH' ) || exit();

/**
 * Class Main
 *
 * @package Reepay\Checkout
 */
class Main {
	public function __construct() {
		if ( ! apply_filters( 'reepay_running_tests', false ) ) {
			new ReepayCustomer();
			new Subscriptions();
		}
	}
}