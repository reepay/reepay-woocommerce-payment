<?php
/**
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use Stripe\Order;
use WC_Order;
use WC_Order_Factory;
use WC_Order_Item;
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
	 * Singleton instance.
	 *
	 * @var OrderCapture
	 */
	private static $instance;

	/**
	 * Constructor
	 */
	private function __construct() {
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'unset_specific_order_item_meta_data' ), 10, 2 );

		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'add_item_capture_button' ), 10, 3 );

		add_action( 'woocommerce_after_order_fee_item_name', array( $this, 'add_item_capture_button' ), 10, 3 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'capture_full_order' ), 10, 4 );

		add_action( 'admin_init', array( $this, 'process_item_capture' ) );

		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'capture_full_order_button' ), 10, 1 );
	}

	/**
	 * Get class instance.
	 *
	 * @return OrderCapture
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooked to woocommerce_order_item_get_formatted_meta_data. Remove 'settled' meta
	 *
	 * @see WC_Order_Item::get_formatted_meta_data
	 *
	 * @param array<int, object> $formatted_meta order item meta data.
	 * @param WC_Order_Item      $item order item.
	 *
	 * @return mixed
	 */
	public function unset_specific_order_item_meta_data( $formatted_meta, $item ) {
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
	 * @param int        $item_id the id of the item being displayed.
	 * @param object     $item    the item being displayed.
	 * @param WC_Product $product product of item.
	 *
	 * @throws Exception When `WC_Data_Store::load` validation fails.
	 */
	public function add_item_capture_button( $item_id, $item, $product ) {
		$order_id = wc_get_order_id_by_order_item_id( $item_id );
		$order    = wc_get_order( $order_id );

		$payment_method = $order->get_payment_method();

		if ( strpos( $payment_method, 'reepay' ) === false ) {
			return;
		}

		$settled = $item->get_meta( 'settled' );
		$data    = $item->get_data();

		if ( empty( $settled ) && floatval( $data['total'] ) > 0 && $this->check_capture_allowed( $order ) ) {
			$order_item    = WC_Order_Factory::get_order_item( $item_id );
			$price         = self::get_item_price( $order_item, $order );
			$unit_price    = number_format( round( $price['with_tax'], 2 ), 2, '.', '' );
			$instant_items = InstantSettle::get_instant_settle_items( $order );

			if ( ! in_array( $item_id, $instant_items, true ) ) {
				echo '<button type="submit" class="button save_order button-primary capture-item-button" name="line_item_capture" value="' . $item_id . '">
                    ' . __( 'Capture ', 'reepay-checkout-gateway' ) . $order->get_currency() . $unit_price . '
                </button>';
			}
		}
	}

	/**
	 * Hooked to woocommerce_order_status_changed.
	 *
	 * @see WC_Order::status_transition
	 *
	 * @param int      $order_id current order id.
	 * @param string   $this_status_transition_from old status.
	 * @param string   $this_status_transition_to new status.
	 * @param WC_Order $order current order.
	 *
	 * @throws Exception If settle error.
	 */
	public function capture_full_order( $order_id, $this_status_transition_from, $this_status_transition_to, $order ) {
		$payment_method = $order->get_payment_method();

		if ( strpos( $payment_method, 'reepay' ) === false ) {
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
		if ( isset( $_POST['line_item_capture'] ) && isset( $_POST['post_type'] ) && isset( $_POST['post_ID'] ) ) {
			if ( 'shop_order' === $_POST['post_type'] ) {

				$order = wc_get_order( $_POST['post_ID'] );

				$item = WC_Order_Factory::get_order_item( $_POST['line_item_capture'] );
				$this->settle_item( $item, $order );
			}
		}

		if ( isset( $_POST['all_items_capture'] ) && isset( $_POST['post_type'] ) && isset( $_POST['post_ID'] ) ) {
			if ( 'shop_order' === $_POST['post_type'] ) {
				$order = wc_get_order( $_POST['post_ID'] );

				$payment_method = $order->get_payment_method();

				if ( strpos( $payment_method, 'reepay' ) === false ) {
					return;
				}

				$this->multi_settle( $order );
			}
		}
	}

	/**
	 * Hooked to woocommerce_order_item_add_action_buttons. Print full capture button.
	 *
	 * @param WC_Order $order current order.
	 */
	public function capture_full_order_button( $order ) {
		$payment_method = $order->get_payment_method();

		if ( strpos( $payment_method, 'reepay' ) === false ) {
			return;
		}

		if ( $this->check_capture_allowed( $order ) ) {
			$amount = $this->get_not_settled_amount( $order );

			if ( $amount > 0 ) {
				$amount = number_format( round( $amount, 2 ), 2, '.', '' );
				echo '<button type="submit" class="button save_order button-primary capture-item-button" name="all_items_capture" value="' . $order->get_id() . '">
                    ' . __( 'Capture ', 'reepay-checkout-gateway' ) . $order->get_currency() . $amount . '
                </button>';
			}
		}
	}

	/**
	 * Settle order items.
	 *
	 * @param WC_Order        $order order to settle.
	 * @param array[]         $items_data items data from self::get_item_data.
	 * @param float           $total_all order total amount ot settle.
	 * @param WC_Order_Item[] $line_items order line items.
	 *
	 * @return bool
	 */
	public function settle_items( $order, $items_data, $total_all, $line_items ) {
		unset( $_POST['post_status'] );

		$gateway = rp_get_payment_method( $order );
		$result  = reepay()->api( $gateway )->settle( $order, $total_all, $items_data, $line_items );

		if ( is_wp_error( $result ) ) {
			$gateway->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );
			set_transient( 'reepay_api_action_error', $result->get_error_message(), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		if ( 'failed' === $result['state'] ) {
			$gateway->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );
			set_transient( 'reepay_api_action_error', __( 'Failed to settle item', 'reepay-checkout-gateway' ), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		if ( $result ) {
			foreach ( $line_items as $item ) {
				$item_data = $this->get_item_data( $item, $order );
				$total     = $item_data['amount'] * $item_data['quantity'];
				$this->complete_settle( $item, $order, $total );
			}
		}

		return true;
	}

	/**
	 * Settle all items in order.
	 *
	 * @param WC_Order $order order to settle.
	 */
	public function multi_settle( $order ) {
		$items_data = array();
		$line_items = array();
		$total_all  = 0;

		foreach ( $order->get_items() as $item ) {
			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$item_data = $this->get_item_data( $item, $order );
				$total     = $item_data['amount'] * $item_data['quantity'];

				if ( $total <= 0 && method_exists( $item, 'get_product' ) && wcs_is_subscription_product( $item->get_product() ) ) {
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
				if ( $total > 0 && $this->check_capture_allowed( $order ) ) {
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
	 * @param WC_Order_Item $item order item to set 'settled' meta.
	 * @param WC_Order      $order order to activate woo subscription (if it is possible).
	 * @param float|int     $total settled total to set to 'settled' meta.
	 */
	public function complete_settle( $item, $order, $total ) {
		if ( method_exists( $item, 'get_product' ) && wcs_is_subscription_product( $item->get_product() ) ) {
			WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
		}
		$item->update_meta_data( 'settled', $total / 100 );
		$item->save();
	}

	/**
	 * @param WC_Order_Item $item  order item to settle.
	 * @param WC_Order      $order current order.
	 *
	 * @return bool
	 */
	public function settle_item( $item, $order ) {
		$settled = $item->get_meta( 'settled' );

		if ( empty( $settled ) ) {
			$item_data      = $this->get_item_data( $item, $order );
			$line_item_data = array( $item_data );
			$total          = $item_data['amount'] * $item_data['quantity'];
			unset( $_POST['post_status'] );
			$gateway = rp_get_payment_method( $order );
			if ( $total <= 0 && method_exists( $item, 'get_product' ) && wcs_is_subscription_product( $item->get_product() ) ) {
				WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
			} elseif ( $total > 0 && $this->check_capture_allowed( $order ) ) {
				$result = reepay()->api( $gateway )->settle( $order, $total, $line_item_data, $item );

				if ( is_wp_error( $result ) ) {
					$gateway->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );
					set_transient( 'reepay_api_action_error', $result->get_error_message(), MINUTE_IN_SECONDS / 2 );

					return false;
				}

				if ( 'failed' === $result['state'] ) {
					$gateway->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );
					set_transient( 'reepay_api_action_error', __( 'Failed to settle item', 'reepay-checkout-gateway' ), MINUTE_IN_SECONDS / 2 );

					return false;
				}

				if ( $result ) {
					$this->complete_settle( $item, $order, $total );
				}
			}
		}

		return true;
	}

	/**
	 * Check if capture is allowed
	 *
	 * @param WC_Order $order order to check.
	 *
	 * @return bool
	 */
	public function check_capture_allowed( $order ) {
		$payment_method = $order->get_payment_method();

		if ( class_exists( WC_Reepay_Renewals::class ) && WC_Reepay_Renewals::is_order_contain_subscription( $order ) ) {
			return false;
		}

		if ( strpos( $payment_method, 'reepay' ) === false ) {
			return false;
		}

		$gateway = rp_get_payment_method( $order );

		$invoice_data = reepay()->api( $gateway )->get_invoice_data( $order );

		return ! is_wp_error( $invoice_data ) && $invoice_data['authorized_amount'] > $invoice_data['settled_amount'];
	}

	/**
	 * Get not settled order items amount.
	 *
	 * @param WC_Order $order order to get.
	 *
	 * @return int|mixed
	 */
	public function get_not_settled_amount( $order ) {
		$amount = 0;

		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item ) {
			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$amount += self::get_item_price( $item, $order )['with_tax'];
			}
		}

		return $amount;
	}

	/**
	 * @param WC_Order_Item $order_item order item to get data.
	 * @param WC_Order      $order      current order.
	 *
	 * @return array
	 */
	public function get_item_data( $order_item, $order ) {
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
	 * @param WC_Order_Item $order_item order item to get price and tax.
	 * @param WC_Order      $order current order.
	 *
	 * @return array
	 */
	public static function get_item_price( $order_item, $order ) {
		$price = array();

		if ( is_object( $order_item ) && get_class( $order_item ) === 'WC_Order_Item_Product' ) {
			$price['original'] = $order->get_line_subtotal( $order_item, false, false );
			if ( $order_item->get_subtotal() !== $order_item->get_total() ) {
				$discount          = $order_item->get_subtotal() - $order_item->get_total();
				$price['original'] = $price['original'] - $discount;
			}
		} else {
			$price['original'] = floatval( $order->get_line_total( $order_item, false, false ) );
		}

		$tax_data = wc_tax_enabled() && method_exists( $order_item, 'get_taxes' ) ? $order_item->get_taxes() : false;
		$taxes    = method_exists( $order, 'get_taxes' ) ? $order->get_taxes() : false;

		$res_tax = 0;
		if ( ! empty( $taxes ) ) {
			foreach ( $taxes as $tax ) {
				$tax_item_id    = $tax->get_rate_id();
				$tax_item_total = $tax_data['total'][ $tax_item_id ] ?? '';
				if ( ! empty( $tax_item_total ) ) {
					$res_tax += floatval( $tax_item_total );
				}
			}
		}

		if ( ! empty( $order_item->get_meta( '_is_card_fee' ) ) ) {
			$price['with_tax'] = $price['original'];
		} else {
			$price['with_tax'] = $price['original'] + $res_tax;
		}

		return $price;
	}
}
