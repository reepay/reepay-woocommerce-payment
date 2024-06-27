<?php
/**
 * Main class shortcut
 *
 * @package Reepay\Checkout
 */

/**
 * Shortcut for getting Main class instance
 *
 * @return WC_ReepayCheckout
 */
function reepay(): WC_ReepayCheckout {
	return WC_ReepayCheckout::get_instance();
}
