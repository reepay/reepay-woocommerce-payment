<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Logger
$log     = new WC_Logger();
$handler = 'wc-reepay-update';

$gateway = new WC_Gateway_Reepay_Checkout();
$is_webhook_configured = $gateway->get_option( 'is_webhook_configured' );
if ( empty( $is_webhook_configured ) &&
     ( ! empty( $gateway->private_key ) || ! empty( $gateway->private_key_test ) )
) {
	$gateway->is_webhook_configured();
}
