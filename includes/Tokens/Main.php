<?php

namespace Reepay\Checkout\Tokens;

class Main {
	public function __construct() {
		TokenReepay::register_view_actions();
		TokenReepayMS::register_view_actions();

		add_action( 'woocommerce_payment_token_class', array( $this, 'set_token_class_name' ), 10, 1 );
	}

	public function set_token_class_name( $token_class_name ) {
		static $tokens = array(
			'WC_Payment_Token_Reepay' => TokenReepay::class,
			'WC_Payment_Token_Reepay_MS' => TokenReepayMS::class,
		);

		return $tokens[ $token_class_name ] ?? $token_class_name;
	}
}