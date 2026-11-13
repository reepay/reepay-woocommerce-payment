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
	 * The handle is reused only from one of the user's own previous orders; it is never looked up
	 * by the (unverified) email address.
	 *
	 * @param int $user_id user id to set handle.
	 *
	 * @return string Handle, or an empty string if the user has none yet.
	 */
	public static function set_reepay_handle( int $user_id ): string {
		// Per-request cache (found handles only): prevents repeating the order lookup for the same user within one request.
		// This avoids a double lookup when user_register hook fires first, then rp_get_customer_handle()
		// calls set_reepay_handle() again during the same checkout process.
		static $cache = array();
		if ( array_key_exists( $user_id, $cache ) ) {
			return $cache[ $user_id ];
		}

		// The handle is never inferred from the (unverified) email: only a handle already
		// stored on a previous order of this very user is reused.
		$customer_handle = '';

		if ( $user_id > 0 ) {
			// meta_query is not supported by every order datastore (legacy posts storage), so filter in PHP.
			// Orders are scanned page by page (oldest first) so a handle on an older order is not missed
			// when the store used another gateway first. The scan is bounded to keep checkout fast.
			$per_page = 50;
			for ( $page = 1; $page <= 20 && '' === $customer_handle; $page++ ) {
				$orders = wc_get_orders(
					array(
						'customer_id' => $user_id,
						'limit'       => $per_page,
						'paged'       => $page,
						'orderby'     => 'ID',
						'order'       => 'ASC',
					)
				);

				foreach ( $orders as $order ) {
					$order_handle = (string) $order->get_meta( '_reepay_customer' );
					if ( '' !== $order_handle ) {
						$customer_handle = $order_handle;
						break;
					}
				}

				if ( count( $orders ) < $per_page ) {
					break;
				}
			}
		}

		if ( '' === $customer_handle ) {
			// Do not cache an empty result: user_register runs before WooCommerce links guest orders,
			// so a later call in the same request (checkout) must be able to find the handle.
			return '';
		}

		update_user_meta( $user_id, 'reepay_customer_id', $customer_handle );

		$cache[ $user_id ] = $customer_handle;
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
			'https://api.reepay.com/v1/customer/' . rawurlencode( $handle ),
		);

		if ( ! empty( $user_reepay ) && ! is_wp_error( $user_reepay ) ) {
			$customer = new WC_Customer( $user_id );
			$email    = $customer->get_billing_email();

			if ( empty( $email ) && ! empty( $_POST['billing_email'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
				$email = sanitize_email( wp_unslash( $_POST['billing_email'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			if ( ! empty( $email ) && ( $user_reepay['email'] ?? '' ) !== $email ) {
				return true;
			}
		}

		return false;
	}
}
