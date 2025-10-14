<?php
/**
 * Reepay api class
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout;

use Exception;
use Reepay\Checkout\Gateways\ApplePay;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\Gateways\VippsRecurring;
use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\Actions\ReepayCustomer;
use Reepay\Checkout\Utils\LoggingTrait;
use Reepay\Checkout\Utils\MetaField;
use WC_Order;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use WC_Order_Item;
use WP_Error;

defined( 'ABSPATH' ) || exit();

/**
 * Class Api
 *
 * @package Reepay\Checkout
 */
class Api {
	use LoggingTrait;

	/**
	 * Logging source for woo logs
	 *
	 * @var string
	 */
	private string $logging_source;

	/**
	 * If repeated request after "Request rate limit exceeded"
	 *
	 * @var bool
	 */
	private bool $request_retry = false;

	const ERROR_CODES = array(
		'Ok'                                              => 0,
		'Invalid request'                                 => 1,
		'Internal error'                                  => 2,
		'Invalid user or password'                        => 3,
		'No accounts for user'                            => 4,
		'Unknown account'                                 => 5,
		'Not authenticated'                               => 6,
		'Unauthorized'                                    => 7,
		'Not found'                                       => 8,
		'Customer not found'                              => 9,
		'Subscription plan not found'                     => 10,
		'Duplicate handle'                                => 11,
		'Subscription not found'                          => 12,
		'Subscription expired'                            => 13,
		'Must be in future'                               => 14,
		'Account not found'                               => 15,
		'User not found'                                  => 17,
		'Missing customer'                                => 18,
		'Card not found'                                  => 19,
		'Test data not allowed for live account'          => 20,
		'Live data not allowed for test account'          => 21,
		'Subscription cancelled'                          => 22,
		'From date after to date'                         => 23,
		'Missing amount'                                  => 24,
		'Additional cost not pending'                     => 25,
		'Additional cost not found'                       => 26,
		'Credit not found'                                => 27,
		'Credit not pending'                              => 28,
		'Invoice already cancelled'                       => 29,
		'Invoice has active transactions'                 => 30,
		'Invoice not found'                               => 31,
		'Customer has non expired subscriptions'          => 32,
		'Customer has pending invoices'                   => 33,
		'Invalid card token'                              => 34,
		'Missing card'                                    => 35,
		'Missing card token'                              => 36,
		'Start date cannot be more than one period away in the past' => 37,
		'Card not allowed for signup method'              => 38,
		'Card token not allowed for signup method'        => 39,
		'Payment method not found'                        => 40,
		'Payment method not inactive'                     => 41,
		'Payment method not active'                       => 42,
		'Not implemented'                                 => 43,
		'Dunning plan not found'                          => 44,
		'Organisation not found'                          => 45,
		'Webhook not found'                               => 46,
		'Event not found'                                 => 47,
		'Dunning plan in use'                             => 48,
		'Last dunning plan'                               => 49,
		'Search error'                                    => 50,
		'Private key not found'                           => 51,
		'Public key not found'                            => 52,
		'Mail not found'                                  => 53,
		'No order lines for invoice'                      => 54,
		'Agreement not found'                             => 55,
		'Multiple agreements'                             => 56,
		'Duplicate email'                                 => 57,
		'Invalid group'                                   => 58,
		'User blocked due to failed logins'               => 59,
		'Invalid template'                                => 60,
		'Mail type not found'                             => 61,
		'Card gateway state live/test much match account state live/test' => 62,
		'Subscription has pending or dunning invoices'    => 63,
		'Invoice not settled'                             => 64,
		'Refund amount too high'                          => 65,
		'Refund failed'                                   => 66,
		'The subdomain is reserved'                       => 67,
		'User email already verified'                     => 68,
		'Go live not allowed'                             => 69,
		'Transaction not found'                           => 70,
		'Customer has been deleted'                       => 71,
		'Currency change not allowed'                     => 72,
		'Invalid reminder emails days'                    => 73,
		'Concurrent resource update'                      => 74,
		'Subscription not eligible for invoice'           => 75,
		'Payment method not provided'                     => 76,
		'Transaction declined'                            => 77,
		'Transaction processing error'                    => 78,
		'Invoice already settled'                         => 79,
		'Invoice has processing transaction'              => 80,
		'Online refund not supported, use manual refund'  => 81,
		'Invoice wrong state'                             => 82,
		'Discount not found'                              => 83,
		'Subscription discount not found'                 => 84,
		'Multiple discounts not allowed'                  => 85,
		'Coupon not found or not eligible'                => 86,
		'Coupon already used'                             => 87,
		'Coupon code already exists'                      => 88,
		'Used coupon cannot be deleted'                   => 89,
		'Coupon not active'                               => 90,
		'Coupon cannot be updated'                        => 91,
		'Cannot expire in current period'                 => 93,
		'Cannot uncancel in partial period'               => 94,
		'Subscription on hold'                            => 95,
		'Subscription in trial'                           => 96,
		'Subscription not on hold'                        => 97,
		'Invalid setup token'                             => 98,
		'Customer cannot be changed on invoice'           => 99,
		'Amount change not allowed on invoice'            => 100,
		'Request does not belong to invoice'              => 101,
		'Amount higher than authorized amount'            => 102,
		'Card token already used'                         => 103,
		'Card token expired'                              => 104,
		'Invoice already authorized'                      => 105,
		'Invoice must be authorized'                      => 106,
		'Refund not found'                                => 107,
		'Transaction cancel failed'                       => 108,
		'Transaction wrong state for operation'           => 109,
		'Unknown or missing source'                       => 110,
		'Source not allowed for signup method'            => 111,
		'Invoice wrong type'                              => 112,
		'Add-on not found'                                => 113,
		'Add-on already added to subscription'            => 114,
		'Add-on quantity not allowed for on-off add-on type' => 115,
		'Add-on not eligible for subscription plan'       => 116,
		'Subscription add-on not found'                   => 117,
		'Subscription pending'                            => 118,
		'Subscription must be pending'                    => 119,
		'Credit amount too high'                          => 120,
		'Discount is deleted'                             => 121,
		'Request rate limit exceeded'                     => 122,
		'Concurrent request limit exceeded'               => 123,
		'Payment method in use'                           => 124,
		'Subscription has pending payment method'         => 125,
		'Payment method not pending'                      => 127,
		'Payment method pending'                          => 128,
		'Multiple settles not allowed for payment method' => 129,
		'Partial settle not allowed for payment method'   => 130,
		'Multiple refunds not allowed for payment method' => 131,
		'Partial refund not allowed for payment method'   => 132,
		'Payout processing'                               => 133,
		'Payout already paid'                             => 134,
		'Payment method not allowed for payout'           => 135,
		'Customer cannot be changed on payout'            => 136,
		'Payout not found'                                => 137,
		'No suitable card verification agreement found'   => 138,
		'Currency not supported by payment method'        => 139,
		'Source type must be reusable'                    => 140,
		'Too many settle attempts'                        => 141,
		'Invalid MFA verification code'                   => 142,
		'MFA authentication required'                     => 143,
		'Query took too long, adjust time range'          => 144,
		'Invoice has zero amount'                         => 145,
		'Non positive amount'                             => 146,
		'Payment method failed'                           => 147,
		'Mfa code expired'                                => 148,
		'Cannot activate VTS'                             => 149,
		'Subscription product not found'                  => 150,
	);

