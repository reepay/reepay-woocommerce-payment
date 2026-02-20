<?php
/**
 * Main reepay gateway class
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

use Exception;
use Reepay\Checkout\Api;
use Reepay\Checkout\Integrations\PWGiftCardsIntegration;
use Reepay\Checkout\Integrations\WCGiftCardsIntegration;
use Reepay\Checkout\Integrations\WPCProductBundlesWooCommerceIntegration;
use Reepay\Checkout\Integrations\PolylangIntegration;
use SitePress;
use Reepay\Checkout\Utils\LoggingTrait;
use Reepay\Checkout\Utils\MetaField;
use Reepay\Checkout\Tokens\TokenReepay;
use Reepay\Checkout\Tokens\ReepayTokens;
use WC_Admin_Settings;
use WC_Countries;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Payment_Gateway;
use WC_Rate_Limiter;
use Reepay\Checkout\OrderFlow\InstantSettle;
use Reepay\Checkout\OrderFlow\OrderCapture;
use WC_Reepay_Renewals;
use WP_Error;

defined( 'ABSPATH' ) || exit();

/**
 * Class ReepayGateway
 *
 * @package Reepay\Checkout\Gateways
 */
abstract class ReepayGateway extends WC_Payment_Gateway {
	use LoggingTrait;

	const METHOD_WINDOW = 'WINDOW';

	const METHOD_OVERLAY = 'OVERLAY';

	/**
	 * Test mode
	 *
	 * @var string
	 */
	public string $test_mode = 'yes';

	/**
	 * Private key
	 *
	 * @var string
	 */
	public string $private_key = '';

	/**
	 * Test private key
	 *
	 * @var string
	 */
	public string $private_key_test = '';

	/**
	 * Public key
	 *
	 * @var string
	 */

	public string $public_key = '';

	/**
	 * Settle options
	 *
	 * @var string[]
	 */
	public array $settle = array(
		InstantSettle::SETTLE_VIRTUAL,
		InstantSettle::SETTLE_PHYSICAL,
		InstantSettle::SETTLE_RECURRING,
		InstantSettle::SETTLE_FEE,
	);

	/**
	 * Debug enabled
	 *
	 * @var string
	 */
	public string $debug = 'yes';

	/**
	 * Reepay checkout language
	 *
	 * @var string
	 */
	public string $language = '';

	/**
	 * Available payment logos
	 *
	 * @var array
	 */
	public array $logos = array();

	/**
	 * Current payment type
	 *
	 * @var string
	 */
	public string $payment_type = 'OVERLAY';

	/**
	 * Save card token
	 *
	 * @var string
	 */
	public string $save_cc = 'yes';

	/**
	 * Skip order lines to Reepay and use order totals instead
	 *
	 * @var string
	 */
	public string $skip_order_lines = 'no';

	/**
	 * If automatically cancel unpaid orders should be ignored
	 *
	 * @var string
	 */
	public string $enable_order_autocancel = 'no';

	/**
	 * Email address for notification about failed webhooks
	 *
	 * @var string
	 */
	public string $failed_webhooks_email = '';

	/**
	 * Order handle failover.
	 *
	 * @var string
	 */
	public string $handle_failover = 'yes';

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array();

	/**
	 * Logging source
	 *
	 * @var string
	 */
	private string $logging_source;

	/**
	 * Currencies for which payment method is not displayed
	 * (If $supported_currencies is empty)
	 *
	 * @var string[]
	 */
	protected array $unsupported_currencies = array();

	/**
	 * Show payment method only for these currencies
	 *
	 * @var string[]
	 */
	protected array $supported_currencies = array();

	/**
	 * ReepayGateway constructor.
	 */
	public function __construct() {
		$this->logging_source = $this->id;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->enabled     = $this->settings['enabled'] ?? 'no';
		$this->title       = $this->settings['title'] ?? 'no';
		$this->description = $this->settings['description'] ?? 'no';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Add payment method via account screen.
	 */
	public function add_payment_method() {
		$user            = get_userdata( get_current_user_id() );
		$customer_handle = '';

		// Allow to pay exist orders by guests.
		if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
			$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				// Get customer handle by order.
				$gateway         = rp_get_payment_method( $order );
				$customer_handle = reepay()->api( $gateway )->get_customer_handle( $order );
			}
		} else {
			$customer_handle = rp_get_customer_handle( $user->ID );
		}

		$accept_url = add_query_arg( 'action', 'reepay_card_store_' . $this->id, admin_url( 'admin-ajax.php' ) );
		$accept_url = apply_filters( 'woocommerce_reepay_payment_accept_url', $accept_url );
		$cancel_url = wc_get_account_endpoint_url( 'payment-methods' );
		$cancel_url = apply_filters( 'woocommerce_reepay_payment_cancel_url', $cancel_url );

		$location = wc_get_base_location();

		if ( empty( $customer_handle ) ) {
			// Create reepay customer.
			$params = array(
				'locale'          => $this->get_language(),
				'button_text'     => __( 'Add card', 'reepay-checkout-gateway' ),
				'create_customer' => array(
					'test'        => 'yes' === $this->test_mode,
					'handle'      => $customer_handle,
					'email'       => $user->user_email,
					'address'     => '',
					'address2'    => '',
					'city'        => '',
					'country'     => $location['country'],
					'phone'       => '',
					'company'     => '',
					'vat'         => '',
					'first_name'  => $user->first_name,
					'last_name'   => $user->last_name,
					'postal_code' => '',
				),
				'accept_url'      => $accept_url,
				'cancel_url'      => $cancel_url,
			);
		} else {
			// Use existing user.
			$params = array(
				'locale'      => $this->get_language(),
				'button_text' => __( 'Add card', 'reepay-checkout-gateway' ),
				'customer'    => $customer_handle,
				'accept_url'  => $accept_url,
				'cancel_url'  => $cancel_url,
			);
		}

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		$result = reepay()->api( $this )->request( 'POST', 'https://checkout-api.reepay.com/v1/session/recurring', $params );
		if ( is_wp_error( $result ) ) {
			if ( $result->get_error_code() === Api::ERROR_CODES['Customer has been deleted'] ||
				$result->get_error_code() === Api::ERROR_CODES['Customer not found'] ) {
				$params = array(
					'locale'          => $this->get_language(),
					'button_text'     => __( 'Add card', 'reepay-checkout-gateway' ),
					'create_customer' => array(
						'test'        => 'yes' === $this->test_mode,
						'handle'      => $customer_handle,
						'email'       => $user->user_email,
						'address'     => '',
						'address2'    => '',
						'city'        => '',
						'country'     => $location['country'],
						'phone'       => '',
						'company'     => '',
						'vat'         => '',
						'first_name'  => $user->first_name,
						'last_name'   => $user->last_name,
						'postal_code' => '',
					),
					'accept_url'      => $accept_url,
					'cancel_url'      => $cancel_url,
				);

				$result = reepay()->api( $this )->request( 'POST', 'https://checkout-api.reepay.com/v1/session/recurring', $params );

				if ( is_wp_error( $result ) ) {
					wc_add_notice( $result->get_error_message(), 'error' );
					parent::add_payment_method();
				}
			} else {
				wc_add_notice( $result->get_error_message(), 'error' );
				parent::add_payment_method();
			}
		}

		$this->log(
			array(
				'source' => 'add_payment_method',
				'result' => $result,
			)
		);

