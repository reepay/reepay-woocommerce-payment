<?php
/**
 * Reepay api class
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout;

use Exception;
use Reepay\Checkout\Gateways\ReepayGateway;
use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\OrderCapture;
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
		} else {
			if ( is_a( $source, ReepayGateway::class ) ) {
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
					'source'    => 'Api::request',
					'url'       => $url,
					'method'    => $method,
					'request'   => $params,
					'response'  => $body,
					'time'      => microtime( true ) - $start,
					'http_code' => $http_code,
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

						return new WP_Error( 0, __( 'Billwerk+: Request rate limit exceeded', 'reepay-checkout-gateway' ) );
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
			return new WP_Error( 400, 'Empty Billwerk+ invoice handle', 'empty_handle' );
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
	public function recurring( array $payment_methods, WC_Order $order, array $data, $token = false, $payment_text = '' ) {
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
	 * @param WC_Order       $order      order to settle.
	 * @param float|int|null $amount     amount to settle.
	 * @param false|array    $items_data order items info. @see OrderCapture::get_item_data.
	 * @param WC_Order_Item  $line_item  order line item.
	 *
	 * @return array|WP_Error
	 *
	 * @ToDO refactor function. $amount is useless.
	 */
	public function settle( WC_Order $order, $amount = null, $items_data = false, $line_item = false ) {
		$this->log( sprintf( 'Settle: %s, %s', $order->get_id(), $amount ) );

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
			$request_data['amount'] = $amount;
		} else {
			$request_data['order_lines'] = $items_data;
		}

		if ( ! empty( $items_data ) && floatval( current( $items_data )['amount'] ) <= 0 ) {
			return new WP_Error( 100, 'Amount must be lager than zero' );
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

				if ( count( $request_data['order_lines'] ) > 1 && is_array( $line_item ) ) {
					$request_data['amount'] = $remaining;
					unset( $request_data['order_lines'] );

					return $this->request(
						'POST',
						'https://api.reepay.com/v1/charge/' . $handle . '/settle',
						$request_data
					);
				} else {
					$price = OrderCapture::get_item_price( $line_item, $order );
					if ( $remaining > 0 &&
						 round( $remaining / 100 ) === $price['with_tax'] &&
						 ! empty( $request_data['order_lines'][0] ) ) {
						$full = $remaining / ( $request_data['order_lines'][0]['vat'] + 1 );
						if ( $full > 0 ) {
							$request_data['order_lines'][0]['amount'] = $full;

							return $this->request(
								'POST',
								'https://api.reepay.com/v1/charge/' . $handle . '/settle',
								$request_data
							);
						}
					}
				}
			}

			$error = sprintf(
				// translators: %1$s amount, %2$s error message.
				__( 'Failed to settle %1$s. Error: %2$s.', 'reepay-checkout-gateway' ),
				$items_data ? floatval( $items_data[0]['amount'] ) / 100 : $amount,
				$result->get_error_message()
			);

			set_transient( 'reepay_api_action_error', $error, MINUTE_IN_SECONDS / 2 );

			return $result;
		}

		if ( 'failed' === $result['state'] ) {
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

		$message = sprintf(
			// translators: %1$s amount to settle, %2$s transaction number.
			__( 'Payment has been settled. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
			rp_make_initial_amount( $amount, $order->get_currency() ) . ' ' . $order->get_currency(),
			$result['transaction']
		);

		$order->add_order_note( $message );

		set_transient( 'reepay_api_action_success', $message, MINUTE_IN_SECONDS / 2 );

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
	 * @param string|null    $reason refund reason.
	 *
	 * @return array|WP_Error
	 */
	public function refund( WC_Order $order, $amount = null, $reason = null ) {
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
				update_post_meta( $order->get_id(), '_reepay_capture_transaction', $result['transaction'] );

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
				update_post_meta( $order->get_id(), '_reepay_cancel_transaction', $result['transaction'] );

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
		$result = $this->request(
			'GET',
			'https://api.reepay.com/v1/customer/' . $customer_handle . '/payment_method'
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! isset( $result['cards'] ) ) {
			return new WP_Error( 0, 'Unable to retrieve customer payment methods' );
		}

		if ( ! $reepay_token ) {
			return $result['cards'];
		}

		$cards = $result['cards'];
		foreach ( $cards as $card ) {
			if ( $card['id'] === $reepay_token && 'active' === $card['state'] ) {
				return $card;
			}
		}

		$mps_subscriptions = $result['mps_subscriptions'];
		foreach ( $mps_subscriptions as $subscription ) {
			if ( $subscription['id'] === $reepay_token && 'active' === $subscription['state'] ) {
				return $subscription;
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
		if ( ! empty( $handle ) ) {
			return $handle;
		}

		if ( $order->get_customer_id() === 0 ) {
			$handle = $order->get_meta( '_reepay_customer' );
			if ( ! empty( $handle ) ) {
				return $handle;
			}
		}

		$handle = rp_get_customer_handle( $order->get_customer_id() );
		if ( ! empty( $handle ) ) {
			$order->add_meta_data( '_reepay_customer', $handle );
			$order->save_meta_data();
			return $handle;
		}

		if ( empty( $handle ) ) {
			if ( $order->get_customer_id() > 0 ) {
				$handle = 'customer-' . $order->get_customer_id();
			} else {
				$handle = 'cust-' . time();
			}
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
}
