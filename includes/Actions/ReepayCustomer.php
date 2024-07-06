<?php
/**
 * Reepay customer class
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Actions;

use Billwerk\Sdk\Model\Customer\CustomerCollectionGetModel;
use Billwerk\Sdk\Model\Customer\CustomerGetModel;
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
	 * Set reepay user handle.
	 * Returns an empty string if not set
	 *
	 * @param int|WC_Customer $user_id user id to set handle.
	 */
	public static function set_reepay_handle( $user_id ): string {
		try {
			$wc_customer = new WC_Customer( $user_id );
		} catch ( Exception $e ) {
			reepay()->log()->error( $e->getMessage() );
			return '';
		}

		$email = $wc_customer->get_billing_email();
		if ( empty( $email ) ) {
			$email = $_POST['billing_email'] ?? '';
		}

		if ( empty( $email ) ) {
			$email = $wc_customer->get_email();
		}

		if ( empty( $email ) ) {
			return '';
		}

		try {
			$list = reepay()->sdk()->customer()->list(
				( new CustomerCollectionGetModel() )
					->setEmail( $email )
			);

			$customers = $list->getContent();
		} catch ( Exception $e ) {
			return '';
		}

		if ( count( $customers ) === 0 ) {
			return '';
		}

		$customer        = $customers[0];
		$customer_handle = $customer->getHandle();

		update_user_meta( $user_id, 'reepay_customer_id', $customer_handle );

		return $customer_handle;
	}

	/**
	 * Check exist customer in reepay with same handle but another email
	 *
	 * @param int    $user_id user id to set handle.
	 * @param string $handle user id to set handle.
	 */
	public static function have_same_handle( int $user_id, string $handle ): bool {
		try {
			$customer = reepay()->sdk()->customer()->get(
				( new CustomerGetModel() )
					->setHandle( $handle )
			);
		} catch ( Exception $e ) {
			$customer = null;
		}

		if ( ! is_null( $customer ) ) {
			try {
				$wc_customer = new WC_Customer( $user_id );
			} catch ( Exception $e ) {
				return false;
			}

			$email = $wc_customer->get_billing_email();
			if ( empty( $email ) ) {
				$email = $_POST['billing_email'] ?? '';
			}
			if ( ! empty( $email ) && $customer->getEmail() !== $email ) {
				return true;
			}
		}

		return false;
	}
}
