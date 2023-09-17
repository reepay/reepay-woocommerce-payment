<?php
/**
 * Class MainTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase_Trait_Tokens;
use Reepay\Checkout\Tokens\Main;
use Reepay\Checkout\Tokens\TokenReepay;
use Reepay\Checkout\Tokens\TokenReepayMS;

/**
 * MainTest.
 *
 * @covers \Reepay\Checkout\Tokens\Main
 */
class MainTest extends Reepay_UnitTestCase {
	use Reepay_UnitTestCase_Trait_Tokens;

	private Main $tokens_main;

	public function set_up() {
		parent::set_up();

		$this->tokens_main = new Main();
	}

	/**
	 * Test @see Main::set_token_class_name
	 */
	public function test_set_token_class_name_checkout() {
		$this->assertSame(
			TokenReepay::class,
			$this->tokens_main->set_token_class_name( 'WC_Payment_Token_Reepay' )
		);
	}

	/**
	 * Test @see Main::set_token_class_name
	 */
	public function test_set_token_class_name_ms() {
		$this->assertSame(
			TokenReepayMS::class,
			$this->tokens_main->set_token_class_name( 'WC_Payment_Token_Reepay_MS' )
		);
	}

	/**
	 * Test @see Main::set_token_class_name
	 */
	public function test_set_token_class_name_other() {
		$this->assertSame(
			WC_Payment_Token_Other::class,
			$this->tokens_main->set_token_class_name( 'WC_Payment_Token_Other' )
		);
	}

	/**
	 * Test @see Main::add_reepay_cards_to_list
	 */
	public function test_add_reepay_cards_to_list_with_non_reepay_gateway() {
		$token = $this->generate_token( 'simple' );
		$tokens = array( $token->get_id() => $token );

		$this->assertSame(
			$tokens,
			$this->tokens_main->add_reepay_cards_to_list( $tokens, 0, 'cod' )
		);
	}

	/**
	 * Test @see Main::add_reepay_cards_to_list
	 */
	public function test_add_reepay_cards_to_list_with_api_error() {
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( new WP_Error() );

		$token = $this->generate_token( 'simple' );
		$tokens = array( $token->get_id() => $token );

		$this->assertSame(
			$tokens,
			$this->tokens_main->add_reepay_cards_to_list( $tokens, 0, 'cod' )
		);
	}

	/**
	 * Test @see Main::add_reepay_cards_to_list
	 */
	public function test_add_reepay_cards_to_list_with_empty_api_cards() {
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( array() );

		$token = $this->generate_token( 'simple' );
		$tokens = array( $token->get_id() => $token );

		$this->assertSame(
			$tokens,
			$this->tokens_main->add_reepay_cards_to_list( $tokens, 0, 'cod' )
		);
	}


	/**
	 * Test @see Main::add_reepay_cards_to_list
	 */
	public function test_add_reepay_cards_to_list_reepay_gateway_already_saved_token() {
		$customer_id = $this->generate_user_for_token();

		$token_reepay_string = 'rp_1234567890';
		$this->generate_token( 'reepay', array(
			'token' => $token_reepay_string,
			'user_id' => $customer_id
		) );

		$api_response = array(
			array( 'id' => $token_reepay_string )
		);
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		$result_tokens = $this->tokens_main->add_reepay_cards_to_list(
			array(
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) ),
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) )
			),
			0,
			reepay()->gateways()->checkout()->id
		);

		$this->assertSame( 1, count( $result_tokens ) );
		$result_token = current( $result_tokens );
		$this->assertInstanceOf( TokenReepay::class, $result_token );
		$this->assertSame( $token_reepay_string, $result_token->get_token() );
		$this->assertSame( 3, count( WC_Payment_Tokens::get_customer_tokens( $customer_id ) ) );
	}

	/**
	 * Test @see Main::add_reepay_cards_to_list
	 */
	public function test_add_reepay_cards_to_list_reepay_gateway_save_token() {
		$customer_id = $this->generate_user_for_token();

		$token_reepay_string = 'rp_1234567890';

		$api_response = array(
			array(
				'id'          => $token_reepay_string,
				'exp_date'    => '20-77',
				'masked_card' => '457111XXXXXX2077',
				'card_type'   => 'visa_dk'
			)
		);
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		$result_tokens = $this->tokens_main->add_reepay_cards_to_list(
			array(
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) ),
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) )
			),
			0,
			reepay()->gateways()->checkout()->id
		);

		$this->assertSame( 1, count( $result_tokens ) );
		$result_token = current( $result_tokens );
		$this->assertInstanceOf( TokenReepay::class, $result_token );
		$this->assertSame( $token_reepay_string, $result_token->get_token() );
		$this->assertSame( 3, count( WC_Payment_Tokens::get_customer_tokens( $customer_id ) ) );
	}

	/**
	 * Test @see Main::add_reepay_cards_to_list
	 */
	public function test_add_reepay_cards_to_list_ms_gateway_already_saved_token() {
		$customer_id = $this->generate_user_for_token();

		$token_reepay_string = 'ms_1234567890';
		$this->generate_token( 'reepay_ms', array(
			'token' => $token_reepay_string,
			'user_id' => $customer_id
		) );

		$api_response = array(
			array( 'id' => $token_reepay_string )
		);
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		$result_tokens = $this->tokens_main->add_reepay_cards_to_list(
			array(
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) ),
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) )
			),
			0,
			reepay()->gateways()->checkout()->id
		);

		$this->assertSame( 1, count( $result_tokens ) );
		$result_token = current( $result_tokens );
		$this->assertInstanceOf( TokenReepayMS::class, $result_token );
		$this->assertSame( $token_reepay_string, $result_token->get_token() );
		$this->assertSame( 3, count( WC_Payment_Tokens::get_customer_tokens( $customer_id ) ) );
	}

	/**
	 * Test @see Main::add_reepay_cards_to_list
	 */
	public function test_add_reepay_cards_to_list_ms_gateway_save_token() {
		$customer_id = $this->generate_user_for_token();

		$token_reepay_string = 'ms_1234567890';

		$api_response = array( array( 'id' => $token_reepay_string ) );
		$this->api_mock->method( 'get_reepay_cards' )->willReturn( $api_response );

		$result_tokens = $this->tokens_main->add_reepay_cards_to_list(
			array(
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) ),
				$this->generate_token( 'simple', array( 'user_id' => $customer_id ) )
			),
			0,
			reepay()->gateways()->checkout()->id
		);

		$this->assertSame( 1, count( $result_tokens ) );
		$result_token = current( $result_tokens );
		$this->assertInstanceOf( TokenReepayMS::class, $result_token );
		$this->assertSame( $token_reepay_string, $result_token->get_token() );
		$this->assertSame( 3, count( WC_Payment_Tokens::get_customer_tokens( $customer_id ) ) );
	}
}