	/**
	 * Set logging source.
	 *
	 * @param ReepayGateway|WC_Order|string $source logging source.
	 */
	public function set_logging_source( $source ) {
		if ( is_string( $source ) ) {
			$this->logging_source = $source;
		} elseif ( is_a( $source, ReepayGateway::class ) ) {
				$this->logging_source = $source->id;
		} elseif ( is_a( $source, WC_Order::class ) ) {
			$payment_method = rp_get_payment_method( $source );

			if ( $payment_method ) {
				$this->logging_source = rp_get_payment_method( $source )->id;
			} else {
				$this->logging_source = 'reepay-unknown-payment-method';
			}
		} else {
			$this->logging_source = 'reepay';
		}
	}

	/**
	 * Request
	 *
	 * @param string $method http method.
	 * @param string $url    request url.
	 * @param array  $params request params.
	 * @param bool   $force_live request params.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function request( string $method, string $url, $params = array(), $force_live = false ) {
		$start = microtime( true );
		if ( reepay()->get_setting( 'debug' ) === 'yes' ) {
			$this->log( sprintf( 'Request: %s %s %s', $method, $url, wp_json_encode( $params, JSON_PRETTY_PRINT ) ) );
		}

		if ( reepay()->get_setting( 'test_mode' ) === 'yes' && ! $force_live ) {
			$key = reepay()->get_setting( 'private_key_test' );
		} else {
			$key = reepay()->get_setting( 'private_key' );
		}

		if ( empty( $key ) ) {
			return new WP_Error(
				401,
				sprintf(
					// translators: %s - url to gateway settings.
					__( 'Frisbii Pay: API key not specified. Specify it in <a href="%s" target="_blank">gateway settings</a>', 'reepay-checkout-gateway' ),
					admin_url( 'admin.php?page=wc-settings&tab=checkout&section=reepay_checkout' )
				)
			);
		}

		$args = array(
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $key . ':' ), //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			),
			'method'  => $method,
			'timeout' => 60,
		);
		if ( count( $params ) > 0 ) {
			$args['body']                      = wp_json_encode( $params, JSON_PRETTY_PRINT );
			$args['headers']['Content-Length'] = strlen( wp_json_encode( $params, JSON_PRETTY_PRINT ) );
		}

		$response  = wp_remote_request( $url, $args );
		$body      = wp_remote_retrieve_body( $response );
		$http_code = wp_remote_retrieve_response_code( $response );
		$code      = (int) ( intval( $http_code ) / 100 );

		if ( reepay()->get_setting( 'debug' ) === 'yes' ) {
			$this->log(
				array(
					'source'       => 'Api::request',
					'url'          => $url,
					'method'       => $method,
					'request'      => $params,
					'response'     => $body,
					'time'         => microtime( true ) - $start,
					'http_code'    => $http_code,
					'backtrace(3)' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ), //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
				)
			);
		}

		switch ( $code ) {
			case 0:
				if ( is_wp_error( $response ) ) {
					return $response;
				}

				return new WP_Error( $http_code, __( 'Unknown error.', 'reepay-checkout-gateway' ) );
			case 1:
				// translators: %s http error code.
				return new WP_Error( $http_code, sprintf( __( 'Invalid HTTP Code: %s', 'reepay-checkout-gateway' ), $http_code ) );
			case 2:
			case 3:
				return json_decode( $body, true );
			case 4:
			case 5:
				if ( mb_strpos( $body, 'Request rate limit exceeded', 0, 'UTF-8' ) !== false ) {
					if ( $this->request_retry ) {
						$this->request_retry = false;

						return new WP_Error( 0, __( 'Frisbii Pay: Request rate limit exceeded', 'reepay-checkout-gateway' ) );
					}

					// Wait and try it again.
					sleep( 10 );
					$this->request_retry = true;
					$result              = $this->request( $method, $url, $params );
					$this->request_retry = false;

					return $result;
				}

				$data = json_decode( $body, true );
				if ( JSON_ERROR_NONE === json_last_error() && isset( $data['code'] ) && ! empty( $data['message'] ) ) {
					// translators: %1$s error %1$s message.
					return new WP_Error( $data['code'], sprintf( __( 'API Error: %1$s - %2$s.', 'reepay-checkout-gateway' ), $data['error'], $data['message'] ) );
				}

				if ( ! empty( $data['code'] ) && ! empty( $data['error'] ) ) {
					return new WP_Error(
						$data['code'],
						// translators: %1$s error %1$s code.
						sprintf( __( 'API Error (request): %1$s. Error Code: %2$s', 'reepay-checkout-gateway' ), $data['error'], $data['code'] )
					);
				} else {
					return new WP_Error(
						$http_code,
						// translators: %1$s error %1$s code.
						sprintf( __( 'API Error (request): %1$s. HTTP Code: %2$s', 'reepay-checkout-gateway' ), $body, $http_code )
					);
				}

			default:
				return new WP_Error( $http_code, __( 'Unknown error.', 'reepay-checkout-gateway' ) );
		}
	}

	/**
	 * Get Invoice data by handle.
	 *
	 * @param string $handle invoice handle.
	 *
	 * @return array|WP_Error
	 */
	public function get_invoice_by_handle( string $handle ) {
		return $this->request( 'GET', 'https://api.reepay.com/v1/invoice/' . $handle );
	}

