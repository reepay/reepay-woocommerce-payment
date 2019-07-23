<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set PHP Settings
set_time_limit( 0 );
ini_set( 'memory_limit', '2048M' );

// Logger
$log     = new WC_Logger();
$handler = 'wc-reepay-update';

// Preliminary checking
if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
	$log->add( $handler, '[INFO] WooCommerce Subscription don\'t installed' );
	return;
}

// Gateway
$gateway = new WC_Gateway_Reepay_Checkout();

$log->add( $handler, 'Start upgrade....' );

try {
	// Load Subscriptions
	$subscriptions = array();
	foreach ( get_users() as $user ) {
		foreach ( wcs_get_users_subscriptions( $user->ID ) as $subscription ) {
			if ( $subscription->get_payment_method() === $gateway->id ) {
				$subscriptions[ $subscription->get_id() ] = $subscription;
			}
		}
	}

	$log->add( $handler, sprintf( 'Loaded %s subscriptions', count( $subscriptions ) ) );

	// Process Subscriptions
	foreach ( $subscriptions as $subscription ) {
		$token = WC_Gateway_Reepay_Checkout::retrieve_payment_token_order( $subscription );

		if ( ! $token ) {
			$log->add( $handler, sprintf( '[INFO] Subscription #%s doesn\'t have assigned tokens.', $subscription->get_id() ) );
			continue;
		}

		// Check Token ID
		$token_id = get_post_meta( $subscription->get_id(), '_reepay_token_id', true );
		if ( empty( $token_id ) ) {
			update_post_meta( $subscription->get_id(), '_reepay_token_id', $token->get_id() );
			$log->add( $handler, sprintf( '[INFO] Subscription #%s. Token ID is filled.', $subscription->get_id() ) );
		}

		// Check Token
		$reepay_token = get_post_meta( $subscription->get_id(), '_reepay_token', true );
		if ( empty( $reepay_token ) ) {
			update_post_meta( $subscription->get_id(), '_reepay_token', $token->get_token() );
			$log->add( $handler, sprintf( '[INFO] Subscription #%s. Token is filled.', $subscription->get_id() ) );
		}
	}
} catch (Exception $e) {
	$log->add( $handler, sprintf( '[ERROR] Upgrade failed. Details: %s. %s', $e->getMessage(), $e->getTraceAsString() ) );

	throw new Exception( 'Upgrade failed. Please check logs.' );
}

$log->add( $handler, 'Upgrade has been completed!' );
