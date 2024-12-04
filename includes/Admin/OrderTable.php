<?php
/**
 * Order Table admin
 *
 * @package Reepay\Checkout\Admin
 */

namespace Reepay\Checkout\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit();

/**
 * Class OrderTable
 *
 * @package Reepay\Checkout\Admin
 */
class OrderTable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'reepay_checkout_form_fields', array( $this, 'form_fields' ), 11, 2 );
		$gateway_settings = get_option( 'woocommerce_reepay_checkout_settings' );
		if ( 'yes' === $gateway_settings['order_table_billwerk_status'] ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				add_action(
					'manage_woocommerce_page_wc-orders_custom_column',
					array( $this, 'shop_order_columns' ),
					11,
					2
				);
				add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'admin_order_edit_columns' ), 20 );
			} else {
				add_action( 'manage_shop_order_posts_custom_column', array( $this, 'shop_order_columns' ), 11, 2 );
				add_filter( 'manage_edit-shop_order_columns', array( $this, 'admin_order_edit_columns' ), 20 );
			}
		}
	}

	/**
	 * Add Settings
	 *
	 * @param array $form_fields default form fields.
	 *
	 * @return array
	 */
	public function form_fields( array $form_fields ): array {
		$form_fields['order_table_billwerk_status'] = array(
			'title'       => __( 'Order Column: Billwerk Status', 'reepay-checkout-gateway' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable Column', 'reepay-checkout-gateway' ),
			'description' => __( 'The "Billwerk Status" column displays the status of Billwerk in the orders table.', 'reepay-checkout-gateway' ),
			'default'     => 'no',
		);

		return $form_fields;
	}

	/**
	 * Change the columns shown in admin.
	 *
	 * @param array $existing_columns WC column.
	 *
	 * @return array
	 */
	public function admin_order_edit_columns( $existing_columns ) {
		$columns = array_slice( $existing_columns, 0, count( $existing_columns ) - 1, true ) +
					array(
						'billwerk_status' => __( 'Billwerk status', 'reepay-checkout-gateway' ),
					)
					+ array_slice( $existing_columns, count( $existing_columns ) - 1, count( $existing_columns ), true );

		return $columns;
	}

	/**
	 * Show billwerk status in column
	 *
	 * @param  string        $column_id  column id.
	 * @param  WC_Order|null $order  order object. For compatibility with WooCommerce HPOS orders table.
	 *
	 * @return void
	 */
	public function shop_order_columns( $column_id, $order = null ) {
		if ( ! in_array( $column_id, array( 'billwerk_status' ), true ) ) {
			return;
		}

		if ( 'billwerk_status' === $column_id ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$order   = wc_get_order( $order );
			$gateway = rp_get_payment_method( $order );

			if ( empty( $gateway ) ) {
				return;
			}

			if ( empty( $order->get_transaction_id() ) ) {
				return;
			}

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

			echo ucfirst( $order_data['state'] );
		}
	}
}
