<?php
/**
 * Customer functions
 *
 * @package Reepay\Checkout\Functions
 */

use Reepay\Checkout\Actions\ReepayCustomer;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_get_customer_handle' ) ) {
	/**
	 * Get Customer handle by User ID.
	 *
	 * @param int $user_id user id to get handle.
	 *
	 * @return string
	 */
	function rp_get_customer_handle( int $user_id ): string {
		$handle = get_user_meta( $user_id, 'reepay_customer_id', true );

		if ( empty( $handle ) ) {
			$handle = ReepayCustomer::set_reepay_handle( $user_id );

			if ( empty( $handle ) ) {

				if ( $user_id > 0 ) {
					$handle = 'customer-' . $user_id;
				} else {
					$handle = 'cust-' . time();
				}

				update_user_meta( $user_id, 'reepay_customer_id', $handle );
			}
		}

		return $handle;
	}
}

if ( ! function_exists( 'rp_get_userid_by_handle' ) ) {
	/**
	 * Get user id by reepay user handle.
	 *
	 * @param string $handle reepay user handle.
	 *
	 * @return int|false
	 */
	function rp_get_userid_by_handle( string $handle ) {
		if ( strpos( $handle, 'guest-' ) !== false ) {
			return 0;
		}

		$users = get_users(
			array(
				'meta_key'    => 'reepay_customer_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $handle, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'      => 1,
				'count_total' => false,
			)
		);

		return ! empty( $users ) ? array_shift( $users )->ID : false;
	}
}
