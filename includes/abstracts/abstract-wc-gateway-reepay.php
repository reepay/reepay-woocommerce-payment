<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

abstract class WC_Gateway_Reepay extends WC_Payment_Gateway implements WC_Payment_Gateway_Reepay_Interface
{
    use WC_Reepay_Token;

    /**
     * @var WC_Reepay_Api
     */
    public $api;

    /**
     * Test Mode
     * @var string
     */
    public $test_mode = 'yes';

    /**
     * @var string
     */
    public $private_key;

    /**
     * @var
     */
    public $private_key_test;

    /**
     * @var string
     */

    public $public_key;

    /**
     * Settle
     * @var string
     */
    public $settle = array(
        WC_Reepay_Instant_Settle::SETTLE_VIRTUAL,
        WC_Reepay_Instant_Settle::SETTLE_PHYSICAL,
        WC_Reepay_Instant_Settle::SETTLE_RECURRING,
        WC_Reepay_Instant_Settle::SETTLE_FEE
    );

    /**
     * Debug Mode
     * @var string
     */
    public $debug = 'yes';

    /**
     * Language
     * @var string
     */
    public $language = 'en_US';

    /**
     * Logos
     * @var array
     */
    public $logos = array(
        'dankort',
        'visa',
        'mastercard',
        'visa-electron',
        'maestro',
        'mobilepay',
        'viabill',
        'applepay',
        'paypal_logo',
        'klarna-pay-later',
        'klarna-pay-now',
        'klarna',
        'resursbank'
    );

    /**
     * Payment Type
     * @var string
     */
    public $payment_type = 'OVERLAY';

    /**
     * Save CC
     * @var string
     */
    public $save_cc = 'yes';

    /**
     * Logo Height
     * @var string
     */
    public $logo_height = '';

    /**
     * Skip order lines to Reepay and use order totals instead
     */
    public $skip_order_lines = 'no';

    /**
     * If automatically cancel unpaid orders should be ignored
     */
    public $enable_order_autocancel = 'no';

    /**
     * Email address for notification about failed webhooks
     * @var string
     */
    public $failed_webhooks_email = '';

    /**
     * If webhooks have been configured
     * @var string
     */
    public $is_webhook_configured = 'no';

    /**
     * Order handle failover.
     * @var string
     */
    public $handle_failover = 'yes';

    /**
     * Init
     */
    public function __construct()
    {

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables
        $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'no';
        $this->title = isset($this->settings['title']) ? $this->settings['title'] : '';
        $this->description = isset($this->settings['description']) ? $this->settings['description'] : '';

        $this->api = new WC_Reepay_Api($this);

        add_action('admin_notices', array($this, 'admin_notice_warning'));

        add_action('admin_notices', array($this, 'admin_notice_api_action'));

        // JS Scrips
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Payment confirmation
        add_action('the_post', array($this, 'payment_confirm'));

        // Cancel actions
        add_action('wp_ajax_reepay_cancel_payment', array($this, 'reepay_cancel_payment'));
        add_action('wp_ajax_nopriv_reepay_cancel_payment', array($this, 'reepay_cancel_payment'));
    }

    /**
     * Check is Capture possible
     *
     * @param WC_Order|int $order
     * @param bool $amount
     *
     * @return bool
     * @api
     */
    public function can_capture($order, $amount = false)
    {
        return $this->api->can_capture($order, $amount);
    }

    /**
     * Check is Cancel possible
     *
     * @param WC_Order|int $order
     *
     * @return bool
     * @api
     */
    public function can_cancel($order)
    {
        return $this->api->can_cancel($order);
    }

    /**
     * @param \WC_Order $order
     * @param bool $amount
     *
     * @return bool
     * @api
     */
    public function can_refund($order, $amount = false)
    {
        return $this->api->can_refund($order, $amount);
    }

    /**
     * Capture.
     *
     * @param WC_Order|int $order
     * @param float|false $amount
     *
     * @return void
     * @throws Exception
     * @api
     */
    public function capture_payment($order, $amount)
    {
        // Check if the order is cancelled - if so - then return as nothing has happened
        if ('1' === $order->get_meta('_reepay_order_cancelled')) {
            throw new Exception('Order is already canceled');
        }

        $result = $this->api->capture_payment($order, $amount);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
    }

