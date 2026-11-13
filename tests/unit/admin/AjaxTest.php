<?php
/**
 * Class AjaxTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Admin\Ajax;
use Reepay\Checkout\Tests\Helpers\Reepay_Ajax_UnitTestCase;

/**
 * AjaxTest.
 *
 * @covers \Reepay\Checkout\Admin\Ajax
 */
class AjaxTest extends Reepay_Ajax_UnitTestCase {

	/**
	 * Run an admin action and return the decoded JSON response.
	 *
	 * @param string $action ajax action.
	 * @param array  $params request params.
	 *
	 * @return array
	 */
	private function call_action( string $action, array $params ): array {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST            = $params;
		$_POST['nonce']   = wp_create_nonce( 'nonce' );
		$_REQUEST         = $_POST;
		$this->_last_response = '';

		new Ajax();

		$buffer_level = ob_get_level();

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		while ( ob_get_level() > $buffer_level ) {
			ob_end_clean();
		}
		while ( ob_get_level() < $buffer_level ) {
			ob_start();
		}

		// The handlers send JSON inside try/catch ( Exception ); in tests the ajax die exception is
		// caught there and a second JSON object follows the first one. Only the first one counts.
		preg_match( '/^\{.*?\}(?=\{|$)/', $this->_last_response, $first );

		return json_decode( $first[0] ?? '', true );
	}

	/**
	 * Create an order with the given payment method.
	 *
	 * @param string $payment_method payment method id.
	 *
	 * @return WC_Order
	 */
	private function create_order( string $payment_method ): WC_Order {
		$order = wc_create_order();
		$order->set_payment_method( $payment_method );
		$order->set_total( 100 );
		$order->save();

		return $order;
	}

	/**
	 * Parse an amount with the private parser.
	 *
	 * @param string $amount raw amount.
	 *
	 * @return array JSON error response or array with key "amount".
	 */
	private function parse_amount( string $amount ): array {
		$order = $this->create_order( 'reepay_checkout' );

		$_REQUEST['amount'] = $amount;

		$method = new ReflectionMethod( Ajax::class, 'get_amount_or_exit' );
		$method->setAccessible( true );

		add_filter( 'wp_doing_ajax', '__return_true' );

		// The ajax die handler consumes this buffer into $this->_last_response.
		ob_start();
		try {
			$result = array( 'amount' => $method->invoke( new Ajax(), $order ) );
			ob_end_clean();
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
			$result = json_decode( $this->_last_response, true );
		}

		return $result;
	}

	/**
	 * Data for the partial actions.
	 *
	 * @return array
	 */
	public function partial_actions(): array {
		return array(
			'capture' => array( 'reepay_capture_partly' ),
			'refund'  => array( 'reepay_refund_partly' ),
		);
	}

	/**
	 * A missing order id is an error response, not a fatal error.
	 *
	 * @dataProvider partial_actions
	 *
	 * @param string $action ajax action.
	 */
	public function test_missing_order_id_returns_error( string $action ) {
		$response = $this->call_action( $action, array( 'amount' => '10.00' ) );

		$this->assertFalse( $response['success'] );
	}

