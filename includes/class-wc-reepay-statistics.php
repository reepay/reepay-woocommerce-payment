<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Reepay_Gateway_Statistics{

    /**
     * Constructor
     */
    public function __construct() {
        register_deactivation_hook( REEPAY_CHECKOUT_PLUGIN_FILE, [static::class, 'plugin_deactivated'] );
        register_uninstall_hook( REEPAY_CHECKOUT_PLUGIN_FILE, [static::class, 'plugin_deleted'] );
        register_activation_hook( REEPAY_CHECKOUT_PLUGIN_FILE, 'private_key_activated' );
        add_action( 'upgrader_process_complete', [static::class, 'upgrade_completed'], 10, 2 );
    }

    public static function send_event($event) {
        $gateway = new WC_Gateway_Reepay_Checkout();
        if(!empty($gateway->private_key)){
            $params = [
                'plugin' => 'WOOCOMMERCE-REEPAY-CHECKOUT',
                'version' => get_option( 'woocommerce_reepay_version' ),
                'privatekey' => $gateway->private_key,
                'url' => home_url(),
                'event' => $event,
            ];

            $url = 'https://hook.reepay.integromat.celonis.com/1dndgwx6cwsvl3shsee29yyqf4d648xf';

            return wp_remote_post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($params, JSON_PRETTY_PRINT),
            ]);
        }
        return false;
    }

    public static function plugin_deactivated() {
        static::send_event('deactivated');
    }

    public static function plugin_deleted() {
        static::send_event('deleted');
    }

    public static function private_key_activated() {
        static::send_event('activated');
    }

    public static function upgrade_completed($upgrader_object, $options) {
        foreach( $options['plugins'] as $plugin ) {
            if( strpos($plugin, REEPAY_CHECKOUT_PLUGIN_FILE) ) {
                static::send_event('updated');
            }
        }
    }
}

new WC_Reepay_Gateway_Statistics();