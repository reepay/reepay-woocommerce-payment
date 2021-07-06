<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

class WC_Gateway_Reepay_Mobilepay_Subscriptions extends WC_Gateway_Reepay
{
    /**
     * Logos
     * @var array
     */
    public $logos = array(
        'mobilepay',
    );

    /**
     * Payment methods.
     *
     * @var array|null
     */
    public $payment_methods = array(
        'mobilepay_subscriptions'
    );

    public function __construct() {
        $this->id           = 'reepay_mobilepay_subscriptions';
        $this->has_fields   = true;
        $this->method_title = __( 'Reepay - Mobilepay Subscriptions', 'woocommerce-gateway-reepay-checkout' );

        $this->supports     = array(
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
            'multiple_subscriptions'
        );

        $this->logos = array( 'mobilepay' );

        parent::__construct();

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables
        $this->enabled                  = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
        $this->title                    = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
        $this->description              = isset( $this->settings['description'] ) ? $this->settings['description'] : '';

        // Load setting from parent method
        $settings = $this->get_parent_settings();

        $this->private_key             = $settings['private_key'];
        $this->private_key_test        = $settings['private_key_test'];
        $this->test_mode               = $settings['test_mode'];
        $this->settle                  = $settings['settle'];
        $this->language                = $settings['language'];
        $this->debug                   = $settings['debug'];
        $this->payment_type            = $settings['payment_type'];
        $this->skip_order_lines        = $settings['skip_order_lines'];
        $this->enable_order_autocancel = $settings['enable_order_autocancel'];

        if (!is_array($this->settle)) {
            $this->settle = array();
        }

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options'
        ) );

        // Payment listener/API hook
        add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
            $this,
            'return_handler'
        ) );

        add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
            $this,
            'scheduled_subscription_payment'
        ), 10, 2 );

        // Allow store managers to manually set card id as the payment method on a subscription
        add_filter( 'woocommerce_subscription_payment_meta', array(
            $this,
            'add_subscription_payment_meta'
        ), 10, 2 );

        add_filter( 'woocommerce_subscription_validate_payment_meta', array(
            $this,
            'validate_subscription_payment_meta'
        ), 10, 3 );

        // Lock "Save card" if needs
        add_filter(
            'woocommerce_payment_gateway_save_new_payment_method_option_html',
            array(
                $this,
                'save_new_payment_method_option_html',
            ),
            10,
            2
        );

        add_action( 'wcs_save_other_payment_meta', array( $this, 'save_subscription_payment_meta' ), 10, 4 );

        // Action for "Add Payment Method"
        add_action( 'wp_ajax_reepay_ms_token_store', array( $this, 'reepay_ms_token_store' ) );

    }

    /**
     * Initialise Settings Form Fields
     * @return string|void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'        => array(
                'title'   => __( 'Enable/Disable', 'woocommerce-gateway-reepay-checkout' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable plugin', 'woocommerce-gateway-reepay-checkout' ),
                'default' => 'no'
            ),
            'title'          => array(
                'title'       => __( 'Title', 'woocommerce-gateway-reepay-checkout' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout', 'woocommerce-gateway-reepay-checkout' ),
                'default'     => __( 'Reepay - Mobilepay Subscriptions', 'woocommerce-gateway-reepay-checkout' )
            ),
            'description'    => array(
                'title'       => __( 'Description', 'woocommerce-gateway-reepay-checkout' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which the user sees during checkout', 'woocommerce-gateway-reepay-checkout' ),
                'default'     => __( 'Reepay - Mobilepay Subscriptions', 'woocommerce-gateway-reepay-checkout' ),
            ),
        );
    }

    /**
     * When a subscription payment is due.
     *
     * @param          $amount_to_charge
     * @param WC_Order $renewal_order
     */
    public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

          // Lookup token
           try {
            $token = self::get_payment_token_order( $renewal_order );

            // Try to find token in parent orders
            if ( ! $token ) {
                // Get Subscriptions
                $subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
                foreach ( $subscriptions as $subscription ) {
                    /** @var WC_Subscription $subscription */
                    $token = self::get_payment_token_order( $subscription );
                    if ( ! $token ) {
                        $token = self::get_payment_token_order( $subscription->get_parent() );
                    }
                }
            }

            // Failback: If token doesn't exist, but reepay token is here
            // We need that to provide woocommerce_subscription_payment_meta support
            // See https://github.com/Prospress/woocommerce-subscriptions-importer-exporter#importing-payment-gateway-meta-data
            if ( ! $token ) {
                $reepay_token = get_post_meta( $renewal_order->get_id(), '_reepay_token', true );

                // Try to find token in parent orders
                if ( empty( $reepay_token ) ) {
                    // Get Subscriptions
                    $subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
                    foreach ( $subscriptions as $subscription ) {
                        /** @var WC_Subscription $subscription */
                        $reepay_token = get_post_meta( $subscription->get_id(), '_reepay_token', true );
                        if ( empty( $reepay_token ) ) {
                            if ( $order = $subscription->get_parent() ) {
                                $reepay_token = get_post_meta( $order->get_id(), '_reepay_token', true );
                            }
                        }
                    }
                }

                // Save token
                if ( ! empty( $reepay_token ) ) {
                    if ( $token = $this->add_payment_token( $renewal_order, $reepay_token ) ) {
                        self::assign_payment_token( $renewal_order, $token );
                    }
                }
            }

            if ( ! $token ) {
                throw new Exception( 'Payment token isn\'t exists' );
            }

            // Validate
            if ( empty( $token->get_token() ) ) {
                throw new Exception( 'Payment token is empty' );
            }

            // Fix the reepay order value to prevent "Invoice already settled"
            $currently = get_post_meta( $renewal_order->get_id(), '_reepay_order', true );
            $shouldBe = 'order-' . $renewal_order->get_id();
            if ( $currently !== $shouldBe ) {
                update_post_meta( $renewal_order->get_id(), '_reepay_order', $shouldBe );
            }

            // Charge payment
            if ( true !== ( $result = $this->reepay_charge( $renewal_order, $token->get_token(), $amount_to_charge ) ) ) {
                throw new Exception( $result );
            }

            // Instant settle
            $this->process_instant_settle( $renewal_order );
        } catch (Exception $e) {
            $renewal_order->update_status( 'failed' );
            $renewal_order->add_order_note(
                sprintf( __( 'Error: "%s". %s.', 'woocommerce-gateway-reepay-checkout' ),
                    wc_price( $amount_to_charge ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * This payment method works only for subscription products
     * @return bool
     */
    public function is_available()
    {
        if(!parent::is_available()) {
            return false;
        }

        // need for cron to available
        if(!is_checkout()) {
            return true;
        }

        if(is_checkout() && !is_null(WC()->cart) ) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if ('subscription' == $cart_item['data']->get_type()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function add_payment_method() {
        $user            = get_userdata( get_current_user_id() );
        $customer_handle = get_user_meta( $user->ID, 'reepay_customer_id', true );

        if ( empty ( $customer_handle ) ) {
            // Create reepay customer
            $customer_handle = $this->get_customer_handle( $user->ID );
            $location = wc_get_base_location();

            $params = [
                'locale'> $this->get_language(),
                'button_text' => __( 'Add card', 'woocommerce-gateway-reepay-checkout' ),
                'create_customer' => [
                    'test' => $this->test_mode === 'yes',
                    'handle' => $customer_handle,
                    'email' => $user->user_email,
                    'address' => '',
                    'address2' => '',
                    'city' => '',
                    'country' => $location['country'],
                    'phone' => '',
                    'company' => '',
                    'vat' => '',
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'postal_code' => ''
                ],
                'accept_url' => add_query_arg( 'action', 'reepay_ms_token_store', admin_url( 'admin-ajax.php' ) ),
                'cancel_url' => wc_get_account_endpoint_url( 'payment-methods' )
            ];
        } else {
            // Use customer who exists
            $params = [
                'locale'> $this->get_language(),
                'button_text' => __( 'Add card', 'woocommerce-gateway-reepay-checkout' ),
                'customer' => $customer_handle,
                'accept_url' => add_query_arg( 'action', 'reepay_ms_token_store', admin_url( 'admin-ajax.php' ) ),
                'cancel_url' => wc_get_account_endpoint_url( 'payment-methods' )
            ];
        }

        if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
            $params['payment_methods'] = $this->payment_methods;
        }

        $result = $this->request('POST', 'https://checkout-api.reepay.com/v1/session/recurring', $params);
        $this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

        wp_redirect( $result['url'] );
        exit();
    }

    /**
     * Ajax: Add Payment Method
     * @return void
     */
    public function reepay_ms_token_store()
    {
        $id = wc_clean( $_GET['id'] );
        $customer_handle = wc_clean( $_GET['customer'] );
        $reepay_token = wc_clean( $_GET['payment_method'] );

        try {
            // Create Payment Token
            $source = $this->get_reepay_cards( $customer_handle, $reepay_token );

            $token = new WC_Payment_Token_Reepay_MS();
            $token->set_gateway_id( $this->id );
            $token->set_token( $reepay_token );
            $token->set_user_id( get_current_user_id() );

            // Save Credit Card
            $token->save();
            if ( ! $token->get_id() ) {
                throw new Exception( __( 'There was a problem adding the card.', 'woocommerce-gateway-reepay-checkout' ) );
            }

            wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-gateway-reepay-checkout' ) );
            wp_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
            exit();
        } catch (Exception $e) {
            wc_add_notice( $e->getMessage(), 'error' );
            wp_redirect( wc_get_account_endpoint_url( 'add-payment-method' ) );
            exit();
        }
    }

    /**
     * If There are no payment fields show the description if set.
     * @return void
     */
    public function payment_fields() {

        wc_get_template(
            'checkout/payment-fields.php',
            array(
                'gateway' => $this,
            ),
            '',
            dirname( __FILE__ ) . '/../templates/'
        );

        // The "Save card or use existed" form should appears when active or when the cart has a subscription
        if ( ( true /*$this->save_cc === 'yes'*/ && ! is_add_payment_method_page() ) ||
            ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() )
        ) {
            $this->tokenization_script();
            $this->saved_payment_methods();
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * Include the payment meta data required to process automatic recurring payments so that store managers can
     * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
     *
     * @param array           $payment_meta associative array of meta data required for automatic payments
     * @param WC_Subscription $subscription An instance of a subscription object
     *
     * @return array
     */
    public function add_subscription_payment_meta($payment_meta, $subscription) {
        $reepay_token = get_post_meta( $subscription->get_id(), '_reepay_token', true );

        // If token wasn't stored in Subscription
        if ( empty( $reepay_token ) ) {
            $order = $subscription->get_parent();
            if ( $order ) {
                $reepay_token = get_post_meta( $order->get_id(), '_reepay_token', true );
            }
        }

        $payment_meta[$this->id] = array(
            'post_meta' => array(
                '_reepay_token' => array(
                    'value' => $reepay_token,
                    'label' => 'Reepay Mobilepay Subscription Token',
                )
            )
        );

        return $payment_meta;

    }

    /**
     * Validate the payment meta data required to process automatic recurring payments so that store managers can
     * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
     *
     * @param string $payment_method_id The ID of the payment method to validate
     * @param array  $payment_meta      associative array of meta data required for automatic payments
     * @param WC_Subscription $subscription
     *
     * @throws Exception
     * @return array
     */
    public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
         if ( $payment_method_id === $this->id ) {
            if ( empty( $payment_meta['post_meta']['_reepay_token']['value'] ) ) {
                throw new Exception( 'A "Reepay Token" value is required.' );
            }

            $tokens = explode( ',', $payment_meta['post_meta']['_reepay_token']['value'] );
            if ( count( $tokens ) > 1 ) {
                throw new Exception( 'Only one "Reepay Token" is allowed.' );
            }

            $token = self::get_payment_token( $tokens[0] );
            if ( ! $token ) {
                throw new Exception( 'This "Reepay Token" value not found.' );
            }

            if ( $token->get_gateway_id() !== $this->id ) {
                throw new Exception( 'This "Reepay Token" value should related to Reepay.' );
            }

            if ( $token->get_user_id() !== $subscription->get_user_id() ) {
                throw new Exception( 'Access denied for this "Reepay Token" value.' );
            }
        }
    }

    /**
     * Save payment method meta data for the Subscription
     *
     * @param WC_Subscription $subscription
     * @param string $meta_table
     * @param string $meta_key
     * @param string $meta_value
     */
    public function save_subscription_payment_meta( $subscription, $meta_table, $meta_key, $meta_value ) {
        if ( $subscription->get_payment_method() === $this->id ) {
            if ( $meta_table === 'post_meta' && $meta_key === '_reepay_token' ) {
                // Add tokens
                $tokens = explode( ',', $meta_value );
                foreach ( $tokens as $reepay_token ) {
                    // Get Token ID
                    $token = self::get_payment_token( $reepay_token );
                    if ( ! $token ) {
                        // Create Payment Token
                        $token = $this->add_payment_token( $subscription, $reepay_token );
                    }

                    self::assign_payment_token( $subscription, $token );
                }
            }
        }
    }

    /**
     * Modify "Save to account" to lock that if needs.
     *
     * @param string $html
     * @param WC_Payment_Gateway $gateway
     *
     * @return string
     */
    public function save_new_payment_method_option_html( $html, $gateway ) {
        if ( $gateway->id !== $this->id ) {
            return $html;
        }

        // Lock "Save to Account" for Recurring Payments / Payment Change
        if ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() ) {
            // Load XML
            libxml_use_internal_errors( true );
            $doc = new \DOMDocument();
            $status = @$doc->loadXML( $html );
            if ( false !== $status ) {
                $item = $doc->getElementsByTagName('input')->item( 0 );
                $item->setAttribute('checked','checked' );
                $item->setAttribute('disabled','disabled' );

                $html = $doc->saveHTML($doc->documentElement);
            }
        }

        return $html;
    }

}

// Register Gateway
WC_ReepayCheckout::register_gateway( 'WC_Gateway_Reepay_Mobilepay_Subscriptions' );
