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
		add_action( 'wp', array( __CLASS__, 'delete_payment_method_action' ), 10 ); // Before WooCommerce action.
	}

	/**
	 * Process the delete payment method form.
	 */
	public static function delete_payment_method_action() {
		global $wp;

		// Check if this is a delete-payment-method request.
		if ( ! isset( $wp->query_vars['delete-payment-method'] ) ) {
			return;
		}

		$token_id = absint( $wp->query_vars['delete-payment-method'] );
		if ( empty( $token_id ) ) {
			return;
		}

		$token = WC_Payment_Tokens::get( $token_id );
		if ( ! $token ) {
			return;
		}

		// Check if ReepayTokens class exists and method.
		$reepay_tokens_exists         = class_exists( ReepayTokens::class );
		$reepay_gateway_method_exists = method_exists( ReepayGateway::class, 'is_reepay_token' );
		$is_reepay_token              = false;

		if ( $reepay_tokens_exists ) {
			$is_reepay_token = ReepayTokens::is_reepay_token( $token );
		} elseif ( $reepay_gateway_method_exists ) {
			$is_reepay_token = ReepayGateway::is_reepay_token( $token );
		}

		// Only handle Reepay tokens, let WooCommerce handle others.
		if ( ( $reepay_tokens_exists && ! ReepayTokens::is_reepay_token( $token ) ) ||
			( $reepay_gateway_method_exists && ! ReepayGateway::is_reepay_token( $token ) ) ) {
			return;
		}

		wc_nocache_headers();

		// Security: Verify nonce first (from GET parameter)
		if ( ! isset( $_GET['_wpnonce'] ) || 
		     ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'delete-payment-method-' . $token_id ) ) {
			wc_add_notice( __( 'Security check failed.', 'reepay-checkout-gateway' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
			exit();
		}

		// Security: Verify user ownership
		$current_user_id = get_current_user_id();
		if ( $current_user_id !== $token->get_user_id() ) {
			wc_add_notice( __( 'Invalid payment method.', 'reepay-checkout-gateway' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
			exit();
		}

		// Delete the token
		$deleted = false;
		if ( class_exists( ReepayTokens::class ) ) {
			$deleted = ReepayTokens::delete_card( $token );
		} elseif ( method_exists( ReepayGateway::class, 'is_reepay_token' ) ) {
			$deleted = ReepayGateway::delete_card( $token );
		}

		if ( $deleted ) {
			wc_add_notice( __( 'Payment method deleted.', 'reepay-checkout-gateway' ) );
		} else {
			wc_add_notice( __( 'Payment method cannot be deleted.', 'reepay-checkout-gateway' ), 'error' );
		}

		wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}
}
