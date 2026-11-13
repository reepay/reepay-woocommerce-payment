<?php
/**
 * Class for handling reepay hooks
 *
 * @package Reepay\Checkout\OrderFlow
 */

namespace Reepay\Checkout\OrderFlow;

use Exception;
use Reepay\Checkout\Utils\LoggingTrait;
use Reepay\Checkout\Tokens\ReepayTokens;
use Automattic\WooCommerce\Caches\OrderCache;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Subscriptions_Manager;
use WP_Error;

defined( 'ABSPATH' ) || exit();

/**
 * Class Webhook
 *
 * @package Reepay\Checkout\OrderFlow
 */
class Webhook {
	use LoggingTrait;

	/**
	 * Maximum accepted request body size in bytes.
	 */
	const MAX_BODY_SIZE = 65536;

	/**
	 * Logging source
	 *
	 * @var string
	 */
	private string $logging_source = 'reepay-webhook';

	/**
	 * Seconds to wait for an order lock held by another request.
	 *
	 * @var int
	 */
	private int $lock_wait = 10;

	/**
	 * Order lock held by this request.
	 *
	 * @var WebhookLock|null
	 */
	private ?WebhookLock $order_lock = null;

	/**
	 * Set when the event could not be handled because of a temporary error and Frisbii should send it again.
	 *
	 * @var bool
	 */
	private bool $retry_requested = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_' . strtolower( 'WC_Gateway_Reepay' ), array( $this, 'return_handler' ) );
	}

	/**
	 * Fetch the checkout session's event log and persist the age verification
	 * result, if any, as order meta.
	 *
	 * Both a failed API call and a missing EXTERNAL_AGE_VERIFICATION_RESULT
	 * event are treated the same way: logged, no meta stored, webhook
	 * processing continues unaffected. If the event appears more than once,
	 * the last (most recent) occurrence wins.
	 *
	 * @param WC_Order $order order to update.
	 *
	 * @return void
	 */
	private function save_age_verification_result( WC_Order $order ) {
		$session_id = $order->get_meta( 'reepay_session_id' );
		if ( empty( $session_id ) ) {
			return;
		}

		$events = reepay()->api( $order )->get_session_events( $session_id );
		if ( is_wp_error( $events ) ) {
			$this->log(
				sprintf(
					'WebHook: Failed to fetch session events for age verification. Order: %d, Session: %s, Error: %s',
					$order->get_id(),
					$session_id,
					$events->get_error_message()
				)
			);

			return;
		}

		$result = null;
		foreach ( (array) $events as $event ) {
			if ( isset( $event['name'] ) && 'EXTERNAL_AGE_VERIFICATION_RESULT' === $event['name'] ) {
				$result = $event['data'] ?? array();
			}
		}

		if ( null === $result ) {
			return;
		}

		$order->update_meta_data( '_reepay_age_verification_result', wp_json_encode( $result ) );
		$order->save_meta_data();
	}

	/**
	 * Filter sensitive data from arrays before logging.
	 *
	 * @param array $data Data to filter.
	 *
	 * @return array Filtered data.
	 */
	private function filter_sensitive_data( array $data ): array {
		$sensitive_keys = array( 'card_number', 'cvv', 'cvc', 'masked_card', 'card_token', 'pan', 'secret', 'password', 'authorization' );
		$filtered       = array();

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$filtered[ $key ] = $this->filter_sensitive_data( $value );
			} elseif ( in_array( strtolower( $key ), $sensitive_keys, true ) ) {
				$filtered[ $key ] = '***REDACTED***';
			} else {
				$filtered[ $key ] = $value;
			}
		}

		return $filtered;
	}

	/**
	 * Make a request-supplied value safe for a single log line: no control characters, bounded length.
	 *
	 * @param mixed $value Value to log.
	 * @param int   $max   Maximum length.
	 *
	 * @return string
	 */
	private function sanitize_log_value( $value, int $max = 100 ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return substr( preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $value ), 0, $max );
	}

	/**
	 * Allow a bounded number of Reepay secret requests per minute.
	 *
	 * @param bool $initial True for the first fetch when no secret is cached, false for a refresh
	 *                      forced by a failed signature.
	 *
	 * @return bool True if a request may be made now.
	 */
	private function may_refresh_secret( bool $initial = false ): bool {
		if ( $initial ) {
			/**
			 * Maximum number of webhook secret fetches per minute when no secret is cached.
			 *
			 * @param int $limit Default 30.
			 */
			$limit = (int) apply_filters( 'reepay_webhook_secret_fetch_limit', 30 );
			$key   = 'reepay_webhook_secret_fetch';
		} else {
			/**
			 * Maximum number of webhook secret refreshes caused by failed signatures per minute.
			 *
			 * @param int $limit Default 5.
			 */
			$limit = (int) apply_filters( 'reepay_webhook_secret_refresh_limit', 5 );
			$key   = 'reepay_webhook_secret_refresh';
		}
		$state = get_transient( $key );

		if ( ! is_array( $state ) || time() - $state['start'] >= MINUTE_IN_SECONDS ) {
			$state = array(
				'start' => time(),
				'count' => 0,
			);
		}

		if ( $state['count'] >= $limit ) {
			$this->log( 'WebHook: Secret refresh limit reached' );

			return false;
		}

		++$state['count'];
		set_transient( $key, $state, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Check the webhook signature in constant time.
	 *
	 * @param array  $data   webhook payload with string timestamp, id and signature.
	 * @param string $secret webhook secret.
	 *
	 * @return bool
	 */
	private function is_signature_valid( array $data, string $secret ): bool {
		if ( '' === $secret ) {
			return false;
		}

		$check = bin2hex( hash_hmac( 'sha256', $data['timestamp'] . $data['id'], $secret, true ) );

		return hash_equals( $check, $data['signature'] );
	}

	/**
	 * Reject events outside the allowed time window and events already processed.
	 * Call only after the signature has been verified.
	 *
	 * @param array $data webhook payload with verified timestamp and id.
	 *
	 * @return bool True if the event may be processed.
	 */
	private function is_fresh_and_new( array $data ): bool {
		/**
		 * Allowed difference in seconds between the webhook timestamp and now.
		 *
		 * @param int $window Default 900 (15 minutes). Frisbii retries a webhook that was answered with a server error, with the original timestamp, so the window must cover the retry delay.
		 */
		$window = (int) apply_filters( 'reepay_webhook_timestamp_window', 15 * MINUTE_IN_SECONDS );
		$time   = ctype_digit( $data['timestamp'] ) ? (int) $data['timestamp'] : strtotime( $data['timestamp'] );

		if ( false === $time || abs( time() - $time ) > $window ) {
			$this->log( sprintf( 'WebHook: Error: Timestamp outside allowed window. ID: %s', $data['id'] ) );

			return false;
		}

		$key = 'reepay_webhook_event_' . md5( $data['id'] );
		if ( false !== get_transient( $key ) ) {
			$this->log( sprintf( 'WebHook: Duplicate event ignored. ID: %s', $data['id'] ) );

			return false;
		}
		set_transient( $key, 1, 2 * $window );

		return true;
	}

	/**
	 * WebHook Callback
	 *
	 * @return void
	 */
	public function return_handler() {
		http_response_code( 200 );

		// Read one byte more than the limit to detect an oversized body without loading it all.
		$raw_body = (string) file_get_contents( 'php://input', false, null, 0, self::MAX_BODY_SIZE + 1 );
		// Before the signature is verified only minimal, bounded metadata is logged.
		$this->log( sprintf( 'WebHook: Initialized from %s, body length %d', $this->sanitize_log_value( $_SERVER['REMOTE_ADDR'] ?? '' ), strlen( $raw_body ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( strlen( $raw_body ) > self::MAX_BODY_SIZE ) {
			$this->log( 'WebHook: Error: Body too large' );

			return;
		}

		$data = json_decode( $raw_body, true );
		if ( ! $data || ! is_array( $data ) ) {
			$this->log( 'WebHook: Error: Missing parameters' );

			return;
		}

		foreach ( array( 'timestamp', 'id', 'signature' ) as $field ) {
			if ( empty( $data[ $field ] ) || ! is_string( $data[ $field ] ) ) {
				$this->log( sprintf( 'WebHook: Error: Missing or invalid field: %s', $field ) );

				return;
			}
		}

		$secret            = get_transient( 'reepay_webhook_settings_secret' );
		$secret_from_cache = ! empty( $secret );

		if ( empty( $secret ) ) {
			if ( ! $this->may_refresh_secret( true ) ) {
				return;
			}

			$result = reepay()->api( $this->logging_source )->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
			if ( is_wp_error( $result ) ) {
				$this->log( 'WebHook: Error: ' . $result->get_error_message() );
				return;
			}

			$secret = $result['secret'] ?? '';
			if ( empty( $secret ) || ! is_string( $secret ) ) {
				$this->log( 'WebHook: Error: Webhook secret is not available' );

				return;
			}

			/**
			 * Cache webhook secret for 10 minutes to reduce API calls while limiting exposure window.
			 * Note: For PCI DSS compliance in high-security environments, consider implementing
			 * encryption at rest or disabling caching via the filter below.
			 *
			 * @param int $ttl Time to live in seconds. Default 600 (10 minutes). Set to 0 to disable caching.
			 */
			$cache_ttl = apply_filters( 'reepay_webhook_secret_cache_ttl', 10 * MINUTE_IN_SECONDS );

			if ( $cache_ttl > 0 ) {
				set_transient( 'reepay_webhook_settings_secret', $secret, $cache_ttl );
			}
		}

		$valid = $this->is_signature_valid( $data, $secret );
		if ( ! $valid ) {
			// Retry with a fresh secret in case it was recently rotated, unless it was just fetched
			// in this request or too many refreshes were made recently (unauthenticated traffic).
			$result = false;
			if ( $secret_from_cache && $this->may_refresh_secret() ) {
				$result = reepay()->api( $this->logging_source )->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
			}
			if ( $result && ! is_wp_error( $result ) && ! empty( $result['secret'] ) && is_string( $result['secret'] ) ) {
				$secret = $result['secret'];
				// Use the same cache TTL as above for consistency.
				$cache_ttl = apply_filters( 'reepay_webhook_secret_cache_ttl', 10 * MINUTE_IN_SECONDS );
				if ( $cache_ttl > 0 ) {
					set_transient( 'reepay_webhook_settings_secret', $secret, $cache_ttl );
				}
				$valid = $this->is_signature_valid( $data, $secret );
			}

			if ( ! $valid ) {
				$this->log(
					sprintf(
						'WebHook: Error: Signature verification failed. Event: %s, ID: %s, Remote IP: %s',
						$this->sanitize_log_value( $data['event_type'] ?? 'unknown', 64 ),
						$this->sanitize_log_value( $data['id'] ),
						$this->sanitize_log_value( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					)
				);

				return;
			}
		}

		if ( ! $this->is_fresh_and_new( $data ) ) {
			return;
		}

		try {
			$this->process( $data );
		} catch ( Exception $e ) {
			$this->log( 'WebHook: Error:' . $e->getMessage() );
		}

		if ( $this->retry_requested ) {
			// Forget the event id so that the retry from Frisbii is processed and not ignored as a duplicate.
			delete_transient( 'reepay_webhook_event_' . md5( $data['id'] ) );
			http_response_code( 503 );

			return;
		}

		http_response_code( 200 );
	}

	/**
	 * Whether the Frisbii API error is temporary, so that sending the event again can succeed.
	 * Network errors, rate limits and server errors are temporary; other errors are not.
	 *
	 * @param WP_Error $error error from the API client.
	 *
	 * @return bool
	 */
	private function is_temporary_error( WP_Error $error ): bool {
		$code = (int) $error->get_error_code();

		return 0 === $code || 429 === $code || $code >= 500;
	}

	/**
	 * Ask Frisbii to send the event again. The order is left unchanged.
	 *
	 * @param array    $data  webhook payload.
	 * @param WP_Error $error error from the API client.
	 *
	 * @return void
	 */
	private function request_retry( array $data, WP_Error $error ) {
		$this->retry_requested = true;
		$this->log(
			sprintf(
				'WebHook: Temporary error, retry requested. Event: %s, ID: %s, Error: %s',
				$this->sanitize_log_value( $data['event_type'] ?? 'unknown', 64 ),
				$this->sanitize_log_value( $data['id'] ),
				$this->sanitize_log_value( $error->get_error_message() )
			)
		);
	}

	/**
	 * Whether the last processed event asked Frisbii to send it again.
	 *
	 * @return bool
	 */
	public function is_retry_requested(): bool {
		return $this->retry_requested;
	}

	/**
	 * Process WebHook and release the order lock on every exit path.
	 *
	 * @param array $data data from Reepay.
	 *
	 * @return void
	 * @throws Exception If invalid data.
	 */
	public function process( array $data ) {
		try {
			$this->process_event( $data );
		} finally {
			$this->unlock_order();
		}
	}

	/**
	 * Process WebHook.
	 *
	 * @param array $data data from Reepay.
	 *
	 * @return void
	 * @throws Exception If invalid data.
	 *
	 * @todo split switch into methods
	 * @todo remove code duplication
	 */
	private function process_event( array $data ) {
		// Log caller information to reepay_order_statuses log.
		$caller       = 'unknown';
		$caller_class = '';

		// Only collect backtrace information when debugging is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$backtrace    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$caller       = isset( $backtrace[2] ) ? $backtrace[2]['function'] : 'unknown';
			$caller_class = isset( $backtrace[2]['class'] ) ? $backtrace[2]['class'] . '::' : '';
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				sprintf(
					'Webhook::process called for event %s from %s%s',
					$data['event_type'] ?? 'unknown',
					$caller_class,
					$caller
				),
				array(
					'source'  => 'reepay_order_statuses',
					'_legacy' => true,
				)
			);
		}

		do_action( 'reepay_webhook', $data );

		$this->log(
			sprintf(
				'WebHook %1$s: Data: %2$s',
				$data['event_type'],
				wp_json_encode( $this->filter_sensitive_data( $data ) )
			)
		);

		switch ( $data['event_type'] ) {
			case 'invoice_authorized':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				if ( isset( $data['subscription'] ) ) {
					$order = rp_get_not_subs_order_by_handle( $data['invoice'] );
				} else {
					$order = rp_get_order_by_handle( $data['invoice'] );
				}

				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$order = $this->lock_and_reload_order( $order );
				if ( ! $order ) {
					return;
				}

				if ( $order->has_status( OrderStatuses::$status_sync_enabled ? OrderStatuses::$status_authorized : 'on-hold' ) ) {
					$this->log(
						sprintf(
							'WebHook: Event type: %s success. But the order had status early: %s',
							$data['event_type'],
							$order->get_status()
						)
					);

					http_response_code( 200 );

					return;
				}

				$invoice_data = reepay()->api( $order )->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					if ( $this->is_temporary_error( $invoice_data ) ) {
						$this->request_retry( $data, $invoice_data );

						return;
					}
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', wp_json_encode( $invoice_data, JSON_PRETTY_PRINT ) ) );

				if ( ! OrderStatuses::invoice_matches_order( $order, $invoice_data, 'webhook_invoice_authorized', array( 'authorized', 'settled' ) ) ) {
					http_response_code( 200 );

					return;
				}

				$order->set_transaction_id( $data['transaction'] );
				$order->save();

				// skip set authorized status for settled charge of the Vipps MobilePay Recurring.
				if ( array_key_exists( 'recurring_payment_method', $invoice_data ) && 'vr_' === substr( $invoice_data['recurring_payment_method'], 0, 3 ) && 'settled' === $invoice_data['state'] ) {
					$order->add_order_note(
						sprintf(
						// translators: %1$s - order amount, %2$s - transaction id.
							__( 'Payment has been authorized. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
							wc_price( rp_make_initial_amount( $invoice_data['amount'], $order->get_currency() ), array( 'currency' => $order->get_currency() ) ),
							$data['transaction']
						)
					);
					$order->update_meta_data( '_reepay_state_authorized', 1 );
					$order->save_meta_data();
					$order->save();
				} else {
					OrderStatuses::set_authorized_status(
						$order,
						sprintf(
						// translators: %1$s - order amount, %2$s - transaction id.
							__( 'Payment has been authorized. Amount: %1$s. Transaction: %2$s', 'reepay-checkout-gateway' ),
							wc_price( rp_make_initial_amount( $invoice_data['amount'], $order->get_currency() ), array( 'currency' => $order->get_currency() ) ),
							$data['transaction']
						),
						$data['transaction']
					);
				}

				// Save card information from invoice.
				try {
					ReepayTokens::save_card_info_from_invoice( $order, $data['invoice'] );
				} catch ( Exception $e ) {
					// Log error but don't fail webhook processing.
					$this->log(
						sprintf(
							'WebHook invoice_authorized: Failed to save card info. Order: %d, Invoice: %s, Error: %s',
							$order->get_id(),
							$data['invoice'],
							$e->getMessage()
						)
					);
				}

				$this->save_age_verification_result( $order );

				$this->unlock_order();

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_authorized', $data );

				// Need for analytics.
				$order->set_date_paid( time() );
				$order->save(); // Ensure date_paid is saved for analytics.

				// Trigger order save to ensure analytics are updated.
				$order = wc_get_order( $order->get_id() );
				if ( $order ) {
					// Trigger WooCommerce update hook for analytics.
					do_action( 'woocommerce_update_order', $order->get_id(), $order );
					$order->save();
				}

				if ( ! empty( $invoice_data['order_lines'] ) ) {
					$this->log( sprintf( 'WebHook: event type: %s order: %s checking surcharge fee', $data['event_type'], $invoice_data['handle'] ) );
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
								$this->log( sprintf( 'WebHook: event type: %s  order: %s adding surcharge fee', $data['event_type'], $invoice_data['handle'] ) );
								$fees_item = new WC_Order_Item_Fee();
								$fees_item->set_name( $invoice_line['ordertext'] );
								$fees_item->set_amount( floatval( $invoice_line['unit_amount'] ) / 100 );
								$fees_item->set_total( floatval( $invoice_line['amount'] ) / 100 );
								$fees_item->set_tax_status( 'none' );
								$fees_item->add_meta_data( '_is_card_fee', true );
								$order->add_item( $fees_item );
								$order->calculate_totals( false );
								$order->save();
								$this->log( sprintf( 'WebHook: event type: %s  order: %s surcharge fee added', $data['event_type'], $invoice_data['handle'] ) );
							} else {
								$this->log( sprintf( 'WebHook: event type: %s  order: %s surcharge fee already exists', $data['event_type'], $invoice_data['handle'] ) );
							}
						}
					}
				}

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );

				do_action( 'reepay_instant_settle', $order );

				break;
			case 'invoice_settled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				if ( isset( $data['subscription'] ) ) {
					$order = rp_get_not_subs_order_by_handle( $data['invoice'] );
				} else {
					$order = rp_get_order_by_handle( $data['invoice'] );
				}

				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$order = $this->lock_and_reload_order( $order );
				if ( ! $order ) {
					return;
				}

				if ( $order->has_status( OrderStatuses::$status_settled ) && ! empty( $order->get_meta( '_reepay_state_settled' ) ) ) {
					$this->log(
						sprintf(
							'WebHook: Event type: %s success. But the order had status early: %s',
							$data['event_type'],
							$order->get_status()
						)
					);

					http_response_code( 200 );

					return;
				}

				$invoice_data = reepay()->api( $order )->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					if ( $this->is_temporary_error( $invoice_data ) ) {
						$this->request_retry( $data, $invoice_data );

						return;
					}
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', var_export( $invoice_data, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

				if ( ! OrderStatuses::invoice_matches_order( $order, $invoice_data, 'webhook_invoice_settled', array( 'settled' ) ) ) {
					http_response_code( 200 );

					return;
				}

				if ( ! empty( $invoice_data['id'] ) && ! empty( $data['transaction'] ) ) {
					$transaction = reepay()->api( $order )->request( 'GET', 'https://api.reepay.com/v1/invoice/' . rawurlencode( (string) $invoice_data['id'] ) . '/transaction/' . rawurlencode( (string) $data['transaction'] ) );
					$this->log( sprintf( 'WebHook: Transaction data: %s', var_export( $transaction, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

					// A failed lookup is logged above and does not stop the settled handling.
					if ( is_array( $transaction ) && ! empty( $transaction['card_transaction']['card'] ) ) {
						if ( ! empty( $transaction['card_transaction']['error'] ) && ! empty( $transaction['card_transaction']['acquirer_message'] ) ) {
							$order->add_order_note( 'Item settle error: ' . $transaction['card_transaction']['acquirer_message'] );

							return;
						}
					}
				}

				if ( empty( $order->get_meta( '_reepay_subscription_handle' ) ) ) {
					// Log to reepay_order_statuses log.
					if ( function_exists( 'wc_get_logger' ) ) {
						wc_get_logger()->debug(
							sprintf(
								'Webhook::process calling set_settled_status for Order ID %d in invoice_settled case',
								$order->get_id()
							),
							array(
								'source'  => 'reepay_order_statuses',
								'_legacy' => true,
							)
						);
					}

					OrderStatuses::set_settled_status(
						$order,
						'',
						$data['transaction']
					);
				} elseif ( function_exists( 'wc_get_logger' ) ) {
					// Log to reepay_order_statuses log.
					wc_get_logger()->debug(
						sprintf(
							'Webhook::process skipping set_settled_status for Order ID %d (has subscription handle)',
							$order->get_id()
						),
						array(
							'source'  => 'reepay_order_statuses',
							'_legacy' => true,
						)
					);
				}

				// Save card information from invoice.
				try {
					ReepayTokens::save_card_info_from_invoice( $order, $data['invoice'] );
				} catch ( Exception $e ) {
					// Log error but don't fail webhook processing.
					$this->log(
						sprintf(
							'WebHook invoice_settled: Failed to save card info. Order: %d, Invoice: %s, Error: %s',
							$order->get_id(),
							$data['invoice'],
							$e->getMessage()
						)
					);
				}

				// For subscription renewals, also update parent subscription order.
				if ( isset( $data['subscription'] ) ) {
					$subscription_order = rp_get_order_by_subscription_handle( $data['subscription'] );
					if ( $subscription_order && $subscription_order->get_id() !== $order->get_id() ) {
						try {
							ReepayTokens::save_card_info_from_invoice( $subscription_order, $data['invoice'] );
							$this->log(
								sprintf(
									'WebHook invoice_settled: Saved card info for subscription order. Subscription Order: %d, Renewal Order: %d, Invoice: %s',
									$subscription_order->get_id(),
									$order->get_id(),
									$data['invoice']
								)
							);
						} catch ( Exception $e ) {
							// Log error but don't fail webhook processing.
							$this->log(
								sprintf(
									'WebHook invoice_settled: Failed to save card info for subscription order. Subscription Order: %d, Invoice: %s, Error: %s',
									$subscription_order->get_id(),
									$data['invoice'],
									$e->getMessage()
								)
							);
						}
					}
				}

				$order->update_meta_data( '_reepay_capture_transaction', $data['transaction'] );
				$order->save_meta_data();

				$this->unlock_order();

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_settled', $data );

				// Trigger order save to ensure analytics are updated.
				$order = wc_get_order( $order->get_id() );
				if ( $order ) {
					// Trigger WooCommerce update hook for analytics.
					do_action( 'woocommerce_update_order', $order->get_id(), $order );
					$order->save();
				}

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_cancelled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$order = rp_get_order_by_handle( $data['invoice'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$order = $this->lock_and_reload_order( $order );
				if ( ! $order ) {
					return;
				}

				if ( $order->has_status( 'cancelled' ) ) {
					$this->log(
						sprintf(
							'WebHook: Event type: %s success. Order status: %s',
							$data['event_type'],
							$order->get_status()
						)
					);

					http_response_code( 200 );

					return;
				}

				$order->update_status(
					'cancelled',
					__( 'Cancelled by WebHook.', 'reepay-checkout-gateway' )
				);

				$order->update_meta_data( '_reepay_cancel_transaction', $data['transaction'] );
				$order->save_meta_data();

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_cancelled', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_refund':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$order = rp_get_order_by_handle( $data['invoice'] );

				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );

					return;
				}

				$sub_order = $order->get_meta( '_reepay_subscription_handle' );
				if ( ! empty( $sub_order ) ) {
					return;
				}

				$order = $this->lock_and_reload_order( $order );
				if ( ! $order ) {
					return;
				}

				$invoice_data = reepay()->api( $order )->get_invoice_by_handle( $data['invoice'] );
				if ( is_wp_error( $invoice_data ) ) {
					$this->log( sprintf( 'WebHook: Error: Failed to get invoice %s: %s', $data['invoice'], $invoice_data->get_error_message() ) );
					$invoice_data = array();
				}

				$credit_notes = $invoice_data['credit_notes'] ?? array();
				foreach ( $credit_notes as $credit_note ) {
					// Get registered credit notes.
					$credit_note_ids = $order->get_meta( '_reepay_credit_note_ids' );
					if ( ! is_array( $credit_note_ids ) ) {
						$credit_note_ids = array();
					}

					// Check is refund already registered.
					if ( in_array( $credit_note['id'], $credit_note_ids, true ) ) {
						continue;
					}

					$credit_note_id = $credit_note['id'];
					$amount         = rp_make_initial_amount( $credit_note['amount'], $order->get_currency() );
					$reason         = sprintf(
					// translators: #%s credit note id.
						__( 'Credit Note Id #%s.', 'reepay-checkout-gateway' ),
						$credit_note_id
					);

					$refund = wc_create_refund(
						array(
							'amount'   => $amount,
							'reason'   => '', // don't add Credit note to refund line.
							'order_id' => $order->get_id(),
						)
					);

					if ( $refund ) {
						$credit_note_ids[] = $credit_note_id;
						$order->update_meta_data( '_reepay_credit_note_ids', $credit_note_ids );
						$order->save_meta_data();

						$order->add_order_note(
							sprintf(
							// translators: %1$s refund amount, %2$s refund reason.
								__( 'Refunded: %1$s. Reason: %2$s', 'reepay-checkout-gateway' ),
								wc_price( $amount, array( 'currency' => $order->get_currency() ) ),
								$reason
							)
						);
					}
				}

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_refund', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_created':
				// FIX: Graceful skip for non-subscription invoices that lack these params.
				if ( ! isset( $data['invoice'] ) || ! isset( $data['subscription'] ) ) {
					$this->log(
						sprintf(
							'WebHook: invoice_created event skipped — missing %s. ID: %s',
							! isset( $data['invoice'] ) ? 'invoice' : 'subscription',
							$data['id'] ?? 'unknown'
						)
					);
					break;
				}

				$order = rp_get_order_by_subscription_handle( $data['subscription'] );
				if ( ! $order ) {
					$this->log( sprintf( 'WebHook: Order is not found. Invoice: %s', $data['invoice'] ) );
					do_action( 'reepay_webhook_invoice_created', $data );

					return;
				} else {
					$this->log( sprintf( 'WebHook: Order is found. Order: %s', $order ) );
				}

				$data['order_id'] = $order->get_id();
				do_action( 'reepay_webhook_invoice_created', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_created':
				$customer = $data['customer'];
				$user_id  = rp_get_user_id_by_handle( $customer );
				if ( ! $user_id ) {
					if ( strpos( $customer, 'customer-' ) !== false ) {
						$user_id = (int) str_replace( 'customer-', '', $customer );
						if ( $user_id > 0 ) {
							update_user_meta( $user_id, 'reepay_customer_id', $customer );
							$this->log( sprintf( 'WebHook: Customer created: %s', var_export( $customer, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
						}
					}

					if ( ! $user_id ) {
						$this->log( sprintf( 'WebHook: Customer doesn\'t exists: %s', var_export( $customer, true ) ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					}
				}

				$data['user_id'] = $user_id;
				do_action( 'reepay_webhook_customer_created', $data );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_payment_method_added':
				if ( ! empty( $data['payment_method_reference'] ) ) {
					$order = rp_get_order_by_session( $data['payment_method_reference'], $data['customer'] );
					if ( $order && order_contains_subscription( $order ) && $this->order_payment_verified( $order ) ) {
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );

						if ( ! empty( $data['payment_method'] ) ) {
							try {
								$token = ReepayTokens::reepay_save_token( $order, $data['payment_method'] );
								ReepayTokens::assign_payment_token( $order, $token );
								ReepayTokens::save_card_info_to_order( $order, $token->get_token() );

								$order->payment_complete();
							} catch ( Exception $e ) {
								$order->add_order_note( $e->getMessage() );
								$this->log( sprintf( 'WebHook: Token save error: %s', $e->getMessage() ) );
								return;
							}
						} else {
							wc_get_logger()->debug(
								__METHOD__,
								array(
									'source'  => 'billwerk-token',
									'_legacy' => true,
								)
							);
						}
					} else {
						wc_get_logger()->debug(
							__METHOD__,
							array(
								'source'  => 'billwerk-token',
								'_legacy' => true,
							)
						);
					}
				}

				do_action( 'reepay_webhook_customer_payment_method_added', $data );

				break;
			default:
				global $wp_filter;
				$this->log( sprintf( 'WebHook: %s', $data['event_type'] ) );
				$base_hook_name    = 'reepay_webhook_raw_event';
				$current_hook_name = "{$base_hook_name}_{$data['event_type']}";

				if ( isset( $wp_filter[ $base_hook_name ] ) || isset( $wp_filter[ $current_hook_name ] ) ) {
					do_action( $base_hook_name, $data );
					do_action( $current_hook_name, $data );
				} else {
					$this->log( sprintf( 'WebHook: Unknown event type: %s', $data['event_type'] ) );
					http_response_code( 200 );

					return;
				}
		}
	}

	/**
	 * Verify that an order genuinely received payment before a
	 * customer_payment_method_added webhook is allowed to activate its
	 * subscription and mark it paid.
	 *
	 * The order lookup can fall back to matching purely by customer handle
	 * (rp_get_order_by_customer()) when the webhook's session reference does
	 * not belong to any order. That fallback does not check payment state, so
	 * without this verification any pending order of the customer that
	 * happens to contain a subscription would be completed for free by an
	 * unrelated action such as adding a new card.
	 *
	 * @param WC_Order $order order matched for the webhook.
	 *
	 * @return bool
	 */
	private function order_payment_verified( WC_Order $order ): bool {
		if ( ! $order->needs_payment() ) {
			return true;
		}

		$invoice = reepay()->api( $this )->get_invoice_data( $order );

		if ( is_wp_error( $invoice ) ) {
			return false;
		}

		$expected = rp_prepare_amount( (float) $order->get_total(), $order->get_currency() );

		return (float) $invoice['settled_amount'] >= $expected || (float) $invoice['authorized_amount'] >= $expected;
	}

	/**
	 * Lock the order for this request, waiting for another request that holds the lock.
	 * If the lock is still held after the wait, or the lock table cannot be used, the event is processed without it.
	 *
	 * @param int $order_id order id to lock.
	 *
	 * @return bool True if the order may have been changed by another request and must be reloaded.
	 */
	private function lock_order( int $order_id ): bool {
		$lock     = new WebhookLock();
		$deadline = microtime( true ) + $this->lock_wait;
		$waited   = false;

		while ( true ) {
			$acquired = $lock->acquire( $order_id );

			if ( true === $acquired ) {
				$this->order_lock = $lock;

				// Another request may have finished between the order lookup and this lock.
				return true;
			}

			if ( null === $acquired ) {
				$this->log( sprintf( 'WebHook: Order lock table cannot be used, continuing without the lock for order %d', $order_id ) );

				break;
			}

			$waited = true;

			if ( microtime( true ) >= $deadline ) {
				$this->log( sprintf( 'WebHook: Order %d is still locked by another request, continuing without the lock', $order_id ) );

				break;
			}

			usleep( 100000 );
		}

		return $waited;
	}

	/**
	 * Lock the order and load it again, so the checks that follow see what a request that finished
	 * before this one wrote.
	 *
	 * @param WC_Order $order order found by the webhook.
	 *
	 * @return WC_Order|null Null if the order cannot be loaded any more.
	 */
	private function lock_and_reload_order( WC_Order $order ): ?WC_Order {
		if ( ! $this->lock_order( $order->get_id() ) ) {
			return $order;
		}

		$reloaded = $this->reload_order( $order->get_id() );

		return $reloaded instanceof WC_Order ? $reloaded : null;
	}

	/**
	 * Load the order again from the database. After waiting for a lock the order was changed by another
	 * request, so the copies of it cached in this request must not be used.
	 *
	 * @param int $order_id order id.
	 *
	 * @return WC_Order|false
	 */
	private function reload_order( int $order_id ) {
		clean_post_cache( $order_id );
		wp_cache_delete( $order_id, 'post_meta' );
		wp_cache_delete( WC_Order::generate_meta_cache_key( $order_id, 'orders' ), 'orders' );

		if ( class_exists( OrderCache::class ) ) {
			wc_get_container()->get( OrderCache::class )->remove( $order_id );
		}

		return wc_get_order( $order_id );
	}

	/**
	 * Release the lock held by this request, if any.
	 *
	 * @return void
	 */
	private function unlock_order() {
		if ( null === $this->order_lock ) {
			return;
		}

		$this->order_lock->release();
		$this->order_lock = null;
	}
}
