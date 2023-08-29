<?php
/**
 * Class ReepayTokensTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase_Trait_Tokens;
use Reepay\Checkout\Tokens\ReepayTokens;
use Reepay\Checkout\Tokens\TokenReepay;
use Reepay\Checkout\Tokens\TokenReepayMS;

/**
 * ReepayTokensTest.
 *
 * @covers \Reepay\Checkout\Tokens\ReepayTokens
 */
class ReepayTokensTest extends Reepay_UnitTestCase {
	use Reepay_UnitTestCase_Trait_Tokens;

	/**
	 * Test @see ReepayTokens::assign_payment_token
	 *
	 * @param string $type token type.
	 * @param string $token_arg token argument sent to function.
	 * @param bool $expect_exception expect exception.
	 *
	 * @testWith
	 * ["reepay", "int", false]
	 * ["reepay", "string", false]
	 * ["reepay", "object", false]
	 * ["reepay_ms", "int", false]
	 * ["reepay_ms", "string", false]
	 * ["reepay_ms", "object", false]
	 * ["simple", "false", true]
	 * ["simple", "string", true]
	 * ["simple", "object", true]
	 */
	public function test_assign_payment_token( string $type, string $token_arg, bool $expect_exception ) {
		$token = $this->generate_token( $type );

		if ( $expect_exception ) {
			$this->expectException( Exception::class );
		}

		ReepayTokens::assign_payment_token(
			$this->order_generator->order(),
			$token_arg === 'object' ?
				$token :
				( $token_arg === 'string' ?
					$token->get_token() :
					$token->get_id() )
		);

		$this->order_generator->reset_order();

		$this->assertSame( $token->get_id(), (int) $this->order_generator->get_meta( '_reepay_token_id' ) );
		$this->assertSame( array( $token->get_id() ), $this->order_generator->order()->get_payment_tokens() );
		$this->assertSame( $token->get_token(), $this->order_generator->get_meta( 'reepay_token' ) );
		$this->assertSame( $token->get_token(), $this->order_generator->get_meta( '_reepay_token' ) );
	}

