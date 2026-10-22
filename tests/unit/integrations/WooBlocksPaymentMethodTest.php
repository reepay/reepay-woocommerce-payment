<?php
/**
 * Class WooBlocksPaymentMethodTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Integrations\WooBlocks\WooBlocksPaymentMethod;
use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;

/**
 * WooBlocksPaymentMethodTest.
 *
 * Covers the block checkout payment method data, in particular the 'logos'
 * key added for BWSM-113 so Frisbii payment logos render dynamically on the
 * WooCommerce block checkout the same way they already do on classic checkout.
 *
 * @covers \Reepay\Checkout\Integrations\WooBlocks\WooBlocksPaymentMethod
 * @group integrations_woo_blocks
 */
class WooBlocksPaymentMethodTest extends Reepay_UnitTestCase {
	/**
	 * Logos of the reepay_checkout gateway singleton, saved before each test and
	 * restored after, since tests mutate the public property directly.
	 *
	 * @var array
	 */
	private array $original_checkout_logos;

	/**
	 * Runs before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_checkout_logos = reepay()->gateways()->get_gateway( 'reepay_checkout' )->logos;
	}

	/**
	 * Restores gateway logos mutated by tests so later tests/suites aren't affected.
	 */
	public function tear_down() {
		reepay()->gateways()->get_gateway( 'reepay_checkout' )->logos = $this->original_checkout_logos;

		parent::tear_down();
	}

	/**
	 * Builds an initialized WooBlocksPaymentMethod for the given gateway id.
	 *
	 * @param string $name Gateway id.
	 *
	 * @return WooBlocksPaymentMethod
	 * @throws Exception If gateway not found.
	 */
	private function build_payment_method( string $name ): WooBlocksPaymentMethod {
		$payment_method = new WooBlocksPaymentMethod( $name );
		$payment_method->initialize();

		return $payment_method;
	}

	// -----------------------------------------------------------------------
	// Constructor
	// -----------------------------------------------------------------------

	/**
	 * Test @see WooBlocksPaymentMethod::__construct throws for an unknown gateway id.
	 *
	 * @group integrations_woo_blocks
	 */
	public function test_constructor_throws_for_unknown_gateway() {
		$this->expectException( Exception::class );

		new WooBlocksPaymentMethod( 'reepay_not_a_real_gateway' );
	}

	// -----------------------------------------------------------------------
	// get_payment_method_data() - logos (BWSM-113)
	// -----------------------------------------------------------------------

	/**
	 * Test @see WooBlocksPaymentMethod::get_payment_method_data includes a 'logos'
	 * key built from the gateway's configured logos, so the block checkout can
	 * render payment method icons the same way classic checkout does.
	 *
	 * @group integrations_woo_blocks
	 */
	public function test_get_payment_method_data_includes_logos_for_configured_gateway() {
		$gateway        = reepay()->gateways()->get_gateway( 'reepay_checkout' );
		$gateway->logos = array( 'visa', 'mastercard' );

		$data = $this->build_payment_method( 'reepay_checkout' )->get_payment_method_data();

		$this->assertArrayHasKey( 'logos', $data );
		$this->assertCount( 2, $data['logos'] );

		$this->assertStringEndsWith( 'svg/visa.logo.svg', $data['logos'][0]['src'] );
		$this->assertStringEndsWith( 'svg/mastercard.logo.svg', $data['logos'][1]['src'] );

		foreach ( $data['logos'] as $logo ) {
			$this->assertArrayHasKey( 'src', $logo );
			$this->assertArrayHasKey( 'alt', $logo );
			$this->assertNotEmpty( $logo['alt'] );
		}
	}

	/**
	 * Test @see WooBlocksPaymentMethod::get_payment_method_data returns an empty
	 * 'logos' array when the gateway has no logos configured, instead of omitting
	 * the key or erroring — the block checkout script treats an empty array as
	 * "render no logos, fall back to the plain label".
	 *
	 * @group integrations_woo_blocks
	 */
	public function test_get_payment_method_data_logos_empty_when_none_configured() {
		$gateway        = reepay()->gateways()->get_gateway( 'reepay_checkout' );
		$gateway->logos = array();

		$data = $this->build_payment_method( 'reepay_checkout' )->get_payment_method_data();

		$this->assertArrayHasKey( 'logos', $data );
		$this->assertSame( array(), $data['logos'] );
	}

	/**
	 * Test @see WooBlocksPaymentMethod::get_payment_method_data filters out empty
	 * entries from the gateway's logos list rather than producing broken <img> tags.
	 *
	 * @group integrations_woo_blocks
	 */
	public function test_get_payment_method_data_logos_filters_empty_entries() {
		$gateway        = reepay()->gateways()->get_gateway( 'reepay_checkout' );
		$gateway->logos = array( 'visa', '', 'mastercard' );

		$data = $this->build_payment_method( 'reepay_checkout' )->get_payment_method_data();

		$this->assertCount( 2, $data['logos'] );
		$this->assertStringEndsWith( 'svg/visa.logo.svg', $data['logos'][0]['src'] );
		$this->assertStringEndsWith( 'svg/mastercard.logo.svg', $data['logos'][1]['src'] );
	}

	/**
	 * Test @see WooBlocksPaymentMethod::get_payment_method_data uses the gateway's
	 * own fixed logos (not the user-configurable "Payment Logos" setting, which only
	 * exists on reepay_checkout) for sub-method gateways like Vipps — matching
	 * classic checkout's ReepayGateway::get_icon() behavior.
	 *
	 * @group integrations_woo_blocks
	 */
	public function test_get_payment_method_data_logos_for_fixed_logo_gateway() {
		$data = $this->build_payment_method( 'reepay_vipps' )->get_payment_method_data();

		$this->assertCount( 1, $data['logos'] );
		$this->assertStringEndsWith( 'svg/vipps.logo.svg', $data['logos'][0]['src'] );
		$this->assertStringContainsString( 'Vipps', $data['logos'][0]['alt'] );
	}

	/**
	 * Test @see WooBlocksPaymentMethod::get_payment_method_data alt text references
	 * the gateway's own title for each logo entry, so screen readers announce which
	 * payment method the logos belong to.
	 *
	 * @group integrations_woo_blocks
	 */
	public function test_get_payment_method_data_logo_alt_references_gateway_title() {
		$gateway = reepay()->gateways()->get_gateway( 'reepay_vipps' );

		$data = $this->build_payment_method( 'reepay_vipps' )->get_payment_method_data();

		$this->assertStringContainsString( $gateway->get_title(), $data['logos'][0]['alt'] );
	}
}
