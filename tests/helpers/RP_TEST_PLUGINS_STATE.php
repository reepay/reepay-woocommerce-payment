<?php

abstract class RP_TEST_PLUGINS_STATE {
	public static function woo_subs_activated() {
		return class_exists( 'WC_Subscriptions', false );
	}

	public static function rp_subs_activated() {
		return class_exists( 'WooCommerce_Reepay_Subscriptions', false );
	}
}