		wp_redirect( $result['url'] );
		exit();
	}

	/**
	 * Ajax: Add Payment Method
	 */
	public function reepay_card_store() {
		$reepay_token    = isset( $_GET['payment_method'] ) ? wc_clean( $_GET['payment_method'] ) : '';
		$current_user_id = get_current_user_id();

		// Rate limiting to prevent rapid duplicate submissions.
		$rate_limit_id = 'reepay_add_payment_method_' . $current_user_id . '_' . md5( $reepay_token );
		$delay         = 10; // 10 seconds delay between same token additions.

		if ( WC_Rate_Limiter::retried_too_soon( $rate_limit_id ) ) {
			wc_add_notice( __( 'Please wait before adding the same payment method again.', 'reepay-checkout-gateway' ), 'error' );
			wp_redirect( wc_get_account_endpoint_url( 'add-payment-method' ) );
			exit();
		}

		// Set rate limit.
		WC_Rate_Limiter::set_rate_limit( $rate_limit_id, $delay );

		try {
			$token = ReepayTokens::add_payment_token_to_customer( $current_user_id, $reepay_token )['token'];

			// Check if token was actually created (not returned from existing).
			if ( ! $token || ! $token->get_id() ) {
				throw new Exception( __( 'Failed to create payment token.', 'reepay-checkout-gateway' ) );
			}
		} catch ( Exception $e ) {
			wc_add_notice( __( 'There was a problem adding the card.', 'reepay-checkout-gateway' ), 'error' );
			wp_redirect( wc_get_account_endpoint_url( 'add-payment-method' ) );
			exit();
		}

		do_action( 'woocommerce_reepay_payment_method_added', $token );

		wc_add_notice( __( 'Payment method successfully added.', 'reepay-checkout-gateway' ) );
		wp_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}

	/**
	 * Check if payment method activated in reepay
	 *
	 * @return bool
	 */
	public function check_is_active(): bool {
		$gateways_reepay = get_transient( 'gateways_reepay' );
		if ( empty( $gateways_reepay ) ) {
			$gateways_reepay = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/agreement?only_active=true' );
			set_transient( 'gateways_reepay', $gateways_reepay, 5 );
		}

		$current_name = str_replace( 'reepay_', '', $this->id );

		if ( ! empty( $gateways_reepay ) && ! is_wp_error( $gateways_reepay ) ) {
			foreach ( $gateways_reepay as $app ) {
				if ( stripos( $app['type'], $current_name ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if current page is gateway settings page
	 *
	 * @return bool
	 */
	public function is_gateway_settings_page(): bool {
		return isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] &&
				! empty( $_GET['section'] ) && $_GET['section'] === $this->id;
	}

	/**
	 * Get reepay account info
	 *
	 * @param bool $is_test use test or live reepay api keys.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function get_account_info( $is_test = false ) {
		if ( $this->is_gateway_settings_page() ) {
			$key   = 'account_info';
			$force = true;

			if ( $is_test ) {
				$key   = 'account_info_test';
				$force = false;
			}

			$account_info = get_transient( $key );
			if ( empty( $account_info ) ) {
				$account_info = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/account', array(), $force );
				set_transient( $key, $account_info, 5 );
			}

			return $account_info;
		}

		return array();
	}


	/**
	 * Return site webhook handler url
	 *
	 * @return string
	 */
	public static function get_webhook_url(): string {
		$default_wc_api_url = WC()->api_request_url( '' );

		if ( class_exists( SitePress::class ) && ! is_multisite() ) {
			$languages = apply_filters( 'wpml_active_languages', null, 'orderby=id&order=desc' );

			$languages = wp_list_pluck( $languages, 'default_locale' );

			if ( ! empty( $languages ) ) {
				foreach ( $languages as $available_language ) {
					$lang = explode( '_', $available_language );
					if ( stripos( $default_wc_api_url, '/' . $lang[0] . '/' ) !== false ) {
						$default_wc_api_url = str_replace( '/' . $lang[0] . '/', '/', $default_wc_api_url );
					}
				}
			}
		}

		$structure = get_option( 'permalink_structure' );
		if ( empty( $structure ) ) {
			$default_wc_api_url = $default_wc_api_url . '=WC_Gateway_Reepay/';
		} else {
			$default_wc_api_url = $default_wc_api_url . 'WC_Gateway_Reepay/';
		}

		return $default_wc_api_url;
	}

	/**
	 * Check if webhook configured.
	 * Valid keys entered and webhook url registered in reepay
	 *
	 * @return bool
	 *
	 * @throws Exception Never, just for phpcs.
	 */
	public function is_webhook_configured(): bool {
		try {
			$response = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/account/webhook_settings' );
			if ( is_wp_error( $response ) ) {
				if ( ! empty( $response->get_error_code() ) ) {
					throw new Exception( $response->get_error_message(), intval( $response->get_error_code() ) );
				}
			}

			$webhook_url = self::get_webhook_url();

			$alert_emails = $response['alert_emails'];

			// The webhook settings of the payment plugin.
			$alert_email = '';
			if ( ! empty( $this->settings['failed_webhooks_email'] ) &&
				is_email( $this->settings['failed_webhooks_email'] )
			) {
				$alert_email = $this->settings['failed_webhooks_email'];
			}

			$exist_waste_urls = false;

			$urls = array();

			foreach ( $response['urls'] as $url ) {
				if ( ( strpos( $url, $webhook_url ) === false || // either another site or exact url match.
					$url === $webhook_url ) &&
					strpos( $url, 'WC_Gateway_Reepay_Checkout' ) === false ) {
					$urls[] = $url;
				} else {
					$exist_waste_urls = true;
				}
			}

			// Verify the webhook settings.
			if ( ! $exist_waste_urls &&
				in_array( $webhook_url, $urls, true )
				&& ( empty( $alert_email ) || in_array( $alert_email, $alert_emails, true ) )
			) {
				return true;
			}

			// Update the webhook settings.
			if ( ! in_array( $webhook_url, $urls, true ) ) {
				$urls[] = $webhook_url;
			}

			if ( ! empty( $alert_email ) && is_email( $alert_email ) ) {
				$alert_emails[] = $alert_email;
			}

			$data = array(
				'urls'         => array_unique( $urls ),
				'disabled'     => false,
				'alert_emails' => array_unique( $alert_emails ),
			);

			$response = reepay()->api( $this )->request( 'PUT', 'https://api.reepay.com/v1/account/webhook_settings', $data );
			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message(), $response->get_error_code() );
			}

			$this->log(
				array(
					'source' => 'WebHook has been successfully created/updated',
					$response,
				)
			);

			WC_Admin_Settings::add_message( __( 'Frisbii Pay: WebHook has been successfully created/updated', 'reepay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$this->log(
				array(
					'source' => 'WebHook creation/update has been failed',
					'error'  => $e->getMessage(),
				)
			);

			WC_Admin_Settings::add_error( __( 'Unable to retrieve the webhook settings. Wrong api credentials?', 'reepay-checkout-gateway' ) );

			return false;
		}

		return true;
	}

	/**
	 * Redirect to the gateway settings page after enabling the gateway if it needs to be configured.
	 *
	 * @return bool
	 * @see WC_AJAX::toggle_gateway_enabled
	 */
	public function needs_setup(): bool {
		return doing_action( 'wp_ajax_woocommerce_toggle_gateway_enabled' ) && ! $this->check_is_active();
	}

	/**
	 * Generate Gateway Status HTML.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 *
	 * @return string
	 * @see WC_Settings_API::generate_settings_html
	 */
	public function generate_gateway_status_html( string $key, array $data ): string {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'       => '',
			'type'        => 'webhook_status',
			'desc_tip'    => false,
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$is_active     = $this->check_is_active();
		$active_text   = $is_active ? esc_html__( 'Active', 'reepay-checkout-gateway' ) : esc_html__( 'Inactive', 'reepay-checkout-gateway' );
		$active_color  = $is_active ? 'green' : 'red';
		$esc_field_key = esc_attr( $field_key );
		$esc_is_active = esc_attr( $is_active );
		$title         = wp_kses_post( $data['title'] );
		$tooltip_html  = $this->get_tooltip_html( $data );
		$html_output   = <<<HTML
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="{$esc_field_key}">{$title}
						{$tooltip_html}
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>{$title}</span></legend>
			
						<span style="color: {$active_color};">
							{$active_text}
						</span>
			
						<input type="hidden" name="{$esc_field_key}"
							   id="{$esc_field_key}"
							   value="{$esc_is_active}"/>
					</fieldset>
				</td>
			</tr>
		HTML;

		return $html_output;
	}

	/**
	 * Check if order can be captured
	 *
	 * @param mixed       $order  order to check.
	 * @param float|false $amount amount to capture.
	 *
	 * @return bool
	 */
	public function can_capture( $order, $amount = false ): bool {
		$order = wc_get_order( $order );
		return reepay()->api( $this )->can_capture( $order, $amount );
	}

	/**
	 * Check if order can be cancelled
	 *
	 * @param mixed $order order to check.
	 *
	 * @return bool
	 */
	public function can_cancel( $order ): bool {
		$order = wc_get_order( $order );
		return reepay()->api( $this )->can_cancel( $order );
	}

	/**
	 * Check if order can be refunded
	 *
	 * @param mixed $order order to check.
	 *
	 * @return bool
	 */
	public function can_refund( $order ): bool {
		$order = wc_get_order( $order );
		return reepay()->api( $this )->can_refund( $order );
	}

	/**
	 * Capture order payment
	 *
	 * @param mixed      $order  order to capture.
	 * @param float|null $amount amount to capture. Null to capture order total.
	 *
	 * @return void
	 * @throws Exception If capture error.
	 */
	public function capture_payment( $order, $amount = null ) {
		$order = wc_get_order( $order );

		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( esc_html__( 'Order is canceled', 'reepay-checkout-gateway' ) );
		}

		$result = reepay()->api( $this )->capture_payment( $order, $amount );

		if ( is_wp_error( $result ) ) {
			throw new Exception( esc_html( $result->get_error_message() ) );
		}
	}

	/**
	 * Cancel order payment
	 *
	 * @param mixed $order order to cancel.
	 *
	 * @throws Exception If cancellation error.
	 */
	public function cancel_payment( $order ) {
		$order = wc_get_order( $order );

		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( esc_html__( 'Order is already canceled', 'reepay-checkout-gateway' ) );
		}

		$result = reepay()->api( $this )->cancel_payment( $order );

		if ( is_wp_error( $result ) ) {
			throw new Exception( esc_html( $result->get_error_message() ) );
		}
	}

	/**
	 * Refund order payment
	 *
	 * @param WC_Order|int $order  order to refund.
	 * @param float|null   $amount amount to refund. Null to refund total amount.
	 * @param string       $reason refund reason.
	 *
	 * @return void
	 * @throws Exception If already refunded or refund error.
	 */
	public function refund_payment( $order, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order );

		if ( '1' === $order->get_meta( '_reepay_order_cancelled' ) ) {
			throw new Exception( esc_html__( 'Order is already canceled', 'reepay-checkout-gateway' ) );
		}

		if ( ! $this->can_refund( $order ) ) {
			throw new Exception( esc_html__( 'Payment can\'t be refunded.', 'reepay-checkout-gateway' ) );
		}

		if ( ! is_null( $amount ) && $amount <= 0 ) {
			throw new Exception( esc_html__( 'Refund amount must be greater than 0.', 'reepay-checkout-gateway' ) );
		}

		$result = reepay()->api( $this )->refund( $order, $amount, $reason );

		if ( is_wp_error( $result ) ) {
			throw new Exception( esc_html( $result->get_error_message() ) );
		}
	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		$logos = array_map(
			function ( $logo ) {
				$logo_url = $this->get_logo( $logo );

				return array(
					'src' => $logo_url,
					// translators: %s gateway title.
					'alt' => esc_attr( sprintf( __( 'Pay with %s on Frisbii Pay', 'reepay-checkout-gateway' ), $this->get_title() ) ),
				);
			},
			array_filter( (array) $this->logos, 'strlen' )
		);

		$html = reepay()->get_template(
			'checkout/gateway-logos.php',
			array(
				'logos' => $logos,
			),
			true
		);

		return apply_filters( 'woocommerce_gateway_icon', $html, $this->id );
	}

	/**
	 * If There are no payment fields show the description if set.
	 *
	 * @return void
	 */
	public function payment_fields() {
		$description = $this->get_description();

		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array|false
	 * @throws Exception If payment error.
	 */
	public function process_payment( $order_id ) {
		// VALIDATION: Check guest checkout settings (BWPM-178, BWPM-184)
		// Prevent guest users from checking out when guest checkout is disabled
		// BUT allow account creation during checkout if registration is enabled.
		if ( ! is_user_logged_in() &&
			WC()->checkout()->is_registration_required() &&
			! WC()->checkout()->is_registration_enabled() ) {

			$this->log(
				array(
					'source'   => 'process_payment_guest_checkout_blocked',
					'order_id' => $order_id,
					'message'  => 'Guest checkout is disabled, registration is disabled, and user is not logged in',
				)
			);

			wc_add_notice(
				sprintf(
					/* translators: 1: login URL, 2: register URL */
					__( 'You must be logged in to checkout. Please <a href="%1$s" class="showlogin">log in</a> or <a href="%2$s">create an account</a> to continue.', 'reepay-checkout-gateway' ),
					esc_url( wc_get_page_permalink( 'myaccount' ) ),
					esc_url( wc_get_account_endpoint_url( 'register' ) )
				),
				'error'
			);

			return false;
		}

		$is_woo_blocks_checkout_request = false;

		if ( isset( $_SERVER['CONTENT_TYPE'] ) && 'application/json' === $_SERVER['CONTENT_TYPE'] ) {
			$is_woo_blocks_checkout_request = true;

			$this->log(
				array(
					'source'       => 'process_payment_woo_blocks_detected',
					'order_id'     => $order_id,
					'content_type' => $_SERVER['CONTENT_TYPE'],
				)
			);

			try {
				$_POST = json_decode( file_get_contents( 'php://input' ), true );
				foreach ( $_POST['payment_data'] ?? array() as $data ) {
					if ( empty( $_POST[ $data['key'] ] ) ) {
						$_POST[ $data['key'] ] = $data['value'];
					}
				}
			} catch ( Exception $e ) {
				wc_add_notice( __( 'Wrong Request. Try again', 'reepay-checkout-gateway' ), 'error' );
				return false;
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->log(
				array(
					'source'   => 'process_payment_order_not_found',
					'order_id' => $order_id,
				)
			);
			return false;
		}

		$this->log(
			array(
				'source'         => 'process_payment_order_loaded',
				'order_id'       => $order_id,
				'order_status'   => $order->get_status(),
				'order_total'    => $order->get_total(),
				'order_currency' => $order->get_currency(),
				'customer_id'    => $order->get_customer_id(),
			)
		);

		if ( $is_woo_blocks_checkout_request ) {
			/**
			 * Fix for zero total amount in woo blocks checkout integration
			 */
			$order->calculate_totals();

			$this->log(
				array(
					'source'    => 'process_payment_woo_blocks_totals_calculated',
					'order_id'  => $order_id,
					'new_total' => $order->get_total(),
				)
			);
		}

		$token_id = isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ? wc_clean( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) : 'new';

		$this->log(
			array(
				'source'          => 'process_payment_token_detection',
				'order_id'        => $order_id,
				'token_id'        => $token_id,
				'save_cc_setting' => $this->save_cc,
			)
		);

		if ( 'yes' === $this->save_cc
			&& 'new' === $token_id
			&& isset( $_POST[ 'wc-' . $this->id . '-new-payment-method' ] )
			&& false !== $_POST[ 'wc-' . $this->id . '-new-payment-method' ]
		) {
			$maybe_save_card = 'true' === $_POST[ 'wc-' . $this->id . '-new-payment-method' ];
		} else {
			$maybe_save_card = wcs_cart_have_subscription();
		}

		$this->log(
			array(
				'source'           => 'process_payment_save_card_decision',
				'order_id'         => $order_id,
				'maybe_save_card'  => $maybe_save_card,
				'has_subscription' => wcs_cart_have_subscription(),
			)
		);

		$country = WC()->countries->country_exists( $order->get_billing_country() ) ? $order->get_billing_country() : '';
		if ( $order->needs_shipping_address() ) {
			$country = WC()->countries->country_exists( $order->get_shipping_country() ) ? $order->get_shipping_country() : '';
		}

		if ( wcs_is_payment_change() ) {
			$this->log(
				array(
					'source'   => 'process_payment_subscription_payment_change',
					'order_id' => $order_id,
					'token_id' => $token_id,
				)
			);
			return $this->wcs_change_payment_method( $order, $token_id );
		}

		$customer_handle = reepay()->api( $this )->get_customer_handle_by_order( $order_id );

		$this->log(
			array(
				'source'          => 'process_payment_customer_handle',
				'order_id'        => $order_id,
				'customer_handle' => $customer_handle,
			)
		);

		$data = array(
			'country'         => $country,
			'customer_handle' => $customer_handle,
			'test_mode'       => $this->test_mode,
			'return_url'      => $this->get_return_url( $order ),
			'language'        => $this->get_language(),
		);

		$order_handle = rp_get_order_handle( $order );

		$this->log(
			array(
				'source'       => 'process_payment_order_handle',
				'order_id'     => $order_id,
				'order_handle' => $order_handle,
				'data'         => $data,
			)
		);

		// Initialize Payment.
		$params = array(
			'locale'     => $this->get_language(),
			'recurring'  => apply_filters(
				'order_contains_reepay_subscription',
				$maybe_save_card || order_contains_subscription( $order ),
				$order
			),
			'order'      => array(
				'handle'          => $order_handle,
				'amount'          => 'yes' === $this->skip_order_lines ? $this->get_skip_order_lines_amount( $order ) : null,
				'order_lines'     => 'no' === $this->skip_order_lines ? $this->get_order_items( $order ) : null,
				'currency'        => $order->get_currency(),
				'customer'        => array(
					'test'        => 'yes' === $this->test_mode,
					'handle'      => $customer_handle,
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
				'billing_address' => array(
					'attention'         => '',
					'email'             => $order->get_billing_email(),
					'address'           => $order->get_billing_address_1(),
					'address2'          => $order->get_billing_address_2(),
					'city'              => $order->get_billing_city(),
					'phone'             => $order->get_billing_phone(),
					'company'           => $order->get_billing_company(),
					'vat'               => '',
					'first_name'        => $order->get_billing_first_name(),
					'last_name'         => $order->get_billing_last_name(),
					'postal_code'       => $order->get_billing_postcode(),
					'state_or_province' => $order->get_billing_state(),
				),
			),
			'accept_url' => $this->get_return_url( $order ),
			'cancel_url' => $order->get_cancel_order_url(),
		);

		if ( $params['recurring'] ) {
			$params['button_text'] = reepay()->get_setting( 'payment_button_text' );
		}

		if ( ! empty( $country ) ) {
			$params['order']['customer']['country']        = $country;
			$params['order']['billing_address']['country'] = $country;
		}

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		if ( $order->needs_shipping_address() ) {
			$params['order']['shipping_address'] = array(
				'attention'         => '',
				'email'             => $order->get_billing_email(),
				'address'           => $order->get_shipping_address_1(),
				'address2'          => $order->get_shipping_address_2(),
				'city'              => $order->get_shipping_city(),
				'phone'             => $order->get_billing_phone(),
				'company'           => $order->get_shipping_company(),
				'vat'               => '',
				'first_name'        => $order->get_shipping_first_name(),
				'last_name'         => $order->get_shipping_last_name(),
				'postal_code'       => $order->get_shipping_postcode(),
				'state_or_province' => $order->get_shipping_state(),
			);

			if ( ! empty( $country ) ) {
				$params['order']['shipping_address']['country'] = $country;
			}
		}

		if ( 'reepay_mobilepay_subscriptions' === $order->get_payment_method() ) {
			$params['parameters']['mps_ttl'] = 'PT24H';
		}

		// Add age verification data if needed.
		$this->add_age_verification_to_charge_session( $params, $order );

		// Try to charge with saved token.
		if ( absint( $token_id ) > 0 ) {
			$this->log(
				array(
					'source'   => 'process_payment_using_saved_token',
					'order_id' => $order_id,
					'token_id' => $token_id,
				)
			);

			$token = new TokenReepay( $token_id );
			if ( ! $token->get_id() ) {
				$this->log(
					array(
						'source'   => 'process_payment_token_load_failed',
						'order_id' => $order_id,
						'token_id' => $token_id,
					)
				);
				wc_add_notice( __( 'Failed to load token.', 'reepay-checkout-gateway' ), 'error' );
				return false;
			}

			if ( $token->get_user_id() !== $order->get_user_id() ) {
				$this->log(
					array(
						'source'        => 'process_payment_token_access_denied',
						'order_id'      => $order_id,
						'token_id'      => $token_id,
						'token_user_id' => $token->get_user_id(),
						'order_user_id' => $order->get_user_id(),
					)
				);
				wc_add_notice( __( 'Access denied.', 'reepay-checkout-gateway' ), 'error' );
				return false;
			}

			$this->log(
				array(
					'source'        => 'process_payment_token_validated',
					'order_id'      => $order_id,
					'token_id'      => $token_id,
					'token_gateway' => $token->get_gateway_id(),
					'token_last4'   => $token->get_last4(),
				)
			);

			$params['card_on_file']                  = $token->get_token();
			$params['card_on_file_require_cvv']      = false;
			$params['card_on_file_require_exp_date'] = false;
			unset( $params['recurring'] );

			// Don't charge payment if zero amount.
			if ( abs( $order->get_total() ) < 0.01 ) {
				if ( wcs_cart_only_subscriptions() ) {
					$this->log(
						array(
							'source'   => 'process_payment_zero_amount_subscription_setup',
							'order_id' => $order_id,
							'token'    => $token->get_token(),
						)
					);

					$result = reepay()->api( $this )->recurring( $this->payment_methods, $order, $data, $token->get_token(), $params['button_text'] );

					if ( is_wp_error( $result ) ) {
						$this->log(
							array(
								'source'     => 'process_payment_zero_amount_subscription_error',
								'order_id'   => $order_id,
								'error'      => $result->get_error_message(),
								'error_code' => $result->get_error_code(),
							)
						);
						throw new Exception( esc_html( $result->get_error_message() ), esc_html( $result->get_error_code() ) );
					}

					if ( ! empty( $result['id'] ) ) {
						$order->update_meta_data( 'reepay_session_id', $result['id'] );
						$order->save_meta_data();
					}

					if ( is_wp_error( $result ) ) {
						throw new Exception( esc_html( $result->get_error_message() ), esc_html( $result->get_error_code() ) );
					}

					try {
						ReepayTokens::assign_payment_token( $order, $token );
						ReepayTokens::save_card_info_to_order( $order, $token->get_token() );

						$this->log(
							array(
								'source'   => 'process_payment_zero_amount_token_assigned',
								'order_id' => $order_id,
								'token'    => $token->get_token(),
							)
						);
					} catch ( Exception $e ) {
						$this->log(
							array(
								'source'   => 'process_payment_zero_amount_token_assignment_error',
								'order_id' => $order_id,
								'error'    => $e->getMessage(),
							)
						);

						$order->add_order_note( $e->getMessage() );
						wc_add_notice( $e->getMessage(), 'error' );
						return false;
					}
				}
				$order->payment_complete();
			} else {
				try {
					ReepayTokens::assign_payment_token( $order, $token );
					// Use legacy method for zero amount orders (no invoice created yet).
					ReepayTokens::save_card_info_to_order( $order, $token->get_token() );
					$this->log(
						array(
							'source'   => 'process_payment_token_assigned_successfully',
							'order_id' => $order_id,
							'token'    => $token->get_token(),
						)
					);
				} catch ( Exception $e ) {
					$this->log(
						array(
							'source'   => 'process_payment_token_assignment_failed',
							'order_id' => $order_id,
							'error'    => $e->getMessage(),
						)
					);

					$order->add_order_note( $e->getMessage() );
					wc_add_notice( $e->getMessage(), 'error' );
					return false;
				}

				if ( wcr_cart_only_reepay_subscriptions() ) {
					$method = reepay()->api( $this )->request( 'GET', 'https://api.reepay.com/v1/payment_method/' . $token->get_token() );
					if ( is_wp_error( $method ) ) {
						wc_add_notice( $method->get_error_message(), 'error' );
						return false;
					} elseif ( ! empty( $method ) ) {
						if ( 'active' !== $method['state'] ) {
							wc_add_notice( __( 'You payment method has failed, please choose another or add new', 'reepay-checkout-gateway' ), 'error' );
							return false;
						}

						$data = array(
							'payment_method' => $method['id'],
							'customer'       => $method['customer'],
						);

						// Check PW Gift cards.
						$exist_gift_card = PWGiftCardsIntegration::check_exist_gift_cards_in_order( $order );
						if ( $exist_gift_card ) {
							wc_add_notice( __( 'Gift Cards cannot be used with Reepay subscriptions.', 'reepay-checkout-gateway' ), 'error' );
							return false;
						}

						do_action( 'reepay_create_subscription', $data, $order );

						try {
							foreach ( $order->get_meta( '_reepay_another_orders' ) ?: array() as $order_id ) {
								ReepayTokens::save_card_info_to_order( wc_get_order( $order_id ), $token->get_token() );
							}
						} catch ( Exception $e ) {
							wc_get_order( $order_id )->add_order_note( $e->getMessage() );
						}
					}
				} elseif ( wcs_cart_have_subscription() && $order->get_payment_method() !== 'reepay_vipps_recurring' ) {
					$this->log(
						array(
							'source'         => 'process_payment_subscription_session_charge',
							'order_id'       => $order_id,
							'payment_method' => $order->get_payment_method(),
						)
					);
					return $this->process_session_charge( $params, $order );
				} else {
					$order_lines = 'no' === $this->skip_order_lines ? $this->get_order_items( $order ) : null;
					$amount      = 'yes' === $this->skip_order_lines ? $this->get_skip_order_lines_amount( $order, true ) : null;

					$this->log(
						array(
							'source'            => 'process_payment_direct_charge',
							'order_id'          => $order_id,
							'amount'            => $amount,
							'skip_order_lines'  => $this->skip_order_lines,
							'order_lines_count' => is_array( $order_lines ) ? count( $order_lines ) : 0,
						)
					);

					// Charge payment.
					$result = reepay()->api( $this )->charge( $order, $token->get_token(), $amount, $order_lines );

					if ( is_wp_error( $result ) ) {
						$this->log(
							array(
								'source'     => 'process_payment_direct_charge_failed',
								'order_id'   => $order_id,
								'error'      => $result->get_error_message(),
								'error_code' => $result->get_error_code(),
							)
						);

						wc_add_notice( $result->get_error_message(), 'error' );
						return false;
					}

					$this->log(
						array(
							'source'   => 'process_payment_direct_charge_success',
							'order_id' => $order_id,
							'result'   => is_array( $result ) ? array_keys( $result ) : 'non-array-result',
						)
					);

					// Save card information from invoice (after direct charge creates invoice).
					try {
						ReepayTokens::save_card_info_from_invoice( $order );
						$this->log(
							array(
								'source'   => 'process_payment_saved_card_info_from_invoice',
								'order_id' => $order_id,
								'token'    => $token->get_token(),
							)
						);
					} catch ( Exception $e ) {
						// Log error but don't fail the payment process.
						$this->log(
							array(
								'source'   => 'process_payment_saved_card_info_failed',
								'order_id' => $order_id,
								'error'    => $e->getMessage(),
							)
						);
					}
				}

				do_action( 'reepay_instant_settle', $order );
			}

			$this->log(
				array(
					'source'       => 'process_payment_token_success',
					'order_id'     => $order_id,
					'redirect_url' => $this->get_return_url( $order ),
				)
			);

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		// "Save Card" flag
		$order->update_meta_data( '_reepay_maybe_save_card', $maybe_save_card );
		$order->save_meta_data();

		if ( ! empty( $customer_handle ) && 0 === $order->get_customer_id() ) {
			$this->log(
				array(
					'source'          => 'process_payment_updating_customer',
					'order_id'        => $order_id,
					'customer_handle' => $customer_handle,
				)
			);

			reepay()->api( $this )->request(
				'PUT',
				'https://api.reepay.com/v1/customer/' . $customer_handle,
				$params['order']['customer']
			);
		}

		$have_sub = ( class_exists( WC_Reepay_Renewals::class ) && WC_Reepay_Renewals::is_order_contain_subscription( $order ) ) || wcs_cart_have_subscription();

		$only_items_lines = array();

		foreach ( $order->get_items() as $order_item ) {
			/**
			 * WC_Order_Item_Product returns not WC_Order_Item
			 *
			 * @var WC_Order_Item_Product $order_item
			 */

			if ( ! $order_item->get_product() || ! wcr_is_subscription_product( $order_item->get_product() ) ) {
				$only_items_lines[] = $order_item;
			}
		}

		$this->log(
			array(
				'source'                 => 'process_payment_subscription_analysis',
				'order_id'               => $order_id,
				'has_subscription'       => $have_sub,
				'order_total'            => $order->get_total(),
				'only_items_lines_count' => count( $only_items_lines ),
				'total_items_count'      => count( $order->get_items() ),
			)
		);

		// If here's Subscription or zero payment.
		if ( ( $have_sub ) && ( abs( $order->get_total() ) < 0.01 || empty( $only_items_lines ) ) ) {

			$this->log(
				array(
					'source'          => 'process_payment_subscription_recurring_setup',
					'order_id'        => $order_id,
					'payment_methods' => $this->payment_methods,
					'button_text'     => $params['button_text'] ?? 'N/A',
				)
			);

			$result = reepay()->api( $this )->recurring( $this->payment_methods, $order, $data, false, $params['button_text'] );

			if ( is_wp_error( $result ) ) {
				$this->log(
					array(
						'source'     => 'process_payment_subscription_recurring_error',
						'order_id'   => $order_id,
						'error'      => $result->get_error_message(),
						'error_code' => $result->get_error_code(),
					)
				);
				throw new Exception( esc_html( $result->get_error_message() ), esc_html( $result->get_error_code() ) );
			}

			if ( ! empty( $result['id'] ) ) {
				$order->update_meta_data( 'reepay_session_id', $result['id'] );
				$order->save_meta_data();
			}

			do_action( 'reepay_instant_settle', $order );

			$redirect = '#!reepay-checkout';

			if ( ! empty( $result['url'] ) ) {
				$redirect = $result['url'];
			}

			$this->log(
				array(
					'source'     => 'process_payment_subscription_recurring_success',
					'order_id'   => $order_id,
					'session_id' => $result['id'] ?? 'N/A',
					'redirect'   => $redirect,
				)
			);

			return array(
				'result'             => 'success',
				'redirect'           => $redirect,
				'is_reepay_checkout' => true,
				'reepay'             => $result,
				'reepay_id'          => $result['id'],
				'accept_url'         => $this->get_return_url( $order ),
				'cancel_url'         => $order->get_cancel_order_url(),
			);
		}

		$this->log(
			array(
				'source'   => 'process_payment_fallback_to_session_charge',
				'order_id' => $order_id,
			)
		);

		return $this->process_session_charge( $params, $order );
	}

	/**
	 * Handle WooCommerce subscription changing or adding payment method.
	 *
	 * @param mixed  $order param to get order.
	 * @param string $token_id optional. Existed payment method to add.
	 *
	 * @return array|false
	 */
	public function wcs_change_payment_method( $order, $token_id = '' ) {
		$order = wc_get_order( $order );

		if ( empty( $order ) ) {
			return false;
		}

		$customer_handle = reepay()->api( $this )->get_customer_handle_by_order( $order->get_id() );

		if ( absint( $token_id ) > 0 ) {
			$token = new TokenReepay( $token_id );
			if ( ! $token->get_id() ) {
				wc_add_notice( __( 'Failed to load token.', 'reepay-checkout-gateway' ), 'error' );

				return false;
			}

			if ( $token->get_user_id() !== $order->get_user_id() ) {
				wc_add_notice( __( 'Access denied.', 'reepay-checkout-gateway' ), 'error' );

				return false;
			}

			try {
				ReepayTokens::assign_payment_token( $order, $token );
			} catch ( Exception $e ) {
				$order->add_order_note( $e->getMessage() );

				return array(
					'result'  => 'failure',
					'message' => $e->getMessage(),
				);
			}

			$order->add_order_note(
				sprintf(
				// translators: %s payment method name.
					__( 'Payment method changed to "%s"', 'reepay-checkout-gateway' ),
					$token->get_display_name()
				)
			);

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} else {
			// Add new Card.
			$params = array(
				'locale'          => $this->get_language(),
				'button_text'     => __( 'Add card', 'reepay-checkout-gateway' ),
				'create_customer' => array(
					'test'        => 'yes' === $this->test_mode,
					'handle'      => $customer_handle,
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
				'accept_url'      => add_query_arg(
					array(
						'action' => 'reepay_finalize',
						'key'    => $order->get_order_key(),
					),
					admin_url( 'admin-ajax.php' )
				),
				'cancel_url'      => $order->get_cancel_order_url(),
			);

			if ( ! empty( $country ) ) {
				$params['create_customer']['country'] = $country;
			}

			if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
				$params['payment_methods'] = $this->payment_methods;
			}

			$result = reepay()->api( $this )->request(
				'POST',
				'https://checkout-api.reepay.com/v1/session/recurring',
				$params
			);
			if ( is_wp_error( $result ) ) {
				return array(
					'result'  => 'failure',
					'message' => $result->get_error_message(),
				);
			}

			return array(
				'result'   => 'success',
				'redirect' => $result['url'],
			);
		}
	}

	/**
	 * Process session charge
	 *
	 * @param array    $params reepay api params.
	 * @param WC_Order $order  current order.
	 *
	 * @return array
	 * @throws Exception If reepay api error.
	 */
	public function process_session_charge( array $params, WC_Order $order ): array {
		$this->log(
			array(
				'source'          => 'process_session_charge_start',
				'order_id'        => $order->get_id(),
				'order_handle'    => $params['order']['handle'] ?? 'N/A',
				'payment_type'    => $this->payment_type,
				'handle_failover' => $this->handle_failover,
			)
		);

		$result = reepay()->api( $this )->request(
			'POST',
			'https://checkout-api.reepay.com/v1/session/charge',
			$params
		);

		if ( is_wp_error( $result ) ) {
			$this->log(
				array(
					'source'                  => 'process_session_charge_error',
					'order_id'                => $order->get_id(),
					'error'                   => $result->get_error_message(),
					'error_code'              => $result->get_error_code(),
					'handle_failover_enabled' => $this->handle_failover,
				)
			);

			if ( 'yes' === $this->handle_failover ) {
				// invoice with handle $params['order']['handle'] already exists and authorized/settled
				// try to create another invoice with unique handle in format order-<order_id>-<time>.
				if ( in_array(
					$result->get_error_code(),
					array(
						Api::ERROR_CODES['Invoice already authorized'],
						Api::ERROR_CODES['Invoice already settled'],
						Api::ERROR_CODES['Invoice already cancelled'],
						Api::ERROR_CODES['Customer cannot be changed on invoice'],
						Api::ERROR_CODES['Currency change not allowed'],
					),
					true
				) ) {
					$this->log(
						array(
							'source'          => 'process_session_charge_failover_attempt',
							'order_id'        => $order->get_id(),
							'original_handle' => $params['order']['handle'],
							'error_code'      => $result->get_error_code(),
						)
					);

					$handle                    = rp_get_order_handle( $order, true );
					$params['order']['handle'] = $handle;

					$order->update_meta_data( '_reepay_order', $handle );
					$order->save_meta_data();

					$this->log(
						array(
							'source'     => 'process_session_charge_failover_retry',
							'order_id'   => $order->get_id(),
							'new_handle' => $handle,
						)
					);

					$result = reepay()->api( $this )->request(
						'POST',
						'https://checkout-api.reepay.com/v1/session/charge',
						$params
					);
					if ( is_wp_error( $result ) ) {
						$this->log(
							array(
								'source'     => 'process_session_charge_failover_failed',
								'order_id'   => $order->get_id(),
								'error'      => $result->get_error_message(),
								'error_code' => $result->get_error_code(),
							)
						);

						wc_add_notice( $result->get_error_message(), 'error' );

						return array(
							'result'  => 'failure',
							'message' => $result->get_error_message(),
						);
					}

					$this->log(
						array(
							'source'     => 'process_session_charge_failover_success',
							'order_id'   => $order->get_id(),
							'new_handle' => $handle,
						)
					);
				} else {
					$this->log(
						array(
							'source'     => 'process_session_charge_non_recoverable_error',
							'order_id'   => $order->get_id(),
							'error_code' => $result->get_error_code(),
						)
					);

					wc_add_notice( $result->get_error_message(), 'error' );

					return array(
						'result'  => 'failure',
						'message' => $result->get_error_message(),
					);
				}
			} else {
				$this->log(
					array(
						'source'   => 'process_session_charge_failover_disabled',
						'order_id' => $order->get_id(),
					)
				);

				wc_add_notice( $result->get_error_message(), 'error' );

				return array(
					'result'  => 'failure',
					'message' => $result->get_error_message(),
				);
			}
		}

		if ( ! empty( $result['id'] ) ) {
			$order->update_meta_data( 'reepay_session_id', $result['id'] );
			$order->save_meta_data();

			$this->log(
				array(
					'source'     => 'process_session_charge_session_id_saved',
					'order_id'   => $order->get_id(),
					'session_id' => $result['id'],
				)
			);
		}

		if ( is_checkout_pay_page() ) {
			$this->log(
				array(
					'source'       => 'process_session_charge_checkout_pay_page',
					'order_id'     => $order->get_id(),
					'payment_type' => $this->payment_type,
				)
			);

			if ( self::METHOD_OVERLAY === $this->payment_type ) {
				$redirect_url = sprintf(
					'#!reepay-pay?rid=%s&accept_url=%s&cancel_url=%s',
					$result['id'],
					html_entity_decode( $this->get_return_url( $order ) ),
					html_entity_decode( $order->get_cancel_order_url() )
				);

				$this->log(
					array(
						'source'       => 'process_session_charge_overlay_redirect',
						'order_id'     => $order->get_id(),
						'redirect_url' => $redirect_url,
					)
				);

				return array(
					'result'   => 'success',
					'redirect' => $redirect_url,
				);
			} else {
				$this->log(
					array(
						'source'       => 'process_session_charge_window_redirect',
						'order_id'     => $order->get_id(),
						'redirect_url' => $result['url'],
					)
				);

				return array(
					'result'   => 'success',
					'redirect' => $result['url'],
				);
			}
		}

		if ( is_wp_error( $result ) ) {
			$this->log(
				array(
					'source'     => 'process_session_charge_final_error',
					'order_id'   => $order->get_id(),
					'error'      => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				)
			);
			throw new Exception( esc_html( $result->get_error_message() ), esc_html( $result->get_error_code() ) );
		} else {
			$this->log(
				array(
					'source'     => 'process_session_charge_success',
					'order_id'   => $order->get_id(),
					'session_id' => $result['id'],
					'has_url'    => ! empty( $result['url'] ),
				)
			);

			$this->log(
				array(
					'source'       => 'process_session_charge_payment_window_display_debug',
					'order_id'     => $order->get_id(),
					'payment_type' => $this->payment_type,
					'accept_url'   => $this->get_return_url( $order ),
					'result_url'   => $result['url'] ?? 'N/A',
					'note'         => 'This should check payment_type before returning hash redirect',
				)
			);

			return array(
				'result'             => 'success',
				'redirect'           => '#!reepay-checkout',
				'is_reepay_checkout' => true,
				'reepay'             => $result,
				'reepay_id'          => $result['id'],
				'accept_url'         => $this->get_return_url( $order ),
				'cancel_url'         => add_query_arg(
					array(
						'action'   => 'reepay_cancel',
						'order_id' => $order->get_id(),
					),
					admin_url( 'admin-ajax.php' )
				),
			);
		}
	}

	/**
	 * Ajax: Cancel Payment
	 */
	public function reepay_cancel() {
		if ( ! isset( $_GET['order_id'] ) ) {
			return;
		}

		$order  = wc_get_order( wc_clean( $_GET['order_id'] ) );
		$result = reepay()->api( $order )->get_invoice_data( $order );
		if ( is_wp_error( $result ) ) {
			return;
		}

		if ( 'failed' === $result['state'] ) {
			if ( count( $result['transactions'] ) > 0 &&
				isset( $result['transactions'][0]['card_transaction']['acquirer_message'] )
			) {
				$message = $result['transactions'][0]['card_transaction']['acquirer_message'];

				$order->add_order_note( 'Payment failed. Error from acquire: ' . $message );
				wc_add_notice( __( 'Payment error: ', 'reepay-checkout-gateway' ) . $message, 'error' );
			}

			wp_redirect( wc_get_cart_url() );
			exit();
		}
	}

	/**
	 * Apply settings from Reepay Checkout Gateway to other gateways. Use it in constructor
	 */
	protected function apply_parent_settings() {
		$this->private_key             = (string) reepay()->get_setting( 'private_key' );
		$this->private_key_test        = (string) reepay()->get_setting( 'private_key_test' );
		$this->test_mode               = (string) reepay()->get_setting( 'test_mode' );
		$this->settle                  = (array) reepay()->get_setting( 'settle' );
		$this->language                = (string) reepay()->get_setting( 'language' );
		$this->debug                   = (string) reepay()->get_setting( 'debug' );
		$this->payment_type            = (string) reepay()->get_setting( 'payment_type' );
		$this->skip_order_lines        = (string) reepay()->get_setting( 'skip_order_lines' );
		$this->handle_failover         = (string) reepay()->get_setting( 'handle_failover' );
		$this->enable_order_autocancel = (string) reepay()->get_setting( 'enable_order_autocancel' );
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Full Refund.
		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		try {
			$this->refund_payment( $order, $amount, $reason );

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'refund', $e->getMessage() );
		}
	}

	/**
	 * Get Order lines.
	 *
	 * @param WC_Order $order            order to get items.
	 * @param bool     $only_not_settled get only not settled items.
	 * @param bool     $skip_order_line get item price without calculate quantity.
	 *
	 * @return array
	 */
	public function get_order_items( WC_Order $order, bool $only_not_settled = false, $skip_order_line = false ): array {
		$prices_incl_tax = wc_prices_include_tax();

		$items               = array();
		$setup_fees          = array();
		$sub_amount_discount = 0;
		foreach ( $order->get_items() as $order_item ) {
			/**
			 * WC_Order_Item_Product returns not WC_Order_Item
			 *
			 * @var WC_Order_Item_Product $order_item
			 */

			if ( WPCProductBundlesWooCommerceIntegration::is_product_woosb( $order_item->get_product() ) ) {
				continue;
			}

			if ( $order_item->get_product() && wcr_is_subscription_product( $order_item->get_product() ) ) {
				$fee = $order_item->get_product()->get_meta( '_reepay_subscription_fee' );
				if ( ! empty( $fee ) && ! empty( $fee['enabled'] ) && 'yes' === $fee['enabled'] ) {
					$setup_fees[] = rp_clear_ordertext( $order_item->get_product()->get_name() ) . ' - ' . $fee['text'];
				}
				$sub_amount_discount += floatval( $order_item->get_meta( '_line_discount' ) );
				continue;
			}

			$price       = OrderCapture::get_item_price( $order_item, $order );
			$tax_percent = $price['tax_percent'];

			if ( $skip_order_line ) {
				/**
				 * Make dynamic tax by country to true for skip order line.
				 */
				if ( $tax_percent > 0 ) {
					$prices_incl_tax = true;
				}
				$unit_price = round( ( $prices_incl_tax ? $price['subtotal_with_tax'] : $price['subtotal'] ), 2 );
			} else {
				$unit_price = round( ( $prices_incl_tax ? $price['subtotal_with_tax'] : $price['subtotal'] ) / $order_item->get_quantity(), 2 );
			}

			if ( $only_not_settled && ! empty( $order_item->get_meta( 'settled' ) ) ) {
				continue;
			}

			$items[] = array(
				'ordertext'       => rp_clear_ordertext( $order_item->get_name() ),
				'quantity'        => $order_item->get_quantity(),
				'amount'          => rp_prepare_amount( $unit_price, $order->get_currency() ),
				'vat'             => round( $tax_percent / 100, 2 ),
				'amount_incl_vat' => $prices_incl_tax,
			);
		}

		// Add Shipping Line.
		if ( (float) $order->get_shipping_total() > 0 ) {
			foreach ( $order->get_items( 'shipping' ) as $item_shipping ) {
				$prices_incl_tax = wc_prices_include_tax();

				/**
				 * Make dynamic tax by country to true for skip order line.
				 */
				if ( $skip_order_line ) {
					if ( $tax_percent > 0 ) {
						$prices_incl_tax = true;
					}
				}

				$price = OrderCapture::get_item_price( $item_shipping, $order );

				$tax_percent = $price['tax_percent'];

				$unit_price = round( ( $prices_incl_tax ? $price['with_tax_and_discount'] : $price['original_with_discount'] ) / $item_shipping->get_quantity(), 2 );

				if ( $only_not_settled && ! empty( $item_shipping->get_meta( 'settled' ) ) ) {
					continue;
				}

				$items[] = array(
					'ordertext'       => rp_clear_ordertext( $item_shipping->get_name() ),
					'quantity'        => $item_shipping->get_quantity(),
					'amount'          => rp_prepare_amount( $unit_price, $order->get_currency() ),
					'vat'             => round( $tax_percent / 100, 2 ),
					'amount_incl_vat' => $prices_incl_tax,
				);
			}
		}

		// Add fee lines.
		foreach ( $order->get_fees() as $order_fee ) {
			if ( ! empty( $setup_fees ) && in_array( $order_fee['name'], $setup_fees, true ) ) {
				continue;
			}

			$fee          = (float) $order_fee->get_total();
			$tax          = (float) $order_fee->get_total_tax();
			$fee_with_tax = $fee + $tax;
			$tax_percent  = ( $tax > 0 ) ? round( 100 / ( $fee / $tax ) ) : 0;

			if ( $only_not_settled && ! empty( $order_fee->get_meta( 'settled' ) ) ) {
				continue;
			}

			$items[] = array(
				'ordertext'       => rp_clear_ordertext( $order_fee->get_name() ),
				'quantity'        => 1,
				'amount'          => rp_prepare_amount( $prices_incl_tax ? $fee_with_tax : $fee, $order->get_currency() ),
				'vat'             => round( $tax_percent / 100, 2 ),
				'amount_incl_vat' => $prices_incl_tax,
			);
		}

		// Add discount line.
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount          = $order->get_total_discount();
			$discount_with_tax = $order->get_total_discount( false );
			$tax               = $discount_with_tax - $discount;
			$tax_percent       = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			if ( abs( floatval( $sub_amount_discount ) ) > 0.001 && abs( floatval( $discount ) ) > 0.001 ) {
				/**
				 * Discount for subscription
				 */
				if ( $prices_incl_tax || $tax_percent > 0 ) {
					$simple_discount_amount = $discount_with_tax - $sub_amount_discount;
				} else {
					$simple_discount_amount = $discount - $sub_amount_discount;
				}
			} elseif ( $prices_incl_tax ) {
				/**
				 * Discount for simple product included tax
				 */
				$simple_discount_amount = $discount_with_tax;
			} else {
				$simple_discount_amount = $discount;
			}

			$discount_amount = round( - 1 * rp_prepare_amount( $simple_discount_amount, $order->get_currency() ) );

			if ( $discount_amount < 0 ) {
				$items[] = array(
					'ordertext'       => __( 'Discount', 'reepay-checkout-gateway' ),
					'quantity'        => 1,
					'amount'          => round( $discount_amount, 2 ),
					'vat'             => round( $tax_percent / 100, 2 ),
					'amount_incl_vat' => $prices_incl_tax,
				);
			}
		}

		// Add "PW Gift Cards" support.
		$items = array_merge( $items, PWGiftCardsIntegration::get_order_lines_for_reepay( $order, $prices_incl_tax ) );

		// Add "WC Gift Cards" support.
		$items = array_merge( $items, WCGiftCardsIntegration::get_order_lines_for_reepay( $order, $prices_incl_tax ) );

		// Add "Gift Up!" discount.
		if ( defined( 'GIFTUP_ORDER_META_CODE_KEY' ) &&
			defined( 'GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY' )
		) {
			if ( $order->meta_exists( GIFTUP_ORDER_META_CODE_KEY ) ) {
				$code              = $order->get_meta( GIFTUP_ORDER_META_CODE_KEY );
				$requested_balance = $order->get_meta( GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY );

				if ( $requested_balance > 0 ) {
					$items[] = array(
						// translators: gift card code.
						'ordertext'       => sprintf( __( 'Gift card (%s)', 'reepay-checkout-gateway' ), $code ),
						'quantity'        => 1,
						'amount'          => rp_prepare_amount( - 1 * $requested_balance, $order->get_currency() ),
						'vat'             => 0,
						'amount_incl_vat' => $prices_incl_tax,
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Get order amount from order item amount
	 *
	 * @param WC_Order $order            order to get items.
	 * @param bool     $skip_fn_rp_amount        skip call funcion rp_prepare_amount.
	 */
	public function get_skip_order_lines_amount( WC_Order $order, $skip_fn_rp_amount = false ) {
		$total_amount = 0;

		if ( wcs_cart_have_subscription() ) {
			$items = $this->get_order_items( $order, false, true );
			if ( $items ) {
				foreach ( $items as $item ) {
					$total_amount += $item['amount'];
				}
			}
		} elseif ( true === $skip_fn_rp_amount ) {
				$total_amount = $order->get_total();
		} else {
			$total_amount = rp_prepare_amount( $order->get_total(), $order->get_currency() );
		}

		return $total_amount;
	}

	/**
	 * Get Language
	 *
	 * @return string
	 */
	protected function get_language(): string {
		if ( ! empty( $this->language ) ) {
			return $this->language;
		}

		$locale = get_locale();

		// Wpml support.
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$languages = apply_filters( 'wpml_active_languages', null, 'orderby=id&order=desc' );
			if ( ! empty( $languages ) && count( $languages ) > 1 ) {
				$locale_wpml = apply_filters( 'wpml_current_language', get_locale() );
				if ( ! empty( $languages[ $locale_wpml ] ) ) {
					$locale = $languages[ $locale_wpml ]['default_locale'];
				}
			}
		}

		// Polylang support.
		if ( function_exists( 'pll_current_language' ) ) {
			if ( isset( $_COOKIE['billwerk_pll_current_language'] ) ) {
				$locale = $_COOKIE['billwerk_pll_current_language'];
			} else {
				$locale = pll_current_language();
			}
		}

		if ( in_array(
			$locale,
			array( 'en_US', 'da_DK', 'sv_SE', 'no_NO', 'fi_FI', 'is_IS', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'pl_PL', 'hu_HU', 'ro_RO', 'cs_CZ', 'el_GR', 'sk_SK', 'sr_RS' ),
			true
		) ) {
			return $locale;
		}

		return 'en_US';
	}

	/**
	 * Converts a Reepay card_type into a logo.
	 *
	 * @param string $card_type is the Reepay card type.
	 *
	 * @return string the logo
	 */
	public function get_logo( string $card_type ): string {
		$card_types = array(
			'visa'                        => 'visa',
			'visa_elec'                   => 'visa-electron',
			'visa-electron'               => 'visa-electron',
			'mc'                          => 'mastercard',
			'mastercard'                  => 'mastercard',
			'dankort'                     => 'dankort',
			'anyday'                      => 'anyday',
			'visa_dk'                     => 'dankort',
			'ffk'                         => 'forbrugsforeningen',
			'maestro'                     => 'maestro',
			'amex'                        => 'american-express',
			'diners'                      => 'diners',
			'discover'                    => 'discover',
			'jcb'                         => 'jcb',
			'mobilepay'                   => 'mobilepay',
			'ms_subscripiton'             => 'mobilepay',
			'viabill'                     => 'viabill',
			'klarna_pay_later'            => 'klarna',
			'klarna_pay_now'              => 'klarna',
			'china_union_pay'             => 'cup',
			'paypal'                      => 'paypal',
			'applepay'                    => 'applepay',
			'googlepay'                   => 'googlepay',
			'vipps'                       => 'vipps',
			'ideal'                       => 'ideal',
			'sepa'                        => 'sepa',
			'klarna_direct_bank_transfer' => 'klarna',
			'klarna_direct_debit'         => 'klarna',
			'bancontact'                  => 'bancontact',
			'blik_oc'                     => 'blik',
			'blik'                        => 'blik',
			'eps'                         => 'eps',
			'estonia_banks'               => 'card',
			'latvia_banks'                => 'card',
			'lithuania_banks'             => 'card',
			'giropay'                     => 'giropay',
			'mbway'                       => 'mbway',
			'multibanco'                  => 'multibanco',
			'mybank'                      => 'mybank',
			'p24'                         => 'p24',
			'paycoinq'                    => 'paycoinq',
			'paysafecard'                 => 'paysafecard',
			'paysera'                     => 'paysera',
			'postfinance'                 => 'postfinance',
			'satispay'                    => 'satispay',
			'trustly'                     => 'trustly',
			'verkkopankki'                => 'verkkopankki',
			'wechatpay'                   => 'wechatpay',
			'santander'                   => 'santander',
			'offline_bank_transfer'       => 'card',
			'offline_cash'                => 'card',
			'offline_other'               => 'card',
		);

		if ( isset( $card_types[ $card_type ] ) ) {
			$logo_svg_path = reepay()->get_setting( 'images_path' ) . 'svg/' . $card_types[ $card_type ] . '.logo.svg';
			$logo_png_path = reepay()->get_setting( 'images_path' ) . $card_types[ $card_type ] . '.png';
			if ( file_exists( $logo_svg_path ) ) {
				return reepay()->get_setting( 'images_url' ) . 'svg/' . $card_types[ $card_type ] . '.logo.svg';
			} elseif ( file_exists( $logo_png_path ) ) {
				return reepay()->get_setting( 'images_url' ) . $card_types[ $card_type ] . '.png';
			} else {
				return reepay()->get_setting( 'images_url' ) . 'svg/card.logo.svg';
			}
		} else {
			return reepay()->get_setting( 'images_url' ) . 'svg/card.logo.svg';
		}
	}

	/**
	 * Initialise default settings form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'is_reepay_configured' => array(
				'title'   => __( 'Status in Frisbii Pay', 'reepay-checkout-gateway' ),
				'type'    => 'gateway_status',
				'label'   => __( 'Status in Frisbii Pay', 'reepay-checkout-gateway' ),
				'default' => $this->test_mode,
			),
			'enabled'              => array(
				'title'    => __( 'Enable/Disable', 'reepay-checkout-gateway' ),
				'type'     => 'checkbox',
				'label'    => __( 'Enable plugin', 'reepay-checkout-gateway' ),
				'default'  => 'no',
				'disabled' => $this->is_gateway_settings_page() && ! $this->check_is_active(), // Check calls api, so use it only on gateway page.
			),
			'title'                => array(
				'title'       => __( 'Title', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => $this->method_title,
			),
			'description'          => array(
				'title'       => __( 'Description', 'reepay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'reepay-checkout-gateway' ),
				'default'     => $this->method_title,
			),
		);
	}

	/**
	 * Exclude payment method if the order contains an unsupported currency
	 *
	 * @param WC_Payment_Gateway[] $available_gateways gateways.
	 *
	 * @return array
	 */
	public function exclude_payment_gateway_based_on_currency( array $available_gateways ): array {
		if ( is_null( WC()->cart ) ) {
			return $available_gateways;
		}
		$current_currencies = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$item_data = $cart_item['data'];
			$currency  = get_woocommerce_currency();
			if ( method_exists( $item_data, 'get_currency' ) ) {
				$currency = $item_data->get_currency();
			}
			$current_currencies[] = $currency;
		}
		foreach ( $available_gateways as $gateway_id => $gateway ) {
			if ( $gateway_id === $this->id ) {
				if ( ! empty( $this->supported_currencies ) ) {
					if ( ! empty( array_diff( $current_currencies, $this->supported_currencies ) ) ) {
						unset( $available_gateways[ $gateway_id ] );
						break;
					}
				} elseif ( ! empty( $this->unsupported_currencies ) && array_intersect( $this->unsupported_currencies, $current_currencies ) ) {
					unset( $available_gateways[ $gateway_id ] );
					break;
				}
			}
		}

		return $available_gateways;
	}

	/**
	 * Add age verification data to charge session parameters based on payment method
	 *
	 * @param array    $params Session parameters (passed by reference).
	 * @param WC_Order $order  Current order.
	 * @return void
	 */
	private function add_age_verification_to_charge_session( array &$params, WC_Order $order ): void {
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

		// Log final age verification data for debugging.
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
