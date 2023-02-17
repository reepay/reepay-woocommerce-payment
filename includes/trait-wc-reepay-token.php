<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

trait WC_Reepay_Token {
	/**
	 * Assign payment token to order.
	 *
	 * @param WC_Order $order
	 * @param WC_Payment_Token_Reepay|int $token
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public static function assign_payment_token( $order, $token ) {
		if ( is_numeric( $token ) ) {
			$token = new WC_Payment_Token_Reepay( $token );
		} elseif ( ! $token instanceof WC_Payment_Token_Reepay && ! $token instanceof WC_Payment_Token_Reepay_MS ) {
			throw new Exception( 'Invalid token parameter' );
		}

		if ( $token->get_id() ) {
			// Delete tokens if exist
			delete_post_meta( $order->get_id(), '_payment_tokens' );

			// Reload order
			$order = wc_get_order( $order->get_id() );

			// Add payment token
			$order->add_payment_token( $token );

			$order->update_meta_data( '_reepay_token_id', $token->get_id() );
			$order->update_meta_data( 'reepay_token', $token->get_token() );
			$order->update_meta_data( '_reepay_token', $token->get_token() );
			$order->save_meta_data();
		}
	}

	/**
	 * Save Payment Token
	 *
	 * @param WC_Order $order
	 * @param string $reepay_token
	 *
	 * @return bool|WC_Payment_Token_Reepay
	 *
	 * @throws Exception
	 */
	protected function reepay_save_token( $order, $reepay_token ) {
		// Check if token is exists in WooCommerce
		$token = self::get_payment_token( $reepay_token );

		if ( ! $token ) {
			// Create Payment Token
			$token = $this->add_payment_token( $order, $reepay_token );
		}

		// Assign token to order
		self::assign_payment_token( $order, $token );

		return $token;
	}

	/**
	 * Add Payment Token.
	 *
	 * @param WC_Order $order
	 * @param string $reepay_token
	 *
	 * @return bool|WC_Payment_Token_Reepay
	 * @throws Exception
	 */
	public function add_payment_token( $order, $reepay_token ) {
		// Create Payment Token
		$customer_handle = $this->api->get_customer_handle_order( $order->get_id() );
		$source          = $this->api->get_reepay_cards( $customer_handle, $reepay_token );

		if ( ! $source ) {
			throw new Exception( 'Unable to retrieve customer payment methods' );
		}

		if ( 'ms_' == substr( $source['id'], 0, 3 ) ) {
			$token = new WC_Payment_Token_Reepay_MS();
			$token->set_user_id( $order->get_customer_id() );
			$token->set_token( $reepay_token );
			$token->set_gateway_id( $this->id );
		} else {
			$expiry_date = explode( '-', $source['exp_date'] );

			// Initialize Token
			$token = new WC_Payment_Token_Reepay();
			$token->set_gateway_id( $this->id );
			$token->set_token( $reepay_token );
			$token->set_last4( substr( $source['masked_card'], - 4 ) );
			$token->set_expiry_year( 2000 + $expiry_date[1] );
			$token->set_expiry_month( $expiry_date[0] );
			$token->set_card_type( $source['card_type'] );
			$token->set_user_id( $order->get_customer_id() );
			$token->set_masked_card( $source['masked_card'] );

			update_post_meta( $order->get_id(), 'reepay_masked_card', $source['masked_card'] );
			update_post_meta( $order->get_id(), 'reepay_card_type', $source['card_type'] );
		}

		// Save Credit Card
		if ( ! $token->save() ) {
			throw new Exception( __( 'There was a problem adding the card.', 'reepay-checkout-gateway' ) );
		}

		update_post_meta( $order->get_id(), '_reepay_source', $source );
		self::assign_payment_token( $order, $token );
		$this->log( sprintf( '%s Payment token #%s created for %s',
			__METHOD__,
			$token->get_id(),
			isset( $source['masked_card'] ) ? $source['masked_card'] : ''
		) );

		return $token;
	}

	/**
	 * Get payment token.
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Payment_Token_Reepay|false
	 * @deprecated
	 *
	 */
	public static function retrieve_payment_token_order( $order ) {
		$tokens = $order->get_payment_tokens();

		foreach ( $tokens as $token_id ) {
			try {
				$token = new WC_Payment_Token_Reepay( $token_id );
			} catch ( Exception $e ) {
				return false;
			}

			if ( ! $token->get_id() ) {
				continue;
			}

			if ( ! in_array( $token->get_gateway_id(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
				continue;
			}

			return $token;
		}

		return false;
	}

	/**
	 * Get payment token.
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Payment_Token_Reepay|false
	 */
	public static function get_payment_token_order( WC_Order $order ) {
		$token = $order->get_meta( '_reepay_token' );
		if ( empty( $token ) ) {
			return false;
		}

		return self::get_payment_token( $token );
	}

	/**
	 * Get Payment Token by Token string.
	 *
	 * @param string $token
	 *
	 * @return null|bool|WC_Payment_Token
	 */
	public static function get_payment_token( $token ) {
		global $wpdb;

		$query    = "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = '%s';";
		$token_id = $wpdb->get_var( $wpdb->prepare( $query, $token ) );
		if ( ! $token_id ) {
			return false;
		}

		return WC_Payment_Tokens::get( $token_id );
	}

}