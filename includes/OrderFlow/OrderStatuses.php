<?php
/**
 * Changing order status depending on order status in reepay and gateway settings
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use Reepay\Checkout\Gateways\ReepayCheckout;
use Reepay\Checkout\Gateways\ReepayGateway;
use WC_Admin_Meta_Boxes;
use WC_Data_Exception;
use WC_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class OrderStatuses
 *
 * @package Reepay\Checkout\OrderFlow
 */
class OrderStatuses {
	/**
	 * Is sync enabled
	 *
	 * @var bool
	 */
	public static bool $status_sync_enabled;

	/**
	 * Order created status
	 *
	 * @var string
	 */
	public static string $status_created;

	/**
	 * Order authorized status
	 *
	 * @var string
	 */
	public static string $status_authorized;

	/**
	 * Order settled status
	 *
	 * @var string
	 */
	public static string $status_settled;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'reepay_checkout_form_fields', array( $this, 'form_fields' ), 10, 2 );

		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'add_valid_order_statuses_for_payment_complete' ), 10, 2 );

		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'payment_complete_order_status' ), 10, 3 );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 10 );

		add_action( 'woocommerce_payment_complete', array( $this, 'payment_complete' ), 10, 1 );

		add_filter( 'wc_order_is_editable', array( $this, 'is_editable' ), 10, 2 );

		add_filter( 'woocommerce_order_is_paid', array( $this, 'is_paid' ), 10, 2 );

		add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'cancel_unpaid_order' ), 10, 2 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 4 );
	}

	/**
	 * Init status variables
	 */
	public static function init_statuses() {
		self::$status_sync_enabled = reepay()->get_setting( 'enable_sync' ) === 'yes';
		self::$status_created      = str_replace( 'wc-', '', reepay()->get_setting( 'status_created' ) ) ?: 'pending';
		self::$status_authorized   = str_replace( 'wc-', '', reepay()->get_setting( 'status_authorized' ) ) ?: 'on-hold';
		self::$status_settled      = str_replace( 'wc-', '', reepay()->get_setting( 'status_settled' ) ) ?: 'processing';
	}

	/**
	 * Add complete payment hook for all statuses
	 */
	public function plugins_loaded() {
		foreach ( array_keys( wc_get_order_statuses() ) as $status ) {
			$status = str_replace( 'wc-', '', $status );
			add_action( 'woocommerce_payment_complete_order_status_' . $status, array( $this, 'payment_complete' ), 10, 1 );
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
		$form_fields['hr_sync'] = array(
			'type' => 'separator',
		);

		$form_fields['enable_sync'] = array(
			'title'       => __( 'Sync statuses', 'reepay-checkout-gateway' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable sync', 'reepay-checkout-gateway' ),
			'description' => __( '2-way synchronization of order statuses in Woocommerce with invoice statuses in Billwerk+', 'reepay-checkout-gateway' ),
			'default'     => 'yes',
		);

		$pending_statuses = wc_get_order_statuses();
		unset(
			$pending_statuses['wc-processing'],
			$pending_statuses['wc-on-hold'],
			$pending_statuses['wc-completed'],
			$pending_statuses['wc-cancelled'],
			$pending_statuses['wc-refunded'],
			$pending_statuses['wc-failed']
		);

		$form_fields['status_created'] = array(
			'title'   => __( 'Status: Billwerk+ Created', 'reepay-checkout-gateway' ),
			'type'    => 'select',
			'options' => $pending_statuses,
			'default' => 'wc-pending',
		);

		$authorized_statuses = wc_get_order_statuses();
		unset(
			$authorized_statuses['wc-pending'],
			$authorized_statuses['wc-cancelled'],
			$authorized_statuses['wc-refunded'],
			$authorized_statuses['wc-failed']
		);

		$form_fields['status_authorized'] = array(
			'title'   => __( 'Status: Billwerk+ Authorized', 'reepay-checkout-gateway' ),
			'type'    => 'select',
			'options' => $authorized_statuses,
			'default' => 'wc-on-hold',
		);

		$settled_statuses = wc_get_order_statuses();
		unset(
			$settled_statuses['wc-pending'],
			$settled_statuses['wc-cancelled'],
			$settled_statuses['wc-refunded'],
			$settled_statuses['wc-failed']
		);

		$form_fields['status_settled'] = array(
			'title'   => __( 'Status: Billwerk+ Settled', 'reepay-checkout-gateway' ),
			'type'    => 'select',
			'options' => $settled_statuses,
			'default' => 'wc-processing',
		);

		return $form_fields;
	}

	/**
	 * Allow processing/completed statuses for capture
	 *
	 * @param array    $statuses default statuses.
	 * @param WC_Order $order    current order.
	 *
	 * @return array
	 */
	public function add_valid_order_statuses_for_payment_complete( array $statuses, WC_Order $order ): array {
		if ( self::$status_sync_enabled && rp_is_order_paid_via_reepay( $order ) ) {
			$statuses = array_merge( $statuses, array( self::$status_authorized, self::$status_settled ) );
		}

		return $statuses;
	}

	/**
	 * Get Status For Payment Complete.
	 *
	 * @param string   $status   current status.
	 * @param int      $order_id current order id.
	 * @param WC_Order $order    current order.
	 *
	 * @return mixed|string
	 */
	public function payment_complete_order_status( string $status, int $order_id, WC_Order $order ) {
		if ( self::$status_sync_enabled && rp_is_order_paid_via_reepay( $order ) ) {
			$status = apply_filters( 'reepay_settled_order_status', self::$status_settled, $order );
		}

		return $status;
	}

	/**
	 * Payment Complete.
	 *
	 * @param int $order_id order id.
	 */
	public function payment_complete( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( self::$status_sync_enabled && rp_is_order_paid_via_reepay( $order ) ) {
			$status = apply_filters(
				'reepay_settled_order_status',
				self::$status_settled,
				$order
			);

			if ( ! $order->has_status( $status ) ) {
				$order->set_status( $status );
				$order->save();
			}
		}
	}

	/**
	 * Get a status for Authorized payments.
	 *
	 * @param WC_Order $order  current order.
	 * @param string   $default default status.
	 *
	 * @return string
	 */
	public static function get_authorized_order_status( WC_Order $order, string $default = 'on-hold' ): string {
		if ( self::$status_sync_enabled && rp_is_order_paid_via_reepay( $order ) && ! order_contains_subscription( $order ) ) {
			return self::$status_authorized;
		}

		return $default;
	}

	/**
	 * Set Authorized Status.
	 *
	 * @param WC_Order $order          order to set.
	 * @param string   $note           order note.
	 * @param string   $transaction_id transaction id to set.
	 *
	 * @return bool
	 */
	public static function set_authorized_status( WC_Order $order, string $note = '', string $transaction_id = '' ): bool {
		$authorized_status = self::get_authorized_order_status( $order );

		if ( ! empty( $order->get_meta( '_reepay_state_authorized' ) ) || $order->get_status() === $authorized_status ) {
			return false;
		}

		if ( empty( $order->get_meta( '_order_stock_reduced' ) ) ) {
			wc_reduce_stock_levels( $order->get_id() );
		}

		self::update_order_status(
			$order,
			$authorized_status,
			$note,
			$transaction_id
		);

		$order->update_meta_data( '_reepay_state_authorized', 1 );
		$order->save_meta_data();

		return true;
	}

	/**
	 * Set Settled Status.
	 *
	 * @param WC_Order $order          order to set.
	 * @param string   $note           order note.
	 * @param string   $transaction_id transaction id to set.
	 *
	 * @return bool
	 */
	public static function set_settled_status( WC_Order $order, string $note = '', string $transaction_id = '' ): bool {
		if ( ! rp_is_reepay_payment_method( $order->get_payment_method() ) || ! empty( $order->get_meta( '_reepay_state_settled' ) ) ) {
			return false;
		}

		$invoice = reepay()->api( $order )->get_invoice_data( $order );

		if ( is_wp_error( $invoice ) ) {
			return false;
		}

		if ( $invoice['settled_amount'] < $invoice['authorized_amount'] ) {
			// Use the authorized status if order has been settled partially.
			self::set_authorized_status( $order, $note, $transaction_id );
		} else {
			$order->payment_complete( $transaction_id );

			if ( $note ) {
				$order->add_order_note( $note );
			}

			$order->update_meta_data( '_reepay_state_settled', 1 );
			$order->save_meta_data();
		}

		return true;
	}

	/**
	 * Update Order Status.
	 *
	 * @param WC_Order    $order          order to update.
	 * @param string      $new_status     status to set.
	 * @param string      $note           order note.
	 * @param string|null $transaction_id transaction to set.
	 * @param bool        $manual         Is this a manual order status change.
	 *
	 * @return void
	 */
	public static function update_order_status( WC_Order $order, string $new_status, $note = '', $transaction_id = null, $manual = false ) {
		if ( ! empty( $transaction_id ) ) {
			$order->set_transaction_id( $transaction_id );
		}

		$order->set_status( $new_status, $note, $manual );
		$order->save();
	}

	/**
	 * Checks if an order can be edited, specifically for use on the Edit Order screen.
	 *
	 * @param bool     $is_editable default value.
	 * @param WC_Order $order       order to check.
	 *
	 * @return bool
	 */
	public function is_editable( bool $is_editable, WC_Order $order ): bool {
		if ( ! $is_editable &&
			 self::$status_sync_enabled &&
			 rp_is_order_paid_via_reepay( $order ) &&
			 in_array( $order->get_status(), array( self::$status_created, self::$status_authorized ), true ) ) {
			$is_editable = true;
		}

		return $is_editable;
	}

	/**
	 * Returns if an order has been paid for based on the order status.
	 *
	 * @param bool     $is_paid default value.
	 * @param WC_Order $order   order to check.
	 *
	 * @return bool
	 */
	public function is_paid( bool $is_paid, WC_Order $order ): bool {
		if ( ! $is_paid &&
			 self::$status_sync_enabled &&
			 rp_is_order_paid_via_reepay( $order ) &&
			 $order->get_status() === self::$status_settled
		) {
			$is_paid = true;
		}

		return $is_paid;
	}

	/**
	 * Prevent the pending cancellation for Reepay Orders if allowed
	 *
	 * @param bool     $maybe_cancel default value.
	 * @param WC_Order $order        order to check.
	 *
	 * @return bool
	 * @see wc_cancel_unpaid_orders()
	 */
	public function cancel_unpaid_order( bool $maybe_cancel, WC_Order $order ): bool {
		if ( $maybe_cancel && rp_is_order_paid_via_reepay( $order ) && 'yes' !== reepay()->get_setting( 'enable_order_autocancel' ) ) {
			$maybe_cancel = false;
		}

		return $maybe_cancel;
	}

	/**
	 * Order Status Change: Capture/Cancel
	 *
	 * @param int      $order_id current order id.
	 * @param string   $from     old status.
	 * @param string   $to       current status.
	 * @param WC_Order $order    current order.
	 *
	 * @throws Exception If error with payment capture.
	 */
	public function order_status_changed( int $order_id, string $from, string $to, WC_Order $order ) {
		if ( ! rp_is_order_paid_via_reepay( $order ) || order_contains_subscription( $order ) ) {
			return;
		}

		$gateway = rp_get_payment_method( $order );

		switch ( $to ) {
			case 'cancelled':
				// Cancel payment.
				if ( $gateway->can_cancel( $order ) ) {
					try {
						$gateway->cancel_payment( $order );
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error( $message );

						// Rollback.
						// translators: %s error message.
						$order->update_status( $from, sprintf( __( 'Order status rollback. %s', 'reepay-checkout-gateway' ), $message ) );
					}
				}
				break;
			case self::$status_sync_enabled ? self::$status_settled : 'processing':
				// Capture payment.
				$value = get_transient( 'reepay_order_complete_should_settle_' . $order->get_id() );
				if ( ( '1' === $value || false === $value ) && $gateway->can_capture( $order ) ) {
					try {
						$order_data = reepay()->api( $gateway )->get_invoice_data( $order );
						if ( is_wp_error( $order_data ) ) {
							throw new Exception( $order_data->get_error_message() );
						}

						$amount_to_capture = rp_make_initial_amount( $order_data['authorized_amount'] - $order_data['settled_amount'], $order->get_currency() );
						$items_to_capture  = InstantSettle::calculate_instant_settle( $order )['items'];

						if ( ! empty( $items_to_capture ) && $amount_to_capture > 0 ) {
							$gateway->capture_payment( $order, $amount_to_capture );
						}
					} catch ( Exception $e ) {
						$message = $e->getMessage();
						WC_Admin_Meta_Boxes::add_error(
							sprintf(
							// translators: %1$s order edit url, #%2$u order id.
								__( 'Error with order <a href="%1$s">#%2$u</a>. View order notes for more info', 'reepay-checkout-gateway' ),
								$order->get_edit_order_url(),
								$order_id
							)
						);

						// Rollback.
						$order->update_status(
							$from,
							// translators: %s rollback message.
							sprintf( __( 'Order status rollback. %s', 'reepay-checkout-gateway' ), $message )
						);
					}
				}
				break;
		}
	}
}
