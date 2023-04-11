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
	 * @param WC_Order        $order order to assign.
	 * @param TokenReepay|int $token token to assign.
	 *
	 * @return void
	 *
	 * @throws Exception If invalid token or order.
	 */
	public static function assign_payment_token( $order, $token ) {
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
	 * @return bool|TokenReepay
	 *
	 * @throws Exception If invalid token or order.
	 */
	protected function reepay_save_token( $order, $reepay_token ) {
		// Check if token is exists in WooCommerce.
		$token = self::get_payment_token( $reepay_token );

		if ( ! $token ) {
			// Create Payment Token.
			$token = $this->add_payment_token( $order, $reepay_token );
		}

		// Assign token to order.
		self::assign_payment_token( $order, $token );

		return $token;
	}

	/**
	 * Add Payment Token.
	 *
	 * @param WC_Order $order        order to add token.
	 * @param string   $reepay_token token to add.
	 *
	 * @return TokenReepay
	 * @throws Exception If invalid token or order.
	 */
	public function add_payment_token( $order, $reepay_token ) {
		// Create Payment Token.
		$customer_handle = reepay()->api( $this->id )->get_customer_handle_by_order( $order );
		$source          = reepay()->api( $this->id )->get_reepay_cards( $customer_handle, $reepay_token );

		$this->log(
			array(
				'source'  => 'TokenReepayTrait::add_payment_token',
				'$source' => $source,
			),
		);

		if ( is_wp_error( $source ) || empty( $source ) ) {
			throw new Exception( __( 'Reepay error. Try again or contact us.', 'reepay-checkout-gateway' ) );
		}

		if ( 'ms_' === substr( $source['id'], 0, 3 ) ) {
			$token = new TokenReepayMS();
			$token->set_user_id( $order->get_customer_id() );
			$token->set_token( $reepay_token );
			$token->set_gateway_id( $this->id );
		} else {
			$expiry_date = explode( '-', $source['exp_date'] );

			$token = new TokenReepay();
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

		// Save Credit Card.
		if ( ! $token->save() ) {
			throw new Exception( __( 'There was a problem adding the card.', 'reepay-checkout-gateway' ) );
		}

		update_post_meta( $order->get_id(), '_reepay_source', $source );
		self::assign_payment_token( $order, $token );
		$this->log(
			sprintf(
				'%s Payment token #%s created for %s',
				__METHOD__,
				$token->get_id(),
				$source['masked_card'] ?? ''
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
	 * @return null|bool|WC_Payment_Token
	 */
	public static function get_payment_token( $token ) {
		global $wpdb;

		$token_id = wp_cache_get( $token, 'reepay_tokens' );

		if ( ! empty( $token_id ) ) {
			return $token_id;
		}

		$token_id = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = %s;",
				$token
			)
		);

		if ( ! $token_id ) {
			return false;
		}

		wp_cache_set( $token, 'reepay_tokens' );

		return WC_Payment_Tokens::get( $token_id );
	}
}
