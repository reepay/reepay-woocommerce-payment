<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Reepay_Api {
	use WC_Reepay_Log;

	/**
	 * @var string
	 */
	private $logging_source;

	/**
	 * @var WC_Gateway_Reepay
	 */
	private $gateway;

	/**
	 * @var bool
	 */
	private $request_retry = false;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_Reepay_Interface $gateway
	 */
	public function __construct( WC_Payment_Gateway_Reepay_Interface $gateway ) {
		$this->gateway        = $gateway;
		$this->logging_source = $gateway->id;
	}

	/**
	 * Request
	 *
	 * @param $method
	 * @param $url
	 * @param array $params
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function request( $method, $url, $params = array() ) {
		$start = microtime( true );
		if ( $this->gateway->debug === 'yes' ) {
			$this->log( sprintf( 'Request: %s %s %s', $method, $url, json_encode( $params, JSON_PRETTY_PRINT ) ) );
		}

		$key = $this->gateway->test_mode === 'yes' ? $this->gateway->private_key_test : $this->gateway->private_key;

		$args = [
			'headers' => [
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $key . ':' )
			],
			'method'  => $method,
			'timeout' => 60,
		];
		if ( count( $params ) > 0 ) {
			$args['body']                      = json_encode( $params, JSON_PRETTY_PRINT );
			$args['headers']['Content-Length'] = strlen( json_encode( $params, JSON_PRETTY_PRINT ) );
		}

		$response  = wp_remote_request( $url, $args );
		$body      = wp_remote_retrieve_body( $response );
		$http_code = wp_remote_retrieve_response_code( $response );
		$code      = (int) ( intval( $http_code ) / 100 );

		if ( $this->gateway->debug === 'yes' ) {
			$this->log( print_r( [
				'source'    => 'WC_Reepay_Api::request',
				'url'       => $url,
				'method'    => $method,
				'request'   => $params,
				'response'  => $body,
				'time'      => microtime( true ) - $start,
				'http_code' => $http_code,
			], true ) );
		}

		switch ( $code ) {
			case 0:
				if ( is_wp_error( $response ) ) {
					return $response;
				}

				return new WP_Error( $http_code, __( 'Unknown error.', 'reepay-checkout-gateway' ) );
			case 1:
				return new WP_Error( $http_code, sprintf( __( 'Invalid HTTP Code: %s', 'reepay-checkout-gateway' ), $http_code ) );
			case 2:
			case 3:
				return json_decode( $body, true );
			case 4:
			case 5:
				if ( mb_strpos( $body, 'Request rate limit exceeded', 0, 'UTF-8' ) !== false ) {
					if ( $this->request_retry ) {
						$this->request_retry = false;

						return new WP_Error( 0, __( 'Reepay: Request rate limit exceeded', 'reepay-checkout-gateway' ) );
					}

					// Wait and try it again
					sleep( 10 );
					$this->request_retry = true;
					$result              = $this->request( $method, $url, $params );
					$this->request_retry = false;

					return $result;
				}

				$data = json_decode( $body, true );
				if ( JSON_ERROR_NONE === json_last_error() && isset( $data['code'] ) && ! empty( $data['message'] ) ) {
					return new WP_Error( $data['code'], sprintf( __( 'API Error: %s - %s.', 'reepay-checkout-gateway' ), $data['error'], $data['message'] ) );
				}

				if ( ! empty( $data['code'] ) && ! empty( $data['error'] ) ) {
					return new WP_Error( $data['code'],
						sprintf( __( 'API Error (request): %s. Error Code: %s', 'reepay-checkout-gateway' ), $data['error'], $data['code'] ) );
				} else {
					return new WP_Error( $http_code,
						sprintf( __( 'API Error (request): %s. HTTP Code: %s', 'reepay-checkout-gateway' ), $body, $http_code ) );
				}

			default:
				return new WP_Error( $http_code, __( 'Unknown error.', 'reepay-checkout-gateway' ) );
		}
	}

	/**
	 * Get Invoice data by handle.
	 *
	 * @param string $handle
	 *
	 * @return array|WP_Error
	 */
	public function get_invoice_by_handle( $handle ) {
		return $this->request( 'GET', 'https://api.reepay.com/v1/invoice/' . $handle );
	}

	/**
	 * Get Invoice data of Order.
	 *
	 * @param WC_Order $order
	 *
	 * @return array|WP_Error
	 */
	public function get_invoice_data( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! in_array( $order->get_payment_method(), WC_ReepayCheckout::PAYMENT_METHODS, true ) ) {
			return new WP_Error( 0, 'Unable to get invoice data.' );
		}

		$handle = rp_get_order_handle( $order );

		if ( empty( $handle ) ) {
			return new WP_Error( 400, 'Empty reepay invoice handle', 'empty_handle' );
		}

		$order_data = $this->get_invoice_by_handle( $handle );

		if ( is_wp_error( $order_data ) ) {
			/** @var WP_Error $order_data */
			$this->log(
				sprintf(
					'Error (get_invoice_data): %s. Order ID: %s',
					$order_data->get_error_message(),
					$order->get_id()
				)
			);

			return $order_data;
		}

		return array_merge( array(
			'authorized_amount' => 0,
			'settled_amount'    => 0,
			'refunded_amount'   => 0
		), $order_data );
	}


	/**
	 * Check is Capture possible
	 *
	 * @param WC_Order|int $order
	 * @param bool $amount
	 *
	 * @return bool
	 */
	public function can_capture( $order, $amount = false ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		$result = $this->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			/** @var WP_Error $result */

			$this->log(
				sprintf(
					'Payment can\'t be captured. Error: %s. Order ID: %s',
					$result->get_error_message(),
					$order->get_id()
				)
			);

			return false;
		}

		$authorizedAmount = $result['authorized_amount'];
		$settledAmount    = $result['settled_amount'];

		return (
			( $result['state'] === 'authorized' ) ||
			( $result['state'] === 'settled' && $authorizedAmount >= $settledAmount + $amount )
		);
	}

	/**
	 * Check is Cancel possible
	 *
	 * @param WC_Order|int $order
	 *
	 * @return bool
	 */
	public function can_cancel( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$result = $this->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			/** @var WP_Error $result */

			$this->log(
				sprintf(
					'Payment can\'t be cancelled. Error: %s. Order ID: %s',
					$result->get_error_message(),
					$order->get_id()
				)
			);

			return false;
		}

		// return $result['state'] === 'authorized' || ( $result['state'] === "settled" && $result["settled_amount"] < $result["authorized_amount"] );
		// can only cancel payments when the state is authorized (partly void is not supported yet)
		return ( $result['state'] === 'authorized' );
	}

	/**
	 * @param \WC_Order $order
	 * @param bool $amount
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function can_refund( $order, $amount = false ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Check if hte order is cancelled - if so - then return as nothing has happened
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			return false;
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		$result = $this->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			/** @var WP_Error $result */

			$this->log(
				sprintf(
					'Payment can\'t be refunded. Error: %s. Order ID: %s',
					$result->get_error_message(),
					$order->get_id()
				)
			);

			return false;
		}

		return $result['state'] === 'settled';
	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param float|false $amount
	 *
	 * @return array|WP_Error|Bool
	 */
	public function capture_payment( $order, $amount ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		$order_data = $this->get_invoice_data( $order );

		if ( floatval( $order->get_total() ) * 100 == $order_data['settled_amount'] ) {
			return false;
		}

		if ( ! $this->can_capture( $order, $amount ) ) {
			$this->log( sprintf( 'Payment can\'t be captured. Order ID: %s', $order->get_id() ) );

			return new WP_Error( 0, 'Payment can\'t be captured.' );
		}

		$gateway = rp_get_payment_method( $order );

		$order_lines   = $gateway->get_order_items( $order, true );
		$settled_lines = WC_Reepay_Instant_Settle::get_settled_items( $order );

		foreach ( $settled_lines as $settled_line_key => $settled_line ) {
			foreach ( $order_lines as $order_line_key => $order_line ) {
				if ( $settled_line['ordertext'] == $order_line['ordertext'] ) {
					$amount -= rp_make_initial_amount( $order_lines[ $order_line_key ]['amount'], $order->get_currency() );

					unset( $order_lines[ $order_line_key ] );
					break;
				}
			}
		}

		return $this->settle( $order, $amount, array_values( $order_lines ) );
	}

	/**
	 * Cancel
	 *
	 * @param WC_Order|int $order
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function cancel_payment( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Check if hte order is cancelled - if so - then return as nothing has happened
		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			return;
		}

		if ( ! $this->can_cancel( $order ) ) {
			$this->log( sprintf( 'Payment can\'t be cancelled. Order ID: %s', $order->get_id() ) );

			throw new Exception( 'Payment can\'t be cancelled.' );
		}

		$result = $this->cancel( $order );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
	}

	public function recurring( $payment_methods, $order, $data, $token = false, $payment_text = '' ) {
		$params = [
			'locale'          => $data['language'],
			'create_customer' => [
				'test'        => $data['test_mode'] === 'yes',
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
				'postal_code' => $order->get_billing_postcode()
			],
			'accept_url'      => $data['return_url'],
			'cancel_url'      => $order->get_cancel_order_url()
		];

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

		$result = $this->request(
			'POST',
			'https://checkout-api.reepay.com/v1/session/recurring',
			$params
		);

		return $result;
	}

	/**
	 * Charge payment.
	 *
	 * @param WC_Order $order
	 * @param string $token
	 * @param float $amount
	 * @param string $currency
	 *
	 * @return array|WP_Error
	 */
	public function charge( WC_Order $order, $token, $amount, $currency, $order_lines = null, $settle = false ) {
		// @todo Use order lines instead of amount
		// @todo Use `settle` parameter
		// @todo Add `customer`, `billing_address`, `shipping_address`
		$params = array(
			'handle'      => rp_get_order_handle( $order ),
			'amount'      => ! is_null( $amount ) ? rp_prepare_amount( $amount, $currency ) : null,
			'currency'    => $currency,
			'source'      => $token,
			'recurring'   => order_contains_subscription( $order ),
			'order_lines' => $order_lines,
			'settle'      => $settle,
		);

		if ( $order->get_payment_method() == 'reepay_mobilepay_subscriptions' ) {
			$params['parameters']['mps_ttl'] = "PT24H";
		}

		try {
			$result = $this->request( 'POST', 'https://api.reepay.com/v1/charge', $params );

			if ( is_wp_error( $result ) ) {

				if ( 'yes' == $this->gateway->handle_failover &&
				     ( in_array( $result->get_error_code(), array( 105, 79, 29, 99, 72 ) ) )
				) {

					// Workaround: handle already exists lets create another with unique handle
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
			$this->log( $e->getMessage(), WC_Log_Levels::ERROR );

			/** @var WP_Error $result */
			$order->update_status( 'failed' );
			$order->add_order_note(
				sprintf( __( 'Failed to charge "%s". Error: %s. Token ID: %s', 'reepay-checkout-gateway' ),
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
	 * @param WC_Order $order
	 * @param float|int|null $amount
	 * @param false|array $item_data
	 *
	 * @return array|WP_Error
	 * @throws Exception
	 */
	public function settle( WC_Order $order, $amount = null, $item_data = false, $item = false ) {
		$this->log( sprintf( 'Settle: %s, %s', $order->get_id(), $amount ) );

		$handle = rp_get_order_handle( $order );
		if ( empty( $handle ) ) {
			return new WP_Error( 0, 'Unable to get order handle' );
		}

		if ( ! $amount || ! $item_data ) {
			$settle_data = WC_Reepay_Instant_Settle::calculate_instant_settle( $order );

			if ( ! $amount ) {
				$amount = $settle_data->settle_amount;
			}

			if ( ! $item_data ) {
				$item_data = $settle_data->items;
			}
		}

		$request_data['order_lines'] = $item_data;
		if ( ! empty( $item_data ) && floatval( current( $item_data )['amount'] ) <= 0 ) {
			return new WP_Error( 100, 'Amount must be lager than zero' );
		}

		$result = $this->request(
			'POST',
			'https://api.reepay.com/v1/charge/' . $handle . '/settle',
			$request_data
		);

		if ( is_wp_error( $result ) ) {
			// Workaround: Check if the invoice has been settled before to prevent "Invoice already settled"
			if ( mb_strpos( $result->get_error_message(), 'Invoice already settled', 0, 'UTF-8' ) !== false ) {
				// @todo Fetch invoice transaction
				return array(); // @todo
			}


			if ( mb_strpos( $result->get_error_message(), 'Amount higher than authorized amount', 0, 'UTF-8' ) !== false && ! empty( $item ) ) {

				if ( count( $request_data['order_lines'] ) > 1 && is_array( $item ) ) {
					$order_data = $this->get_invoice_data( $order );
					$remaining  = $order_data['authorized_amount'] - $order_data['settled_amount'];

					$request_data['amount'] = $remaining;
					unset( $request_data['order_lines'] );

					$result = $this->request(
						'POST',
						'https://api.reepay.com/v1/charge/' . $handle . '/settle',
						$request_data
					);

					return $result;
				} else {
					$order_data = $this->get_invoice_data( $order );
					$remaining  = $order_data['authorized_amount'] - $order_data['settled_amount'];
					$price      = WC_Reepay_Order_Capture::get_item_price( $item, $order );
					if ( $remaining > 0 && round( $remaining / 100 ) == $price['with_tax'] && ! empty( $request_data['order_lines'][0] ) ) {
						$full = $remaining / ( $request_data["order_lines"][0]['vat'] + 1 );
						if ( $full > 0 ) {
							$request_data["order_lines"][0]['amount'] = $full;

							$result = $this->request(
								'POST',
								'https://api.reepay.com/v1/charge/' . $handle . '/settle',
								$request_data
							);

							return $result;
						}
					}
				}

			}

			// need to be shown on admin notices
			if ( $item_data ) {
				$error = sprintf( __( 'Failed to settle %s. Error: %s.', 'reepay-checkout-gateway' ),
					floatval( $item_data[0]['amount'] ) / 100,
					$result->get_error_message()
				);
			} else {
				$error = sprintf( __( 'Failed to settle %s. Error: %s.', 'reepay-checkout-gateway' ),
					$amount,
					$result->get_error_message()
				);
			}

			set_transient( 'reepay_api_action_error', $error, MINUTE_IN_SECONDS / 2 );

			return $result;
		}

		if ( 'failed' === $result['state'] ) {
			return new WP_Error( 0, 'Settle has been failed.' );
		}

		// @todo Check $result['processing']
		// @todo Check $result['authorized_amount']
		// @todo Check state $result['state']

		$order->update_meta_data( '_reepay_capture_transaction', $result['transaction'] );
		$order->save_meta_data();

		// Set transaction Id
		$order->set_transaction_id( $result['transaction'] );
		$order->save();

		// Check the amount and change the order status to settled if needs
		$order_data = $this->get_invoice_data( $order );
		if ( is_wp_error( $order_data ) ) {
			/** @var WP_Error $order_data */
			return new WP_Error(
				0,
				'Settled, but unable to verify the transaction. Error: ' . $order_data->get_error_message()
			);
		}

		$message = sprintf(
			__( 'Payment has been settled. Amount: %s. Transaction: %s', 'reepay-checkout-gateway' ),
			rp_make_initial_amount( $amount, $order->get_currency() ) . ' ' . $order->get_currency(),
			$result['transaction']
		);

		// Add order note
		$order->add_order_note(
			$message
		);

		set_transient( 'reepay_api_action_success', $message, MINUTE_IN_SECONDS / 2 );

		return $result;
	}

	/**
	 * Cancel the payment online.
	 *
	 * @param WC_Order $order
	 *
	 * @return array|WP_Error
	 */
	public function cancel( WC_Order $order ) {
		$handle = rp_get_order_handle( $order );
		if ( empty( $handle ) ) {
			return new WP_Error( 0, 'Unable to get order handle' );
		}

		$result = $this->request( 'POST', 'https://api.reepay.com/v1/charge/' . $handle . '/cancel' );
		if ( is_wp_error( $result ) ) {
			$error = sprintf( __( 'Failed to cancel the payment. Error: %s.', 'reepay-checkout-gateway' ),
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
	 * @param WC_Order $order
	 * @param float|int|null $amount
	 * @param string|null $reason
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
			/** @var WP_Error $result */
			$error = sprintf( __( 'Failed to refund "%s". Error: %s.', 'reepay-checkout-gateway' ),
				wc_price( $amount ),
				$result->get_error_message()
			);

			set_transient( 'reepay_api_action_error', $error, MINUTE_IN_SECONDS / 2 );

			$order->add_order_note( $error );

			//if ( 'woocommerce_refund_line_items' == trim( wc_clean( $_POST['action'] ) ) ) {
			//	throw new Exception($api_error['error']);
			//}

			return $result;
		}

		// Save Credit Note ID
		$credit_note_ids = $order->get_meta( '_reepay_credit_note_ids' );
		if ( ! is_array( $credit_note_ids ) ) {
			$credit_note_ids = array();
		}

		array_push( $credit_note_ids, $result['credit_note_id'] );
		$order->update_meta_data( '_reepay_credit_note_ids', $credit_note_ids );
		$order->save_meta_data();

		$message = sprintf( __( 'Refunded: %s. Credit Note Id #%s. Reason: %s', 'reepay-checkout-gateway' ),
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
	 * @param WC_Order $order
	 * @param array $result
	 *
	 * @throws Exception
	 */
	private function process_charge_result( WC_Order $order, array $result ) {
		// @todo Check $result['processing']
		// @todo Check $result['authorized_amount']
		// @todo Check state $result['state']

		// For asynchronous payment methods this flag indicates that the charge is awaiting result.
		// The charge/invoice state will be pending.

		// Check results
		switch ( $result['state'] ) {
			case 'pending':
				WC_Reepay_Order_Statuses::update_order_status(
					$order,
					'pending',
					sprintf(
						__( 'Transaction is pending. Amount: %s. Transaction: %s', 'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $order->get_currency() ) ),
						$result['transaction']
					),
					$result['transaction']
				);

				break;
			case 'authorized':
				WC_Reepay_Order_Statuses::set_authorized_status(
					$order,
					sprintf(
						__( 'Payment has been authorized. Amount: %s. Transaction: %s',
							'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $order->get_currency() ) ),
						$result['transaction']
					),
					$result['transaction']
				);

				// Settle an authorized payment instantly if possible
				do_action( 'reepay_instant_settle', $order );

				break;
			case 'settled':
				update_post_meta( $order->get_id(), '_reepay_capture_transaction', $result['transaction'] );

				WC_Reepay_Order_Statuses::set_settled_status(
					$order,
					sprintf(
						__( 'Payment has been settled. Amount: %s. Transaction: %s',
							'reepay-checkout-gateway' ),
						wc_price( rp_make_initial_amount( $result['amount'], $order->get_currency() ) ),
						$result['transaction']
					),
					$result['transaction']
				);

				break;
			case 'cancelled':
				update_post_meta( $order->get_id(), '_reepay_cancel_transaction', $result['transaction'] );

				if ( ! $order->has_status( 'cancelled' ) ) {
					$order->update_status( 'cancelled',
						__( 'Payment has been cancelled.', 'reepay-checkout-gateway' ) );
				} else {
					$order->add_order_note( __( 'Payment has been cancelled.',
						'reepay-checkout-gateway' ) );
				}

				break;
			case 'failed':
				throw new Exception( 'Cancelled' );
			default:
				throw new Exception( 'Generic error' );
		}
	}

	/**
	 * Get Customer Cards from Reepay
	 *
	 * @param string $customer_handle
	 * @param string|null $reepay_token
	 *
	 * @return array|WP_Error
	 */
	public function get_reepay_cards( $customer_handle, $reepay_token = null ) {
		$result = $this->request(
			'GET',
			'https://api.reepay.com/v1/customer/' . $customer_handle . '/payment_method'
		);
		if ( is_wp_error( $result ) ) {
			/** @var WP_Error $result */
			return $result;
		}

		if ( ! isset( $result['cards'] ) ) {
			return new WP_Error( 0, 'Unable to retrieve customer payment methods' );
		}

		// @todo Add mps_subscriptions
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
	 * @param $order_id
	 *
	 * @return string
	 */
	public function get_customer_handle_order( $order_id ) {
		$order = wc_get_order( $order_id );

		$handle = $this->get_customer_handle_online( $order );
		if ( ! empty( $handle ) ) {
			return $handle;
		}

		if ( $order->get_customer_id() == 0 ) {
			$handle = $order->get_meta( '_reepay_customer' );
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
	 * @param WC_Order $order
	 *
	 * @return false|string
	 */
	public function get_customer_handle_online( $order ) {
		// Get customer handle by order
		$handle = rp_get_order_handle( $order );

		$result = get_transient( 'reepay_invoice_' . $handle );

		if ( ! $result ) {
			$invoice_data = $this->get_invoice_by_handle( wc_clean( $handle ) );
			if ( is_wp_error( $invoice_data ) ) {
				/** @var WP_Error $result */
				return null;
			}
		}

		if ( is_array( $result ) && isset( $result['customer'] ) ) {
			return $result['customer'];
		}

		return null;
	}
}
