<?php
/**
 * Token classes registration
 *
 * @package Reepay\Checkout\Tokens
 */

namespace Reepay\Checkout\Tokens;

use Exception;

/**
 * Class Main
 *
 * @package Reepay\Checkout\Tokens
 */
class Main {
	/**
	 * Main constructor.
	 */
	public function __construct() {
		TokenReepay::register_view_actions();
		TokenReepayMS::register_view_actions();

		add_filter( 'woocommerce_payment_token_class', array( $this, 'set_token_class_name' ), 10, 1 );
		add_filter( 'woocommerce_get_customer_payment_tokens', array( $this, 'add_reepay_cards_to_list' ), 1000, 3 );
	}

	/**
	 * Rename token classes for woocommerce
	 *
	 * @param string $token_class_name token class name in woo.
	 *
	 * @return string
	 * @see WC_Payment_Tokens::get_token_classname
	 */
	public function set_token_class_name( string $token_class_name ): string {
		static $tokens = array(
			'WC_Payment_Token_Reepay'    => TokenReepay::class,
			'WC_Payment_Token_Reepay_MS' => TokenReepayMS::class,
		);

		return $tokens[ $token_class_name ] ?? $token_class_name;
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param array  $tokens      Tokens list.
	 * @param int    $customer_id Id of the customer.
	 * @param string $gateway_id  Gateway ID.
	 *
	 * @return array                           Filtered item.
	 * @throws Exception If unable to save token.
	 */
	public function add_reepay_cards_to_list( array $tokens, int $customer_id, string $gateway_id = '' ): array {
		/**
		 * Gateway id is optional. For getting tokens for a specific gateway
		 *
		 * @see WC_Payment_Tokens::get_customer_tokens
		 */

		if ( ! empty( $gateway_id ) && reepay()->gateways()->checkout()->id !== $gateway_id ) {
			return $tokens;
		}

		$reepay_cards = reepay()->api( $gateway_id )->get_reepay_cards( rp_get_customer_handle( $customer_id ) );

		if ( is_wp_error( $reepay_cards ) || empty( $reepay_cards ) ) {
			return $tokens;
		}

		if ( reepay()->gateways()->checkout()->id === $gateway_id ) {
			$tokens = array(); // Only Reepay tokens if Reepay gateway specified.
		}

		foreach ( $reepay_cards as $card_info ) {
			$token = ReepayTokens::get_payment_token( $card_info['id'] );

			if ( empty( $token ) ) {
				$token = ReepayTokens::add_payment_token_to_customer( $customer_id, $card_info )['token'];
			}

			$tokens[ $token->get_id() ] = $token;
		}

		return $tokens;
	}
}