	/**
	 * Get Invoice data of Order.
	 *
	 * @param mixed $order order to get data.
	 *
	 * @return array|WP_Error
	 */
	public function get_invoice_data( $order ) {
		$order = wc_get_order( $order );

		if ( empty( $order ) ) {
			return new WP_Error( 0, 'Wrong order' );
		}

		if ( ! rp_is_order_paid_via_reepay( $order ) ) {
			return new WP_Error( 0, 'Unable to get invoice data.' );
		}

		$handle = rp_get_order_handle( $order );

		if ( empty( $handle ) ) {
			return new WP_Error( 400, 'Empty Frisbii Pay invoice handle', 'empty_handle' );
		}

		$order_data = $this->get_invoice_by_handle( $handle );

		if ( is_wp_error( $order_data ) ) {
			$this->log(
				sprintf(
					'Error (get_invoice_data): %s. Order ID: %s',
					$order_data->get_error_message(),
					$order->get_id()
				)
			);

			return $order_data;
		}

		return array_merge(
			array(
				'authorized_amount' => 0,
				'settled_amount'    => 0,
				'refunded_amount'   => 0,
			),
			$order_data
		);
	}


	/**
	 * Check is Capture possible
	 *
	 * @param WC_Order|int $order order to capture.
	 * @param float|null   $amount amount to capture. Null to capture order total.
	 *
	 * @return bool
	 */
	public function can_capture( $order, $amount = null ): bool {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		$result = $this->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			$this->log(
				sprintf(
					'Payment can\'t be captured. Error: %s. Order ID: %s',
					$result->get_error_message(),
					$order->get_id()
				)
			);

			return false;
		}

		$authorized_amount = $result['authorized_amount'];
		$settled_amount    = $result['settled_amount'];

