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
	/**
	 * Main constructor.
	 */
	public function __construct() {
		if ( ! apply_filters( 'reepay_running_tests', false ) ) {
			new Admin();
			new Checkout();
			new ReepayCustomer();
			new Subscriptions();

			add_filter( 'allowed_redirect_hosts', array( $this, 'add_allowed_redirect_hosts' ) );
		}
	}

	public function add_allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'reepay.com';
		$hosts[] = 'checkout.reepay.com';

		return $hosts;
	}
}
