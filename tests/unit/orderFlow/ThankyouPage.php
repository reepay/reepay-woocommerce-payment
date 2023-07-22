<?php
/**
 * Class ThankyouPage
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Tests\Helpers\Reepay_UnitTestCase;


/**
 * ThankyouPage.
 *
 * @covers \Reepay\Checkout\OrderFlow\InstantSettle
 */
class ThankyouPage extends Reepay_UnitTestCase {
	public function test_override_template() {
		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		$args = array(
			'checkout/thankyou.php',
			'',
			array(
				'order' => $this->order_generator->order()->get_id()
			),
			'',
			''
		);

		$path = ( new \Reepay\Checkout\OrderFlow\ThankyouPage() )->override_template( ...$args );

		$this->assertSame(
			reepay()->get_setting( 'templates_path' ) . 'checkout/thankyou.php',
			$path
		);

		$this->assertSame(
			reepay()->get_setting( 'templates_path' ) . 'checkout/thankyou.php',
			apply_filters( 'wc_get_template', ...$args )
		);
	}
}
