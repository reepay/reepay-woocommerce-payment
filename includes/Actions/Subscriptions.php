<?php
/**
 * Woocommerce and Reepay subscriptions payment logic
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Actions;

use DOMDocument;
use DOMElement;
use Exception;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\Tokens\ReepayTokens;
use Reepay\Checkout\Tokens\TokenReepay;
use WC_Order;
use WC_Order_Refund;
use WC_Payment_Gateway;
use WC_Payment_Token;
use WC_Subscription;

defined( 'ABSPATH' ) || exit();

/**
 * Class Subscriptions
 *
 * @package Reepay\Checkout
 */
class Subscriptions {
	const PAYMENT_METHODS = array(
		'reepay_checkout',
		'reepay_mobilepay_subscriptions',
	);

	/**
	 * Subscriptions constructor.
	 */
	public function __construct() {
		// Add payment token when subscription was created.
		add_action( 'woocommerce_payment_token_added_to_order', array( $this, 'add_payment_token_id' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'add_subscription_card_id' ), 10, 1 );
		add_action( 'woocommerce_payment_complete_order_status_on-hold', array( $this, 'add_subscription_card_id' ), 10, 1 );

		// Subscriptions.
		add_filter( 'wcs_renewal_order_created', array( $this, 'renewal_order_created' ), 10, 2 );

		foreach ( self::PAYMENT_METHODS as $method ) {
			add_action( 'woocommerce_thankyou_' . $method, array( $this, 'thankyou_page' ) );

			// Update failing payment method.
			add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $method, array( $this, 'update_failing_payment_method' ), 10, 2 );

			// Charge the payment when a subscription payment is due.
			add_action( 'woocommerce_scheduled_subscription_payment_' . $method, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

			// Charge the payment when a subscription payment is due.
			add_action( 'scheduled_subscription_payment_' . $method, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		}

		// Don't transfer customer meta to resubscribe orders.
		add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );

		// Allow store managers to manually set card id as the payment method on a subscription.
		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );

		// Validate the payment meta data.
		add_action( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 3 );

		// Save payment method meta data for the Subscription.
		add_action( 'wcs_save_other_payment_meta', array( $this, 'save_subscription_payment_meta' ), 10, 4 );

		// Display the credit card used for a subscription in the "My Subscriptions" table.
		add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

