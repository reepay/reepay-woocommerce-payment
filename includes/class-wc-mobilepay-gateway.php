<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_MobilePay_Gateway extends WC_Payment_Gateway_Reepay {
    public $enabled = 'yes'; 

    public function __construct() {  
        // Get gateway variables
        $this->id = 'mobilepay_gateway';
        $this->has_fields = TRUE;
        $this->method_title = 'MobilePay gateway';
        $this->method_description = 'Pay with mobilePay';
        $this->supports           = array(
            'refunds',
            'products',
        );
        $this->init_form_fields();
        $this->init_settings();

        $this->enabled          = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
        $this->title            = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
        $this->description      = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
        
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'the_post', array( &$this, 'payment_confirm' ) );

/*  VIRKER IKKE ENDNU
        require_once ( dirname( __FILE__ ) . '/class-wc-gateway-reepay-checkout.php' );
        $WC_Gateway_Reepay_Checkout = new WC_Gateway_Reepay_Checkout;
        
        // JS Scrips
		add_action( 'wp_enqueue_scripts', array( $WC_Gateway_Reepay_Checkout, 'payment_scripts' ) );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$WC_Gateway_Reepay_Checkout,
			'process_admin_options'
		) );

		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$WC_Gateway_Reepay_Checkout,
			'thankyou_page'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$WC_Gateway_Reepay_Checkout,
			'return_handler'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$WC_Gateway_Reepay_Checkout, 'payment_confirm' ) );

		// Authorized Status
		add_filter( 'reepay_authorized_status', array(
			$WC_Gateway_Reepay_Checkout,
			'reepay_authorized_status'
        ), 10, 2 );
*/        
    }


    /**
     * init_form_fields function.
     *
     * Initiates the plugin settings form fields
     *
     * @access public
     * @return array
     */
    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Enable MobilePay', 'woocommerce' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Title', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default' => __( 'MobilePay', 'woocommerce' ),
                'desc_tip'      => true,
            ),
            'description' => array(
                'title' => __( 'Description', 'woocommerce' ),
                'type' => 'textarea',
                'default' => ''
            )
        );
    }

    function admin_options(){
        $token = md5( $this->private_key );
        ?>
        <h2><?php _e('Settings for MobilePay by Reepay','woocommerce'); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html( $this->get_form_fields(), true ); ?>
        </table> <?php
    }

    function process_payment( $order_id ) {
        require_once ( dirname( __FILE__ ) . '/class-wc-gateway-reepay-checkout.php' );
        $WC_Gateway_Reepay_Checkout = new WC_Gateway_Reepay_Checkout;
        return $WC_Gateway_Reepay_Checkout->process_payment( $order_id );
    }

    function payment_confirm(){
        require_once ( dirname( __FILE__ ) . '/class-wc-gateway-reepay-checkout.php' );
        $WC_Gateway_Reepay_Checkout = new WC_Gateway_Reepay_Checkout;
        return $WC_Gateway_Reepay_Checkout->payment_confirm();
    } 

}


// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_MobilePay_Gateway' );
