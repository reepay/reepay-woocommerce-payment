<?php
/**
 * Reepay customer class
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Actions;

use Exception;
use WC_Customer;

/**
 * Class ReepayCustomer
 *
 * @package Reepay\Checkout
 */
class ReepayCustomer {
	/**
	 * ReepayCustomer constructor.
	 */
	public function __construct() {
		add_action( 'user_register', array( $this, 'user_register' ), 1000, 1 );
	}

	/**
	 * Action user_register
	 *
	 * @param int $user_id registered customer id.
	 */
	public function user_register( int $user_id ) {
		self::set_reepay_handle( $user_id );
	}

	/**
	 * Set reepay user handle
	 *
	 * @param int $user_id user id to set handle.
	 */
	public static function set_reepay_handle( int $user_id ): string {
		// Per-request cache: prevents duplicate API lookups for the same user within one request.
		// This avoids a double call when user_register hook fires first, then rp_get_customer_handle()
		// calls set_reepay_handle() again during the same checkout process.
		static $cache = array();
		if ( array_key_exists( $user_id, $cache ) ) {
			return $cache[ $user_id ];
		}

		$customer = new WC_Customer( $user_id );

		$email = $customer->get_billing_email();
		if ( empty( $email ) && ! empty( $_POST['billing_email'] ) ) {
			$email = $_POST['billing_email'];
		}

		if ( empty( $email ) ) {
			$email = $customer->get_email();
		}

		if ( empty( $email ) ) {
			$cache[ $user_id ] = '';
			return $cache[ $user_id ];
		}

		$reepay_customers = reepay()->api( 'reepay_user_register' )->request( 'GET', "https://api.reepay.com/v1/list/customer?email=$email" );

		if ( is_wp_error( $reepay_customers ) || empty( $reepay_customers['content'][0] ) ) {
			$cache[ $user_id ] = '';
			return $cache[ $user_id ];
		}

		$customer_handle = $reepay_customers['content'][0]['handle'];

		update_user_meta( $user_id, 'reepay_customer_id', $customer_handle );

		$cache[ $user_id ] = $customer_handle;
		return $cache[ $user_id ];
	}

	/**
	 * Check exist customer in reepay with same handle but another data
	 *
	 * @param int    $user_id user id to set handle.
	 * @param string $handle user id to set handle.
	 */
	public static function have_same_handle( int $user_id, string $handle ): bool {
		$user_reepay = reepay()->api( 'reepay_user_register' )->request(
			'GET',
			'https://api.reepay.com/v1/customer/' . $handle,
		);

		if ( ! empty( $user_reepay ) && ! is_wp_error( $user_reepay ) ) {
			$customer = new WC_Customer( $user_id );
			$email    = $customer->get_billing_email();

			if ( empty( $email ) && ! empty( $_POST['billing_email'] ) ) {
				$email = $_POST['billing_email'];
			}

			if ( ! empty( $email ) && $user_reepay['email'] !== $email ) {
				return true;
			}
		}

		return false;
	}
}
