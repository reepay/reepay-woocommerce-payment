<?php
/**
 * Trait for working with tokens
 *
 * @package Reepay\Checkout\Tokens
 */

namespace Reepay\Checkout\Tokens;

use Exception;
use WC_Order;
use WC_Payment_Token;
use WC_Payment_Tokens;

defined( 'ABSPATH' ) || exit();

/**
 * Trait TokenReepayTrait
 *
 * @package Reepay\Checkout\Tokens
 */
trait TokenReepayTrait {
	/**
	 * Assign payment token to order.
	 *
	 * @param WC_Order             $order order to assign.
	 * @param WC_Payment_Token|int $token token to assign.
	 *
	 * @return void
	 *
	 * @throws Exception If invalid token or order.
	 */
	public static function assign_payment_token( WC_Order $order, $token ) {
		if ( is_numeric( $token ) ) {
			$token = new TokenReepay( $token );
		} elseif ( ! $token instanceof TokenReepay && ! $token instanceof TokenReepayMS ) {
			throw new Exception( 'Invalid token parameter' );
		}

		if ( $token->get_id() ) {
			// Delete tokens if exist.
			delete_post_meta( $order->get_id(), '_payment_tokens' );

			// Reload order.
			$order = wc_get_order( $order->get_id() );

			// Add payment token.
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
	 * @param WC_Order $order        order to save.
	 * @param string   $reepay_token token to save.
	 *
	 * @return bool|WC_Payment_Token
	 *
	 * @throws Exception If invalid token or order.
	 */
	protected function reepay_save_token( WC_Order $order, string $reepay_token ) {
		// Check if token is exists in WooCommerce.
		$token = self::get_payment_token( $reepay_token );

		if ( $token ) {
			// Just assign token to order.
			self::assign_payment_token( $order, $token );
		} else {
			// Create and assign payment token.
			$token = $this->add_payment_token_to_order( $order, $reepay_token );
		}

		return $token;
	}

	/**
	 * Save Payment Data (card type and masked card)
	 *
	 * @param WC_Order $order        order to save.
	 * @param string   $reepay_token token to save.
	 *
	 * @throws Exception If invalid token or order.
	 */
	protected function reepay_save_card_info( WC_Order $order, string $reepay_token ) {
		$customer_handle = get_user_meta( $order->get_customer_id(), 'reepay_customer_id', true );
		$card_info       = reepay()->api( $this->id )->get_reepay_cards( $customer_handle, $reepay_token );

		if ( is_wp_error( $card_info ) ) {
			throw new Exception( $card_info->get_error_message() );
		}

		if ( ! empty( $card_info['masked_card'] ) ) {
			update_post_meta( $order->get_id(), 'reepay_masked_card', $card_info['masked_card'] );
		}

		if ( ! empty( $card_info['card_type'] ) ) {
			update_post_meta( $order->get_id(), 'reepay_card_type', $card_info['card_type'] );
		}

		update_post_meta( $order->get_id(), '_reepay_source', $card_info );

		$this->log(
			sprintf(
				'%s Payment info saved for order %s (%s)',
				__METHOD__,
				$order->get_id(),
				$card_info['masked_card'] ?? 'xxxx'
			)
		);
	}

	/**
	 * Add payment token to customer
	 *
	 * @param int    $customer_id  customer id to add token.
	 * @param string $reepay_token card token from reepay.
	 *
	 * @return array
	 * @throws Exception If invalid token or order.
	 */
	public function add_payment_token_to_customer( int $customer_id, string $reepay_token ): array {
		$customer_handle = get_user_meta( $customer_id, 'reepay_customer_id', true );
		$card_info       = reepay()->api( $this->id )->get_reepay_cards( $customer_handle, $reepay_token );

		if ( is_wp_error( $card_info ) || empty( $card_info ) ) {
			throw new Exception( __( 'Card not found', 'reepay-checkout-gateway' ) );
		}

		if ( 'ms_' === substr( $card_info['id'], 0, 3 ) ) {
			$token = new TokenReepayMS();
			$token->set_gateway_id( reepay()->gateways()->get_gateway( 'reepay_mobilepay_subscriptions' )->id );
			$token->set_token( $reepay_token );
			$token->set_user_id( $customer_id );
		} else {
			$expiry_date = explode( '-', $card_info['exp_date'] );

			$token = new TokenReepay();
			$token->set_gateway_id( reepay()->gateways()->checkout()->id );
			$token->set_token( $reepay_token );
			$token->set_last4( substr( $card_info['masked_card'], - 4 ) );
			$token->set_expiry_year( 2000 + $expiry_date[1] );
			$token->set_expiry_month( $expiry_date[0] );
			$token->set_card_type( $card_info['card_type'] );
			$token->set_user_id( $customer_id );
			$token->set_masked_card( $card_info['masked_card'] );
		}

		// Save Credit Card.
		if ( ! $token->save() ) {
			throw new Exception( __( 'There was a problem adding the card.', 'reepay-checkout-gateway' ) );
		}

		return array(
			'token'     => $token,
			'card_info' => $card_info,
		);
	}

	/**
	 * Add Payment Token.
	 *
	 * @param WC_Order $order        order to add token.
	 * @param string   $reepay_token token to add.
	 *
	 * @return WC_Payment_Token
	 * @throws Exception If invalid token or order.
	 */
	public function add_payment_token_to_order( WC_Order $order, string $reepay_token ): WC_Payment_Token {
		[ //phpcs:ignore Generic.Arrays.DisallowShortArraySyntax.Found
			'token'     => $token,
			'card_info' => $card_info,
		] = $this->add_payment_token_to_customer( $order->get_customer_id(), $reepay_token );

		if ( ! empty( $card_info['masked_card'] ) ) {
			update_post_meta( $order->get_id(), 'reepay_masked_card', $card_info['masked_card'] );
		}

		if ( ! empty( $card_info['card_type'] ) ) {
			update_post_meta( $order->get_id(), 'reepay_card_type', $card_info['card_type'] );
		}

		update_post_meta( $order->get_id(), '_reepay_source', $card_info );

		self::assign_payment_token( $order, $token );

		$this->log(
			sprintf(
				'%s Payment token #%s created for %s',
				__METHOD__,
				$token->get_id(),
				$card_info['masked_card'] ?? ''
			)
		);

		return $token;
	}

	/**
	 * Get payment token.
	 *
	 * @param WC_Order $order order to get token.
	 *
	 * @return bool|WC_Payment_Token|null
	 */
	public static function get_payment_token_order( WC_Order $order ) {
		$token = $order->get_meta( '_reepay_token' );
		if ( empty( $token ) ) {
			return false;
		}

		return self::get_payment_token( $token ) ?: false;
	}

	/**
	 * Get Payment Token by Token string.
	 *
	 * @param string $token token string.
	 *
	 * @return WC_Payment_Token|null
	 */
	public static function get_payment_token( string $token ) {
		global $wpdb;

		$token_id = wp_cache_get( $token, 'reepay_tokens' );

		if ( ! empty( $token_id ) ) {
			return WC_Payment_Tokens::get( $token_id );
		}

		$token_id = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = %s;",
				$token
			)
		);

		if ( ! $token_id ) {
			return null;
		}

		wp_cache_set( $token, $token_id, 'reepay_tokens' );

		return WC_Payment_Tokens::get( $token_id );
	}
}
