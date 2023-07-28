<?php
/**
 * Status processing in reepay after payment on the thankyou page
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use Reepay\Checkout\LoggingTrait;
use WC_Order;
use WC_Order_Item_Product;
use WC_Subscriptions_Product;

defined( 'ABSPATH' ) || exit();

/**
 * Class ThankyouPage
 *
 * @package Reepay\Checkout\OrderFlow
 */
class ThankyouPage {
	use LoggingTrait;

	/**
	 * Logging source
	 *
	 * @var string
	 */
	private string $logging_source = 'reepay-thankyou';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 5, 20 );

		add_action( 'wp_enqueue_scripts', array( $this, 'thankyou_scripts' ) );

		add_action( 'wp_ajax_reepay_check_payment', array( $this, 'ajax_check_payment' ) );
		add_action( 'wp_ajax_nopriv_reepay_check_payment', array( $this, 'ajax_check_payment' ) );
	}

	/**
	 * Override "checkout/thankyou.php" template.
	 *
	 * Some plugins send variables with the wrong types to this filter, so the types have been removed to avoid errors.
	 *
	 * @param string $located       path for inclusion.
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return string
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function override_template( $located, $template_name, $args, $template_path, $default_path ): string {
		if ( ! is_array( $args ) || ! is_string( $template_path ) ) {
			return $located;
		}

		if ( strpos( $located, 'checkout/thankyou.php' ) !== false ) {
			if ( ! isset( $args['order'] ) ) {
				return $located;
			}

			$order = wc_get_order( $args['order'] );
			if ( ! $order ) {
				return $located;
			}

			if ( ! rp_is_order_paid_via_reepay( $order ) ) {
				return $located;
			}

			$located = wc_locate_template(
				'checkout/thankyou.php',
				$template_path,
				reepay()->get_setting( 'templates_path' )
			);
		}

		return $located;
	}

	/**
	 * Outputs scripts used for "thankyou" page
	 *
	 * @return void
	 */
	public function thankyou_scripts() {
		if ( ! is_order_received_page() ) {
			return;
		}

		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		$order = wc_get_order( get_query_var( 'order-received', 0 ) );

		if ( empty( $order )
			 || ! $order->key_is_valid( $order_key )
			 || ! rp_is_order_paid_via_reepay( $order )
		) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'wc-gateway-reepay-thankyou',
			reepay()->get_setting( 'js_url' ) . 'thankyou' . $suffix . '.js',
			array(
				'jquery',
				'jquery-blockui',
			),
			reepay()->get_setting( 'plugin_version' ),
			true
		);

		wp_localize_script(
			'wc-gateway-reepay-thankyou',
			'WC_Reepay_Thankyou',
			array(
				'order_id'      => $order->get_id(),
				'order_key'     => $order_key,
				'nonce'         => wp_create_nonce( 'reepay' ),
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'check_message' => __(
					'Please wait. We\'re checking the payment status.',
					'reepay-checkout-gateway'
				),
			)
		);
	}

	/**
	 * Ajax: Check the payment
	 */
	public function ajax_check_payment() {
		check_ajax_referer( 'reepay', 'nonce' );

		$order_id  = isset( $_POST['order_id'] ) ? wc_clean( $_POST['order_id'] ) : '';
		$order_key = isset( $_POST['order_key'] ) ? wc_clean( $_POST['order_key'] ) : '';

		if ( empty( $order_id ) || empty( $order_key ) ) {
			wp_send_json_error( 'Invalid order' );
		}

		$order = wc_get_order( $order_id );
		if ( empty( $order ) || ! $order->key_is_valid( $order_key ) ) {
			wp_send_json_error( 'Invalid order' );
		}

		if ( class_exists( WC_Subscriptions_Product::class, false ) ) {
			foreach ( $order->get_items() as $item ) {
				/**
				 * WC_Order_Item_Product returns not WC_Order_Item
				 *
				 * @var WC_Order_Item_Product $item
				 */
				if ( wcs_is_subscription_product( $item->get_product() ) ) {
					if ( $order->get_total() <= 0 ) {
						$ret = array(
							'state'   => 'paid',
							'message' => 'Subscription is activated in trial',
						);

						wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
					}
				}
			}
		}

		$ret = array();

		$result = reepay()->api( $order )->get_invoice_data( $order );

		if ( is_wp_error( $result ) ) {
			// No information.
			$ret = array(
				'state' => 'unknown',
			);

			wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
		}

		switch ( $result['state'] ) {
			case 'pending':
				$ret = array(
					'state' => 'pending',
				);
				break;
			case 'authorized':
			case 'settled':
				$ret = array(
					'state'   => 'paid',
					'message' => 'Order has been paid',
				);

				break;
			case 'cancelled':
				$ret = array(
					'state'   => 'failed',
					'message' => 'Order has been cancelled',
				);

				break;
			case 'failed':
				$message = 'Order has been failed';

				if ( count( $result['transactions'] ) > 0 &&
					 isset( $result['transactions'][0]['card_transaction']['acquirer_message'] )
				) {
					$message = $result['transactions'][0]['card_transaction']['acquirer_message'];
				}

				$ret = array(
					'state'   => 'failed',
					'message' => $message,
				);

				break;
		}

		wp_send_json_success( apply_filters( 'woocommerce_reepay_check_payment', $ret, $order->get_id() ) );
	}
}