    /**
     * Cancel.
     *
     * @param WC_Order|int $order
     *
     * @return void
     * @throws Exception
     */
    public function cancel_payment($order)
    {
        // Check if hte order is cancelled - if so - then return as nothing has happened
        if ('1' === $order->get_meta('_reepay_order_cancelled')) {
            throw new Exception('Order is already canceled');
        }

        $result = $this->api->cancel($order);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        add_action('the_post', array($this, 'payment_confirm'));

        // Cancel actions
        add_action('wp_ajax_reepay_cancel', array($this, 'reepay_cancel'));
        add_action('wp_ajax_nopriv_reepay_cancel', array($this, 'reepay_cancel'));
    }

    /**
     * Refund
     *
     * @param WC_Order|int $order
     * @param bool $amount
     * @param string $reason
     *
     * @return void
     * @throws Exception
     */
    public function refund_payment($order, $amount = false, $reason = '')
    {
        if (is_int($order)) {
            $order = wc_get_order($order);
        }

        // Check if the order is cancelled - if so - then return as nothing has happened
        if ('1' === $order->get_meta('_reepay_order_cancelled', true)) {
            throw new Exception('Order is already canceled');
        }

        if (!$this->can_refund($order, $amount)) {
            throw new Exception('Payment can\'t be refunded.');
        }

        $result = $this->api->refund($order, $amount, $reason);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
    }

    /**
     * Add notifications in admin for api actions.
     */
    public function admin_notice_api_action()
    {
        if ('yes' === $this->enabled) {
            $error = get_transient('reepay_api_action_error');
            $success = get_transient('reepay_api_action_success');

            if (!empty($error)):
                ?>
                <div class="error notice is-dismissible">
                    <p><?php echo esc_html($error); ?></p>
                </div>
                <?php
                set_transient('reepay_api_action_error', null, 1);
            endif;

            if (!empty($success)):
                ?>
                <div class="notice-success notice is-dismissible">
                    <p><?php echo esc_html($success); ?></p>
                </div>
                <?php
                set_transient('reepay_api_action_success', null, 1);
            endif;
        }
    }

    /**
     * Admin notice warning
     */
    public function admin_notice_warning()
    {
        if ('yes' === $this->enabled && !is_ssl()) {
            $message = __('Reepay is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid', 'reepay-checkout-gateway');
            $message_href = __('SSL certificate', 'reepay-checkout-gateway');
            $url = 'https://en.wikipedia.org/wiki/Transport_Layer_Security';
            printf('<div class="notice notice-warning is-dismissible"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>',
                esc_html($message),
                esc_html($url),
                esc_html($message_href)
            );
        }
    }

