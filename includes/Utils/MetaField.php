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

		// user fields.
		'reepay_customer_id',
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
}
