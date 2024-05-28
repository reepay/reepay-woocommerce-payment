<?php
/**
 * Register reepay order meta boxes
 *
 * @package Reepay\Checkout\Admin\MetaBoxes
 */

namespace Reepay\Checkout\Admin\MetaBoxes;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Exception;
use Reepay\Checkout\Gateways\ReepayCheckout;
use WC_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class
 *
 * @package Reepay\Checkout\Admin\MetaBoxes
 */
class Order {
	/**
	 * Reepay dashboard url
	 *
	 * @var string
	 */
	private string $dashboard_url = 'https://admin.billwerk.plus/#/rp/';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Register meta boxes on order page
	 */
	public function add_meta_boxes() {
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		if ( ! empty( $_REQUEST['id'] ) ) {
			$order_id = $_REQUEST['id'];
		} elseif ( ! empty( $_GET['post'] ) ) {
			$order_id = $_GET['post'];
		}

		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( $order ) {
			$order_data = $order->get_data();

			if ( reepay()->get_setting( 'show_meta_fields_in_orders' ) === 'yes' ) {
				add_meta_box(
					'reepay_meta_fields',
					__( 'Debug: Order meta fields', 'reepay-checkout-gateway' ),
					array( $this, 'generate_meta_box_content_meta_fields' ),
					$screen,
					'normal',
					'high',
					array(
						'order'     => $order,
						'post_type' => get_current_screen()->post_type,
					)
				);
			}

			if ( empty( $order ) || ! rp_is_order_paid_via_reepay( $order ) ) {
				return;
			}

			$gateway = rp_get_payment_method( $order );

			if ( empty( $gateway ) ) {
				return;
			}

			if ( ! empty( $order->get_meta( '_reepay_order' ) ) && 0 !== $order_data['parent_id'] ) {
				$parent_order = wc_get_order( $order_data['parent_id'] );
				$subscription = $parent_order->get_meta( '_reepay_subscription_handle' );
			} elseif ( ! empty( $order->get_meta( '_reepay_subscription_handle_parent' ) ) ) {
				$subscription = $order->get_meta( '_reepay_subscription_handle_parent' );
			} else {
				$subscription = $order->get_meta( '_reepay_subscription_handle' );
			}

			add_meta_box(
				'reepay_checkout_customer',
				__( 'Customer', 'reepay-checkout-gateway' ),
				array( $this, 'generate_meta_box_content_customer' ),
				$screen,
				'side',
				'high',
				array(
					'order'   => $order,
					'gateway' => $gateway,
				)
			);

			if ( ! empty( $order->get_transaction_id() ) ) {
				add_meta_box(
					'reepay_checkout_invoice',
					__( 'Invoice', 'reepay-checkout-gateway' ),
					array( $this, 'generate_meta_box_content_invoice' ),
					$screen,
					'side',
					'high',
					array(
						'order'   => $order,
						'gateway' => $gateway,
					)
				);
			}

			if ( ! empty( $subscription ) ) {
				add_meta_box(
					'reepay_checkout_subscription',
					__( 'Subscription', 'reepay-checkout-gateway' ),
					array( $this, 'generate_meta_box_content_subscription' ),
					$screen,
					'side',
					'high',
					array(
						'order'   => $order,
						'gateway' => $gateway,
					)
				);
			}
		}
	}

	/**
	 * Function to show customer meta box content
	 *
	 * @param mixed $x not used.
	 * @param array $meta additional info. Get arguments by 'args' key.
	 */
	public function generate_meta_box_content_customer( $x, array $meta ) {
		/**
		 * Set types of args variables
		 *
		 * @var WC_Order $order
		 * @var ReepayCheckout $gateway
		 */
		$order      = $meta['args']['order'];
		$order_data = $order->get_data();

		if ( ! empty( $order->get_meta( '_reepay_order' ) ) && 0 !== $order_data['parent_id'] ) {
			$parent_order = wc_get_order( $order_data['parent_id'] );
			$handle       = $parent_order->get_meta( '_reepay_customer' );
		} else {
			$handle = $order->get_meta( '_reepay_customer' );
		}

		if ( empty( $handle ) ) {
			$handle = reepay()->api( $order )->get_customer_handle_by_order( $order->get_id() );
		}

		$user = get_user_by( 'id', $order->get_customer_id() );

		if ( ! empty( $user ) ) {
			$email = $user->get( 'user_email' );
		} else {
			$email = $order->get_billing_email();
		}

		$template_args = array(
			'email'  => $email,
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
	 * @param mixed $x not used.
	 * @param array $meta additional info. Get arguments by 'args' key.
	 */
	public function generate_meta_box_content_invoice( $x, array $meta ) {
		/**
		 * Set types of args variables
		 *
		 * @var WC_Order $order
		 * @var ReepayCheckout $gateway
		 */
		$order   = $meta['args']['order'];
		$gateway = $meta['args']['gateway'];

		$order_data = reepay()->api( $gateway )->get_invoice_data( $order );

		if ( is_wp_error( $order_data ) ) {
			return;
		}

		if ( $order_data['refunded_amount'] > 0 &&
			( $order_data['authorized_amount'] === $order_data['refunded_amount']
				|| $order_data['settled_amount'] === $order_data['refunded_amount']
			)
		) {
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
	 * @param mixed $x not used.
	 * @param array $meta additional info. Get arguments by 'args' key.
	 */
	public function generate_meta_box_content_subscription( $x, array $meta ) {
		/**
		 * Set types of args variables
		 *
		 * @var WC_Order $order
		 * @var ReepayCheckout $gateway
		 */
		$order      = $meta['args']['order'];
		$gateway    = $meta['args']['gateway'];
		$order_data = $order->get_data();

		if ( ! empty( $order->get_meta( '_reepay_order' ) ) && 0 !== $order_data['parent_id'] ) {
			$parent_order = wc_get_order( $order_data['parent_id'] );
			$handle       = $parent_order->get_meta( '_reepay_subscription_handle' );
			$plan         = $parent_order->get_meta( '_reepay_subscription_plan' );
		} elseif ( $order->get_meta( '_reepay_renewal' ) ) {
			$handle = $order->get_meta( '_reepay_subscription_handle_parent' );
			$plan   = $order->get_meta( '_reepay_subscription_plan' );
		} else {
			$handle = $order->get_meta( '_reepay_subscription_handle' );
			$plan   = $order->get_meta( '_reepay_subscription_plan' );
		}

		$template_args = array(
			'handle' => $handle,
			'plan'   => $plan,
		);

		if ( empty( $template_args['plan'] ) && function_exists( 'reepay_s' ) ) {
			try {
				$subscription          = reepay_s()->api()->request( "subscription/{$template_args['handle']}" );
				$template_args['plan'] = $subscription['plan'];
				$order->update_meta_data( '_reepay_subscription_plan', $subscription['plan'] );
				$order->save_meta_data();
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

	/**
	 *  Function to show meta fields content
	 *
	 * @param mixed              $x not used.
	 * @param array{args: array} $meta additional info. Get arguments by 'args' key.
	 *
	 * @return void
	 */
	public function generate_meta_box_content_meta_fields( $x, array $meta ) {
		/**
		 * Args.
		 *
		 * @var array{post_type: string} $args
		 */
		$args          = $meta['args'];
		$template_args = array(
			'post_type' => $args['post_type'],
		);
		reepay()->get_template(
			'meta-boxes/meta-fields.php',
			$template_args
		);
	}
}