		return (
			( 'authorized' === $result['state'] ) ||
			( 'settled' === $result['state'] && $authorized_amount >= $settled_amount + $amount )
		);
	}

	/**
	 * Check is Cancel possible
	 *
	 * @param WC_Order|int $order order to cancel.
	 *
	 * @return bool
	 */
	public function can_cancel( $order ): bool {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$result = $this->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			$this->log(
				sprintf(
					'Payment can\'t be cancelled. Error: %s. Order ID: %s',
					$result->get_error_message(),
					$order->get_id()
				)
			);

			return false;
		}

		// can only cancel payments when the state is authorized (partly void is not supported yet).
		return 'authorized' === $result['state'];
	}

	/**
	 * Check if order can be refunded
	 *
	 * @param WC_Order $order order to refund.
	 *
	 * @return bool
	 */
	public function can_refund( WC_Order $order ): bool {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			return false;
		}

		$result = $this->get_invoice_data( $order );

		if ( is_wp_error( $result ) ) {
			$this->log(
				sprintf(
					'Payment can\'t be refunded. Error: %s. Order ID: %s',
					$result->get_error_message(),
					$order->get_id()
				)
			);

			return false;
		}

		return 'settled' === $result['state'];
	}

	/**
	 * Capture order payment
	 *
	 * @param WC_Order|int $order order to capture.
	 * @param float|null   $amount amount to capture. Null to capture order total.
	 *
	 * @return array|WP_Error|Bool
	 */
	public function capture_payment( $order, $amount = null ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		$order_data = $this->get_invoice_data( $order );

		if ( $order->get_total() * 100 === $order_data['settled_amount'] ) {
			return false;
		}

		if ( ! $this->can_capture( $order, $amount ) ) {
			$this->log( sprintf( 'Payment can\'t be captured. Order ID: %s', $order->get_id() ) );

			return new WP_Error( 0, 'Payment can\'t be captured.' );
		}

		$gateway = rp_get_payment_method( $order );

		$order_lines   = $gateway->get_order_items( $order, true );
		$settled_lines = InstantSettle::get_settled_items( $order );

		foreach ( $settled_lines as $settled_line ) {
			foreach ( $order_lines as $order_line_key => $order_line ) {
				if ( $settled_line['ordertext'] === $order_line['ordertext'] ) {
					$amount -= rp_make_initial_amount( $order_line['amount'], $order->get_currency() );

					unset( $order_lines[ $order_line_key ] );
					break;
				}
			}
		}

		return $this->settle( $order, $amount, array_values( $order_lines ) );
	}

	/**
	 * Process recurring payment
	 *
	 * @param string[]     $payment_methods array of payment methods.
	 * @param WC_Order     $order           order to get data from.
	 * @param array        $data            additional data.
	 * @param string|false $token           payment token.
	 * @param string       $payment_text    payment button text.
	 *
	 * @return array|mixed|object|WP_Error
	 * @see ReepayGateway::payment_methods.
	 */
	public function recurring( array $payment_methods, WC_Order $order, array $data, $token = false, string $payment_text = '' ) {
		$params = array(
			'locale'          => $data['language'],
			'create_customer' => array(
				'test'        => 'yes' === $data['test_mode'],
				'handle'      => $data['customer_handle'],
				'email'       => $order->get_billing_email(),
				'address'     => $order->get_billing_address_1(),
				'address2'    => $order->get_billing_address_2(),
				'city'        => $order->get_billing_city(),
				'phone'       => $order->get_billing_phone(),
				'company'     => $order->get_billing_company(),
				'vat'         => '',
				'first_name'  => $order->get_billing_first_name(),
				'last_name'   => $order->get_billing_last_name(),
				'postal_code' => $order->get_billing_postcode(),
			),
			'accept_url'      => $data['return_url'],
			'cancel_url'      => $order->get_cancel_order_url(),
		);

		if ( ! empty( $payment_text ) ) {
			$params['button_text'] = $payment_text;
		}

		if ( ! empty( $token ) ) {
			$params['card_on_file']                  = $token;
			$params['card_on_file_require_cvv']      = false;
			$params['card_on_file_require_exp_date'] = false;
		}

		if ( ! empty( $data['country'] ) ) {
			$params['create_customer']['country'] = $data['country'];
		}

		if ( $payment_methods && count( $payment_methods ) > 0 ) {
			$params['payment_methods'] = $payment_methods;
		}

		if ( ApplePay::ID === $order->get_payment_method() ) {
			$params['session_data']['applepay_recurring_amount'] = rp_prepare_amount( $order->get_total(), $order->get_currency() );
		}

		if ( VippsRecurring::ID === $order->get_payment_method() ) {
			$params['currency']                               = $order->get_currency();
			$params['session_data']['vipps_recurring_amount'] = rp_prepare_amount( $order->get_total(), $order->get_currency() );
		}

		// Add age verification data if needed.
		$this->add_age_verification_to_session_data( $params, $order );

		return $this->request(
			'POST',
			'https://checkout-api.reepay.com/v1/session/recurring',
			$params
		);
	}

	/**
	 * Charge payment.
	 *
	 * @param WC_Order   $order       order to charge.
	 * @param string     $token       payment token.
	 * @param float|null $amount      amount to charge.
	 * @param array|null $order_items order items data. @see \Reepay\Checkout\Gateways::get_order_items.
	 * @param bool       $settle      settle payment or not.
	 *
	 * @return array|WP_Error
	 * @throws Exception If charge error.
	 */
	public function charge( WC_Order $order, string $token, ?float $amount = null, $order_items = null, $settle = false ) {
		$currency = $order->get_currency();

		$params = array(
			'handle'      => rp_get_order_handle( $order ),
			'amount'      => ! is_null( $amount ) ? rp_prepare_amount( $amount, $currency ) : null,
			'currency'    => $currency,
			'source'      => $token,
			'recurring'   => order_contains_subscription( $order ),
			'order_lines' => $order_items,
			'settle'      => $settle,
		);

		if ( $order->get_payment_method() === 'reepay_mobilepay_subscriptions' ) {
			$params['parameters']['mps_ttl'] = 'PT24H';
		}

		try {
			$result = $this->request( 'POST', 'https://api.reepay.com/v1/charge', $params );

			if ( is_wp_error( $result ) ) {

				if ( 'yes' === reepay()->get_setting( 'handle_failover' ) &&
					( in_array( $result->get_error_code(), array( 105, 79, 29, 99, 72 ), true ) )
				) {
					// Workaround: handle already exists lets create another with unique handle.
					$params['handle'] = rp_get_order_handle( $order, true );
					$result           = $this->request( 'POST', 'https://api.reepay.com/v1/charge', $params );
					if ( is_wp_error( $result ) ) {
						throw new Exception( $result->get_error_message(), $result->get_error_code() );
					}

					$this->process_charge_result( $order, $result );

					return $result;
				}

				throw new Exception( $result->get_error_message(), $result->get_error_code() );
			}

			$this->process_charge_result( $order, $result );

			return $result;
		} catch ( Exception $e ) {
			$this->log( $e->getMessage() );
			$order->update_status( 'failed' );
			$order->add_order_note(
				sprintf(
					// translators: %1$s order amount, %2$s error message, %3$s token id.
					__( 'Failed to charge "%1$s". Error: %2$s. Token ID: %3$s', 'reepay-checkout-gateway' ),
					wc_price( $amount, array( 'currency' => $currency ) ),
					$e->getMessage(),
					$token
				)
			);

			return new WP_Error( $e->getCode(), $e->getMessage() );
		}
	}

	/**
	 * Settle the payment online.
	 *
	 * @param WC_Order       $order        order to settle.
	 * @param float|int|null $amount       amount to settle.
	 * @param false|array    $items_data   order items info. @see OrderCapture::get_item_data.
	 * @param WC_Order_Item  $line_item    order line item.
	 * @param bool           $instant_note add order note instantly.
	 *
	 * @return array|WP_Error
	 *
	 * @ToDO refactor function. $amount is useless.
	 */
	public function settle( WC_Order $order, $amount = null, $items_data = false, $line_item = false, bool $instant_note = true ) {
		$this->log( sprintf( 'Settle: %s, Amount: %s', $order->get_id(), $amount ) );

		$handle = rp_get_order_handle( $order );
		if ( empty( $handle ) ) {
			return new WP_Error( 0, 'Unable to get order handle' );
		}

		if ( ! $amount || ! $items_data ) {
			$settle_data = InstantSettle::calculate_instant_settle( $order );

			if ( ! $amount ) {
				$amount = $settle_data['settle_amount'];
			}

			if ( ! $items_data ) {
				$items_data = $settle_data['items'];
			}
		}

		if ( ! empty( $amount ) && reepay()->get_setting( 'skip_order_lines' ) === 'yes' ) {
			$request_data['amount'] = $this->calculate_amount_with_vat( $items_data, $amount );
		} elseif ( ! empty( $amount ) && false === $line_item ) {
			$request_data['amount'] = $this->calculate_amount_with_vat( $items_data, $amount );
		} else {
			$request_data['order_lines'] = $items_data;
		}

		if ( ! empty( $items_data ) && floatval( current( $items_data )['amount'] ) <= 0 ) {
			return new WP_Error( 100, 'Amount must be larger than zero' );
		}

		$result = $this->request(
			'POST',
			'https://api.reepay.com/v1/charge/' . $handle . '/settle',
			$request_data
		);

		if ( is_wp_error( $result ) ) {
			// Workaround: Check if the invoice has been settled before to prevent "Invoice already settled".
			if ( mb_strpos( $result->get_error_message(), 'Invoice already settled', 0, 'UTF-8' ) !== false ) {
				return array();
			}

			if ( mb_strpos( $result->get_error_message(), 'Amount higher than authorized amount', 0, 'UTF-8' ) !== false && ! empty( $line_item ) ) {
				$order_data = $this->get_invoice_data( $order );
				$remaining  = $order_data['authorized_amount'] - $order_data['settled_amount'];

				if ( ! empty( $request_data['order_lines'] ) && count( $request_data['order_lines'] ) > 1 && is_array( $line_item ) ) {
					$request_data['amount'] = $remaining;
					unset( $request_data['order_lines'] );

					$retry_result = $this->request(
						'POST',
						'https://api.reepay.com/v1/charge/' . $handle . '/settle',
						$request_data
					);

					// Add success order note if the retry was successful.
					if ( ! is_wp_error( $retry_result ) && ! empty( $retry_result['transaction'] ) ) {
						$message = sprintf(
							// translators: %1$s amount to settle, %2$s transaction number.
							__( 'Payment has been settled. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
							rp_make_initial_amount( $remaining, $order->get_currency() ) . ' ' . $order->get_currency(),
							$retry_result['transaction']
						);

						if ( $instant_note ) {
							$order->add_order_note( $message );
						} else {
							$order->add_order_note( $message, true, true );
						}
					}

					return $retry_result;
				} else {
					$price = OrderCapture::get_item_price( $line_item, $order );
					if ( $remaining > 0 &&
						round( $remaining / 100 ) === $price['with_tax'] &&
						! empty( $request_data['order_lines'][0] ) ) {
						$full = $remaining / ( $request_data['order_lines'][0]['vat'] + 1 );
						if ( $full > 0 ) {
							$request_data['order_lines'][0]['amount'] = $full;

							$retry_result = $this->request(
								'POST',
								'https://api.reepay.com/v1/charge/' . $handle . '/settle',
								$request_data
							);

							// Add success order note if the retry was successful.
							if ( ! is_wp_error( $retry_result ) && ! empty( $retry_result['transaction'] ) ) {
								$message = sprintf(
									// translators: %1$s amount to settle, %2$s transaction number.
									__( 'Payment has been settled. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
									rp_make_initial_amount( $full, $order->get_currency() ) . ' ' . $order->get_currency(),
									$retry_result['transaction']
								);

								if ( $instant_note ) {
									$order->add_order_note( $message );
								} else {
									$order->add_order_note( $message, true, true );
								}
							}

							return $retry_result;
						}
					}
				}
			}

			// Skip add order note process if error code is 31 (Invoice not found).
			if ( $result->get_error_code() === 31 ) {
				return $result;
			}

			$error = sprintf(
				// translators: %1$s amount, %2$s error message.
				__( 'Failed to settle %1$s. Error: %2$s.', 'reepay-checkout-gateway' ),
				$items_data ? floatval( $items_data[0]['amount'] ) / 100 : floatval( $amount ) / 100,
				$result->get_error_message()
			);

			if ( $instant_note ) {
				$order->add_order_note( $error );
			} else {
				$order->add_order_note( $error, true, true );
			}

			return $result;
		}

		if ( 'failed' === $result['state'] ) {
			return new WP_Error( 0, 'Settle has been failed.' );
		}

		if ( array_key_exists( 'error', $result ) && ! empty( $result['error'] ) ) {
			return new WP_Error( 0, 'Settle has been failed.' );
		}

		if ( array_key_exists( 'error_state', $result ) && ! empty( $result['error_state'] ) ) {
			return new WP_Error( 0, 'Settle has been failed.' );
		}

		$order->update_meta_data( '_reepay_capture_transaction', $result['transaction'] );
		$order->save_meta_data();

		try {
			$order->set_transaction_id( $result['transaction'] );
		} catch ( Exception $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing if error.
		}

		$order->save();

		// Check the amount and change the order status to settled if needs.
		$order_data = $this->get_invoice_data( $order );
		if ( is_wp_error( $order_data ) ) {
			return new WP_Error(
				0,
				'Settled, but unable to verify the transaction. Error: ' . $order_data->get_error_message()
			);
		}

		// Use the actual amount that was sent to the API for the settlement message.
		$settled_amount = $this->calculate_amount_with_vat( $items_data, $amount );

		$message = sprintf(
			// translators: %1$s amount to settle, %2$s transaction number.
			__( 'Payment has been settled. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
			rp_make_initial_amount( $settled_amount, $order->get_currency() ) . ' ' . $order->get_currency(),
			$result['transaction']
		);

		if ( $instant_note ) {
			$order->add_order_note( $message );
		} else {
			$order->add_order_note( $message, true, true );
		}

		return $result;
	}

	/**
	 * Cancel the payment online.
	 *
	 * @param WC_Order $order order to cancel payment.
	 *
	 * @return array|WP_Error
	 */
	public function cancel_payment( WC_Order $order ) {
		$handle = rp_get_order_handle( $order );
		if ( empty( $handle ) ) {
			return new WP_Error( 0, 'Unable to get order handle' );
		}

		$result = $this->request( 'POST', 'https://api.reepay.com/v1/charge/' . $handle . '/cancel' );
		if ( is_wp_error( $result ) ) {
			$error = sprintf(
				// translators: %s error message.
				__( 'Failed to cancel the payment. Error: %s.', 'reepay-checkout-gateway' ),
				$result->get_error_message()
			);

			$order->add_order_note( $error );

			set_transient( 'reepay_api_action_error', $error, MINUTE_IN_SECONDS / 2 );

			return $result;
		}

		$order->update_meta_data( '_reepay_cancel_transaction', $result['transaction'] );
		$order->update_meta_data( '_transaction_id', $result['transaction'] );
		$order->save_meta_data();

		if ( ! $order->has_status( 'cancelled' ) ) {
			$message = __( 'Payment has been cancelled.', 'reepay-checkout-gateway' );
			$order->update_status( 'cancelled', $message );

			set_transient( 'reepay_api_action_success', $message, MINUTE_IN_SECONDS / 2 );
		}

		return $result;
	}

	/**
	 * Refund the payment online.
	 *
	 * @param WC_Order       $order  order to refund.
	 * @param float|int|null $amount amount ot refund.
	 * @param string         $reason refund reason.
	 *
	 * @return array|WP_Error
	 */
	public function refund( WC_Order $order, $amount = null, $reason = '' ) {
		$handle = rp_get_order_handle( $order );
		if ( empty( $handle ) ) {
			return new WP_Error( 0, 'Unable to get order handle' );
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		$params = array(
			'invoice' => $handle,
			'amount'  => rp_prepare_amount( $amount, $order->get_currency() ),
		);
		$result = $this->request( 'POST', 'https://api.reepay.com/v1/refund', $params );
		if ( is_wp_error( $result ) ) {
			$error = sprintf(
				// translators: %1$s refund amount, %2$s error message.
				__( 'Failed to refund "%1$s". Error: %2$s.', 'reepay-checkout-gateway' ),
				wc_price( $amount ),
				$result->get_error_message()
			);

			set_transient( 'reepay_api_action_error', $error, MINUTE_IN_SECONDS / 2 );

			$order->add_order_note( $error );

			return $result;
		}

		// Save Credit Note ID.
		$credit_note_ids = $order->get_meta( '_reepay_credit_note_ids' );
		if ( ! is_array( $credit_note_ids ) ) {
			$credit_note_ids = array();
		}

		array_push( $credit_note_ids, $result['credit_note_id'] );
		$order->update_meta_data( '_reepay_credit_note_ids', $credit_note_ids );
		$order->save_meta_data();

		$message = sprintf(
			// translators: refunded amount, %2$s credit note id, %3$s refund reason.
			__( 'Refunded: %1$s. Credit Note Id #%2$s. Reason: %3$s', 'reepay-checkout-gateway' ),
			$amount,
			$result['credit_note_id'],
			$reason
		);

		$order->add_order_note( $message );

		set_transient( 'reepay_api_action_success', $message, MINUTE_IN_SECONDS / 2 );

		return $result;
	}

	/**
	 * Process the result of Charge request.
	 *
	 * @param WC_Order $order  order to check state.
	 * @param array    $result data from API @see https://reference.reepay.com/api/#create-charge.
	 *
	 * @throws Exception If cannot change order status.
	 */
	private function process_charge_result( WC_Order $order, array $result ) {
		// For asynchronous payment methods this flag indicates that the charge is awaiting result.
		// The charge/invoice state will be pending.

		switch ( $result['state'] ) {
			case 'pending':
				OrderStatuses::update_order_status(
					$order,
					'pending',
					sprintf(
						// translators: %1$s pending amount, transaction id.
						__( 'Transaction is pending. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $order->get_currency() ) ),
						$result['transaction']
					),
					$result['transaction']
				);

				break;
			case 'authorized':
				OrderStatuses::set_authorized_status(
					$order,
					sprintf(
						// translators: %1$s authorized amount, %2$s transaction id.
						__( 'Payment has been authorized. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $order->get_currency() ) ),
						$result['transaction']
					),
					$result['transaction']
				);

				// Settle an authorized payment instantly if possible.
				do_action( 'reepay_instant_settle', $order );

				break;
			case 'settled':
				$order->update_meta_data( '_reepay_capture_transaction', $result['transaction'] );
				$order->save_meta_data();
				OrderStatuses::set_settled_status(
					$order,
					sprintf(
						// translators: %1$s settled amount, transaction id.
						__( 'Payment has been settled. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
						wc_price( $result['amount'] ),
						$result['transaction']
					),
					$result['transaction']
				);

				break;
			case 'cancelled':
				$order->update_meta_data( '_reepay_cancel_transaction', $result['transaction'] );
				$order->save_meta_data();
				if ( ! $order->has_status( 'cancelled' ) ) {
					$order->update_status(
						'cancelled',
						__( 'Payment has been cancelled.', 'reepay-checkout-gateway' )
					);
				} else {
					$order->add_order_note(
						__(
							'Payment has been cancelled.',
							'reepay-checkout-gateway'
						)
					);
				}

				break;
			case 'failed':
				throw new Exception( 'Cancelled' );
			default:
				throw new Exception( 'Generic error' );
		}
	}

	/**
	 * Delete payment method in Reepay
	 *
	 * @param string $id payment method id.
	 */
	public function delete_payment_method( string $id ) {
		return $this->request(
			'DELETE',
			"https://api.reepay.com/v1/payment_method/$id"
		);
	}

	/**
	 * Get Customer Cards from Reepay
	 *
	 * @param string      $customer_handle reepay customer handle.
	 * @param string|null $reepay_token    when specified, a card with such a token will be returned.
	 *
	 * @return array|WP_Error
	 */
	public function get_reepay_cards( string $customer_handle, $reepay_token = null ) {
		$params = array(
			'size'     => '100',
			'from'     => '1970-01-01',
			'state'    => 'active',
			'customer' => $customer_handle,
		);
		$result = $this->request(
			'GET',
			'https://api.reepay.com/v1/list/payment_method?' . http_build_query( $params ),
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! isset( $result['count'] ) ) {
			return new WP_Error( 0, 'Unable to retrieve customer payment methods' );
		}

		$tmp = array(
			'cards'             => array(),
			'mps_subscriptions' => array(),
			'vipps_recurring'   => array(),
		);
		if ( $result['count'] > 0 ) {
			foreach ( $result['content'] as $payment_method ) {
				if ( in_array( $payment_method['payment_type'], array( 'card', 'emv_token' ), true ) ) {
					$card = $payment_method;
					$card = array_merge( $card, $payment_method['card'] );
					unset( $card['card'], $card['gateway'], $card['card_agreement'], $card['payment_type'] );
					$tmp['cards'][] = $card;
				} elseif ( 'mobilepay_subscriptions' === $payment_method['payment_type'] ) {
					$mps_subscription = $payment_method;
					$mps_subscription = array_merge( $mps_subscription, $payment_method['mps_subscription'] );
					unset( $mps_subscription['mps_subscription'], $mps_subscription['payment_type'] );
					$tmp['mps_subscriptions'][] = $mps_subscription;
				} elseif ( 'vipps_recurring' === $payment_method['payment_type'] ) {
					$vipps_recurring = $payment_method;
					$vipps_recurring = array_merge( $vipps_recurring, $payment_method['vipps_recurring_mandate'] );
					unset( $vipps_recurring['vipps_recurring_mandate'], $vipps_recurring['payment_type'] );
					$tmp['vipps_recurring_mandate'][] = $vipps_recurring;
				}
			}
		}

		$result = $tmp;

		if ( ! $reepay_token ) {
			return $result['cards'];
		}

		if ( ! empty( $result['cards'] ) ) {
			$cards = $result['cards'];
			foreach ( $cards as $card ) {
				if ( $card['id'] === $reepay_token && 'active' === $card['state'] ) {
					return $card;
				}
			}
		}

		if ( ! empty( $result['mps_subscriptions'] ) ) {
			$mps_subscriptions = $result['mps_subscriptions'];
			foreach ( $mps_subscriptions as $subscription ) {
				if ( ( $subscription['id'] === $reepay_token || $subscription['reference'] === $reepay_token ) && 'active' === $subscription['state'] ) {
					return $subscription;
				}
			}
		}

		if ( ! empty( $result['vipps_recurring_mandate'] ) ) {
			$mps_subscriptions = $result['vipps_recurring_mandate'];
			foreach ( $mps_subscriptions as $subscription ) {
				if ( ( $subscription['id'] === $reepay_token || $subscription['reference'] === $reepay_token ) && 'active' === $subscription['state'] ) {
					return $subscription;
				}
			}
		}

		return array();
	}

	/**
	 * Get Customer handle by Order ID.
	 *
	 * @param mixed $order order id to get id.
	 *
	 * @return string
	 */
	public function get_customer_handle_by_order( $order ) {
		$order = wc_get_order( $order );

		$handle = $this->get_customer_handle( $order );

		if ( $order->get_customer_id() === 0 && empty( $handle ) ) {
			$handle = $order->get_meta( '_reepay_customer' );
		}

		if ( empty( $handle ) ) {
			$handle = get_user_meta( $order->get_customer_id(), 'reepay_customer_id', true );
			if ( ! empty( $handle ) ) {
				return $handle;
			}
		}

		if ( empty( $handle ) ) {
			$handle = rp_get_customer_handle( $order->get_customer_id() );
		}

		if ( empty( $handle ) ) {
			if ( $order->get_customer_id() > 0 ) {
				$handle = 'customer-' . $order->get_customer_id();
			} else {
				$handle = 'cust-' . time();
			}
		}

		if ( ReepayCustomer::have_same_handle( $order->get_customer_id(), $handle ) ) {
			$handle = 'cust-' . time();
			update_user_meta( $order->get_customer_id(), 'reepay_customer_id', $handle );
		}

		$order->add_meta_data( '_reepay_customer', $handle );
		$order->save_meta_data();

		return $handle;
	}

	/**
	 * Get Customer handle by order online.
	 *
	 * @param WC_Order $order order to get handle.
	 *
	 * @return false|string
	 */
	public function get_customer_handle( WC_Order $order ) {
		$handle = rp_get_order_handle( $order );

		$result = get_transient( 'reepay_invoice_' . $handle );

		if ( ! $result ) {
			$invoice_data = $this->get_invoice_by_handle( wc_clean( $handle ) );
			if ( is_wp_error( $invoice_data ) ) {
				return null;
			}
		}

		if ( is_array( $result ) && isset( $result['customer'] ) ) {
			return $result['customer'];
		}

		return null;
	}

	/**
	 * Calculate total amount including VAT from items data
	 *
	 * @param array     $items_data Array of item data with VAT information.
	 * @param float|int $fallback_amount Fallback amount if calculation fails.
	 *
	 * @return float|int Calculated amount with VAT or fallback amount.
	 */
	private function calculate_amount_with_vat( $items_data, $fallback_amount ) {
		$total_amount_with_vat = 0;

		if ( ! empty( $items_data ) ) {
			foreach ( $items_data as $item_data ) {
				$item_amount = $item_data['amount'] * $item_data['quantity'];
				if ( ! empty( $item_data['vat'] ) && $item_data['vat'] > 0 ) {
					// If amount_incl_vat is false, add VAT to the amount.
					if ( empty( $item_data['amount_incl_vat'] ) ) {
						$item_amount = $item_amount * ( 1 + $item_data['vat'] );
					}
					// If amount_incl_vat is true, amount already includes VAT.
				}
				$total_amount_with_vat += $item_amount;
			}
		}

		// Use calculated amount with VAT if available, otherwise use fallback amount.
		return $total_amount_with_vat > 0 ? $total_amount_with_vat : $fallback_amount;
	}

	/**
	 * Add age verification data to session parameters based on payment method
	 *
	 * @param array    $params Session parameters (passed by reference).
	 * @param WC_Order $order  Current order.
	 * @return void
	 */
	private function add_age_verification_to_session_data( array &$params, WC_Order $order ): void {
		// Check if age verification should be included.
		$should_include = MetaField::should_include_age_verification( $order->get_id() );
		$max_age        = MetaField::get_cart_maximum_age( $order->get_id() );
		$payment_method = $order->get_payment_method();

		$this->log(
			array(
				'source'                                 => 'add_age_verification_start',
				'order_id'                               => $order->get_id(),
				'payment_method'                         => $payment_method,
				'has_existing_session_data'              => isset( $params['session_data'] ),
				'age_verification_global_setting_enable' => $should_include,
				'max_age'                                => $max_age,
				'max_age_is_null'                        => null === $max_age,

			)
		);

		if ( ! $should_include ) {
			return;
		}

		if ( null === $max_age ) {
			return;
		}

		// Add root-level minimum_user_age parameter.
		$params['minimum_user_age'] = $max_age;

		// Initialize session_data if not exists.
		$session_data_existed = isset( $params['session_data'] );
		if ( ! $session_data_existed ) {
			$params['session_data'] = array();
		}

		// Add both age verification keys to session_data (always send both regardless of payment method).
		$params['session_data']['mpo_minimum_user_age']            = $max_age;
		$params['session_data']['vipps_epayment_minimum_user_age'] = $max_age;

		$fields_added = array( 'minimum_user_age', 'mpo_minimum_user_age', 'vipps_epayment_minimum_user_age' );
		$log_source   = 'add_age_verification_all_fields_configured';

		// Single log entry for all payment methods.
		$this->log(
			array(
				'source'         => $log_source,
				'order_id'       => $order->get_id(),
				'payment_method' => $payment_method,
				'max_age'        => $max_age,
				'fields_added'   => $fields_added,
			)
		);

		// Log age verification data for debugging.
		$this->log(
			array(
				'source'                  => 'add_age_verification_completed',
				'order_id'                => $order->get_id(),
				'final_session_data_keys' => array_keys( $params['session_data'] ),
				'age_verification_fields' => array_filter(
					$params['session_data'],
					function ( $key ) {
						return in_array( $key, array( 'mpo_minimum_user_age', 'vipps_epayment_minimum_user_age' ), true );
					},
					ARRAY_FILTER_USE_KEY
				),
			)
		);
	}
}