	public function test_add_payment_token_to_customer_wp_error() {
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( new WP_Error() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Card not found' );
		ReepayTokens::add_payment_token_to_customer( 0, 'test' );
	}

	public function test_add_payment_token_to_customer_empty_cards() {
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( array() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Card not found' );
		ReepayTokens::add_payment_token_to_customer( 0, 'test' );
	}

	public function test_add_payment_token_unsaved() {
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( array(
			'id' => 'ms_123456789'
		) );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid or missing payment token fields.' );
		ReepayTokens::add_payment_token_to_customer( 0, '' );
	}

	public function test_add_payment_token_ms() {
		$token_string = 'ms_123456789';

		$api_response = array(
			'id' => $token_string
		);
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		/**
		 * @var TokenReepayMS $token
		 * @var array $card_info
		 */
		[ 'token' => $token, 'card_info' => $card_info ] = ReepayTokens::add_payment_token_to_customer( 0, $token_string );


		$this->assertInstanceOf( TokenReepayMS::class, $token );
		$this->assertSame(
			reepay()->gateways()->get_gateway( 'reepay_mobilepay_subscriptions' )->id,
			$token->get_gateway_id()
		);
		$this->assertSame( $token_string, $token->get_token() );
		$this->assertSame( 0, $token->get_user_id() );

		$this->assertSame( $api_response, $card_info );
	}

	public function test_add_payment_token_checkout() {
		$api_response = array(
			'id' => 'ca_123456789',
			'exp_date' => '20-77',
			'masked_card' => '457111XXXXXX2077',
			'card_type' => 'visa_dk'
		);
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		/**
		 * @var TokenReepay $token
		 * @var array $card_info
		 */
		[ 'token' => $token, 'card_info' => $card_info ] = ReepayTokens::add_payment_token_to_customer( 0, $api_response['id'] );


		$this->assertInstanceOf( TokenReepay::class, $token );
		$this->assertSame(
			reepay()->gateways()->checkout()->id,
			$token->get_gateway_id()
		);
		$this->assertSame( $api_response['id'], $token->get_token() );
		$this->assertSame( 0, $token->get_user_id() );

		$expiry_date = explode( '-', $card_info['exp_date'] );

		$this->assertSame( substr( $card_info['masked_card'], - 4 ), $token->get_last4() );
		$this->assertSame( 2000 + $expiry_date[1], $token->get_expiry_year() );
		$this->assertSame( $expiry_date[0], $token->get_expiry_month() );
		$this->assertSame( $card_info['card_type'], $token->get_card_type() );
		$this->assertSame( $card_info['masked_card'], $token->get_masked_card() );

		$this->assertSame( $api_response, $card_info );
	}

	public function test_reepay_save_card_info() {
		$api_response = array(
			'id' => 'ca_123456789',
			'exp_date' => '20-77',
			'masked_card' => '457111XXXXXX2077',
			'card_type' => 'visa_dk'
		);

		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		ReepayTokens::reepay_save_card_info(
			$this->order_generator->order(),
			$api_response['id']
		);

		$this->order_generator->reset_order();

		$this->assertSame( $api_response['masked_card'], $this->order_generator->get_meta('reepay_masked_card') );
		$this->assertSame( $api_response['card_type'], $this->order_generator->get_meta('reepay_card_type') );
		$this->assertSame( $api_response, $this->order_generator->get_meta('_reepay_source') );
	}

	/**
	 * @testWith
	 * [false, false]
	 * [false, true]
	 * [true, false]
	 * [true, true]
	 *
	 */
	public function test_get_payment_token_order( bool $generate_token, $add_token_to_order ) {
		$token_string = 'rp_1234';

		$token = null;

		if( $generate_token ) {
			$token = $this->generate_token( 'reepay', array(
				'token' => $token_string
			) );
		}

		if( $add_token_to_order ) {
			$this->order_generator->set_meta( '_reepay_token', $token_string );
		}

		$result_token = ReepayTokens::get_payment_token_by_order( $this->order_generator->order() );

		$this->assertSame(
			is_null( $token ) || ! $add_token_to_order ? false : $token->get_id(),
			empty( $result_token ) ? false : $result_token->get_id()
		);
	}

	/**
	 * Test @see ReepayTokens::get_payment_token with empty token string
	 */
	public function test_get_payment_token_with_empty_token_string() {

		$token_object = ReepayTokens::get_payment_token( "" );

		$this->assertSame( null, $token_object);
	}

	/**
	 * Test @see ReepayTokens::get_payment_token with undefined token
	 */
	public function test_get_payment_token_with_undefined_token() {
		$token_object = ReepayTokens::get_payment_token( "rp_1" );

		$this->assertSame( null, $token_object );
	}

	/**
	 * Test @see ReepayTokens::get_payment_token
	 */
	public function test_get_payment_token() {
		$token_string = "rp_1";

		$token_id = $this->generate_token( 'reepay', array(
			'token' => $token_string
		) )->get_id();

		$token_object = ReepayTokens::get_payment_token( $token_string );

		$this->assertSame( $token_id, $token_object->get_id() );

		//Test cache set
		$this->assertSame( $token_id ?: false, wp_cache_get( $token_string, 'reepay_tokens' ) );

		//Test cache get
		$num_queries = get_num_queries();

		$token_object = ReepayTokens::get_payment_token( $token_string );

		$this->assertSame( $token_id, $token_object->get_id() );
		$this->assertSame( 3, get_num_queries() - $num_queries  ); // Token cached, so WC_Payment_Tokens::get make 3 requests
	}

	/**
	 * Test @see ReepayTokens::delete_card
	 *
	 * @testWith
	 * [true, false]
	 * [false, true]
	 */
	public function test_delete_cart( bool $api_error, bool $result ) {
		$this->api_mock->method( 'delete_payment_method' )->willReturn( $api_error ? new WP_Error() : array() );

		$token = $this->generate_token( 'reepay' );

		$this->assertSame( $result, ReepayTokens::delete_card( $token ) );

		if ( ! $api_error ) {
			$this->assertSame( 0, $token->get_id() );
		}
	}

	/**
	 * Test @see ReepayTokens::is_reepay_token
	 *
	 * @param bool $generate_token
	 *
	 * @testWith
	 * [null, false]
	 * ["reepay", true]
	 * ["reepay_ms", true]
	 * ["simple", false]
	 */
	public function test_is_reepay_token( ?string $token_type, bool $result ) {
		if( is_null( $token_type ) ) {
			$token = null;
		} else {
			$token = $this->generate_token( $token_type );
		}

		$this->assertSame( $result, ReepayTokens::is_reepay_token( $token ) );
	}
}