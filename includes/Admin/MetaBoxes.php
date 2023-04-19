<?php
/**
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

use Exception;
use WP_Post;

defined( 'ABSPATH' ) || exit();

/**
 * Class MetaBoxes
 *
 * @package Reepay\Checkout\Admin
 */
class MetaBoxes {

	/**
	 * @var string
	 */
	private $dashboard_url = 'https://app.reepay.com/#/rp/';

	/**
	 * MetaBoxes constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Register meta boxes on order page
	 */
	public function add_meta_boxes() {
		global $post;

		$screen     = get_current_screen();
		$post_types = array( 'shop_order', 'shop_subscription' );

		if ( ! in_array( $screen->id, $post_types, true ) || ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$order = wc_get_order( $post->ID );

		if ( empty( $order ) ) {
			return;
		}

		if ( ! empty( get_post_meta( $post->ID, '_reepay_order', true ) ) && 0 !== $post->post_parent ) {
			$subscription = get_post_meta( $post->post_parent, '_reepay_subscription_handle', true );
		} else {
			$subscription = get_post_meta( $post->ID, '_reepay_subscription_handle', true );
		}

		add_meta_box(
			'reepay_checkout_customer',
			__( 'Customer' ),
			array( $this, 'generate_meta_box_content_customer' ),
			'shop_order',
			'side',
			'high'
		);

		if ( empty( $subscription ) || ( 0 !== $post->post_parent ) ) {
			add_meta_box(
				'reepay_checkout_invoice',
				__( 'Invoice' ),
				array( $this, 'generate_meta_box_content_invoice' ),
				'shop_order',
				'side',
				'high'
			);
		}

		if ( ! empty( $subscription ) ) {
			add_meta_box(
				'reepay_checkout_subscription',
				__( 'Subscription' ),
				array( $this, 'generate_meta_box_content_subscription' ),
				'shop_order',
				'side',
				'high'
			);
		}
	}

	/**
	 * Function to show customer meta box content
	 *
	 * @param WP_Post $post current post object.
	 * @param array   $args additional arguments sent to add_meta_box function.
	 */
	public function generate_meta_box_content_customer( $post, $args ) {
		if ( ! empty( get_post_meta( $post->ID, '_reepay_order', true ) ) && 0 !== $post->post_parent ) {
			$handle = get_post_meta( $post->post_parent, '_reepay_customer', true );
		} else {
			$handle = get_post_meta( $post->ID, '_reepay_customer', true );
		}

		$template_args = array(
			'email'  => get_post_meta( $post->ID, '_billing_email', true ),
			'handle' => $handle,
		);

		$template_args['link'] = $this->dashboard_url . 'customers/customers/customer/' . $template_args['handle'];

		reepay()->get_template(
			'meta-boxes/customer.php',
			$template_args,
		);
	}

	/**
	 * Function to show customer meta box content
	 *
	 * @param WP_Post $post current post object.
	 * @param array   $args additional arguments sent to add_meta_box function.
	 */
	public function generate_meta_box_content_invoice( $post, $args ) {
		$order = wc_get_order( $post );

		if ( ! $order ) {
			return;
		}

		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$gateway = rp_get_payment_method( $order );

		if ( empty( $gateway ) ) {
			return;
		}

		$order_data = reepay()->api( $gateway )->get_invoice_data( $order );

		if ( is_wp_error( $order_data ) ) {
			return;
		}

		reepay()->get_template(
			'meta-boxes/invoice.php',
			array(
				'gateway'            => $gateway,
				'order'              => $order,
				'order_id'           => $order->get_order_number(),
				'order_data'         => $order_data,
				'order_is_cancelled' => $order->get_meta( '_reepay_order_cancelled' ) === '1' && 'cancelled' !== $order_data['state'],
				'link'               => $this->dashboard_url . 'payments/invoices/invoice/' . $order_data['handle'],
			)
		);
	}

	/**
	 * Function to show customer meta box content
	 *
	 * @param WP_Post $post current post object.
	 * @param array   $args additional arguments sent to add_meta_box function.
	 */
	public function generate_meta_box_content_subscription( $post, $args ) {
		if ( ! empty( get_post_meta( $post->ID, '_reepay_order', true ) ) && 0 !== $post->post_parent ) {
			$handle = get_post_meta( $post->post_parent, '_reepay_subscription_handle', true );
			$plan   = get_post_meta( $post->post_parent, '_reepay_subscription_plan', true );
		} else {
			$handle = get_post_meta( $post->ID, '_reepay_subscription_handle', true );
			$plan   = get_post_meta( $post->ID, '_reepay_subscription_plan', true );
		}

		$template_args = array(
			'handle' => $handle,
			'plan'   => $plan,
		);

		if ( empty( $template_args['plan'] ) && function_exists( 'reepay_s' ) ) {
			try {
				$subscription          = reepay_s()->api()->request( "subscription/{$template_args['handle']}" );
				$template_args['plan'] = $subscription['plan'];
				update_post_meta( $post->ID, '_reepay_subscription_plan', $subscription['plan'] );
			} catch ( Exception $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Do not show subscription plan name if api error.
			}
		}

		$template_args['link'] = $this->dashboard_url . 'subscriptions/subscription/' . $template_args['handle'];

		reepay()->get_template(
			'meta-boxes/plan.php',
			$template_args
		);
	}
}
