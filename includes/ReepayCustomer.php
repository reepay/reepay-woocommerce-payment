<?php


namespace Reepay\Checkout;


use Exception;
use WC_Customer;

class ReepayCustomer {
	public function __construct() {
		add_action( 'user_register', array( $this, 'set_reepay_handle' ), 10, 1 );
	}

	/**
	 * @param int $user_id registered customer id.
	 */
	public function set_reepay_handle( int $user_id ) {
		if ( ! empty( get_user_meta( $user_id, 'reepay_customer_id' ) ) ) {
			return;
		}

		try {
			$customer = new WC_Customer( $user_id );
		} catch ( Exception $e ) {
			return;
		}

		$email = $customer->get_email() ?: $customer->get_billing_email();

		if ( empty( $email ) ) {
			return;
		}

		$reepay_customers = reepay()->api( 'reepay_user_register' )->request( 'GET', "https://api.reepay.com/v1/list/customer?email=$email" );

		if ( is_wp_error( $reepay_customers ) || empty( $reepay_customers['content'][0] ) ) {
			return;
		}

		update_user_meta( $user_id, 'reepay_customer_id', $reepay_customers['content'][0]['handle'] );
	}
}