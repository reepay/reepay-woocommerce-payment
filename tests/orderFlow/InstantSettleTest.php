<?php
/**
 * Class InstantSettle
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\InstantSettle;

/**
 * InstantSettle.
 */
class InstantSettleTest extends WP_UnitTestCase {
	private RpTestOptions $options;
	private RpTestProductGenerator $product_generator;
	private RpTestOrderGenerator $order_generator;

	public function set_up() {
		$this->options           = new RpTestOptions();
		$this->product_generator = new RpTestProductGenerator();
		$this->order_generator   = new RpTestOrderGenerator();

		parent::set_up();
	}

	/**
	 * @param string $type
	 * @param bool   $virtual
	 * @param bool   $downloadable
	 * @param bool   $result
	 *
	 * @testWith
	 * ["simple", false, false, true]
	 * ["simple", true, false, false]
	 * ["simple", false, true, false]
	 * ["simple", true, true, false]
	 * ["woo_sub", false, false, false]
	 * ["woo_sub", true, false, false]
	 * ["woo_sub", false, true, false]
	 * ["woo_sub", true, true, false]
	 * ["rp_sub", false, false, true]
	 * ["rp_sub", true, false, false]
	 * ["rp_sub", false, true, false]
	 * ["rp_sub", true, true, false]
	 *
	 */
	public function test_can_product_be_settled_instantly_physical( string $type, bool $virtual, bool $downloadable, bool $result ) {
		RP_TEST_PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		$this->options->set_option( 'settle', array(
			InstantSettle::SETTLE_PHYSICAL
		) );

		$product = $this->product_generator->generate(
			$type,
			array(
				'virtual'      => $virtual,
				'downloadable' => $downloadable,
			)
		);

		$this->assertSame( $result, InstantSettle::can_product_be_settled_instantly( $product ) );
	}

	/**
	 * @param string $type
	 * @param bool   $virtual
	 * @param bool   $downloadable
	 * @param bool   $result
	 *
	 * @testWith
	 * ["simple", false, false, false]
	 * ["simple", true, false, true]
	 * ["simple", false, true, true]
	 * ["simple", true, true, true]
	 * ["woo_sub", false, false, false]
	 * ["woo_sub", true, false, false]
	 * ["woo_sub", false, true, false]
	 * ["woo_sub", true, true, false]
	 * ["rp_sub", false, false, false]
	 * ["rp_sub", true, false, true]
	 * ["rp_sub", false, true, true]
	 * ["rp_sub", true, true, true]
	 *
	 */
	public function test_can_product_be_settled_instantly_virtual( string $type, bool $virtual, bool $downloadable, bool $result ) {
		RP_TEST_PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		$this->options->set_option( 'settle', array(
			InstantSettle::SETTLE_VIRTUAL
		) );

		$product = $this->product_generator->generate(
			$type,
			array(
				'virtual'      => $virtual,
				'downloadable' => $downloadable,
			)
		);

		$this->assertSame( $result, InstantSettle::can_product_be_settled_instantly( $product ) );
	}

	/**
	 * @param string $type
	 * @param bool   $result
	 *
	 * @testWith
	 * ["simple", false]
	 * ["woo_sub", true]
	 * ["rp_sub", false]
	 *
	 */
	public function test_can_product_be_settled_instantly_recurring( string $type, bool $result ) {
		RP_TEST_PLUGINS_STATE::maybe_skip_test_by_product_type( $type );

		$this->options->set_option( 'settle', array(
			InstantSettle::SETTLE_RECURRING
		) );

		$product = $this->product_generator->generate( $type );

		$this->assertSame( $result, InstantSettle::can_product_be_settled_instantly( $product ) );
	}
}