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
}
