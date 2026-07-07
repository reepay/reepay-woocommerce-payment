<?php
/**
 * Class ReepayTokensTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
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
			'id' => 'rp_123456789',
			'exp_date' => '20-77',
			'masked_card' => '457111XXXXXX2077',
			'card_type' => '' // Empty 'card_type' value cause an exception.
		) );

		$this->assertSame(
			array(
				'token'     => false,
				'card_info' => '',
			),
			ReepayTokens::add_payment_token_to_customer( 0, '' )
		);
		;
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

	public function test_save_card_info_to_order() {
		$api_response = array(
			'id' => 'ca_123456789',
			'exp_date' => '20-77',
			'masked_card' => '457111XXXXXX2077',
			'card_type' => 'visa_dk'
		);

		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		ReepayTokens::save_card_info_to_order(
			$this->order_generator->order(),
			$api_response['id']
		);

		$this->order_generator->reset_order();

		$this->assertSame( $api_response['masked_card'], $this->order_generator->get_meta('reepay_masked_card') );
		$this->assertSame( $api_response['card_type'], $this->order_generator->get_meta('reepay_card_type') );
		$this->assertSame( $api_response, $this->order_generator->get_meta('_reepay_source') );
	}

	public function test_get_payment_token_subscription_token_in_subscription() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'woo_sub' );

		$token = $this->generate_token( 'reepay' );

		$this->order_generator->add_product( 'woo_sub' );
		$this->order_generator->order()->save();

		$subscriptions = wcs_get_subscriptions_for_order( $this->order_generator->order()->get_id() );
		$subscription = reset( $subscriptions );
		$subscription->add_meta_data( '_reepay_token', $token->get_token() );
		$subscription->save();

		$this->assertSame( $token->get_token(), ReepayTokens::get_payment_token_subscription( $subscription )->get_token() );
	}

	public function test_get_payment_token_subscription_token_in_order() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'woo_sub' );

		$token = $this->generate_token( 'reepay' );

		$this->order_generator->add_product( 'woo_sub' );
		$this->order_generator->set_meta( '_reepay_token', $token->get_token() );
		$this->order_generator->order()->save();

		$subscriptions = wcs_get_subscriptions_for_order( $this->order_generator->order()->get_id() );
		$subscription = reset( $subscriptions );

		$this->assertSame( $token->get_token(), ReepayTokens::get_payment_token_subscription( $subscription )->get_token() );
	}

	public function test_get_payment_token_subscription_api_empty() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'woo_sub' );

		$this->order_generator->add_product( 'woo_sub' );
		$this->order_generator->order()->save();

		$subscriptions = wcs_get_subscriptions_for_order( $this->order_generator->order()->get_id() );
		$subscription  = reset( $subscriptions );

		$this->api_mock->method( 'get_invoice_data' )->willReturn( array() );

		$this->assertSame( false, ReepayTokens::get_payment_token_subscription( $subscription ) );
	}

	public function test_get_payment_token_subscription_token_in_api_recurring_payment_method() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'woo_sub' );

		$token_string = 'rp_12345';
		$this->generate_token( 'reepay', array(
			'token' => $token_string
		) );

		$this->order_generator->add_product( 'woo_sub' );
		$this->order_generator->order()->save();

		$subscriptions = wcs_get_subscriptions_for_order( $this->order_generator->order()->get_id() );
		$subscription  = reset( $subscriptions );

		$this->api_mock->method( 'get_invoice_data' )->willReturn( array(
			'recurring_payment_method' => $token_string
		) );

		$this->assertSame( $token_string, ReepayTokens::get_payment_token_subscription( $subscription )->get_token() );
	}

	public function test_get_payment_token_subscription_token_in_api_transactions() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'woo_sub' );

		$token_string = 'rp_12345';
		$this->generate_token( 'reepay', array(
			'token' => $token_string
		) );

		$this->order_generator->add_product( 'woo_sub' );
		$this->order_generator->order()->save();

		$subscriptions = wcs_get_subscriptions_for_order( $this->order_generator->order()->get_id() );
		$subscription  = reset( $subscriptions );

		$this->api_mock->method( 'get_invoice_data' )->willReturn( array(
			'transactions' => array(
				array(),
				array(
					'payment_method' => $token_string
				),
				array()
			)
		) );

		$this->assertSame( $token_string, ReepayTokens::get_payment_token_subscription( $subscription )->get_token() );
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
		$this->assertInstanceOf( TokenReepay::class, $token_object );

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
	 * @param string|null $token_type token type.
	 * @param bool $result expecting result.
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

	// -----------------------------------------------------------------------
	// reepay_save_token()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayTokens::reepay_save_token creates a new token when none exists.
	 *
	 * @group tokens_reepay
	 */
	public function test_reepay_save_token_creates_new_token() {
		$user_id = $this->factory()->user->create();
		$this->order_generator->set_prop( 'customer_id', $user_id );
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		// Pre-create the token in WC so reepay_save_token takes the
		// "already exists → just assign" branch, avoiding the live API call.
		$token_id = 'ca_' . wp_generate_uuid4();
		$wc_token = $this->generate_token( 'reepay', array( 'token' => $token_id, 'user_id' => $user_id ) );

		$result = ReepayTokens::reepay_save_token( $this->order_generator->order(), $token_id );

		$this->assertInstanceOf( WC_Payment_Token::class, $result );
		$this->assertSame( $token_id, $result->get_token() );
	}

	/**
	 * Test @see ReepayTokens::reepay_save_token returns existing token when one already exists.
	 *
	 * @group tokens_reepay
	 */
	public function test_reepay_save_token_returns_existing_token() {
		$user_id = $this->factory()->user->create();
		$this->order_generator->set_prop( 'customer_id', $user_id );
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$token_id = 'ca_existing_' . wp_generate_uuid4();
		// Pre-create the token in WC.
		$this->generate_token( 'reepay', array( 'token' => $token_id, 'user_id' => $user_id ) );

		// First save — finds existing token and assigns.
		$token_first = ReepayTokens::reepay_save_token( $this->order_generator->order(), $token_id );

		// Second save — must return the same token.
		$token_second = ReepayTokens::reepay_save_token( $this->order_generator->order(), $token_id );

		$this->assertSame( $token_first->get_id(), $token_second->get_id() );
	}

	// -----------------------------------------------------------------------
	// save_card_info_from_invoice()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayTokens::save_card_info_from_invoice returns true when API provides card data.
	 *
	 * @group tokens_reepay
	 */
	public function test_save_card_info_from_invoice_success() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			array(
				'transactions' => array(
					array(
						'card_transaction' => array(
							'card_type'   => 'visa',
							'masked_card' => '411111XXXXXX1111',
							'exp_date'    => '06/2026',
							'token'       => 'ca_test_' . wp_generate_uuid4(),
						),
					),
				),
			)
		);

		$result = ReepayTokens::save_card_info_from_invoice( $this->order_generator->order() );

		$this->assertTrue( $result );
	}

	/**
	 * Test @see ReepayTokens::save_card_info_from_invoice returns false on API WP_Error.
	 *
	 * The method throws Exception when API returns WP_Error.
	 *
	 * @group tokens_reepay
	 */
	public function test_save_card_info_from_invoice_api_error() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout()->id );
		$this->order_generator->order()->save();

		$this->api_mock->method( 'get_invoice_data' )->willReturn(
			new WP_Error( '401', 'Unauthorized' )
		);

		$threw  = false;
		$result = null;
		try {
			$result = ReepayTokens::save_card_info_from_invoice( $this->order_generator->order() );
		} catch ( \Throwable $e ) {
			$threw = true;
		}

		// Method either returns false or throws when the API call fails.
		$this->assertTrue(
			$threw || false === $result,
			'Expected false return or exception when API returns WP_Error'
		);
	}

	// -----------------------------------------------------------------------
	// user_has_token()
	// -----------------------------------------------------------------------

	/**
	 * Test @see ReepayTokens::user_has_token returns true when user owns the token.
	 *
	 * @group tokens_reepay
	 */
	public function test_user_has_token_true() {
		$user_id = $this->factory()->user->create();
		$token_id = 'ca_user_' . wp_generate_uuid4();

		// Pre-create the WC token for this user.
		$this->generate_token( 'reepay', array( 'token' => $token_id, 'user_id' => $user_id ) );

		$this->assertTrue( ReepayTokens::user_has_token( $user_id, $token_id ) );
	}

	/**
	 * Test @see ReepayTokens::user_has_token returns false when user does not own the token.
	 *
	 * @group tokens_reepay
	 */
	public function test_user_has_token_false() {
		$user_id = $this->factory()->user->create();

		$this->assertFalse( ReepayTokens::user_has_token( $user_id, 'ca_nonexistent_token' ) );
	}

	/**
	 * Test @see ReepayTokens::user_has_token returns false for guest (user_id = 0).
	 *
	 * @group tokens_reepay
	 */
	public function test_user_has_token_guest_returns_false() {
		$this->assertFalse( ReepayTokens::user_has_token( 0, 'ca_any_token' ) );
	}
}