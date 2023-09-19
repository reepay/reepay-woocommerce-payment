<?php
/**
 * Register reepay order metaboxes
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
use Reepay\Checkout\Gateways\ReepayCheckout;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit();

/**
 * Class MetaBoxes
 *
 * @package Reepay\Checkout\Admin
 */
class MetaBoxes {
	/**
	 * Reepay dashboard url
	 *
	 * @var string
	 */
	private string $dashboard_url = 'https://app.reepay.com/#/rp/';

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
		$screen     = get_current_screen();
		$post_types = array( 'shop_order', 'shop_subscription' );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$post_types[] = 'woocommerce_page_wc-orders';

			$post = get_post( wc_clean( $_GET['id'] ?? 0 ) );
		} else {
			global $post;
		}

		if ( empty( $post ) || ! in_array( $screen->id, $post_types, true ) || ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$order = wc_get_order( $post->ID );

		if ( empty( $order ) || ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$gateway = rp_get_payment_method( $order );

		if ( empty( $gateway ) ) {
			return;
		}

		if ( ! empty( get_post_meta( $post->ID, '_reepay_order', true ) ) && 0 !== $post->post_parent ) {
			$subscription = get_post_meta( $post->post_parent, '_reepay_subscription_handle', true );
		} elseif ( ! empty( get_post_meta( $post->ID, '_reepay_subscription_handle_parent', true ) ) ) {
			$subscription = get_post_meta( $post->ID, '_reepay_subscription_handle_parent', true );
		} else {
			$subscription = get_post_meta( $post->ID, '_reepay_subscription_handle', true );
		}

		add_meta_box(
			'reepay_checkout_customer',
			__( 'Customer', 'reepay-checkout-gateway' ),
			array( $this, 'generate_meta_box_content_customer' ),
			$screen->id,
			'side',
			'high',
			array(
				'order'   => $order,
				'gateway' => $gateway,
				'post'    => $post,
			)
		);

		if ( ! empty( get_post_meta( $post->ID, '_transaction_id', true ) ) ) {
			add_meta_box(
				'reepay_checkout_invoice',
				__( 'Invoice', 'reepay-checkout-gateway' ),
				array( $this, 'generate_meta_box_content_invoice' ),
				$screen->id,
				'side',
				'high',
				array(
					'order'   => $order,
					'gateway' => $gateway,
					'post'    => $post,
				)
			);
		}

		if ( ! empty( $subscription ) ) {
			add_meta_box(
				'reepay_checkout_subscription',
				__( 'Subscription', 'reepay-checkout-gateway' ),
				array( $this, 'generate_meta_box_content_subscription' ),
				$screen->id,
				'side',
				'high',
				array(
					'order'   => $order,
					'gateway' => $gateway,
					'post'    => $post,
				)
			);
		}
	}

	/**
	 * Function to show customer meta box content
	 *
	 * @param mixed $x    not used.
	 * @param array $meta additional info. Get arguments by 'args' key.
	 */
	public function generate_meta_box_content_customer( $x, array $meta ) {
		/**
		 * Set types of args variables
		 *
		 * @var WC_Order $order
		 * @var ReepayCheckout $gateway
		 * @var WP_Post $post
		 */
		$order   = $meta['args']['order'];
		$gateway = $meta['args']['gateway'];
		$post    = $meta['args']['post'];

		if ( ! empty( get_post_meta( $post->ID, '_reepay_order', true ) ) && 0 !== $post->post_parent ) {
			$handle = get_post_meta( $post->post_parent, '_reepay_customer', true );
		} else {
			$handle = get_post_meta( $post->ID, '_reepay_customer', true );
		}

		if ( empty( $handle ) ) {
			$handle = reepay()->api( $order )->get_customer_handle_by_order( $order->get_id() );
		}

		$template_args = array(
			'email'  => get_post_meta( $post->ID, '_billing_email', true ),
			'handle' => $handle,
			'link'   => $this->dashboard_url . 'customers/customers/customer/' . $handle,
		);

		reepay()->get_template(
			'meta-boxes/customer.php',
			$template_args
		);
	}

	/**
	 * Function to show customer meta box content
	 *
	 * @param mixed $x    not used.
	 * @param array $meta additional info. Get arguments by 'args' key.
	 */
	public function generate_meta_box_content_invoice( $x, array $meta ) {
		/**
		 * Set types of args variables
		 *
		 * @var WC_Order $order
		 * @var ReepayCheckout $gateway
		 * @var WP_Post $post
		 */
		$order   = $meta['args']['order'];
		$gateway = $meta['args']['gateway'];
		$post    = $meta['args']['post'];

		$order_data = reepay()->api( $gateway )->get_invoice_data( $order );

		if ( is_wp_error( $order_data ) ) {
			return;
		}

		if ( $order_data['authorized_amount'] === $order_data['refunded_amount'] ) {
			$order_data['state'] = 'refunded';
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
	 * @param mixed $x    not used.
	 * @param array $meta additional info. Get arguments by 'args' key.
	 */
	public function generate_meta_box_content_subscription( $x, array $meta ) {
		/**
		 * Set types of args variables
		 *
		 * @var WC_Order $order
		 * @var ReepayCheckout $gateway
		 * @var WP_Post $post
		 */
		$order   = $meta['args']['order'];
		$gateway = $meta['args']['gateway'];
		$post    = $meta['args']['post'];

		if ( ! empty( get_post_meta( $post->ID, '_reepay_order', true ) ) && 0 !== $post->post_parent ) {
			$handle = get_post_meta( $post->post_parent, '_reepay_subscription_handle', true );
			$plan   = get_post_meta( $post->post_parent, '_reepay_subscription_plan', true );
		} elseif ( get_post_meta( $post->ID, '_reepay_renewal', true ) ) {
			$handle = get_post_meta( $post->ID, '_reepay_subscription_handle_parent', true );
			$plan   = get_post_meta( $post->ID, '_reepay_subscription_plan', true );
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
