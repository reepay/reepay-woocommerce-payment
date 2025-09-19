<?php
/**
 * Helper for meta fields WordPress.
 *
 * @package Reepay\Checkout\Utils
 */

namespace Reepay\Checkout\Utils;

/**
 * Class.
 *
 * @package Reepay\Checkout\Utils
 */
class MetaField {
	/**
	 * Static logging method for debugging
	 *
	 * @param mixed $message Log message.
	 * @return void
	 */
	private static function log( $message ) {
		if ( ! is_string( $message ) ) {
			$message = print_r( $message, true );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				$message,
				array(
					'source'  => 'reepay-metafield',
					'_legacy' => true,
				)
			);
		} else {
			// if Woocommerce disabled.
			error_log( print_r( $message, true ) );
		}
	}

	public const BILLWERK_FIELD_KEYS = array(
		// order fields.
		'reepay_session_id',
		'_reepay_maybe_save_card',
		'_reepay_order',
		'_reepay_token_id',
		'_reepay_token',
		'reepay_token',
		'_reepay_order_cancelled',
		'_reepay_subscription_plan',
		'_transaction_id',
		'_reepay_credit_note_ids',
		'_reepay_capture_transaction',
		'_reepay_cancel_transaction',
		'_reepay_state_authorized',
		'_reepay_state_settled',
		'_reepay_locked',
		'reepay_masked_card',
		'reepay_card_type',
		'_reepay_source',
		'_is_instant_settled',
		'_reepay_another_orders',
		'_reepay_customer',
		'_reepay_renewal',
		'_reepay_subscription_handle',
		'_reepay_imported',
		'_reepay_billing_string',
		'_reepay_is_subscription',
		'_reepay_subscription_customer_role',
		'_reepay_subscription_handle_parent',
		'_reepay_is_renewal',
		'_reepay_membership_id',
		'_payment_method_id',
		'_real_total',
		'_reepay_remaining_balance',
		// user fields.
		'reepay_customer_id',
		// product fields - age verification.
		'_reepay_enable_age_verification',
		'_reepay_minimum_age',
	);

	/**
	 * Get meta fields with id.
	 *
	 * @param int    $user_id user id.
	 * @param string $meta_key meta key.
	 *
	 * @return array|bool
	 */
	public static function get_raw_user_meta( int $user_id, string $meta_key ) {
		$cache_key    = 'billwerk_user_meta_' . $user_id . '_' . $meta_key;
		$cached_value = wp_cache_get( $cache_key, 'user_meta' );

		if ( false !== $cached_value ) {
			return $cached_value;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$user_meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s", $user_id, $meta_key ) );
		if ( ! empty( $user_meta ) ) {
			wp_cache_set( $cache_key, $user_meta, 'user_meta' );

			return $user_meta;
		}

		return false;
	}

	/**
	 * Check if age verification is enabled for a product
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_age_verification_enabled( int $product_id ): bool {
		$enabled = get_post_meta( $product_id, '_reepay_enable_age_verification', true );
		return 'yes' === $enabled;
	}

	/**
	 * Get minimum age for a product
	 *
	 * @param int $product_id Product ID.
	 * @return int|null Minimum age or null if not set
	 */
	public static function get_minimum_age( int $product_id ): ?int {
		$age = get_post_meta( $product_id, '_reepay_minimum_age', true );
		return ! empty( $age ) && is_numeric( $age ) ? (int) $age : null;
	}

	/**
	 * Get available age options for age verification
	 *
	 * @return array
	 */
	public static function get_age_options(): array {
		return array(
			15 => __( '15', 'reepay-checkout-gateway' ),
			16 => __( '16', 'reepay-checkout-gateway' ),
			18 => __( '18', 'reepay-checkout-gateway' ),
			21 => __( '21', 'reepay-checkout-gateway' ),
		);
	}

	/**
	 * Validate age verification settings for a product
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of validation errors (empty if valid)
	 */
	public static function validate_age_verification( int $product_id ): array {
		$errors = array();

		if ( self::is_age_verification_enabled( $product_id ) ) {
			$minimum_age = self::get_minimum_age( $product_id );

			if ( null === $minimum_age ) {
				$errors[] = __( 'Minimum age is required when age verification is enabled.', 'reepay-checkout-gateway' );
			} elseif ( ! array_key_exists( $minimum_age, self::get_age_options() ) ) {
				$errors[] = __( 'Invalid minimum age selected.', 'reepay-checkout-gateway' );
			}
		}

		return $errors;
	}

	/**
	 * Check if global age verification is enabled
	 *
	 * @return bool
	 */
	public static function is_global_age_verification_enabled(): bool {
		$gateway_settings = get_option( 'woocommerce_reepay_checkout_settings', array() );
		return isset( $gateway_settings['age_verification'] ) && 'yes' === $gateway_settings['age_verification'];
	}

	/**
	 * Get age-restricted products in the current cart
	 *
	 * @param int|null $order_id Optional order ID for logging context.
	 * @return array Array of product data with age requirements
	 */
	public static function get_age_restricted_products_in_cart( ?int $order_id = null ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}

		$age_restricted_products = array();
		$cart_contents = WC()->cart->get_cart_contents();
		$cart_items_processed = array();

		// Log cart contents overview
		self::log(
			array(
				'source' => 'get_age_restricted_products_process',
				'order_id' => $order_id,
				'total_cart_items' => count( $cart_contents ),
				'cart_item_keys' => array_keys( $cart_contents ),
			)
		);

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			$variation_id = $cart_item['variation_id'] ?? 0;

			// Check variation first, then parent product
			// TO DO implement if age verification is set on varaint level with this code: $check_product_id = $variation_id > 0 ? $variation_id : $product_id;
			$check_product_id = $product_id;

			$is_age_enabled = self::is_age_verification_enabled( $check_product_id );
			$minimum_age = $is_age_enabled ? self::get_minimum_age( $check_product_id ) : null;

			// Log each product analysis
			$cart_items_processed[] = array(
				'cart_item_key' => $cart_item_key,
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'is_age_verification_enabled' => $is_age_enabled,
				'minimum_age' => $minimum_age,
				'quantity' => $cart_item['quantity'],
			);

			if ( $is_age_enabled && null !== $minimum_age ) {
				$age_restricted_products[] = array(
					'product_id' => $product_id,
					'variation_id' => $variation_id,
					'minimum_age' => $minimum_age,
					'quantity' => $cart_item['quantity'],
					'cart_item_key' => $cart_item_key,
				);
			}
		}

		// Log final result
		self::log(
			array(
				'source' => 'get_age_restricted_products_result',
				'order_id' => $order_id,
				'age_restricted_products_count' => count( $age_restricted_products ),
				'cart_items_processed' => $cart_items_processed
			)
		);

		return $age_restricted_products;
	}

	/**
	 * Get the maximum age requirement from all products in cart
	 *
	 * @param int|null $order_id Optional order ID for logging context.
	 * @return int|null Maximum age requirement or null if no age restrictions
	 */
	public static function get_cart_maximum_age( ?int $order_id = null ): ?int {
		$age_restricted_products = self::get_age_restricted_products_in_cart( $order_id );

		// Log initial cart analysis
		self::log(
			array(
				'source' => 'get_cart_maximum_age_start',
				'order_id' => $order_id,
				'cart_exists' => function_exists( 'WC' ) && WC()->cart ? true : false,
				'cart_contents_count' => function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
				'age_restricted_products_count' => count( $age_restricted_products ),
				'age_restricted_products' => $age_restricted_products,
			)
		);

		if ( empty( $age_restricted_products ) ) {
			return null;
		}

		$max_age = 0;
		$product_ages = array();

		foreach ( $age_restricted_products as $product ) {
			$product_ages[] = array(
				'product_id' => $product['product_id'],
				'variation_id' => $product['variation_id'],
				'minimum_age' => $product['minimum_age'],
				'quantity' => $product['quantity'],
			);

			if ( $product['minimum_age'] > $max_age ) {
				$max_age = $product['minimum_age'];
			}
		}

		$final_result = $max_age > 0 ? $max_age : null;

		// Log final result
		self::log(
			array(
				'source' => 'get_cart_maximum_age_result',
				'order_id' => $order_id,
				'product_ages' => $product_ages,
				'calculated_max_age' => $max_age,
				'final_result' => $final_result,
				'result_is_null' => null === $final_result,
			)
		);

		return $final_result;
	}

	/**
	 * Determine if age verification should be included in checkout session
	 *
	 * @param int|null $order_id Optional order ID for logging context.
	 * @return bool
	 */
	public static function should_include_age_verification( ?int $order_id = null ): bool {
		// First check if global setting is enabled
		if ( ! self::is_global_age_verification_enabled() ) {
			return false;
		}

		// Then check if cart has any age-restricted products
		$age_restricted_products = self::get_age_restricted_products_in_cart( $order_id );
		return ! empty( $age_restricted_products );
	}

	/**
	 * Get age verification data for checkout session
	 *
	 * @param int|null $order_id Optional order ID for logging context.
	 * @return array|null Age verification data or null if not needed
	 */
	public static function get_age_verification_session_data( ?int $order_id = null ): ?array {
		if ( ! self::should_include_age_verification( $order_id ) ) {
			return null;
		}

		$max_age = self::get_cart_maximum_age( $order_id );
		$age_restricted_products = self::get_age_restricted_products_in_cart( $order_id );

		return array(
			'minimum_age' => $max_age,
			'products_count' => count( $age_restricted_products ),
			'products' => $age_restricted_products,
		);
	}
}
