<?php
/**
 * Capturing order amount if possible
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use Reepay\Checkout\Integrations\PWGiftCardsIntegration;
use Reepay\Checkout\Integrations\WCGiftCardsIntegration;
use Reepay\Checkout\Integrations\WPCProductBundlesWooCommerceIntegration;
use Reepay\Checkout\Utils\LoggingTrait;
use WC_Order;
use WC_Order_Factory;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;
use WC_Reepay_Renewals;
use WC_Subscriptions_Manager;
use WC_Order_Item_Fee;
use WP_Error;

defined( 'ABSPATH' ) || exit();

/**
 * Class OrderCapture
 *
 * @package Reepay\Checkout\OrderFlow
 */
class OrderCapture {
	use LoggingTrait;

	/**
	 * Debug log for auto settle order capture file name
	 *
	 * @var string
	 */
	private string $logging_source = 'billwerk_plus_debug_auto_settle_order_capture';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'unset_specific_order_item_meta_data' ), 10, 2 );

		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'add_item_capture_button' ), 10, 2 );

		add_action( 'woocommerce_after_order_fee_item_name', array( $this, 'add_item_capture_button' ), 10, 2 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'capture_full_order' ), 10, 4 );

		add_action( 'admin_init', array( $this, 'process_item_capture' ) );

		add_action( 'admin_init', array( $this, 'process_capture_amount' ) );

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
			foreach ( $formatted_meta as $i => $meta ) {
				if ( in_array( $meta->key, array( 'settled' ), true ) ) {
					$meta->display_key = 'Settled';
				}
				if ( in_array( $meta->key, array( '_line_discount' ), true ) ) {
					if ( intval( $meta->value ) === 0 ) {
						unset( $formatted_meta[ $i ] );
					} else {
						$meta->display_key = 'Line discount';
					}
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
	 * @param int                                 $item_id the id of the item being displayed.
	 * @param WC_Order_Item|WC_Order_Item_Product $item    the item being displayed.
	 *
	 * @throws Exception When `WC_Data_Store::load` validation fails.
	 */
	public function add_item_capture_button( int $item_id, $item ) {
		$order_id = wc_get_order_id_by_order_item_id( $item_id );
		$order    = wc_get_order( $order_id );

		if ( rp_is_order_paid_via_reepay( $order ) &&
			empty( $item->get_meta( 'settled' ) ) &&
			floatval( $item->get_data()['total'] ) > 0 &&
			$this->check_capture_allowed( $order ) &&
			! WCGiftCardsIntegration::check_order_have_wc_giftcard( $order ) &&
			empty( $order->get_meta( '_reepay_remaining_balance' ) )
		) {
			$price = self::get_item_price( $item, $order );

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
		$amount = $this->get_not_settled_amount( $order );

		if ( $amount <= 0 || ! $this->check_capture_allowed( $order ) || ! empty( $order->get_meta( '_reepay_remaining_balance' ) ) ) {
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

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order_id,
				'Data'  => array(
					'$this_status_transition_from' => $this_status_transition_from,
					'$this_status_transition_to'   => $this_status_transition_to,
				),
			)
		);

		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$value = get_transient( 'reepay_order_complete_should_settle_' . $order->get_id() );

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order_id,
				'Data'  => array(
					'transient_value' => $value,
				),
			)
		);

		if ( 'completed' === $this_status_transition_to && 'no' === reepay()->get_setting( 'disable_auto_settle' ) && ( '1' === $value || false === $value ) ) {
			$this->log(
				array(
					__METHOD__,
					__LINE__,
					'order' => $order_id,
					'msg'   => 'settle order.',
				)
			);
			$this->multi_settle( $order );
		} else {
			$this->log(
				array(
					__METHOD__,
					__LINE__,
					'order' => $order_id,
					'msg'   => 'can\'t settle order.',
				)
			);
		}
	}

	/**
	 * Hooked to admin_init. Capture items from request
	 */
	public function process_item_capture() {
		if ( rp_hpos_enabled() ) {
			if ( ! rp_hpos_is_order_page() ) {
				return;
			}
		} elseif ( ! isset( $_POST['post_type'] ) ||
				'shop_order' !== $_POST['post_type'] ||
				! isset( $_POST['post_ID'] ) ) {

				return;
		}

		if ( ! isset( $_POST['line_item_capture'] ) && ! isset( $_POST['all_items_capture'] ) ) {
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
	 * Hooked to admin_init. Capture specified amount from request
	 */
	public function process_capture_amount() {
		if ( rp_hpos_enabled() ) {
			if ( ! rp_hpos_is_order_page() ) {
				return;
			}
		} elseif ( ! isset( $_POST['post_type'] ) ||
				'shop_order' !== $_POST['post_type'] ||
				! isset( $_POST['post_ID'] ) ) {

				return;
		}

		if ( ! isset( $_POST['reepay_capture_amount_button'] ) ) {
			return;
		}

		if ( ! isset( $_POST['reepay_capture_amount_input'] ) ) {
			return;
		}

		if ( 'process_capture_amount' !== $_POST['reepay_capture_amount_button'] ) {
			return;
		}

		$reepay_capture_amount_input = wc_format_decimal( $_POST['reepay_capture_amount_input'] );

		$order = wc_get_order( $_POST['post_ID'] );

		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return;
		}

		if ( isset( $_POST['reepay_capture_amount_input'] ) ) {
			$this->settle_amount( $order, $reepay_capture_amount_input );
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

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order->get_id(),
			)
		);

		$invoice_data = reepay()->api( $order )->get_invoice_by_handle( 'order-' . $order->get_id() );
		if ( is_array( $invoice_data ) && array_key_exists( 'authorized_amount', $invoice_data ) && array_key_exists( 'settled_amount', $invoice_data ) && $invoice_data['authorized_amount'] - $invoice_data['settled_amount'] <= 0 ) {
			return false;
		}

		// Disable settle for renewal order.
		$post = get_post( ! empty( $order ) ? $order->get_id() : null );
		if ( ! empty( $order->get_meta( '_reepay_order' ) ) && ( 0 !== $post->post_parent || ! empty( $order->get_meta( '_reepay_is_renewal' ) ) ) ) {
			return false;
		}

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order->get_id(),
				'data'  => array(
					'$invoice_data' => $invoice_data,
				),
			)
		);

		if ( is_array( $invoice_data ) && array_key_exists( 'order_lines', $invoice_data ) ) {
			$this->log( sprintf( 'Capture order: %s checking surcharge fee', $invoice_data['handle'] ) );
			foreach ( $invoice_data['order_lines'] as $invoice_line ) {
				if ( 'surcharge_fee' === $invoice_line['origin'] ) {
					$is_exist = false;
					foreach ( $order->get_items( 'fee' ) as $item ) {
						if ( $item->get_name() === $invoice_line['ordertext'] ) {
							$is_exist = true;
							break;
						}
					}
					if ( ! $is_exist ) {
						$this->log( sprintf( 'Capture order: %s adding surcharge fee', $invoice_data['handle'] ) );
						$fees_item = new WC_Order_Item_Fee();
						$fees_item->set_name( $invoice_line['ordertext'] );
						$fees_item->set_amount( floatval( $invoice_line['unit_amount'] ) / 100 );
						$fees_item->set_total( floatval( $invoice_line['amount'] ) / 100 );
						$fees_item->set_tax_status( 'none' );
						$fees_item->add_meta_data( '_is_card_fee', true );
						$order->add_item( $fees_item );
						$order->calculate_totals( false );
						$order->save();
						$this->log( sprintf( 'Capture order: %s surcharge fee added', $invoice_data['handle'] ) );
					} else {
						$this->log( sprintf( 'Capture order: %s surcharge fee already exists', $invoice_data['handle'] ) );
					}
				}
			}
		}

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order->get_id(),
				'data'  => array(
					'$items_data' => $items_data,
					'$line_items' => $line_items,
					'$total_all'  => $total_all,
				),
			)
		);

		foreach ( $order->get_items() as $item ) {
			$this->log(
				array(
					__METHOD__,
					__LINE__,
					'order' => $order->get_id(),
					'item'  => $item->get_id(),
					'data'  => array(
						'meta_settled' => $item->get_meta( 'settled' ),
					),
				)
			);

			// Skip bundle products to be consistent with other methods.
			if ( WPCProductBundlesWooCommerceIntegration::is_order_item_bundle( $item ) ) {
				$item_data = $this->get_item_data( $item, $order, true );
				$this->log(
					array(
						__METHOD__,
						__LINE__,
						'order' => $order->get_id(),
						'item'  => $item->get_id(),
						'msg'   => 'Skipping bundle product',
						'data'  => array(
							'$item_data' => $item_data,
						),
					)
				);
				continue;
			}

			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$item_data = $this->get_item_data( $item, $order, true );
				$price     = self::get_item_price( $item, $order );
				$total     = rp_prepare_amount( $price['with_tax'], $order->get_currency() );

				$this->log(
					array(
						__METHOD__,
						__LINE__,
						'order' => $order->get_id(),
						'item'  => $item->get_id(),
						'data'  => array(
							'$item_data' => $item_data,
							'$price'     => $price,
							'$total'     => $total,
						),
					)
				);

				if ( $total <= 0 && method_exists( $item, 'get_product' ) && $item->get_product() && wcs_is_subscription_product( $item->get_product() ) ) {
					$this->log(
						array(
							__METHOD__,
							__LINE__,
							'order' => $order->get_id(),
							'item'  => $item->get_id(),
							'msg'   => 'Condition 1',
						)
					);
					WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
				} elseif ( $total > 0 && $this->check_capture_allowed( $order ) ) {
					$this->log(
						array(
							__METHOD__,
							__LINE__,
							'order' => $order->get_id(),
							'item'  => $item->get_id(),
							'msg'   => 'Condition 2',
						)
					);
					$items_data[] = $item_data;
					$line_items[] = $item;
					$total_all   += $total;
				} else {
					$item_data = $this->get_item_data( $item, $order, true );
					$price     = self::get_item_price( $item, $order );
					$total     = rp_prepare_amount( $price['with_tax'], $order->get_currency() );

					// Check for total > 0 before adding to items_data.
					if ( $total > 0 ) {
						$items_data[] = $item_data;
						$line_items[] = $item;
						$total_all   += $total;
						$this->log(
							array(
								__METHOD__,
								__LINE__,
								'order' => $order->get_id(),
								'item'  => $item->get_id(),
								'msg'   => 'else Condition - item with amount > 0',
								'data'  => array(
									'total' => $total,
								),
							)
						);
					} else {
						// For items with a price of 0, mark them as settled immediately.
						$this->complete_settle( $item, $order, 0 );
						$this->log(
							array(
								__METHOD__,
								__LINE__,
								'order' => $order->get_id(),
								'item'  => $item->get_id(),
								'msg'   => 'else Condition - zero amount item marked as settled',
								'data'  => array(
									'total' => $total,
								),
							)
						);
					}
				}
			}
		}

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order->get_id(),
				'data'  => array(
					'$items_data' => $items_data,
					'$line_items' => $line_items,
					'$total_all'  => $total_all,
				),
			)
		);

		// Add discount line.
		if ( $order->get_total_discount( false ) > 0 ) {
			$prices_incl_tax   = wc_prices_include_tax();
			$discount          = $order->get_total_discount();
			$discount_with_tax = $order->get_total_discount( false );
			$tax               = $discount_with_tax - $discount;
			$tax_percent       = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			if ( $prices_incl_tax ) {
				/**
				 * Discount for simple product included tax
				 */
				$simple_discount_amount = $discount_with_tax;
			} else {
				$simple_discount_amount = $discount;
			}

			$discount_amount = round( - 1 * rp_prepare_amount( $simple_discount_amount, $order->get_currency() ) );

			if ( $discount_amount < 0 ) {
				$items_discount = array(
					'ordertext'       => __( 'Discount', 'reepay-checkout-gateway' ),
					'quantity'        => 1,
					'amount'          => round( $discount_amount, 2 ),
					'vat'             => round( $tax_percent / 100, 2 ),
					'amount_incl_vat' => $prices_incl_tax,
				);
				$items_data[]   = $items_discount;
				$total_all     += $discount_amount;
			}
		}

		foreach ( $order->get_items( array( 'shipping', 'fee', PWGiftCardsIntegration::KEY_PW_GIFT_ITEMS ) ) as $item ) {
			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$item_data = $this->get_item_data( $item, $order, true );
				$price     = self::get_item_price( $item, $order );
				$total     = rp_prepare_amount( $price['with_tax'], $order->get_currency() );

				if ( 0.0 !== $total && $this->check_capture_allowed( $order ) ) {
					$items_data[] = $item_data;
					$line_items[] = $item;
					$total_all   += $total;
				}
			}
		}

		foreach ( $order->get_items( WCGiftCardsIntegration::KEY_WC_GIFT_ITEMS ) as $item ) {
			$item_data    = $this->get_item_data( $item, $order, true );
			$price        = $item->get_amount() * - 1;
			$total        = rp_prepare_amount( $price, $order->get_currency() );
			$items_data[] = $item_data;
			$line_items[] = $item;
			$total_all   += $total;
		}

		if ( reepay()->get_setting( 'skip_order_lines' ) === 'yes' ) {
			$total_all = rp_prepare_amount( $order->get_total(), $order->get_currency() );
			// Note: Even with skip_order_lines=yes, we still pass $items_data to settle_items
			// so the API can calculate VAT and include it in the settlement amount.
		}

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order->get_id(),
				'data'  => array(
					'$items_data' => $items_data,
					'$line_items' => $line_items,
					'$total_all'  => $total_all,
				),
			)
		);

		if ( ! empty( $items_data ) ) {
			return $this->settle_items( $order, $items_data, $total_all, $line_items, true );
		} else {
			$this->log(
				array(
					__METHOD__,
					__LINE__,
					'order' => $order->get_id(),
					'msg'   => 'empty item data',
				)
			);
		}

		return false;
	}

	/**
	 * Settle order items.
	 *
	 * @param WC_Order        $order        order to settle.
	 * @param array[]         $items_data   items data from self::get_item_data.
	 * @param float           $total_all    order total amount ot settle.
	 * @param WC_Order_Item[] $line_items   order line items.
	 * @param bool            $instant_note add order note instantly.
	 *
	 * @return bool
	 */
	public function settle_items( WC_Order $order, array $items_data, float $total_all, array $line_items, bool $instant_note = true ): bool {
		unset( $_POST['post_status'] ); // // Prevent order status changing by WooCommerce

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order->get_id(),
				'data'  => array(
					'$items_data'   => $items_data,
					'$line_items'   => $line_items,
					'$total_all'    => $total_all,
					'$instant_note' => $instant_note,
				),
			)
		);

		$invoice_data = reepay()->api( $order )->get_invoice_by_handle( 'order-' . $order->get_id() );
		if ( is_array( $invoice_data ) && array_key_exists( 'authorized_amount', $invoice_data ) && array_key_exists( 'settled_amount', $invoice_data ) && $invoice_data['authorized_amount'] - $invoice_data['settled_amount'] <= 0 ) {
			return false;
		}

		// for payment method with only settle.
		if ( is_array( $invoice_data ) && ! array_key_exists( 'authorized_amount', $invoice_data ) && 'settled' === $invoice_data['state'] && array_key_exists( 'amount', $invoice_data ) && array_key_exists( 'settled_amount', $invoice_data ) && $invoice_data['settled_amount'] === $invoice_data['amount'] ) {
			return false;
		}

		$result = reepay()->api( $order )->settle( $order, $total_all, $items_data, $line_items, $instant_note );

		$this->log(
			array(
				__METHOD__,
				__LINE__,
				'order' => $order->get_id(),
				'data'  => array(
					'$result' => $result,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			rp_get_payment_method( $order )->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );
			set_transient( 'reepay_api_action_error', $result->get_error_message(), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		if ( 'failed' === $result['state'] ) {
			set_transient( 'reepay_api_action_error', __( 'Failed to settle item', 'reepay-checkout-gateway' ), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		foreach ( $line_items as $item ) {
			// Note: This is called after settle_items() succeeds, just to mark items as settled
			// The actual API call already happened with the correct amounts from multi_settle()
			// We use the same $use_pre_discount_price flag for consistency, but it doesn't affect the API
			$item_data = $this->get_item_data( $item, $order, true );
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

		// LOG: Start of settle_item
		$this->log(
			array(
				'=== SETTLE_ITEM START ===',
				'method'       => __METHOD__,
				'line'         => __LINE__,
				'order_id'     => $order->get_id(),
				'item_id'      => $item->get_id(),
				'item_name'    => $item->get_name(),
				'item_qty'     => $item->get_quantity(),
				'already_settled' => ! empty( $settled ),
			)
		);

		if ( ! empty( $settled ) ) {
			$this->log( 'Item already settled, skipping' );
			return true;
		}

		unset( $_POST['post_status'] ); // Prevent order status changing by WooCommerce.

		$item_data = $this->get_item_data( $item, $order );
		$price     = self::get_item_price( $item, $order );
		$total     = rp_prepare_amount( $price['with_tax'], $order->get_currency() );

		// LOG: Initial data after get_item_data
		$this->log(
			array(
				'--- Initial Data ---',
				'item_data_before_override' => $item_data,
				'price_data'                => $price,
				'total'                     => $total,
				'total_formatted'           => rp_make_initial_amount( $total, $order->get_currency() ),
			)
		);

		if ( $total <= 0 ) {
			$this->log( 'Total is zero or negative, marking as settled' );
			do_action( 'reepay_order_item_settled', $item, $order );

			return true;
		}

		if ( ! $this->check_capture_allowed( $order ) ) {
			$this->log( 'Capture not allowed for this order' );
			return false;
		}

		// Amount calculation in case product has a discount for tax-excluding pricing.
		// BWPM-177: Only apply this fix for tax-EXCLUDING pricing scenarios.
		// When prices include tax, the amount from get_item_data() is already correct.
		// The condition 'subtotal > original' is TRUE whenever ANY discount is applied,
		// but we should only override the amount for tax-exclusive pricing.
		//
		// TAX-EXCLUSIVE-DISCOUNT-BUG FIX:
		// When tax-exclusive mode + discount, we need to send the amount INCLUDING VAT
		// because Reepay API expects the total amount to capture, not the base amount.
		// The original code was sending amount WITHOUT VAT, causing under-capture.

		$condition_triggered = ! wc_prices_include_tax() && $price['subtotal'] > $price['original'];

		// LOG: Check if override condition will trigger
		$this->log(
			array(
				'--- Override Condition Check ---',
				'wc_prices_include_tax'     => wc_prices_include_tax(),
				'price[subtotal]'           => $price['subtotal'],
				'price[original]'           => $price['original'],
				'has_discount'              => $price['subtotal'] > $price['original'],
				'condition_will_trigger'    => $condition_triggered,
			)
		);

		if ( $condition_triggered ) {
			// Store original values for logging
			$original_item_data = $item_data;
			$original_total     = $total;

			// CRITICAL FIX: WooCommerce's get_line_total() already rounds the value
			// For example: 22.5 * 1.25 = 28.125, but get_line_total() returns 28.13 (rounded up)
			// We need to calculate from the raw values to apply floor() correctly
			// Calculate: original (excl. VAT) * (1 + tax_percent/100) = with_tax (incl. VAT)
			$tax_rate = $price['tax_percent'] / 100;
			$with_tax_raw = $price['original'] * ( 1 + $tax_rate );

			// Use with_tax_raw (amount including VAT) instead of original (amount excluding VAT)
			// ROUNDING FIX: Use floor() to round DOWN to match Frisbii's rounding behavior
			// Example: 28.125 -> 28.12 (not 28.13)
			// This prevents 0.01 discrepancy between WooCommerce and Reepay
			$unit_price_before_floor = $with_tax_raw / $item->get_quantity();
			$unit_price              = floor( $unit_price_before_floor * 100 ) / 100;
			$item_data['amount']     = rp_prepare_amount( $unit_price, $order->get_currency() );
			// Set vat to 0 and amount_incl_vat to true since amount already includes VAT
			$item_data['vat']             = 0;
			$item_data['amount_incl_vat'] = true;

			// IMPORTANT: Update $total to match the floored amount
			// This ensures we send the same amount in both $total and $item_data['amount']
			$total = $item_data['amount'];

			// LOG: Override applied
			$this->log(
				array(
					'--- Override Applied ---',
					'price[original]'           => $price['original'],
					'price[with_tax]'           => $price['with_tax'],
					'tax_rate'                  => $tax_rate,
					'with_tax_raw'              => $with_tax_raw,
					'unit_price_before_floor'   => $unit_price_before_floor,
					'unit_price_after_floor'    => $unit_price,
					'rounding_difference'       => $unit_price_before_floor - $unit_price,
					'amount_in_cents'           => $item_data['amount'],
					'amount_formatted'          => rp_make_initial_amount( $item_data['amount'], $order->get_currency() ),
					'original_total'            => $original_total,
					'new_total'                 => $total,
					'total_difference'          => $original_total - $total,
					'original_item_data'        => $original_item_data,
					'new_item_data'             => $item_data,
				)
			);
		}

		// LOG: Final data before sending to API
		$this->log(
			array(
				'--- Final Data Before API Call ---',
				'order_id'              => $order->get_id(),
				'item_id'               => $item->get_id(),
				'item_name'             => $item->get_name(),
				'currency'              => $order->get_currency(),
				'wc_prices_include_tax' => wc_prices_include_tax(),
				'item_data'             => $item_data,
				'total'                 => $total,
				'total_formatted'       => rp_make_initial_amount( $total, $order->get_currency() ),
				'order_lines_to_send'   => array( $item_data ),
			)
		);

		$result = reepay()->api( $order )->settle( $order, $total, array( $item_data ), $item );

		// LOG: API Response
		$this->log(
			array(
				'--- API Response ---',
				'is_error'      => is_wp_error( $result ),
				'result'        => $result,
			)
		);

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			rp_get_payment_method( $order )->log( sprintf( '%s Error: %s', __METHOD__, $error_message ) );
			set_transient( 'reepay_api_action_error', $error_message, MINUTE_IN_SECONDS / 2 );

			// LOG: Error details
			$this->log(
				array(
					'!!! API ERROR !!!',
					'error_message' => $error_message,
					'error_code'    => $result->get_error_code(),
					'error_data'    => $result->get_error_data(),
				)
			);

			return false;
		}

		if ( 'failed' === $result['state'] ) {
			set_transient( 'reepay_api_action_error', __( 'Failed to settle item', 'reepay-checkout-gateway' ), MINUTE_IN_SECONDS / 2 );

			// LOG: Settlement failed
			$this->log(
				array(
					'!!! SETTLEMENT FAILED !!!',
					'result_state' => $result['state'],
					'result'       => $result,
				)
			);

			return false;
		}

		$this->complete_settle( $item, $order, $total );

		// LOG: Success
		$this->log(
			array(
				'=== SETTLE_ITEM SUCCESS ===',
				'order_id'        => $order->get_id(),
				'item_id'         => $item->get_id(),
				'item_name'       => $item->get_name(),
				'captured_amount' => rp_make_initial_amount( $total, $order->get_currency() ),
				'currency'        => $order->get_currency(),
				'result'          => $result,
			)
		);

		return true;
	}

	/**
	 * Settle specified amount
	 *
	 * @param WC_Order $order order to settle.
	 * @param float    $amount amount to settle.
	 *
	 * @return bool
	 */
	public function settle_amount( WC_Order $order, float $amount ): bool {
		unset( $_POST['post_status'] ); // Prevent order status changing by WooCommerce.

		if ( $amount <= 0 ) {
			return false;
		}

		if ( ! $this->check_capture_allowed( $order ) ) {
			return false;
		}

		$amount = rp_prepare_amount( $amount, $order->get_currency() );

		$result = reepay()->api( $order )->settle( $order, $amount, false, false );

		if ( is_wp_error( $result ) ) {
			rp_get_payment_method( $order )->log( sprintf( '%s Error: %s', __METHOD__, $result->get_error_message() ) );
			set_transient( 'reepay_api_action_error', $result->get_error_message(), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		if ( 'failed' === $result['state'] ) {
			set_transient( 'reepay_api_action_error', __( 'Failed to settle amount', 'reepay-checkout-gateway' ), MINUTE_IN_SECONDS / 2 );

			return false;
		}

		$remaining_balance = $result['authorized_amount'] - $result['amount'];

		$order->update_meta_data( '_reepay_remaining_balance', $remaining_balance );
		$order->save_meta_data();
		$order->save();

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

		// for payment method with only settle.
		if ( is_array( $invoice_data ) && ! array_key_exists( 'authorized_amount', $invoice_data ) && 'settled' === $invoice_data['state'] && array_key_exists( 'amount', $invoice_data ) && array_key_exists( 'settled_amount', $invoice_data ) && $invoice_data['settled_amount'] === $invoice_data['amount'] ) {
			return false;
		}

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
			if ( WPCProductBundlesWooCommerceIntegration::is_order_item_bundle( $item ) ) {
				continue;
			}
			if ( empty( $item->get_meta( 'settled' ) ) ) {
				$amount += self::get_item_price( $item, $order )['with_tax'];
			}
		}

		$amount -= PWGiftCardsIntegration::get_amount_gift_cards_from_order( $order );

		$amount -= WCGiftCardsIntegration::get_amount_gift_cards_from_order( $order );

		return $amount;
	}

	/**
	 * Prepare order item data for reepay
	 *
	 * @param WC_Order_Item $order_item              order item to get data.
	 * @param WC_Order      $order                   current order.
	 * @param bool          $use_pre_discount_price  whether to use pre-discount price (for multi_settle with separate discount line).
	 *
	 * @return array
	 */
	public function get_item_data( WC_Order_Item $order_item, WC_Order $order, bool $use_pre_discount_price = false ): array {
		$prices_incl_tax = wc_prices_include_tax();
		$price           = self::get_item_price( $order_item, $order );

		$tax_percent = $price['tax_percent'];

		if ( $order_item->is_type( PWGiftCardsIntegration::KEY_PW_GIFT_ITEMS ) ) {
			$unit_price = PWGiftCardsIntegration::get_negative_amount_from_order_item( $order, $order_item );
			$ordertext  = rp_clear_ordertext( $order_item->get_name() );
		} elseif ( $order_item->is_type( WCGiftCardsIntegration::KEY_WC_GIFT_ITEMS ) ) {
			$unit_price = WCGiftCardsIntegration::get_negative_amount_from_order_item( $order, $order_item );
			$ordertext  = WCGiftCardsIntegration::get_name_from_order_item( $order, $order_item );
		} elseif ( $order_item->is_type( 'shipping' ) ) {
			$unit_price = ( $prices_incl_tax ? $price['with_tax'] : $price['original'] );
			$ordertext  = rp_clear_ordertext( $order_item->get_name() );
		} elseif ( $order_item->is_type( 'fee' ) ) {
			$unit_price = ( $prices_incl_tax ? $price['with_tax'] : $price['original'] );
			$ordertext  = rp_clear_ordertext( $order_item->get_name() );
		} else {
			// BWPM-177: Handle two different capture scenarios:
			// 1. multi_settle (Capture All): Uses pre-discount prices + separate discount line
			// 2. settle_item (Capture Individual): Uses post-discount prices directly
			if ( $use_pre_discount_price ) {
				// For multi_settle: use pre-discount prices (subtotal_with_tax/subtotal)
				// because discount will be added as a separate line item
				$unit_price = round( ( $prices_incl_tax ? $price['subtotal_with_tax'] : $price['subtotal'] ) / $order_item->get_quantity(), 2 );
			} else {
				// For settle_item: use post-discount prices (with_tax/original)
				// because we're capturing the item at its actual final price
				$unit_price = round( ( $prices_incl_tax ? $price['with_tax'] : $price['original'] ) / $order_item->get_quantity(), 2 );
			}
			$ordertext = rp_clear_ordertext( $order_item->get_name() );
		}

		return array(
			'ordertext'       => $ordertext,
			'quantity'        => $order_item->get_quantity(),
			'amount'          => rp_prepare_amount( $unit_price, $order->get_currency() ),
			'vat'             => round( $tax_percent / 100, 2 ),
			'amount_incl_vat' => $prices_incl_tax,
		);
	}

	/**
	 * Get order item price for reepay.
	 *
	 * @param WC_Order_Item|WC_Order_Item_Product|int $order_item order item to get price and tax.
	 * @param WC_Order                                $order      current order.
	 *
	 * @return array
	 * @noinspection PhpCastIsUnnecessaryInspection
	 */
	public static function get_item_price( $order_item, WC_Order $order ): array {
		/**
		 * Condition check $order_item is_object or is_array
		 */
		if ( is_object( $order_item ) && method_exists( $order_item, 'get_meta' ) ) {
			$discount = floatval( $order_item->get_meta( '_line_discount' ) );
		} elseif ( is_array( $order_item ) ) {
			$discount   = floatval( $order_item[0]->get_meta( '_line_discount' ) );
			$order_item = $order_item[0];
		}
		if ( empty( $discount ) ) {
			$discount = 0;
		}

		$price['subtotal']          = floatval( $order->get_line_subtotal( $order_item, false, false ) );
		$price['subtotal_with_tax'] = floatval( $order->get_line_subtotal( $order_item, true, false ) );
		$price['original']          = floatval( $order->get_line_total( $order_item, false, false ) );
		$price['with_tax']          = floatval( $order->get_line_total( $order_item, true, false ) );

		if ( WPCProductBundlesWooCommerceIntegration::is_active_plugin() ) {
			$price_bundle = floatval( $order_item->get_meta( '_woosb_price' ) );
			if ( ! empty( $price_bundle ) ) {
				$price['original'] = $price_bundle;

				$price['with_tax'] += $price_bundle;
			}
		}

		// Get tax status from product.
		$tax_status = 'taxable';
		if ( $order_item instanceof WC_Order_Item_Product ) {
			$product = $order_item->get_product();
			if ( $product ) {
				$tax_status = $product->get_tax_status();
			}
		}

		if ( 'none' === $tax_status ) {
			$price['tax_percent'] = 0;
		} else {
			$tax = $price['with_tax'] - $price['original'];
			if ( abs( floatval( $tax ) ) < 0.001 ) {
				$subtotal          = round( $price['subtotal'] / $order_item->get_quantity(), 2 );
				$subtotal_with_tax = round( $price['subtotal_with_tax'] / $order_item->get_quantity(), 2 );
				$tax               = $subtotal_with_tax - $subtotal;
				if ( abs( floatval( $tax ) ) > 0.001 ) {
					$price_tax_percent = round( 100 / ( $subtotal / $tax ) );
				} else {
					$price_tax_percent = 0;
				}
			} else {
				$price_tax_percent = ( $tax > 0 && $price['original'] > 0 ) ? round( 100 / ( $price['original'] / $tax ) ) : 0;
			}
			$price['tax_percent'] = round( $price_tax_percent, 2 );
		}

		$price['original_with_discount'] = $price['original'] + $discount;
		$price['with_tax_and_discount']  = $price['with_tax'] + $discount;

		return $price;
	}
}