    /**
     * Return the gateway's icon.
     *
     * @return string
     */
    public function get_icon()
    {
        $html = '';
        $logos = array_filter((array)$this->logos, 'strlen');
        if (count($logos) > 0) {
            $html = '<ul class="reepay-logos">';
            foreach ($logos as $logo) {
                $html .= '<li class="reepay-logo">';
                $html .= '<img src="' . esc_url(plugins_url('/assets/images/' . $logo . '.png', dirname(__FILE__) . '/../../../')) . '" alt="' . esc_attr(sprintf(__('Pay with %s on Reepay', 'reepay-checkout-gateway'), $this->get_title())) . '" />';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        return apply_filters('woocommerce_gateway_icon', $html, $this->id);
    }

    /**
     * payment_scripts function.
     *
     * Outputs scripts used for payment
     *
     * @return void
     */
    public function payment_scripts()
    {
        if (!is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page()) {
            return;
        }

        if (is_order_received_page()) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_script('reepay-checkout', 'https://checkout.reepay.com/checkout.js', array(), false, false);
        wp_register_script('wc-gateway-reepay-checkout', untrailingslashit(plugins_url('/', __FILE__)) . '/../../assets/js/checkout' . $suffix . '.js', array(
            'jquery',
            'wc-checkout',
            'reepay-checkout',
        ), false, true);

        // Localize the script with new data
        $translation_array = array(
            'payment_type' => $this->payment_type,
            'public_key' => $this->public_key,
            'language' => substr($this->get_language(), 0, 2),
            'buttonText' => __('Pay', 'reepay-checkout-gateway'),
            'recurring' => true,
            'nonce' => wp_create_nonce('reepay'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'cancel_text' => __('Payment was canceled, please try again', 'reepay-checkout-gateway'),
            'error_text' => __('Error with payment, please try again', 'reepay-checkout-gateway'),
        );
        wp_localize_script('wc-gateway-reepay-checkout', 'WC_Gateway_Reepay_Checkout', $translation_array);

        // Enqueued script with localized data.
        wp_enqueue_script('wc-gateway-reepay-checkout');
    }

    /**
     * If There are no payment fields show the description if set.
     * @return void
     */
    public function payment_fields()
    {
        wc_get_template(
            'checkout/payment-fields.php',
            array(
                'gateway' => $this,
            ),
            '',
            dirname(__FILE__) . '/../../templates/'
        );
    }

    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        return true;
    }


    /**
     * Process Payment
     *
     * @param int $order_id
     *
     * @return array|false
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $token_id = isset($_POST['wc-' . $this->id . '-payment-token']) ? wc_clean($_POST['wc-' . $this->id . '-payment-token']) : 'new';

        if (isset($_POST['wc-' . $this->id . '-new-payment-method'])) {
            $maybe_save_card = (bool)$_POST['wc-' . $this->id . '-new-payment-method'];
        } else {
            $maybe_save_card = wcs_cart_have_subscription();
        }

        if ('yes' !== $this->save_cc) {
            $token_id = 'new';
            $maybe_save_card = false;
        }
        $WC_Countries = new WC_Countries();
        $country = '';
        if (method_exists($WC_Countries, 'country_exists')) {
            $country = $WC_Countries->country_exists($order->get_billing_country()) ? $order->get_billing_country() : '';
            if ($order->needs_shipping_address()) {
                $country = $WC_Countries->country_exists($order->get_shipping_country()) ? $order->get_shipping_country() : '';
            }
        }

        // Switch of Payment Method
        if (wcs_is_payment_change()) {
            $customer_handle = $this->api->get_customer_handle_order($order_id);

            if (absint($token_id) > 0) {
                $token = new WC_Payment_Token_Reepay($token_id);
                if (!$token->get_id()) {
                    wc_add_notice(__('Failed to load token.', 'reepay-checkout-gateway'), 'error');

                    return false;
                }

                // Check access
                if ($token->get_user_id() !== $order->get_user_id()) {
                    wc_add_notice(__('Access denied.', 'reepay-checkout-gateway'), 'error');

                    return false;
                }

                // Replace token
                try {
                    self::assign_payment_token($order, $token);
                } catch (Exception $e) {
                    $order->add_order_note($e->getMessage());

                    return array(
                        'result' => 'failure',
                        'message' => $e->getMessage()
                    );
                }

                // Add note
                $order->add_order_note(
                    sprintf(
                        __('Payment method changed to "%s"', 'reepay-checkout-gateway'),
                        $token->get_display_name()
                    )
                );

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                // Add new Card
                $params = [
                    'locale' => $this->get_language(),
                    'button_text' => __('Add card', 'reepay-checkout-gateway'),
                    'create_customer' => [
                        'test' => $this->test_mode === 'yes',
                        'handle' => $customer_handle,
                        'email' => $order->get_billing_email(),
                        'address' => $order->get_billing_address_1(),
                        'address2' => $order->get_billing_address_2(),
                        'city' => $order->get_billing_city(),
                        'phone' => $order->get_billing_phone(),
                        'company' => $order->get_billing_company(),
                        'vat' => '',
                        'first_name' => $order->get_billing_first_name(),
                        'last_name' => $order->get_billing_last_name(),
                        'postal_code' => $order->get_billing_postcode()
                    ],
                    'accept_url' => add_query_arg(
                        array(
                            'action' => 'reepay_finalize',
                            'key' => $order->get_order_key()
                        ),
                        admin_url('admin-ajax.php')
                    ),
                    'cancel_url' => $order->get_cancel_order_url()
                ];

                if (!empty($country)) {
                    $params['create_customer']['country'] = $country;
                }

                if ($this->payment_methods && count($this->payment_methods) > 0) {
                    $params['payment_methods'] = $this->payment_methods;
                }

                $result = $this->api->request(
                    'POST',
                    'https://checkout-api.reepay.com/v1/session/recurring',
                    $params
                );
                if (is_wp_error($result)) {
                    /** @var WP_Error $result */
                    return array(
                        'result' => 'failure',
                        'message' => $result->get_error_message()
                    );
                }

                return array(
                    'result' => 'success',
                    'redirect' => $result['url']
                );
            }
        }

        // Try to charge with saved token
        if (absint($token_id) > 0) {
            $token = new WC_Payment_Token_Reepay($token_id);
            if (!$token->get_id()) {
                wc_add_notice(__('Failed to load token.', 'reepay-checkout-gateway'), 'error');

                return false;
            }

            // Check access
            if ($token->get_user_id() !== $order->get_user_id()) {
                wc_add_notice(__('Access denied.', 'reepay-checkout-gateway'), 'error');

                return false;
            }

            if (abs($order->get_total()) < 0.01) {
                // Don't charge payment if zero amount
                $order->payment_complete();
            } else {
                // Charge payment
                $result = $this->api->charge($order, $token->get_token(), $order->get_total(), $order->get_currency());
                if (is_wp_error($result)) {
                    wc_add_notice($result->get_error_message(), 'error');

                    return false;
                }

                // Settle the charge
                do_action('reepay_instant_settle', $order);
            }

            try {
                self::assign_payment_token($order, $token->get_id());
            } catch (Exception $e) {
                $order->add_order_note($e->getMessage());
            }

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        // "Save Card" flag
        $order->update_meta_data('_reepay_maybe_save_card', $maybe_save_card);
        $order->save_meta_data();

        // Get Customer reference
        $customer_handle = $this->api->get_customer_handle_order($order->get_id());

        // If here's Subscription or zero payment
        if (abs($order->get_total()) < 0.01 || wcs_cart_only_subscriptions()) {
            $params = [
                'locale' > $this->get_language(),
                'button_text' => __('Pay', 'woocommerce-gateway-reepay-checkout'),
                'create_customer' => [
                    'test' => $this->test_mode === 'yes',
                    'handle' => $customer_handle,
                    'email' => $order->get_billing_email(),
                    'address' => $order->get_billing_address_1(),
                    'address2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'phone' => $order->get_billing_phone(),
                    'company' => $order->get_billing_company(),
                    'vat' => '',
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'postal_code' => $order->get_billing_postcode()
                ],
                'accept_url' => $this->get_return_url($order),
                'cancel_url' => $order->get_cancel_order_url()
            ];
            if (!empty($country)) {
                $params['create_customer']['country'] = $country;
            }

            if ($this->payment_methods && count($this->payment_methods) > 0) {
                $params['payment_methods'] = $this->payment_methods;
            }

            $result = $this->api->request(
                'POST',
                'https://checkout-api.reepay.com/v1/session/recurring',
                $params
            );
            if (is_wp_error($result)) {
                /** @var WP_Error $result */
                throw new Exception($result->get_error_message(), $result->get_error_code());
            }

            return array(
                'result' => 'success',
                'redirect' => '#!reepay-checkout',
                'is_reepay_checkout' => true,
                'reepay' => $result,
                'accept_url' => $this->get_return_url($order),
                'cancel_url' => $order->get_cancel_order_url()
            );
        }

        $order_handle = rp_get_order_handle($order);


        // Initialize Payment
        $params = [
            'locale' => $this->get_language(),
            'recurring' => $maybe_save_card || order_contains_subscription($order) || wcs_is_payment_change(),
            'order' => [
                'handle' => $order_handle,
                'amount' => $this->skip_order_lines === 'yes' ? rp_prepare_amount($order->get_total(), $order->get_currency()) : null,
                'order_lines' => $this->skip_order_lines === 'no' ? $this->get_order_items($order) : null,
                'currency' => $order->get_currency(),
                'customer' => [
                    'test' => $this->test_mode === 'yes',
                    'handle' => $customer_handle,
                    'email' => $order->get_billing_email(),
                    'address' => $order->get_billing_address_1(),
                    'address2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'phone' => $order->get_billing_phone(),
                    'company' => $order->get_billing_company(),
                    'vat' => '',
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'postal_code' => $order->get_billing_postcode()
                ],
                'billing_address' => [
                    'attention' => '',
                    'email' => $order->get_billing_email(),
                    'address' => $order->get_billing_address_1(),
                    'address2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'phone' => $order->get_billing_phone(),
                    'company' => $order->get_billing_company(),
                    'vat' => '',
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'postal_code' => $order->get_billing_postcode(),
                    'state_or_province' => $order->get_billing_state()
                ],
            ],
            'accept_url' => $this->get_return_url($order),
            'cancel_url' => $order->get_cancel_order_url(),
        ];

        if ($params['recurring']) {
            $params['button_text'] = __('PAY AND SAVE CARD', 'reepay-checkout-gateway');
        }

        if (!empty($country)) {
            $params['order']['customer']['country'] = $country;
            $params['order']['billing_address']['country'] = $country;
        }

        // skip order lines if calculated amount not equal to total order amount
        /*if ($this->get_calculated_amount($order) != rp_prepare_amount($order->get_total(), $order->get_currency())) {
            $params['order']['amount'] = rp_prepare_amount($order->get_total(), $order->get_currency());
            $params['order']['order_lines'] = null;
        }*/

        if ($this->payment_methods && count($this->payment_methods) > 0) {
            $params['payment_methods'] = $this->payment_methods;
        }

        if ($order->needs_shipping_address()) {

            $params['order']['shipping_address'] = [
                'attention' => '',
                'email' => $order->get_billing_email(),
                'address' => $order->get_shipping_address_1(),
                'address2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'phone' => $order->get_billing_phone(),
                'company' => $order->get_shipping_company(),
                'vat' => '',
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'postal_code' => $order->get_shipping_postcode(),
                'state_or_province' => $order->get_shipping_state()
            ];

            if (!empty($country)) {
                $params['order']['shipping_address']['country'] = $country;
            }

//			if (!strlen($params['order']['shipping_address'])) {
//				$params['order']['shipping_address'] = $params['order']['billing_address'];
//			}
        }

        if ($order->get_payment_method() == 'reepay_mobilepay_subscriptions') {
            $params['parameters']['mps_ttl'] = "PT24H";
        }

        $result = $this->api->request(
            'POST',
            'https://checkout-api.reepay.com/v1/session/charge',
            $params
        );

        if (!empty($customer_handle) && $order->get_customer_id() == 0) {
            $this->api->request(
                'PUT',
                'https://api.reepay.com/v1/customer/' . $customer_handle,
                $params['order']['customer']
            );
        }

        if (is_wp_error($result)) {
            /** @var WP_Error $result */
            if ('yes' === $this->handle_failover) {
                // invoice with handle $params['order']['handle'] already exists and authorized/settled
                // try to create another invoice with unique handle in format order-id-time()
                if (in_array($result->get_error_code(), [105, 79, 29, 99, 72])) {
                    $handle = rp_get_order_handle($order, true);
                    $params['order']['handle'] = $handle;

                    $result = $this->api->request(
                        'POST',
                        'https://checkout-api.reepay.com/v1/session/charge',
                        $params
                    );
                    if (is_wp_error($result)) {
                        /** @var WP_Error $result */
                        return array(
                            'result' => 'failure',
                            'message' => $result->get_error_message()
                        );
                    }
                }
            } else {
                return array(
                    'result' => 'failure',
                    'message' => $result->get_error_message()
                );
            }
        }

        if (is_checkout_pay_page()) {
            if ($this->payment_type === self::METHOD_OVERLAY) {
                return array(
                    'result' => 'success',
                    'redirect' => sprintf('#!reepay-pay?rid=%s&accept_url=%s&cancel_url=%s',
                        $result['id'],
                        html_entity_decode($this->get_return_url($order)),
                        html_entity_decode($order->get_cancel_order_url())
                    ),
                );
            } else {
                return array(
                    'result' => 'success',
                    'redirect' => $result['url'],
                );
            }
        }

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message(), $result->get_error_code());
        } else {
            return array(
                'result' => 'success',
                'redirect' => '#!reepay-checkout',
                'is_reepay_checkout' => true,
                'reepay' => $result,
                'accept_url' => $this->get_return_url($order),
                'cancel_url' => add_query_arg(
                    array('action' => 'reepay_cancel', 'order_id' => $order->get_id()),
                    admin_url('admin-ajax.php')
                )
            );
        }


    }

    /**
     * Ajax: Cancel Payment
     *
     * @throws Exception
     */
    public function reepay_cancel()
    {
        if (!isset($_GET['order_id'])) {
            return;
        }

        $order = wc_get_order(wc_clean($_GET['order_id']));
        $gateway = rp_get_payment_method($order);
        $result = $gateway->api->get_invoice_data($order);
        if (is_wp_error($result)) {
            return;
        }

        if ('failed' == $result['state']) {
            if (count($result['transactions']) > 0 &&
                isset($result['transactions'][0]['card_transaction']['acquirer_message'])
            ) {
                $message = $result['transactions'][0]['card_transaction']['acquirer_message'];

                $order->add_order_note('Payment failed. Error from acquire: ' . $message);
                wc_add_notice(__('Payment error: ', 'error') . $message, 'error');
            }

            wp_redirect(wc_get_cart_url());
            exit();
        }
    }

    /**
     * Payment confirm action
     * @return void
     */
    public function payment_confirm()
    {
        if (!(is_wc_endpoint_url('order-received'))) {
            return;
        }

        if (empty($_GET['id'])) {
            return;
        }

        if (empty($_GET['key'])) {
            return;
        }

        if (!$order_id = wc_get_order_id_by_order_key($_GET['key'])) {
            wc_add_notice(__('Cannot found the order', 'reepay-checkout-gateway'), 'error');
            return;
        }

        if (!$order = wc_get_order($order_id)) {
            wc_add_notice(__('Cannot found the order', 'reepay-checkout-gateway'), 'error');
            return;
        }

        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $this->log(sprintf('accept_url: Incoming data: %s', var_export($_GET, true)));

        // Save Payment Method
        $maybe_save_card = $order->get_meta('_reepay_maybe_save_card');

        if (!empty($_GET['payment_method']) && ($maybe_save_card || order_contains_subscription($order))) {
            $this->reepay_save_token($order, wc_clean($_GET['payment_method']));
        }

        // @see WC_Reepay_Thankyou::thankyou_page()
    }

    /**
     * WebHook Callback
     * @return void
     */
    public function return_handler()
    {
        try {
            $raw_body = file_get_contents('php://input');
            $this->log(sprintf('WebHook: Initialized %s from %s', $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR']));
            $this->log(sprintf('WebHook: Post data: %s', var_export($raw_body, true)));
            $data = @json_decode($raw_body, true);
            if (!$data) {
                throw new Exception(__('Missing parameters', 'reepay-checkout-gateway'));
            }

            // Get Secret
            if (!($secret = get_transient('reepay_webhook_settings_secret'))) {
                $result = $this->api->request('GET', 'https://api.reepay.com/v1/account/webhook_settings');
                if (is_wp_error($result)) {
                    /** @var WP_Error $result */
                    throw new Exception($result->get_error_message(), $result->get_error_code());
                }

                $secret = $result['secret'];

                set_transient('reepay_webhook_settings_secret', $secret, HOUR_IN_SECONDS);
            }

            // Verify secret
            $check = bin2hex(hash_hmac('sha256', $data['timestamp'] . $data['id'], $secret, true));
            if ($check !== $data['signature']) {
                throw new Exception(__('Signature verification failed', 'reepay-checkout-gateway'));
            }

            (new WC_Reepay_Webhook($data))->process();

            http_response_code(200);
        } catch (Exception $e) {
            $this->log(sprintf('WebHook: Error: %s', $e->getMessage()));
            http_response_code(200);
        }
    }

    /**
     * Enqueue the webhook processing.
     *
     * @param $raw_body
     *
     * @return void
     */
    public function enqueue_webhook_processing($raw_body)
    {
        $data = @json_decode($raw_body, true);

        // Create Background Process Task
        $background_process = new WC_Background_Reepay_Queue();
        $background_process->push_to_queue(
            array(
                'payment_method_id' => $this->id,
                'webhook_data' => $raw_body,
            )
        );
        $background_process->save();

        $this->log(
            sprintf('WebHook: Task enqueued. ID: %s',
                $data['id']
            )
        );
    }

    /**
     * Get parent settings
     *
     * @return array
     */
    protected function get_parent_settings()
    {
        // Get setting from parent method
        $settings = get_option('woocommerce_reepay_checkout_settings');
        if (!is_array($settings)) {
            $settings = array();
        }

        if (isset($settings['private_key'])) {
            $settings['private_key'] = apply_filters('woocommerce_reepay_private_key', $settings['private_key']);
        }

        if (isset($settings['private_key_test'])) {
            $settings['private_key_test'] = apply_filters('woocommerce_reepay_private_key_test', $settings['private_key_test']);
        }

        return array_merge(array(
            'enabled' => 'no',
            'private_key' => $this->private_key,
            'private_key_test' => $this->private_key_test,
            'test_mode' => $this->test_mode,
            'payment_type' => $this->payment_type,
            'payment_methods' => $this->payment_methods,
            'settle' => $this->settle,
            'language' => $this->language,
            'save_cc' => $this->save_cc,
            'debug' => $this->debug,
            'logos' => $this->logos,
            'logo_height' => $this->logo_height,
            'skip_order_lines' => $this->skip_order_lines,
            'enable_order_autocancel' => $this->enable_order_autocancel,
            'is_webhook_configured' => isset($settings['is_webhook_configured']) ?
                $settings['is_webhook_configured'] : $this->is_webhook_configured
        ), $settings);
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     *
     * @return array
     * @see WC_Log_Levels
     * Get parent settings
     *
     */
    public function log($message, $level = 'info')
    {
        // Is Enabled
        if ('yes' !== $this->debug) {
            return;
        }

        // Get Logger instance
        $logger = wc_get_logger();

        // Write message to log
        if (!is_string($message)) {
            $message = var_export($message, true);
        }

        $logger->log($level, $message, array(
            'source' => $this->id,
            '_legacy' => true
        ));
    }

    /**
     * Process Refund
     *
     * If the gateway declares 'refunds' support, this will allow it to refund
     * a passed in amount.
     *
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     *
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        // Full Refund
        if (is_null($amount)) {
            $amount = $order->get_total();
        }

        try {
            $this->refund_payment($order, $amount, $reason);

            return true;
        } catch (Exception $e) {
            return new WP_Error('refund', $e->getMessage());
        }
    }

    /**
     * Get Order lines.
     *
     * @param WC_Order $order
     *
     * @return array
     */
    public function get_order_items($order)
    {
        $prices_incl_tax = wc_prices_include_tax();

        $items = [];
        foreach ($order->get_items() as $order_item) {
            /** @var WC_Order_Item_Product $order_item */

            if (wcs_is_subscription_product($order_item->get_product()) || wcr_is_subscription_product($order_item->get_product())) {
                continue;
            }

            $price = $order->get_line_subtotal($order_item, false, false);
            $priceWithTax = $order->get_line_subtotal($order_item, true, false);
            $tax = $priceWithTax - $price;
            $taxPercent = ($tax > 0) ? round(100 / ($price / $tax)) : 0;
            $unitPrice = round(($prices_incl_tax ? $priceWithTax : $price) / $order_item->get_quantity(), 2);

            $items[] = array(
                'ordertext' => $order_item->get_name(),
                'quantity' => $order_item->get_quantity(),
                'amount' => rp_prepare_amount($unitPrice, $order->get_currency()),
                'vat' => round($taxPercent / 100, 2),
                'amount_incl_vat' => $prices_incl_tax
            );
        }

        // Add Shipping Line
        if ((float)$order->get_shipping_total() > 0) {
            $shipping = $order->get_shipping_total();
            $tax = $order->get_shipping_tax();
            $shippingWithTax = $shipping + $tax;
            $taxPercent = ($tax > 0) ? round(100 / ($shipping / $tax)) : 0;

            $items[] = array(
                'ordertext' => $order->get_shipping_method(),
                'quantity' => 1,
                'amount' => rp_prepare_amount($prices_incl_tax ? $shippingWithTax : $shipping, $order->get_currency()),
                'vat' => round($taxPercent / 100, 2),
                'amount_incl_vat' => $prices_incl_tax
            );
        }

        // Add fee lines
        foreach ($order->get_fees() as $order_fee) {
            /** @var WC_Order_Item_Fee $order_fee */
            $fee = $order_fee->get_total();
            $tax = $order_fee->get_total_tax();
            $feeWithTax = $fee + $tax;
            $taxPercent = ($tax > 0) ? round(100 / ($fee / $tax)) : 0;

            $items[] = array(
                'ordertext' => $order_fee->get_name(),
                'quantity' => 1,
                'amount' => rp_prepare_amount($prices_incl_tax ? $feeWithTax : $fee, $order->get_currency()),
                'vat' => round($taxPercent / 100, 2),
                'amount_incl_vat' => $prices_incl_tax
            );
        }

        // Add discount line
        if ($order->get_total_discount(false) > 0) {
            $discount = $order->get_total_discount(true);
            $discountWithTax = $order->get_total_discount(false);
            $tax = $discountWithTax - $discount;
            $taxPercent = ($tax > 0) ? round(100 / ($discount / $tax)) : 0;

            $items[] = array(
                'ordertext' => __('Discount', 'reepay-checkout-gateway'),
                'quantity' => 1,
                'amount' => round(-1 * rp_prepare_amount($prices_incl_tax ? $discountWithTax : $discount, $order->get_currency())),
                'vat' => round($taxPercent / 100, 2),
                'amount_incl_vat' => $prices_incl_tax
            );
        }

        // Add "Gift Up!" discount
        if (defined('GIFTUP_ORDER_META_CODE_KEY') &&
            defined('GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY')
        ) {
            if ($order->meta_exists(GIFTUP_ORDER_META_CODE_KEY)) {
                $code = $order->get_meta(GIFTUP_ORDER_META_CODE_KEY);
                $requested_balance = $order->get_meta(GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY);

                if ($requested_balance > 0) {
                    $items[] = array(
                        'ordertext' => sprintf(__('Gift card (%s)', 'reepay-checkout-gateway'), $code),
                        'quantity' => 1,
                        'amount' => rp_prepare_amount(-1 * $requested_balance, $order->get_currency()),
                        'vat' => 0,
                        'amount_incl_vat' => $prices_incl_tax
                    );
                }
            }
        }

        return $items;
    }

    /**
     * calculate amount from order rows that
     * can be compared with amount from order
     *
     * @param $order
     * @return float|int
     */
    private function get_calculated_amount($order)
    {
        $order_items = $this->get_order_items($order);
        $order_total = 0;

        foreach ($order_items as $item) {
            $order_total += $item['amount'] * $item['quantity'];
        }

        return $order_total;
    }

    /**
     * Get Language
     * @return string
     */
    protected function get_language()
    {
        if (!empty($this->language)) {
            return $this->language;
        }

        $locale = get_locale();
        if (in_array(
            $locale,
            array('en_US', 'da_DK', 'sv_SE', 'no_NO', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL')
        )) {
            return $locale;
        }

        return 'en_US';
    }

    /**
     * Converts a Reepay card_type into a logo.
     *
     * @param string $card_type is the Reepay card type
     *
     * @return string the logo
     */
    public function get_logo($card_type)
    {
        switch ($card_type) {
            case 'visa':
                $image = 'visa.png';
                break;
            case 'mc':
                $image = 'mastercard.png';
                break;
            case 'dankort':
            case 'visa_dk':
                $image = 'dankort.png';
                break;
            case 'ffk':
                $image = 'forbrugsforeningen.png';
                break;
            case 'visa_elec':
                $image = 'visa-electron.png';
                break;
            case 'maestro':
                $image = 'maestro.png';
                break;
            case 'amex':
                $image = 'american-express.png';
                break;
            case 'diners':
                $image = 'diners.png';
                break;
            case 'discover':
                $image = 'discover.png';
                break;
            case 'jcb':
                $image = 'jcb.png';
                break;
            case 'mobilepay':
            case 'ms_subscripiton':
                $image = 'mobilepay.png';
                break;
            case 'viabill':
                $image = 'viabill.png';
                break;
            case 'klarna_pay_later':
            case 'klarna_pay_now':
                $image = 'klarna.png';
                break;
            case 'resurs':
                $image = 'resurs.png';
                break;
            case 'china_union_pay':
                $image = 'cup.png';
                break;
            case 'paypal':
                $image = 'paypal.png';
                break;
            case 'applepay':
                $image = 'applepay.png';
                break;
            case 'googlepay':
                $image = 'googlepay.png';
                break;
            case 'vipps':
                $image = 'vipps.png';
                break;
            default:
                //$image = 'reepay.png';
                // Use an image of payment method
                $logos = $this->logos;
                $logo = array_shift($logos);

                return untrailingslashit(plugins_url('/', __FILE__)) . '/../../assets/images/' . $logo . '.png';
        }

        return untrailingslashit(plugins_url('/', __FILE__)) . '/../../assets/images/' . $image;
    }
}
