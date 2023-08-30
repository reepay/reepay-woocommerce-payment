<?php
/**
 * Capturing order amount if possible
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use WC_Order;
use WC_Order_Factory;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;
use WC_Reepay_Renewals;
use WC_Subscriptions_Manager;

defined( 'ABSPATH' ) || exit();

/**
 * Class OrderCapture
 *
 * @package Reepay\Checkout\OrderFlow
 */
class OrderCapture {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'unset_specific_order_item_meta_data' ), 10, 2 );

		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'add_item_capture_button' ), 10, 2 );

		add_action( 'woocommerce_after_order_fee_item_name', array( $this, 'add_item_capture_button' ), 10, 2 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'capture_full_order' ), 10, 4 );

		add_action( 'admin_init', array( $this, 'process_item_capture' ) );

		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'capture_full_order_button' ), 10, 1 );

		add_action( 'reepay_order_item_settled', array( $this, 'activate_woocommerce_subscription' ), 10, 2 );
	}

	/**
	 * Hooked to woocommerce_order_item_get_formatted_meta_data. Remove 'settled' meta
	 *
	 * @param array<int, object> $formatted_meta order item meta data.
	 * @param WC_Order_Item      $item           order item.
	 *
	 * @return array
	 * @see WC_Order_Item::get_formatted_meta_data
	 */
	public function unset_specific_order_item_meta_data( array $formatted_meta, WC_Order_Item $item ): array {
		// Only on emails notifications.
		if ( is_admin() && isset( $_GET['post'] ) ) {
			foreach ( $formatted_meta as $meta ) {
				if ( in_array( $meta->key, array( 'settled' ), true ) ) {
					$meta->display_key = 'Settle';
				}
			}

			return $formatted_meta;
		}

		foreach ( $formatted_meta as $key => $meta ) {
			if ( in_array( $meta->key, array( 'settled' ), true ) ) {
				unset( $formatted_meta[ $key ] );
			}
		}

		return $formatted_meta;
	}

	/**
	 * Hooked to woocommerce_after_order_fee_item_name. Print capture button.
	 *
	 * @param int    $item_id the id of the item being displayed.
	 * @param object $item    the item being displayed.
	 *
	 * @throws Exception When `WC_Data_Store::load` validation fails.
	 */
	public function add_item_capture_button( int $item_id, object $item ) {
		$order_id = wc_get_order_id_by_order_item_id( $item_id );
		$order    = wc_get_order( $order_id );

		if ( ! rp_is_order_paid_via_reepay( $order ) || ! empty( $item->get_meta( 'settled' ) ) ) {
			return;
		}

		if ( floatval( $item->get_data()['total'] ) > 0 && $this->check_capture_allowed( $order ) ) {
			$price = self::get_item_price( $item_id, $order );

			reepay()->get_template(
				'admin/capture-item-button.php',
				array(
					'name'  => 'line_item_capture',
					'value' => $item_id,
					'text'  => __( 'Capture', 'reepay-checkout-gateway' ) . ' ' . wc_price( $price['with_tax'] ),
				)
			);
		}
	}

	/**
	 * Hooked to woocommerce_order_item_add_action_buttons. Print full capture button.
	 *
	 * @param WC_Order $order current order.
	 */
	public function capture_full_order_button( WC_Order $order ) {
		if ( ! $this->check_capture_allowed( $order ) ) {
			return;
		}

		$amount = $this->get_not_settled_amount( $order );

		if ( $amount <= 0 ) {
			return;
		}

		reepay()->get_template(
			'admin/capture-item-button.php',
			array(
				'name'  => 'all_items_capture',
				'value' => $order->get_id(),
				'text'  => __( 'Capture', 'reepay-checkout-gateway' ) . ' ' . wc_price( $amount ),
			)
		);
	}

	/**
	 * Hooked to woocommerce_order_status_changed.
	 *
	 * @param int      $order_id                    current order id.
	 * @param string   $this_status_transition_from old status.
	 * @param string   $this_status_transition_to   new status.
	 * @param WC_Order $order                       current order.
	 *
	 * @throws Exception If settle error.
	 * @see WC_Order::status_transition
	 */
	public function capture_full_order( int $order_id, string $this_status_transition_from, string $this_status_transition_to, WC_Order $order ) {
		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$value = get_transient( 'reepay_order_complete_should_settle_' . $order->get_id() );

		if ( 'completed' === $this_status_transition_to && ( '1' === $value || false === $value ) ) {
			$this->multi_settle( $order );
		}
	}

	/**
	 * Hooked to admin_init. Capture items from request
	 */
	public function process_item_capture() {
		if ( ! isset( $_POST['post_type'] ) || 'shop_order' !== $_POST['post_type'] ||
			 ! isset( $_POST['post_ID'] ) ||
			 ( ! isset( $_POST['line_item_capture'] ) && ! isset( $_POST['all_items_capture'] ) ) ) {
			return;
		}

		$order = wc_get_order( $_POST['post_ID'] );

		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		if ( isset( $_POST['line_item_capture'] ) ) {
			$this->settle_item( WC_Order_Factory::get_order_item( $_POST['line_item_capture'] ), $order );
		} elseif ( isset( $_POST['all_items_capture'] ) ) {
			$this->multi_settle( $order );
		}
	}

	/**
	 * Activate woocommerce subscription after settle order item
	 *
	 * @param WC_Order_Item $item  woocommerce order item.
	 * @param WC_Order      $order woocomemrce order.
	 */
	public function activate_woocommerce_subscription( WC_Order_Item $item, WC_Order $order ) {
		if ( method_exists( $item, 'get_product' ) && wcs_is_subscription_product( $item->get_product() ) ) {
			WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
		}
	}

	/**
	 * Settle all items in order.
	 *
	 * @param WC_Order $order order to settle.
	 */
	public function multi_settle( WC_Order $order ): bool {
		$items_data = array();
		$line_items = array();
		$total_all  = 0;

		foreach ( $order->get_items() as $item ) {
			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$item_data = $this->get_item_data( $item, $order );
				$total     = $item_data['amount'] * $item_data['quantity'];

				if ( $total <= 0 && method_exists( $item, 'get_product' ) && $item->get_product() && wcs_is_subscription_product( $item->get_product() ) ) {
					WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
				} elseif ( $total > 0 && $this->check_capture_allowed( $order ) ) {
					$items_data[] = $item_data;
					$line_items[] = $item;
					$total_all   += $total;
				}
			}
		}

		foreach ( $order->get_items( array( 'shipping', 'fee' ) ) as $item ) {
			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$item_data = $this->get_item_data( $item, $order );
				$total     = $item_data['amount'] * $item_data['quantity'];
				if ( 0 !== $total && $this->check_capture_allowed( $order ) ) {
					$items_data[] = $item_data;
					$line_items[] = $item;
					$total_all   += $total;
				}
			}
		}

		if ( ! empty( $items_data ) ) {
			return $this->settle_items( $order, $items_data, $total_all, $line_items );
		}

		return false;
	}

	/**
	 * Settle order items.
	 *
	 * @param WC_Order        $order      order to settle.
	 * @param array[]         $items_data items data from self::get_item_data.
	 * @param float           $total_all  order total amount ot settle.
	 * @param WC_Order_Item[] $line_items order line items.
	 *
	 * @return bool
	 */
	public function settle_items( WC_Order $order, array $items_data, float $total_all, array $line_items ): bool {
		unset( $_POST['post_status'] ); // // Prevent order status changing by WooCommerce

		$result = reepay()->api( $order )->settle( $order, $total_all, $items_data, $line_items );

		if ( is_wp_error( $result ) ) {
			rp_get_payment_method( $order )->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );

			return false;
		}

		if ( 'failed' === $result['state'] ) {
			set_transient( 'reepay_api_action_error', __( 'Failed to settle item', 'reepay-checkout-gateway' ), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		foreach ( $line_items as $item ) {
			$item_data = $this->get_item_data( $item, $order );
			$total     = $item_data['amount'] * $item_data['quantity'];
			$this->complete_settle( $item, $order, $total );
		}

		return true;
	}

	/**
	 * Complete settle for order item, activate associated subscription and save data to meta
	 *
	 * @param WC_Order_Item $item  order item to set 'settled' meta.
	 * @param WC_Order      $order order to activate woo subscription (if it is possible).
	 * @param float|int     $total settled total to set to 'settled' meta.
	 */
	public function complete_settle( WC_Order_Item $item, WC_Order $order, $total ) {
		$item->update_meta_data( 'settled', rp_make_initial_amount( $total, $order->get_currency() ) );
		$item->save();

		do_action( 'reepay_order_item_settled', $item, $order );
	}

	/**
	 * Settle order item
	 *
	 * @param WC_Order_Item $item  order item to settle.
	 * @param WC_Order      $order current order.
	 *
	 * @return bool
	 * @see OrderCapture::complete_settle
	 */
	public function settle_item( WC_Order_Item $item, WC_Order $order ): bool {
		$settled = $item->get_meta( 'settled' );

		if ( ! empty( $settled ) ) {
			return true;
		}

		unset( $_POST['post_status'] ); // Prevent order status changing by WooCommerce.

		$item_data = $this->get_item_data( $item, $order );
		$total     = $item_data['amount'] * $item_data['quantity'];

		if ( $total <= 0 ) {
			do_action( 'reepay_order_item_settled', $item, $order );

			return true;
		}

		if ( ! $this->check_capture_allowed( $order ) ) {
			return false;
		}

		$result = reepay()->api( $order )->settle( $order, $total, array( $item_data ), $item );

		if ( is_wp_error( $result ) ) {
			rp_get_payment_method( $order )->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );

			return false;
		}

		if ( 'failed' === $result['state'] ) {
			set_transient( 'reepay_api_action_error', __( 'Failed to settle item', 'reepay-checkout-gateway' ), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		$this->complete_settle( $item, $order, $total );

		return true;
	}

	/**
	 * Check if capture is allowed
	 *
	 * @param WC_Order $order order to check.
	 *
	 * @return bool
	 */
	public function check_capture_allowed( WC_Order $order ): bool {
		if ( ! rp_is_order_paid_via_reepay( $order ) ||
			 class_exists( WC_Reepay_Renewals::class ) && WC_Reepay_Renewals::is_order_contain_subscription( $order ) ) {
			return false;
		}

		$invoice_data = reepay()->api( $order )->get_invoice_data( $order );

		return ! is_wp_error( $invoice_data ) && $invoice_data['authorized_amount'] > $invoice_data['settled_amount'];
	}

	/**
	 * Get not settled order items amount.
	 *
	 * @param WC_Order $order order to get.
	 *
	 * @return float|int
	 */
	public function get_not_settled_amount( WC_Order $order ) {
		$amount = 0;

		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item ) {
			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$amount += self::get_item_price( $item, $order )['with_tax'];
			}
		}

		return $amount;
	}

	/**
	 * Prepare order item data for reepay
	 *
	 * @param WC_Order_Item $order_item order item to get data.
	 * @param WC_Order      $order      current order.
	 *
	 * @return array
	 */
	public function get_item_data( WC_Order_Item $order_item, WC_Order $order ): array {
		$prices_incl_tax = wc_prices_include_tax();

		$price = self::get_item_price( $order_item, $order );

		$tax         = $price['with_tax'] - $price['original'];
		$tax_percent = ( $tax > 0 ) ? 100 / ( $price['original'] / $tax ) : 0;
		$unit_price  = round( ( $prices_incl_tax ? $price['with_tax'] : $price['original'] ) / $order_item->get_quantity(), 2 );

		return array(
			'ordertext'       => $order_item->get_name(),
			'quantity'        => $order_item->get_quantity(),
			'amount'          => rp_prepare_amount( $unit_price, $order->get_currency() ),
			'vat'             => round( $tax_percent / 100, 2 ),
			'amount_incl_vat' => $prices_incl_tax,
		);
	}

	/**
	 * Get order item price for reepay.
	 *
	 * @param WC_Order_Item|int $order_item order item to get price and tax.
	 * @param WC_Order          $order      current order.
	 *
	 * @return array
	 * @noinspection PhpCastIsUnnecessaryInspection
	 */
	public static function get_item_price( $order_item, WC_Order $order ): array {
		if ( is_int( $order_item ) ) {
			$order_item = WC_Order_Factory::get_order_item( $order_item );
		}

		$price = array(
			// get_line_total can return string.
			'original' => (float) $order->get_line_total( $order_item, false, false ),
		);

		$price['with_tax'] = $price['original'];

		if ( ! empty( $order_item ) && ! is_array( $order_item ) && empty( $order_item->get_meta( '_is_card_fee' ) ) ) {
			$tax_data = wc_tax_enabled() && method_exists( $order_item, 'get_taxes' ) ? $order_item->get_taxes() : false;
			$taxes    = method_exists( $order, 'get_taxes' ) ? $order->get_taxes() : false;

			if ( ! empty( $tax_data ) && ! empty( $taxes ) ) {
				foreach ( $taxes as $tax ) {
					$tax_item_id    = $tax->get_rate_id();
					$tax_item_total = $tax_data['total'][ $tax_item_id ] ?? '';
					if ( ! empty( $tax_item_total ) ) {
						$price['with_tax'] += (float) $tax_item_total;
					}
				}
			}
		}

		return $price;
	}
}