		// Lock "Save card" if needs.
		add_filter( 'woocommerce_payment_gateway_save_new_payment_method_option_html', array( $this, 'save_new_payment_method_option_html' ), 10, 2 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'create_sub_invoice' ), 10, 4 );

		add_action( 'updated_post_meta', array( $this, 'sync_reepay_token_meta' ), 10, 4 );
	}

	/**
	 * Add Token ID.
	 *
	 * @param int              $order_id  current order id.
	 * @param int              $token_id  token id to add.
	 * @param WC_Payment_Token $token     token instance.
	 * @param array            $token_ids all order tokens (include current).
	 */
	public function add_payment_token_id( int $order_id, int $token_id, WC_Payment_Token $token, array $token_ids ) {
		$order = wc_get_order( $order_id );
		if ( in_array( $order->get_payment_method(), self::PAYMENT_METHODS, true ) ) {
			$order->update_meta_data( '_reepay_token_id', $token_id );
			$order->update_meta_data( '_reepay_token', $token->get_token() );
			$order->save_meta_data();
		}
	}

	/**
	 * Add Card ID when Subscription was created
	 *
	 * @param int $order_id order id to add card.
	 *
	 * @throws Exception If invalid token sent to assign_payment_token.
	 */
	public function add_subscription_card_id( int $order_id ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		// Get subscriptions.
		$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
		foreach ( $subscriptions as $subscription ) {
			$gateway = rp_get_payment_method( $subscription );

			if ( ! $gateway ) {
				continue;
			}

			$token = ReepayTokens::get_payment_token_subscription( $subscription );
			if ( ! $token ) {
				// Copy tokens from parent order.
				$order = wc_get_order( $order_id );
				$token = ReepayTokens::get_payment_token_order( $order );

				if ( $token ) {
					ReepayTokens::assign_payment_token( $subscription, $token );
				}
			}
		}
	}

	/**
	 * Create a renewal order to record a scheduled subscription payment.
	 *
	 * @param WC_Order|int        $renewal_order new order.
	 * @param WC_Subscription|int $subscription new subscription.
	 *
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public function renewal_order_created( $renewal_order, $subscription ) {
		$renewal_order = wc_get_order( $renewal_order );

		if ( in_array( $renewal_order->get_payment_method(), self::PAYMENT_METHODS, true ) ) {
			// Remove Reepay order handler from renewal order.
			delete_post_meta( $renewal_order->get_id(), '_reepay_order' );
		}

		return $renewal_order;
	}

	/**
	 * Thank you page
	 *
	 * @param int $order_id order id of current order.
	 *
	 * @return void
	 * @throws Exception If invalid order token.
	 */
	public function thankyou_page( int $order_id ) {
		self::add_subscription_card_id( $order_id );
	}

	/**
	 * Update the card meta for a subscription after using this payment method
	 * to complete a payment to make up for an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Subscription $subscription  The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( WC_Subscription $subscription, WC_Order $renewal_order ) {
		$subscription->update_meta_data( '_reepay_token', $renewal_order->get_meta( '_reepay_token' ) );
		$subscription->update_meta_data( '_reepay_token_id', $renewal_order->get_meta( '_reepay_token_id' ) );
		$subscription->save_meta_data();
	}

	/**
	 * Don't transfer customer meta to resubscribe orders.
	 *
	 * @access public
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription.
	 *
	 * @return void
	 */
	public function delete_resubscribe_meta( WC_Order $resubscribe_order ) {
		if ( in_array( $resubscribe_order->get_payment_method(), self::PAYMENT_METHODS, true ) ) {
			// Delete tokens.
			delete_post_meta( $resubscribe_order->get_id(), '_payment_tokens' );
			delete_post_meta( $resubscribe_order->get_id(), '_reepay_token' );
			delete_post_meta( $resubscribe_order->get_id(), '_reepay_token_id' );
			delete_post_meta( $resubscribe_order->get_id(), '_reepay_order' );
		}
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription An instance of a subscription object.
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( array $payment_meta, WC_Subscription $subscription ): array {
		$token = ReepayTokens::get_payment_token_subscription( $subscription );

		if ( ! empty( $token ) ) {
			$token = $token->get_token();
		}

		$payment_meta[ $subscription->get_payment_method() ] = array(
			'post_meta' => array(
				'_reepay_token' => array(
					'value' => $token,
					'label' => 'Billwerk+ Token',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
	 *
	 * @param string          $payment_method_id The ID of the payment method to validate.
	 * @param array           $payment_meta      associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription      the subscription to which the payment method belongs.
	 *
	 * @throws Exception If validation error.
	 */
	public function validate_subscription_payment_meta( string $payment_method_id, array $payment_meta, WC_Subscription $subscription ) {
		if ( in_array( $payment_method_id, self::PAYMENT_METHODS, true ) ) {
			if ( empty( $payment_meta['post_meta']['_reepay_token']['value'] ) ) {
				throw new Exception( 'A "Billwerk+ Token" value is required.' );
			}

			$tokens = explode( ',', $payment_meta['post_meta']['_reepay_token']['value'] );
			if ( count( $tokens ) > 1 ) {
				throw new Exception( 'Only one "Billwerk+ Token" is allowed.' );
			}

			$token = ReepayTokens::get_payment_token( $tokens[0] );

			if ( empty( $token ) ) {
				$token = ReepayTokens::add_payment_token_to_order( $subscription, $tokens[0] );
			}

			if ( $token->get_user_id() !== $subscription->get_user_id() ) {
				throw new Exception( 'Access denied for this "Billwerk+ Token" value.' );
			}
		}
	}

	/**
	 * Save payment method meta data for the Subscription
	 *
	 * @param WC_Subscription $subscription subscription to save meta.
	 * @param string          $meta_table   meta table name.
	 * @param string          $meta_key     meta key name.
	 * @param string          $meta_value   meta value to save.
	 *
	 * @throws Exception If invalid token or order.
	 */
	public function save_subscription_payment_meta( WC_Subscription $subscription, string $meta_table, string $meta_key, string $meta_value ) {
		if ( in_array( $subscription->get_payment_method(), self::PAYMENT_METHODS, true ) &&
			 'post_meta' === $meta_table && '_reepay_token' === $meta_key ) {
			// Add tokens.
			foreach ( explode( ',', $meta_value ) as $reepay_token ) {
				ReepayTokens::assign_payment_token( $subscription, $reepay_token );
				update_post_meta( $subscription->get_id(), 'reepay_token', $reepay_token );
			}
		}
	}

	/**
	 * When a subscription payment is due.
	 *
	 * @param float    $amount_to_charge amount to charge.
	 * @param WC_Order $renewal_order    order to charge amount.
	 * @param bool     $settle           settle payment or not.
	 *
	 * @throws Exception Never, just for phpcs.
	 */
	public function scheduled_subscription_payment( float $amount_to_charge, WC_Order $renewal_order, $settle = false ) {
		$gateway = rp_get_payment_method( $renewal_order );
		$gateway->log( 'WCS process scheduled payment' );
		// Lookup token.
		try {
			$token = ReepayTokens::get_payment_token_order( $renewal_order );
			// Try to find token in parent orders.
			if ( ! $token ) {
				// Get Subscriptions.
				$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
				foreach ( $subscriptions as $subscription ) {
					$token = ReepayTokens::get_payment_token_subscription( $subscription );
					if ( ! $token ) {
						$token = ReepayTokens::get_payment_token_order( $subscription->get_parent() );
					}
				}
			}

			// Failback: If token doesn't exist, but reepay token is here
			// We need that to provide woocommerce_subscription_payment_meta support
			// See https://github.com/Prospress/woocommerce-subscriptions-importer-exporter#importing-payment-gateway-meta-data.
			if ( ! $token ) {
				$token = $renewal_order->get_meta( '_reepay_token' );

				// Try to find token in parent orders.
				if ( empty( $token ) ) {
					// Get Subscriptions.
					$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );

					foreach ( $subscriptions as $subscription ) {
						$token = $subscription->get_meta( '_reepay_token' );
						if ( empty( $token ) ) {
							$order = $subscription->get_parent();
							if ( $order ) {
								$token = $order->get_meta( '_reepay_token' );
								if ( empty( $token ) ) {
									$invoice_data = reepay()->api( $order )->get_invoice_data( $order );
									if ( ! empty( $invoice_data ) ) {
										if ( ! empty( $invoice_data['recurring_payment_method'] ) ) {
											$token = $invoice_data['recurring_payment_method'];
											break;
										} elseif ( ! empty( $invoice_data['transactions'] ) ) {
											foreach ( $invoice_data['transactions'] as $transaction ) {
												if ( ! empty( $transaction['payment_method'] ) ) {
													$token = $transaction['payment_method'];
													break;
												}
											}
										}
									}
								}
							}
						}
					}
				}

				if ( ! empty( $token ) ) {
					$token = ReepayTokens::add_payment_token_to_order( $renewal_order, $token );
				}
			}

			if ( empty( $token ) ) {
				throw new Exception( 'Payment token does not exist' );
			}

			if ( empty( $token->get_token() ) ) {
				throw new Exception( 'Payment token is empty' );
			}

			$gateway->log( sprintf( 'WCS token for schedule payment: %s', $token->get_token() ) );

			// Fix the reepay order value to prevent "Invoice already settled".
			$currently = get_post_meta( $renewal_order->get_id(), '_reepay_order', true );
			$should_be = 'order-' . $renewal_order->get_id();
			if ( $currently !== $should_be ) {
				$renewal_order->update_meta_data( '_reepay_order', $should_be );
				$renewal_order->save_meta_data();
			}

			// Charge payment.
			$result = reepay()->api( $gateway )->charge(
				$renewal_order,
				$token->get_token(),
				$amount_to_charge,
				null,
				$settle
			);

			$gateway->log(
				array(
					'source' => 'WCS charge payment result',
					'result' => $result,
				)
			);

			if ( is_wp_error( $result ) && ! empty( $result->get_error_message() ) ) {
				throw new Exception( $result->get_error_message(), $result->get_error_code() );
			}

			// Instant settle.
			do_action( 'reepay_instant_settle', $renewal_order );
		} catch ( Exception $e ) {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note(
				sprintf(
					// translators: %1$s amount to charge, %2$s.
					__( 'Error: "%1$s". %2$s.', 'reepay-checkout-gateway' ),
					wc_price( $amount_to_charge ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string          $payment_method_to_display the default payment method text to display.
	 * @param WC_Subscription $subscription              the subscription details.
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( string $payment_method_to_display, WC_Subscription $subscription ): string {
		if ( ! in_array( $subscription->get_payment_method(), self::PAYMENT_METHODS, true ) ||
			 ! $subscription->get_user_id()
		) {
			return $payment_method_to_display;
		}

		$tokens = $subscription->get_payment_tokens();
		foreach ( $tokens as $token_id ) {

			try {

				$token = new TokenReepay( $token_id );

			} catch ( Exception $e ) {
				continue;
			}

			if ( ! in_array( $token->get_gateway_id(), self::PAYMENT_METHODS, true ) ) {
				continue;
			}

			return sprintf(
				// translators: 1: pan 2: month 3: year.
				__( 'Via %1$s card ending in %2$s/%3$s', 'reepay-checkout-gateway' ),
				$token->get_masked_card(),
				$token->get_expiry_month(),
				$token->get_expiry_year()
			);
		}

		return $payment_method_to_display;
	}

	/**
	 * Modify "Save to account" to lock that if needs.
	 *
	 * @param string             $html
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return string
	 */
	// phpcs:disable
	public function save_new_payment_method_option_html( string $html, WC_Payment_Gateway $gateway ): string {
		if ( ! in_array( $gateway->id, self::PAYMENT_METHODS ) ) {
			return $html;
		}

		// Lock "Save to Account" for Recurring Payments / Payment Change
		if ( wcs_cart_have_subscription() || wcs_is_payment_change() ) {
			// Load XML
			libxml_use_internal_errors( true );
			$doc    = new DOMDocument();
			$status = @$doc->loadXML( $html );
			if ( false !== $status ) {
				/**
				 * @var DOMElement $item
				 */
				$item = $doc->getElementsByTagName( 'input' )->item( 0 );
				$item->setAttribute( 'checked', 'checked' );
				$item->setAttribute( 'disabled', 'disabled' );

				$html = $doc->saveHTML( $doc->documentElement );
			}
		}

		return $html;
	}
	// phpcs:enable

	/**
	 * Create new invoice after woocommerce_order_status_changed for woocommerce subscriptions
	 *
	 * @param int      $order_id                    current order id.
	 * @param string   $this_status_transition_from old status.
	 * @param string   $this_status_transition_to   new status.
	 * @param WC_Order $instance                    current order.
	 *
	 * @throws Exception Never, just for phpcs.
	 */
	public function create_sub_invoice( int $order_id, string $this_status_transition_from, string $this_status_transition_to, WC_Order $instance ) {
		$renewal_order = wc_get_order( $order_id );
		$renewal_sub   = get_post_meta( $order_id, '_subscription_renewal', true );
		$gateway       = rp_get_payment_method( $renewal_order );
		if ( ! empty( $renewal_sub ) && ! empty( $gateway ) ) {
			$order_data = reepay()->api( $gateway )->get_invoice_data( $renewal_order );
			if ( is_wp_error( $order_data ) && $renewal_order->get_total() > 0 ) {

				if ( 'pending' === $this_status_transition_from &&
					 ( ( OrderStatuses::$status_sync_enabled && OrderStatuses::$status_authorized === $this_status_transition_to ) ||
					   'on-hold' === $this_status_transition_to ) ) {
					self::scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order );
				}

				if ( 'pending' === $this_status_transition_from &&
					 ( ( OrderStatuses::$status_sync_enabled && OrderStatuses::$status_settled === $this_status_transition_to ) ||
					   'processing' === $this_status_transition_to ) ) {
					self::scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order, true );
				}
			}
		}
	}

	/**
	 * Set 'reepay_token' to '_reepay_token' meta
	 *
	 * @param int    $meta_id meta id.
	 * @param int    $post_id post id.
	 * @param string $meta_key meta key.
	 * @param mixed  $meta_value meta value.
	 */
	public function sync_reepay_token_meta( int $meta_id, int $post_id, string $meta_key, $meta_value ) {
		if ( 'reepay_token' === $meta_key ) {
			update_post_meta( $post_id, '_reepay_token', $meta_value );
		}
	}
}
