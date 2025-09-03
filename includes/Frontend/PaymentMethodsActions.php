<?php
/**
 * Payment Methods Actions for My Account
 *
 * @package Reepay\Checkout\Frontend
 */

namespace Reepay\Checkout\Frontend;

use Reepay\Checkout\Tokens\ReepayTokens;
use WC_Payment_Tokens;

defined( 'ABSPATH' ) || exit();

/**
 * Class PaymentMethodsActions
 *
 * @package Reepay\Checkout\Frontend
 */
class PaymentMethodsActions {
	/**
	 * PaymentMethodsActions constructor.
	 */
	public function __construct() {
		add_action( 'wp', array( __CLASS__, 'delete_payment_method_action' ), 10 ); //Before WooCommerce action
	}

	/**
	 * Debug helper function
	 */
	private static function debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_file = ABSPATH . 'wp-content/debug.log';
			$timestamp = date( 'Y-m-d H:i:s' );
			file_put_contents( $log_file, "[$timestamp] [REEPAY DEBUG] $message\n", FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Process the delete payment method form.
	 */
	public static function delete_payment_method_action() {
		// Debug: Log function entry
		self::debug_log( 'delete_payment_method_action() called' );

		global $wp;

		// Debug: Log all query vars
		self::debug_log( 'Query vars: ' . print_r( $wp->query_vars, true ) );
		self::debug_log( '$_REQUEST: ' . print_r( $_REQUEST, true ) );
		self::debug_log( '$_GET: ' . print_r( $_GET, true ) );

		// Check if this is a delete-payment-method request
		if ( ! isset( $wp->query_vars['delete-payment-method'] ) ) {
			self::debug_log( 'No delete-payment-method query var found, returning' );
			return;
		}

		$token_id = absint( $wp->query_vars['delete-payment-method'] );
		self::debug_log( 'Token ID: ' . $token_id );

		if ( empty( $token_id ) ) {
			self::debug_log( 'Empty token ID, returning' );
			return;
		}

		$token = WC_Payment_Tokens::get( $token_id );
		self::debug_log( 'Token object: ' . print_r( $token, true ) );

		if ( ! $token ) {
			self::debug_log( 'Token not found, returning' );
			return;
		}

		// Debug: Check token gateway
		self::debug_log( 'Token gateway ID: ' . $token->get_gateway_id() );

		// Check if ReepayTokens class exists and method
		$reepay_tokens_exists = class_exists( ReepayTokens::class );
		$reepay_gateway_method_exists = method_exists( ReepayGateway::class, 'is_reepay_token' );
		self::debug_log( 'ReepayTokens class exists: ' . ( $reepay_tokens_exists ? 'yes' : 'no' ) );
		self::debug_log( 'ReepayGateway::is_reepay_token method exists: ' . ( $reepay_gateway_method_exists ? 'yes' : 'no' ) );

		$is_reepay_token = false;
		if ( $reepay_tokens_exists ) {
			$is_reepay_token = ReepayTokens::is_reepay_token( $token );
			self::debug_log( 'ReepayTokens::is_reepay_token result: ' . ( $is_reepay_token ? 'yes' : 'no' ) );
		} elseif ( $reepay_gateway_method_exists ) {
			$is_reepay_token = ReepayGateway::is_reepay_token( $token );
			self::debug_log( 'ReepayGateway::is_reepay_token result: ' . ( $is_reepay_token ? 'yes' : 'no' ) );
		}

		// Only handle Reepay tokens, let WooCommerce handle others
		if ( ( $reepay_tokens_exists && ! ReepayTokens::is_reepay_token( $token ) ) ||
		     ( $reepay_gateway_method_exists && ! ReepayGateway::is_reepay_token( $token ) ) ) {
			self::debug_log( 'Not a Reepay token, letting WooCommerce handle it' );
			return;
		}

		self::debug_log( 'This is a Reepay token, processing deletion' );

		wc_nocache_headers();

		// Debug: Check nonce and user validation
		$current_user_id = get_current_user_id();
		$token_user_id = $token->get_user_id();
		$nonce_isset = isset( $_REQUEST['_wpnonce'] );
		$nonce_value = $nonce_isset ? $_REQUEST['_wpnonce'] : '';
		$nonce_valid = $nonce_isset ? wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'delete-payment-method-' . $token_id ) : false;

		self::debug_log( 'Current user ID: ' . $current_user_id );
		self::debug_log( 'Token user ID: ' . $token_user_id );
		self::debug_log( 'Nonce isset: ' . ( $nonce_isset ? 'yes' : 'no' ) );
		self::debug_log( 'Nonce value: ' . $nonce_value );
		self::debug_log( 'Nonce valid: ' . ( $nonce_valid ? 'yes' : 'no' ) );

		if ( $current_user_id !== $token_user_id || ! $nonce_isset || false === $nonce_valid ) {
			self::debug_log( 'Validation failed - adding error notice' );
			wc_add_notice( __( 'Invalid payment method.', 'reepay-checkout-gateway' ), 'error' );
		} else {
			self::debug_log( 'Validation passed - attempting to delete token' );
			$deleted = false;

			if ( class_exists( ReepayTokens::class ) ) {
				self::debug_log( 'Using ReepayTokens::delete_card()' );
				$deleted = ReepayTokens::delete_card( $token );
			} else if ( method_exists( ReepayGateway::class, 'is_reepay_token' ) ) {
				self::debug_log( 'Using ReepayGateway::delete_card()' );
				$deleted = ReepayGateway::delete_card( $token );
			}

			self::debug_log( 'Delete result: ' . ( $deleted ? 'success' : 'failed' ) );

			if ( $deleted ) {
				self::debug_log( 'Adding success notice' );
				wc_add_notice( __( 'Payment method deleted.', 'reepay-checkout-gateway' ) );
			} else {
				self::debug_log( 'Adding failure notice' );
				wc_add_notice( __( 'Payment method cannot be deleted.', 'reepay-checkout-gateway' ) );
			}
		}

		self::debug_log( 'Redirecting to payment methods page and exiting' );
		wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}
}
