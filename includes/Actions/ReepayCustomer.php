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
		$customer = new WC_Customer( $user_id );

		$email = $customer->get_billing_email();
		if ( empty( $email ) && ! empty( $_POST['billing_email'] ) ) {
			$email = $_POST['billing_email'];
		}

		if ( empty( $email ) ) {
			$email = $customer->get_email();
		}

		if ( empty( $email ) ) {
			return '';
		}

		$reepay_customers = reepay()->api( 'reepay_user_register' )->request( 'GET', "https://api.reepay.com/v1/list/customer?email=$email" );

		if ( is_wp_error( $reepay_customers ) || empty( $reepay_customers['content'][0] ) ) {
			return '';
		}

		$customer_handle = $reepay_customers['content'][0]['handle'];

		update_user_meta( $user_id, 'reepay_customer_id', $customer_handle );

		return $customer_handle;
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