	/**
	 * An id that is not an order is an error response, not a TypeError.
	 *
	 * @dataProvider partial_actions
	 *
	 * @param string $action ajax action.
	 */
	public function test_non_order_id_returns_error( string $action ) {
		$response = $this->call_action(
			$action,
			array(
				'order_id' => 99999999,
				'amount'   => '10.00',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid order', $response['data'] );
	}

	/**
	 * An order paid with another gateway is rejected.
	 *
	 * @dataProvider partial_actions
	 *
	 * @param string $action ajax action.
	 */
	public function test_non_reepay_order_returns_error( string $action ) {
		$order = $this->create_order( 'bacs' );

		$response = $this->call_action(
			$action,
			array(
				'order_id' => $order->get_id(),
				'amount'   => '10.00',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Order not paid via Reepay', $response['data'] );
	}

	/**
	 * Register a gateway mock expecting one refund_payment() call.
	 *
	 * @param mixed $expected_amount amount refund_payment() must receive.
	 */
	private function expect_gateway_refund( $expected_amount ) {
		$gateway = $this->getMockBuilder( stdClass::class )->addMethods( array( 'refund_payment' ) )->getMock();
		$gateway->expects( $this->once() )
			->method( 'refund_payment' )
			->with( $this->isInstanceOf( WC_Order::class ), $expected_amount );

		$gateway->id = 'reepay_checkout';
		WC()->payment_gateways()->payment_gateways['reepay_checkout'] = $gateway;
	}

	/**
	 * The full refund handler keeps the decimals of a supplied amount.
	 */
	public function test_refund_keeps_decimals_of_amount() {
		$this->expect_gateway_refund( 10.5 );
		$order = $this->create_order( 'reepay_checkout' );

		$response = $this->call_action(
			'reepay_refund',
			array(
				'order_id' => $order->get_id(),
				'amount'   => '10.50',
			)
		);

		$this->assertTrue( $response["success"], wp_json_encode( $response ) );
	}

	/**
	 * The full refund handler still refunds the whole order when no amount is sent.
	 */
	public function test_refund_without_amount_is_full_refund() {
		$this->expect_gateway_refund( false );
		$order = $this->create_order( 'reepay_checkout' );

		$response = $this->call_action( 'reepay_refund', array( 'order_id' => $order->get_id() ) );

		$this->assertTrue( $response["success"], wp_json_encode( $response ) );
	}

	/**
	 * Both spellings of the same amount are read as 10.50.
	 */
	public function test_amount_formats_give_same_amount() {
		$this->assertSame( 10.5, $this->parse_amount( '10.5' )['amount'] );
		$this->assertSame( 10.5, $this->parse_amount( '10.50' )['amount'] );
		$this->assertSame( 1000.5, $this->parse_amount( '1000.50' )['amount'] );
	}

	/**
	 * Empty, non-numeric, zero and negative amounts are rejected.
	 *
	 * @dataProvider invalid_amounts
	 *
	 * @param string $amount raw amount.
	 */
	public function test_invalid_amounts_are_rejected( string $amount ) {
		$result = $this->parse_amount( $amount );

		$this->assertArrayNotHasKey( 'amount', $result );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Invalid amounts.
	 *
	 * @return array
	 */
	public function invalid_amounts(): array {
		return array(
			'empty'    => array( '' ),
			'text'     => array( 'abc' ),
			'zero'     => array( '0' ),
			'zero 2dp' => array( '0.00' ),
			'negative' => array( '-5' ),
		);
	}

	/**
	 * The Refund button is hidden once the whole amount of a Reepay order is refunded,
	 * and stays for other gateways and for orders with a remaining amount.
	 */
	public function test_refund_button_hidden_only_when_reepay_order_fully_refunded() {
		$handler = new Reepay\Checkout\Admin\MetaBoxes\Order();

		$reepay_order = $this->create_order( 'reepay_checkout' );
		$this->assertTrue( $handler->hide_refund_button_when_fully_refunded( true, $reepay_order->get_id(), $reepay_order ) );

		wc_create_refund(
			array(
				'amount'         => 100,
				'order_id'       => $reepay_order->get_id(),
				'refund_payment' => false,
			)
		);
		$reepay_order = wc_get_order( $reepay_order->get_id() );
		$this->assertFalse( $handler->hide_refund_button_when_fully_refunded( true, $reepay_order->get_id(), $reepay_order ) );

		$other_order = $this->create_order( 'bacs' );
		wc_create_refund(
			array(
				'amount'         => 100,
				'order_id'       => $other_order->get_id(),
				'refund_payment' => false,
			)
		);
		$other_order = wc_get_order( $other_order->get_id() );
		$this->assertTrue( $handler->hide_refund_button_when_fully_refunded( true, $other_order->get_id(), $other_order ) );
	}
}
