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

// Load Subscriptions
$subscriptions = array();
foreach ( get_users() as $user ) {
	foreach ( wcs_get_users_subscriptions( $user->ID ) as $subscription ) {
		$subscriptions[ $subscription->get_id() ] = $subscription;
	}
}

$log->add( $handler, sprintf( 'Loaded %s subscriptions', count( $subscriptions ) ) );

// Process Subscriptions
$cards = array();
foreach ( $subscriptions as $subscription ) {
	/** @var WC_Subscription $subscription */
	if ($subscription->get_payment_method() !== $gateway->id) {
		$log->add( $handler, sprintf( '[INFO] Subscription #%s was paid using %s. Skip it.', $subscription->get_id(), $subscription->get_payment_method() ) );
		continue;
	}

	$log->add( $handler, sprintf( 'Processing subscription #%s', $subscription->get_id() ) );
	$token_id = get_post_meta( $subscription->get_parent()->get_id(), '_reepay_token_id', true );
	if ( empty( $token_id ) ) {
		$log->add( $handler, sprintf( '[INFO] Subscription #%s doesn\'t have token id.', $subscription->get_id() ) );
		continue;
	}

	// Get Token
	$token = new WC_Payment_Token_Reepay( $token_id );
	if ( ! $token->get_id() ) {
		$log->add( $handler, sprintf( '[INFO] Invalid Token ID #%s doesn\'t have token id.', $token_id ) );
		continue;
	}

	if ( $token->get_gateway_id() !== $gateway->id ) {
		$log->add( $handler, sprintf( '[INFO] Card Token ID #%s doesn\'t related to Reepay.', $token_id ) );
		continue;
	}

	// Check subscription's token
	$subscriptions_tokens = get_post_meta( $subscription->get_id(), '_payment_tokens', true );
	if ( empty( $subscriptions_tokens ) ) {
		WC_Gateway_Reepay_Checkout::add_payment_token( $subscription, $token_id );
		$log->add( $handler, sprintf( '[SUCCESS] Token #%s assigned to subscription #%s.', $token->get_id(), $subscription->get_id() ) );
	}

	// Get renewal orders
	$orders = $subscription->get_related_orders( 'all', $order_types = array( 'renewal' ) );
	foreach ( $orders as $order ) {
		/** WC_Order $order */
		$order_tokens = get_post_meta( $order->get_id(), '_payment_tokens', true );
		if ( empty( $order_tokens ) ) {
			WC_Gateway_Reepay_Checkout::add_payment_token( $order, $token_id );
			$log->add( $handler, sprintf( '[SUCCESS] Token #%s assigned to order #%s.', $token->get_id(), $order->get_id() ) );
		}
	}
}

$log->add( $handler, 'Upgrade has been completed!' );
