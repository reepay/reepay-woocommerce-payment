<?php
/**
 * Offline Transfer gateway
 *
 * @package Reepay\Checkout\Gateways
 */

namespace Reepay\Checkout\Gateways;

defined( 'ABSPATH' ) || exit();

/**
 * Class OfflineTransfer
 *
 * @package Reepay\Checkout\Gateways
 */
class OfflineTransfer extends ReepayGateway {
	/**
	 * Logos
	 *
	 * @var array
	 */
	public array $logos = array(
		'obt',
	);

	/**
	 * Payment methods.
	 *
	 * @var array
	 */
	public array $payment_methods = array(
		'offline_bank_transfer',
	);

	/**
	 * PPSepa constructor.
	 */
	public function __construct() {
		$this->id           = 'reepay_offline_bank_transfer';
		$this->has_fields   = true;
		$this->method_title = __( 'Billwerk+ Pay - Bank Transfer', 'reepay-checkout-gateway' );
		$this->supports     = array(
			'products',
			'refunds',
			'add_payment_method',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		parent::__construct();

		$this->apply_parent_settings();

		add_action( 'wp_ajax_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
		add_action( 'wp_ajax_nopriv_reepay_card_store_' . $this->id, array( $this, 'reepay_card_store' ) );
	}



	/**
	 * If There are no payment fields show the description if set.
	 *
	 * @return void
	 */
	public function payment_fields() {
		parent::payment_fields();

		$this->tokenization_script();
		$this->saved_payment_methods();
		$this->save_payment_method_checkbox();
	}
}
