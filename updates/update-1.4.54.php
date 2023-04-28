<?php

use Reepay\Checkout\Gateways\ReepayCheckout;

defined( 'ABSPATH' ) || exit;

// Logger
$log     = new WC_Logger();
$handler = 'wc-reepay-update';

$gateway = new ReepayCheckout();
$is_webhook_configured = $gateway->get_option( 'is_webhook_configured' );
if ( empty( $is_webhook_configured ) &&
     ( ! empty( $gateway->private_key ) || ! empty( $gateway->private_key_test ) )
) {
	$gateway->is_webhook_configured();
}
