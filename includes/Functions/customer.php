<?php

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'rp_get_customer_handle' ) ) {
	/**
	 * Get Customer handle by User ID.
	 *
	 * @param $user_id
	 *
	 * @return string
	 */
	function rp_get_customer_handle( $user_id ) {
		if ( ! $user_id ) {
			// Workaround: Allow to pay exist orders by guests
			if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
				if ( $order_id = wc_get_order_id_by_order_key( $_GET['key'] ) ) {
					$order = wc_get_order( $order_id );

					// Get customer handle by order
					$gateway = rp_get_payment_method( $order );
					$handle  = reepay()->api( $gateway )->get_customer_handle_online( $order );
					if ( $handle ) {
						return $handle;
					}
				}
			}
		}

		$handle = get_user_meta( $user_id, 'reepay_customer_id', true );
		if ( empty( $handle ) ) {
			$handle = 'customer-' . $user_id;
			update_user_meta( $user_id, 'reepay_customer_id', $handle );
		}

		return $handle;
	}
}

if ( ! function_exists( 'rp_get_userid_by_handle' ) ) {
	/**
	 * @param $handle
	 *
	 * @return bool|int
	 */
	function rp_get_userid_by_handle( $handle ) {
		if ( strpos( $handle, 'guest-' ) !== false ) {
			return 0;
		}

		$users = get_users(
			array(
				'meta_key'    => 'reepay_customer_id',
				'meta_value'  => $handle,
				'number'      => 1,
				'count_total' => false,
			)
		);
		if ( count( $users ) > 0 ) {
			$user = array_shift( $users );

			return $user->ID;
		}

		return false;
	}
}
