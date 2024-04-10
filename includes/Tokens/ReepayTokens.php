<?php
/**
 * Trait for working with tokens
 *
 * @package Reepay\Checkout\Tokens
 */

namespace Reepay\Checkout\Tokens;

use Exception;
use WC_Order;
use WC_Subscription;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WP_Error;

defined( 'ABSPATH' ) || exit();

/**
 * Trait TokenReepayTrait
 *
 * @package Reepay\Checkout\Tokens
 */
abstract class ReepayTokens {
	/**
	 * Assign payment token to order.
	 *
	 * @param WC_Order                             $order order to assign.
	 * @param TokenReepay|TokenReepayMS|int|string $token token class, token id or token string.
	 *
	 * @return void
	 *
	 * @throws Exception If invalid token or order.
	 */
	public static function assign_payment_token( WC_Order $order, $token ) {
		if ( is_numeric( $token ) ) {
			$token = WC_Payment_Tokens::get( $token );
		} elseif ( is_string( $token ) ) {
			$token = self::get_payment_token( $token );
		}

		if ( ! $token instanceof TokenReepay && ! $token instanceof TokenReepayMS ) {
			throw new Exception( 'Invalid token parameter' );
		}

		if ( $token->get_id() ) {

			// Reload order.
			$order = wc_get_order( $order->get_id() );
			// Delete tokens if exist.
			$order->delete_meta_data( '_payment_tokens' );
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
	 * @return WC_Payment_Token
	 *
	 * @throws Exception If invalid token or order.
	 */
	public static function reepay_save_token( WC_Order $order, string $reepay_token ): ?WC_Payment_Token {
		// Check if token is exists in WooCommerce.
		$token = self::get_payment_token( $reepay_token );

		if ( $token ) {
			// Just assign token to order.
			self::assign_payment_token( $order, $token );
		} else {
			// Create and assign payment token.
			$token = self::add_payment_token_to_order( $order, $reepay_token );
		}

		return $token;
	}

	/**
	 * Save Payment Data (card type and masked card)
	 *
	 * @param WC_Order     $order     order to save.
	 * @param string|array $card_info card token or card info.
	 *
	 * @throws Exception If invalid token or order.
	 */
	public static function save_card_info_to_order( WC_Order $order, $card_info ) {
		if ( is_string( $card_info ) ) {
			$customer_handle = rp_get_customer_handle( $order->get_customer_id() );
			$card_info       = reepay()->api( 'tokens' )->get_reepay_cards( $customer_handle, $card_info );

			if ( is_wp_error( $card_info ) ) {
				throw new Exception( __( 'Card not found', 'reepay-checkout-gateway' ) );
			}
		}

		if ( ! empty( $card_info['masked_card'] ) ) {
			$order->update_meta_data( 'reepay_masked_card', $card_info['masked_card'] );
		}

		if ( ! empty( $card_info['card_type'] ) ) {
			$order->update_meta_data( 'reepay_card_type', $card_info['card_type'] );
		}

		$order->update_meta_data( '_reepay_source', $card_info );
		$order->save_meta_data();
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
	public static function add_payment_token_to_order( WC_Order $order, string $reepay_token ): WC_Payment_Token {
		[ //phpcs:ignore Generic.Arrays.DisallowShortArraySyntax.Found
			'token'     => $token,
			'card_info' => $card_info,
		] = self::add_payment_token_to_customer( $order->get_customer_id(), $reepay_token );

		self::save_card_info_to_order( $order, $card_info );

		self::assign_payment_token( $order, $token );

		return $token;
	}

	/**
	 * Add payment token to customer
	 *
	 * @param int          $customer_id customer id to add token.
	 * @param string|array $card_info   card token or card info.
	 *
	 * @return array
	 * @throws Exception If invalid token or order.
	 */
	public static function add_payment_token_to_customer( int $customer_id, $card_info ): array {
		if ( empty( $card_info ) ) {
			return array(
				'token'     => false,
				'card_info' => $card_info,
			);
		}

		if ( is_string( $card_info ) ) {
			$customer_handle = rp_get_customer_handle( $customer_id );
			$card_info       = reepay()->api( 'tokens' )->get_reepay_cards( $customer_handle, $card_info );
		}

		if ( is_wp_error( $card_info ) || empty( $card_info ) ) {
			throw new Exception( __( 'Card not found', 'reepay-checkout-gateway' ) );
		}

		if ( 'ms_' === substr( $card_info['id'], 0, 3 ) ) {
			$token = new TokenReepayMS();
			$token->set_gateway_id( reepay()->gateways()->get_gateway( 'reepay_mobilepay_subscriptions' )->id );
			$token->set_token( $card_info['id'] );
			$token->set_user_id( $customer_id );
		} else {
			$token = new TokenReepay();
			$token->set_gateway_id( reepay()->gateways()->checkout()->id );
			$token->set_token( $card_info['id'] );
			$token->set_user_id( $customer_id );

			$expiry_date = explode( '-', $card_info['exp_date'] );

			$token->set_last4( substr( $card_info['masked_card'], - 4 ) );
			$token->set_expiry_year( 2000 + $expiry_date[1] );
			$token->set_expiry_month( $expiry_date[0] );
			$token->set_card_type( $card_info['card_type'] );
			$token->set_masked_card( $card_info['masked_card'] );
		}

		$token->save();

		return array(
			'token'     => $token,
			'card_info' => $card_info,
		);
	}

	/**
	 * Get payment token.
	 *
	 * @param WC_Order $order order to get token.
	 *
	 * @return bool|WC_Payment_Token
	 */
	public static function get_payment_token_by_order( WC_Order $order ) {
		$token = $order->get_meta( '_reepay_token' );

		if ( empty( $token ) ) {
			return false;
		}

		return self::get_payment_token( $token ) ?: false;
	}

	/**
	 * Get payment token for subscription.
	 *
	 * @param WC_Subscription $subscription order to get token.
	 *
	 * @return bool|WC_Payment_Token|null
	 */
	public static function get_payment_token_subscription( WC_Subscription $subscription ) {
		$token = $subscription->get_meta( '_reepay_token' );
		// If token wasn't stored in Subscription.
		if ( ! is_wp_error( $token ) && empty( $token ) ) {
			$order = $subscription->get_parent();
			if ( ! is_wp_error( $order ) && $order ) {
				$token = $order->get_meta( '_reepay_token' );
				if ( ! is_wp_error( $order ) && empty( $token ) ) {
					$invoice_data = reepay()->api( $order )->get_invoice_data( $order );
					if ( ! empty( $invoice_data ) && ! is_wp_error( $invoice_data ) ) {
						if ( ! empty( $invoice_data['recurring_payment_method'] ) ) {
							$token = $invoice_data['recurring_payment_method'];
						} elseif ( ! empty( $invoice_data['transactions'] ) ) {
							foreach ( $invoice_data['transactions'] as $transaction ) {
								if ( ! empty( $transaction['payment_method'] ) ) {
									$token = $transaction['payment_method'];
									break;
								}
							}
						}
					}
				}
			}
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

		if ( empty( $token ) ) {
			return null;
		}

		$token_id = wp_cache_get( $token, 'reepay_tokens' );

		if ( ! empty( $token_id ) ) {
			return WC_Payment_Tokens::get( $token_id );
		}

		$token_id = (int) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = %s;",
				$token
			)
		);

		if ( empty( $token_id ) ) {
			return null;
		}

		wp_cache_set( $token, $token_id, 'reepay_tokens' );

		return WC_Payment_Tokens::get( $token_id );
	}

	/**
	 * Delete Reepay payment method and WooCommerce token
	 *
	 * @param WC_Payment_Token $token token to delete.
	 *
	 * @return bool
	 */
	public static function delete_card( WC_Payment_Token $token ) {
		$result = reepay()->api( 'api-delete-card' )->delete_payment_method( $token->get_token() );

		if ( is_wp_error( $result ) && $result->get_error_code() !== 40 ) {
			return false;
		}

		return $token->delete();
	}

	/**
	 * Check if $token is Reepay token
	 *
	 * @param WC_Payment_Token|null $token token to check.
	 *
	 * @return bool
	 */
	public static function is_reepay_token( ?WC_Payment_Token $token ): bool {
		if ( is_null( $token ) ) {
			return false;
		}

		return in_array(
			$token->get_gateway_id(),
			array(
				reepay()->gateways()->get_gateway( 'reepay_mobilepay_subscriptions' )->id,
				reepay()->gateways()->checkout()->id,
			),
			true
		);
	}
}
