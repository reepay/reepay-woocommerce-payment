<?php

abstract class RP_TEST_PLUGINS_STATE {
	const PLUGINS = array(
		'woo'      => 'woocommerce/woocommerce.php',
		'woo_subs' => 'woocommerce-subscriptions/woocommerce-subscriptions.php',
		'rp_subs'  => 'reepay-subscriptions-for-woocommerce/reepay-subscriptions-for-woocommerce.php'
	);

	public static function activate_plugins( array $plugins = array() ) {
		self::deactivate_plugins();

		if ( empty( $plugins ) ) {
			$env_plugins = getenv( 'PHPUNIT_PLUGINS' );

			if ( empty( $env_plugins ) ) {
				$plugins = array( 'woo', 'woo_subs', 'rp_subs' );
			} else {
				$plugins = explode( ',', $env_plugins );
			}
		}

		$wordpres_plugins_path = ABSPATH . 'wp-content/plugins/';
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $plugins as $plugin ) {
			$plugin = trim( $plugin );
			$plugin_path = $wordpres_plugins_path . ( self::PLUGINS[ $plugin ] ?? '' );

			if ( file_exists( $plugin_path ) ) {
				$active_plugins[] = self::PLUGINS[ $plugin ];
				update_option( 'active_plugins',  $active_plugins );

				include_once $plugin_path;

				if( 'woo' === $plugin ) {
					update_option( 'woocommerce_db_version', WC()->version  );
				}
			} else {
				echo sprintf(
					"Plugin %s not found\n",
					$plugin
				);
			}
		}
	}

	public static function deactivate_plugins() {
		$all_plugins = get_option( 'active_plugins', array() );
		$other_plugins = array();

		foreach ( $all_plugins as $plugin_path ) {
			if( ! in_array( $plugin_path, self::PLUGINS, true ) ) {
				$other_plugins[] = $plugin_path;
			}
		}

		update_option( 'active_plugins',  $other_plugins );
	}

	public static function woo_subs_activated() {
		return class_exists( 'WC_Subscriptions', false );
	}

	public static function rp_subs_activated() {
		return class_exists( 'WooCommerce_Reepay_Subscriptions', false );
	}
}