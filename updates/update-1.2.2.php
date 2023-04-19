<?php

use Reepay\Checkout\Gateways\ReepayCheckout;

defined( 'ABSPATH' ) || exit;

// Set PHP Settings
set_time_limit( 0 );
ini_set( 'memory_limit', '2048M' );

// Logger
$log     = new WC_Logger();
$handler = 'wc-reepay-update';

// Preliminary checking
if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
	$log->add( $handler, '[INFO] WooCommerce Subscription is not installed' );
	return;
}

// Gateway
$gateway = new ReepayCheckout();

$log->add( $handler, sprintf( 'Start upgrade %s....', basename( __FILE__ ) ) );

// Upgrade script was moved to update-1.2.3.php

$log->add( $handler, 'Upgrade has been completed!' );
