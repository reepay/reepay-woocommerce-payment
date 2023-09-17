<?php
/**
 * Trait Reepay_UnitTestCase_Trait_Tokens
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use Reepay\Checkout\Tokens\TokenReepay;
use Reepay\Checkout\Tokens\TokenReepayMS;
use WC_Payment_Token_CC;

/**
 * Trait Reepay_UnitTestCase_Trait_Tokens
 */
trait Reepay_UnitTestCase_Trait_Tokens {
	public function generate_token( string $type, array $args = array() ) {
		if ( 'reepay' === $type ) {
			return $this->generate_token_reepay( $args );
		} else if ( 'reepay_ms' === $type ) {
			return $this->generate_token_reepay_ms( $args );
		}

		return $this->generate_token_simple( $args );
	}

	private function generate_token_reepay( array $args = array() ): TokenReepay {
		if( empty( $args['user_id'] ) ) {
			$args['user_id'] = $this->generate_user_for_token();
		}

		$token = new TokenReepay();
		$token->set_gateway_id( reepay()->gateways()->checkout()->id );
		$token->set_token( $args['token'] ?? 'rp_1234567890' );
		$token->set_last4( $args['last4'] ?? '1234' );
		$token->set_expiry_year( $args['expiry_year'] ?? 2077 );
		$token->set_expiry_month( $args['expiry_month'] ?? 12 );
		$token->set_card_type( $args['card_type'] ?? 'reepay' );
		$token->set_user_id( $args['user_id'] );
		$token->set_masked_card( $args['masked_card'] ?? rp_format_credit_card( '1234567890123456' ) );
		$token->save();

		return $token;
	}

	private function generate_token_reepay_ms( array $args = array() ): TokenReepayMS {
		if( empty( $args['user_id'] ) ) {
			$args['user_id'] = $this->generate_user_for_token();
		}

		$token = new TokenReepayMS();
		$token->set_gateway_id( reepay()->gateways()->get_gateway( 'reepay_mobilepay_subscriptions' )->id );
		$token->set_token( $args['token'] ?? 'ms_1234567890' );
		$token->set_user_id( $args['user_id'] );
		$token->save();

		return $token;
	}

	private function generate_token_simple( array $args = array() ): WC_Payment_Token_CC {
		if( empty( $args['user_id'] ) ) {
			$args['user_id'] = $this->generate_user_for_token();
		}

		$token = new WC_Payment_Token_CC();
		$token->set_gateway_id( 'cod' );
		$token->set_token( $args['token'] ?? 'cc_1234567890' );
		$token->set_last4( $args['last4'] ?? '1234' );
		$token->set_expiry_year( $args['expiry_year'] ?? 2077 );
		$token->set_expiry_month( $args['expiry_month'] ?? 12 );
		$token->set_card_type( $args['card_type'] ?? 'simple_woo' );
		$token->set_user_id( $args['user_id'] );
		$token->save();

		return $token;
	}

	public function generate_user_for_token(): int {
		return $this->factory()->user->create_object( array(
			'user_login' => 'johndoe',
			'user_email' => 'mail@example.com',
			'user_pass'  => 'password',
		) );
	}
}