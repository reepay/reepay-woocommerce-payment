<?php
/**
 * Class TokenReepayTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase_Trait_Tokens;
use Reepay\Checkout\Tokens\TokenReepay;

/**
 * TokenReepayTest.
 *
 * @covers \Reepay\Checkout\Tokens\TokenReepay
 * @group tokens_token_reepay
 */
class TokenReepayTest extends Reepay_UnitTestCase {
	use Reepay_UnitTestCase_Trait_Tokens;

	// -------------------------------------------------------------------------
	// get_display_name
	// -------------------------------------------------------------------------

	/**
	 * Test @see TokenReepay::get_display_name returns non-empty string with card type set.
	 */
	public function test_get_display_name_with_card_type() {
		$token = $this->generate_token( 'reepay', array( 'card_type' => 'visa' ) );

		$display = $token->get_display_name();

		$this->assertIsString( $display );
		$this->assertNotEmpty( $display );
	}

	/**
	 * Test @see TokenReepay::get_display_name falls back gracefully when card type is unknown/unmapped.
	 */
	public function test_get_display_name_without_card_type() {
		$token = $this->generate_token( 'reepay', array( 'card_type' => 'unknown_type_xyz' ) );

		$display = $token->get_display_name();

		$this->assertIsString( $display );
		$this->assertNotEmpty( $display );
	}

	// -------------------------------------------------------------------------
	// get_card_image_url
	// -------------------------------------------------------------------------

	/**
	 * Test @see TokenReepay::get_card_image_url returns URL containing 'dankort' for visa_dk.
	 */
	public function test_get_card_image_url_known_type_visa_dk() {
		$token = $this->generate_token( 'reepay', array( 'card_type' => 'visa_dk' ) );

		$url = $token->get_card_image_url();

		$this->assertStringContainsString( 'dankort', $url );
	}

	/**
	 * Test @see TokenReepay::get_card_image_url returns a URL for an unknown card type (WC fallback).
	 */
	public function test_get_card_image_url_unknown_type() {
		$token = $this->generate_token( 'reepay', array( 'card_type' => 'unknown_card' ) );

		$url = $token->get_card_image_url();

		$this->assertIsString( $url );
		$this->assertNotEmpty( $url );
		$this->assertStringContainsString( 'unknown_card', $url );
	}

	// -------------------------------------------------------------------------
	// validate
	// -------------------------------------------------------------------------

	/**
	 * Test @see TokenReepay::validate returns true when all required fields are present.
	 */
	public function test_validate_valid_token() {
		$token = $this->generate_token( 'reepay' );

		$this->assertTrue( $token->validate() );
	}

	/**
	 * Test @see TokenReepay::validate returns false when masked_card is empty.
	 */
	public function test_validate_missing_masked_card() {
		$token = new TokenReepay();
		$token->set_gateway_id( reepay()->gateways()->checkout()->id );
		$token->set_token( 'rp_test_validate' );
		$token->set_last4( '9999' );
		$token->set_expiry_year( 2077 );
		$token->set_expiry_month( 12 );
		$token->set_card_type( 'visa' );
		$token->set_user_id( $this->generate_user_for_token() );
		// masked_card intentionally not set.

		$this->assertFalse( $token->validate() );
	}

	// -------------------------------------------------------------------------
	// get_masked_card / set_masked_card
	// -------------------------------------------------------------------------

	/**
	 * Test @see TokenReepay::get_masked_card returns the stored value.
	 */
	public function test_get_masked_card() {
		$masked = rp_format_credit_card( '4111111111111111' );
		$token  = $this->generate_token( 'reepay', array( 'masked_card' => $masked ) );

		$this->assertSame( $masked, $token->get_masked_card() );
	}

	/**
	 * Test @see TokenReepay::set_masked_card persists the masked card value.
	 */
	public function test_set_masked_card() {
		$token      = $this->generate_token( 'reepay' );
		$new_masked = rp_format_credit_card( '5500005555555559' );
		$token->set_masked_card( $new_masked );
		$token->save();

		$reloaded = new TokenReepay( $token->get_id() );
		$this->assertSame( $new_masked, $reloaded->get_masked_card() );
	}

	// -------------------------------------------------------------------------
	// is_default
	// -------------------------------------------------------------------------

	/**
	 * Test @see TokenReepay::is_default returns true when token is marked as default.
	 */
	public function test_is_default_true() {
		$user_id = $this->generate_user_for_token();
		$token   = $this->generate_token( 'reepay', array( 'user_id' => $user_id ) );
		$token->set_default( true );
		$token->save();

		// Reload to confirm persistence.
		$reloaded = new TokenReepay( $token->get_id() );
		$this->assertTrue( $reloaded->is_default() );
	}

	/**
	 * Test @see TokenReepay::is_default returns false when another token is set as default.
	 */
	public function test_is_default_false() {
		$user_id = $this->generate_user_for_token();

		// First token — will be auto-set as default by WooCommerce.
		$first_token = $this->generate_token( 'reepay', array( 'user_id' => $user_id ) );
		$first_token->set_default( true );
		$first_token->save();

		// Second token for the same user should not be default.
		$second_token = $this->generate_token(
			'reepay',
			array(
				'user_id' => $user_id,
				'token'   => 'rp_second_token',
			)
		);
		$second_token->set_default( false );
		$second_token->save();

		$reloaded = new TokenReepay( $second_token->get_id() );
		$this->assertFalse( $reloaded->is_default() );
	}

	// -------------------------------------------------------------------------
	// wc_get_account_saved_payment_methods_list_item
	// -------------------------------------------------------------------------

	/**
	 * Test @see TokenReepay::wc_get_account_saved_payment_methods_list_item enriches item for reepay gateway.
	 */
	public function test_wc_get_account_saved_payment_methods_list_item_reepay_gateway() {
		$token = $this->generate_token( 'reepay', array( 'card_type' => 'visa' ) );

		$item = array(
			'method'  => array(
				'gateway' => reepay()->gateways()->checkout()->id,
			),
			'expires' => '',
		);

		$result = TokenReepay::wc_get_account_saved_payment_methods_list_item( $item, $token );

		$this->assertArrayHasKey( 'method', $result );
		$this->assertArrayHasKey( 'expires', $result );
		$this->assertNotEmpty( $result['expires'] );
	}

	/**
	 * Test @see TokenReepay::wc_get_account_saved_payment_methods_list_item skips non-reepay gateway tokens.
	 */
	public function test_wc_get_account_saved_payment_methods_list_item_non_reepay_gateway() {
		$token = $this->generate_token( 'simple' );

		$item = array(
			'method'  => array( 'gateway' => 'cod' ),
			'expires' => 'unchanged',
		);

		$result = TokenReepay::wc_get_account_saved_payment_methods_list_item( $item, $token );

		// Should be returned unchanged when gateway is not reepay_checkout.
		$this->assertSame( 'unchanged', $result['expires'] );
	}
}
