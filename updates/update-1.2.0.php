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

// Get Orders
$orders = wc_get_orders( array() );
foreach ($orders as $key => $order) {
	/** @var WC_Order $order */
	// Skip refunds
	if ( $order->get_type() === 'shop_order_refund'	) {
	    continue;
    }

    if ( $order->get_payment_method() !== $gateway->id ) {
        continue;
    }

    // Check is renewal order
	if ( ! wcs_order_contains_renewal( $order ) ) {
		continue;
	}

	$handle = $order->get_meta( '_reepay_order' );
	if ( ! empty( $handle ) ) {
		// Check if reepay handler is different that expected
		$check_id = str_replace( 'order-', '', $handle );
		if ($check_id != $order->get_id()) {
			// Update handler
			$handle = 'order-' . $order->get_id();
			$order->update_meta_data( '_reepay_order', $handle );
			$order->save_meta_data();
			$log->add( $handler, sprintf( '[SUCCESS] Updated reepay handler for order #%s. Handler before: %s', $order->get_id(), $handle ) );
		}
	}
}

$log->add( $handler, 'Upgrade has been completed!' );
