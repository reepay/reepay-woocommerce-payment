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
	$processed = array();
	foreach ( $subscriptions as $subscription ) {
		$token = ReepayCheckout::get_payment_token_order( $subscription );

		if ( ! $token ) {
			$log->add( $handler, sprintf( '[INFO] Subscription #%s doesn\'t have assigned tokens.', $subscription->get_id() ) );
			continue;
		}

		// Check Token ID
		$token_id = get_post_meta( $subscription->get_id(), '_reepay_token_id', true );
		if ( empty( $token_id ) ) {
			update_post_meta( $subscription->get_id(), '_reepay_token_id', $token->get_id() );
			$processed[ $subscription->get_id() ] = $subscription;
			$log->add( $handler, sprintf( '[SUCCESS] Subscription #%s. Token ID was filled.', $subscription->get_id() ) );
		}

		// Check Token
		$reepay_token = get_post_meta( $subscription->get_id(), '_reepay_token', true );
		if ( empty( $reepay_token ) ) {
			update_post_meta( $subscription->get_id(), '_reepay_token', $token->get_token() );
			update_post_meta( $subscription->get_id(), 'reepay_token', $token->get_token() );
			$processed[ $subscription->get_id() ] = $subscription;
			$log->add( $handler, sprintf( '[SUCCESS] Subscription #%s. Token was filled.', $subscription->get_id() ) );
		}
	}
} catch (Exception $e) {
	$log->add( $handler, sprintf( '[ERROR] Upgrade is failed. Details: %s. %s', $e->getMessage(), $e->getTraceAsString() ) );

	throw new Exception( 'Upgrade is failed. Please check logs.' );
}

// Process renewal orders
foreach ($processed as $subscription_id => $subscription) {
	/** @var WC_Subscription $subscription */
	$renewal_orders = $subscription->get_related_orders( 'ids', 'renewal' );
	if ( count( $renewal_orders ) > 0 ) {
		$order_id = max( $renewal_orders );
		$order = wc_get_order( $order_id );

		// Check order notes
		$is_need_charge = false;
		$notes = reepay_get_order_notes( $order_id );
		foreach ($notes as $note) {
			if ( mb_strpos( $note['content'], 'Transaction already settled', 0, 'UTF-8' ) !== false ) {
				$is_need_charge = true;
				break;
			}
		}

		// Try to charge
		if ( $is_need_charge ) {
			if ( ! empty( $order->get_transaction_id() ) ) {
				$log->add( $handler, sprintf( '[INFO] It seem order #%s was paid with transaction #%s', $order->get_id(), $order->get_transaction_id() ) );
				continue;
			}

			// Update _reepay_order meta
			$meta = get_post_meta( $order_id, '_reepay_order', true );
			update_post_meta( $order_id, '_reepay_order', 'order-' . $order->get_id() );
			$log->add( $handler, sprintf( '[INFO] Meta of order #%s changed. Meta that was before: %s', $order->get_id(), $meta ) );

			// Charge
			try {
				$log->add( $handler, sprintf( '[SUCCESS] Charge method removed. Subscription #%s. Order #%s.', $subscription->get_id(), $order->get_id() ) );
			} catch (Exception $e) {
				$log->add( $handler, sprintf( '[ERROR] scheduled_subscription_payment: %s. %s', $e->getMessage(), $e->getTraceAsString() ) );
			}
		}
	}
}

$log->add( $handler, 'Upgrade has been completed!' );

/**
 * Get Order Notes
 * @param mixed $order_id
 *
 * @return array
 */
function reepay_get_order_notes( $order_id ): array {
	global $wpdb;

	$table = $wpdb->prefix . 'comments';
	$results = $wpdb->get_results("
        SELECT *
        FROM $table
        WHERE  `comment_post_ID` = $order_id
        AND  `comment_type` LIKE  'order_note'
    ");

	$order_notes = array();
	foreach ( $results as $note ) {
		$order_notes[] = array(
			'id'      => $note->comment_ID,
			'date'    => $note->comment_date,
			'author'  => $note->comment_author,
			'content' => $note->comment_content,
		);
	}

	return $order_notes;
}
