<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Reepay_Gateway_Statistics{
    use WC_Reepay_Log;

    /**
     * @var WC_Reepay_Gateway_Statistics
     */
    private static $instance;

    /**
     * @var string
     */
    private $logging_source;

    /**
     * Constructor
     */
    private function __construct() {
        $this->logging_source = 'reepay-statistics';
        register_deactivation_hook( REEPAY_CHECKOUT_PLUGIN_FILE, [static::class, 'plugin_deactivated'] );
        register_uninstall_hook( REEPAY_CHECKOUT_PLUGIN_FILE, [static::class, 'plugin_deleted'] );
        register_activation_hook( REEPAY_CHECKOUT_PLUGIN_FILE, [static::class, 'private_key_activated'] );
        add_action( 'upgrader_process_complete', [static::class, 'upgrade_completed'], 10, 2 );
    }

    /**
     * @return WC_Reepay_Gateway_Statistics
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    public function send_event($event) {
        $this->log( sprintf( 'Event init: %s', $event ) );
        $settings = get_option( 'woocommerce_reepay_checkout_settings' );
        if(!empty($settings["private_key"])){
            $params = [
                'plugin' => 'WOOCOMMERCE',
                'version' => get_option( 'woocommerce_reepay_version' ),
                'privatekey' => $settings["private_key"],
                'url' => home_url(),
                'event' => $event,
            ];

            $this->log( sprintf( 'Request: %s', json_encode( $params, JSON_PRETTY_PRINT ) ) );

            $url = 'https://hook.reepay.integromat.celonis.com/1dndgwx6cwsvl3shsee29yyqf4d648xf';

            $response = wp_remote_post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($params, JSON_PRETTY_PRINT),
            ]);

            $this->log( sprintf( 'Response: %s', wp_remote_retrieve_body( $response ) ) );

            return $response;
        }
        return false;
    }

    public static function plugin_deactivated() {
        self::get_instance()->send_event('deactivated');
    }

    public static function plugin_deleted() {
        self::get_instance()->send_event('deleted');
    }

    public static function private_key_activated() {
        self::get_instance()->send_event('activated');
    }

    public static function upgrade_completed($upgrader_object, $options) {
        if ( !empty($options['plugins']) && is_array($options['plugins']) ) {
            foreach( $options['plugins'] as $plugin ) {
                if( strpos($plugin, REEPAY_CHECKOUT_PLUGIN_FILE) ) {
                    self::get_instance()->send_event('updated');
                }
            }
        }
    }
}

WC_Reepay_Gateway_Statistics::get_instance();
