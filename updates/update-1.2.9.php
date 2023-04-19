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
	// Check the webhooks settings
	try {
		$result = reepay()->api( $gateway )->request('GET', 'https://api.reepay.com/v1/account/webhook_settings' );
		if ( is_wp_error( $result ) ) {
			/** @var WP_Error $result */
			throw new Exception( $result->get_error_message(), $result->get_error_code() );
		}

		// The webhook settings
		$urls         = $result['urls'];
		$alert_emails = $result['alert_emails'];

		// The webhook settings of the payment plugin
		$webhook_url = WC()->api_request_url( get_class( $gateway ) );
		$alert_email = '';
		if ( ! empty( $gateway->failed_webhooks_email ) &&
		     is_email( $gateway->failed_webhooks_email )
		) {
			$alert_email = $gateway->failed_webhooks_email;
		}

		// Verify the webhook settings
		if ( in_array( $webhook_url, $urls ) &&
		     ( empty( $alert_email ) || in_array( $alert_email, $alert_emails ) )
		) {
			// Webhook has been configured before
			$gateway->update_option( 'is_webhook_configured', 'yes' );
		} else {
			// Update the webhook settings
			try {
				$urls[] = $webhook_url;

				if ( ! empty( $alert_email ) && is_email( $alert_email ) ) {
					$alert_emails[] = $alert_email;
				}

				$data = array(
					'urls'         => array_unique( $urls ),
					'disabled'     => false,
					'alert_emails' => array_unique( $alert_emails )
				);

				$result = reepay()->api( $gateway )->request('PUT', 'https://api.reepay.com/v1/account/webhook_settings', $data);
				if ( is_wp_error( $result ) ) {
					/** @var WP_Error $result */
					throw new Exception( $result->get_error_message(), $result->get_error_code() );
				}

				$log->log( $handler, sprintf( 'WebHook has been successfully created/updated: %s', var_export( $result, true ) ) );
				$gateway->update_option( 'is_webhook_configured', 'yes' );
			} catch ( Exception $e ) {
				$gateway->update_option( 'is_webhook_configured', 'no' );
				$log->log( $handler, sprintf( 'WebHook creation/update has been failed: %s', var_export( $result, true ) ) );
			}
		}
	} catch ( Exception $e ) {
		$gateway->update_option( 'is_webhook_configured', 'no' );
		$log->log( $handler, 'Unable to retrieve the webhook settings. Wrong api credentials?' );
	}
}
