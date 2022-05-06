<?php

class WC_Reepay_Order_Capture {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('woocommerce_after_order_itemmeta', array($this, 'add_item_capture_button'), 10, 3);
        add_action('woocommerce_after_order_fee_item_name', array($this, 'add_item_capture_button'), 10, 3);
        add_action('woocommerce_order_status_changed', array($this, 'capture_full_order'), 10, 4);
        add_action('admin_init', array($this, 'process_item_capture'));
    }

    public function capture_full_order( $order_id,  $this_status_transition_from,  $this_status_transition_to,  $instance){
        $order = wc_get_order( $order_id );
        $payment_method = $order->get_payment_method();

        if(strpos($payment_method, 'reepay') === false){
            return;
        }

        if($this_status_transition_to == 'completed'){

            foreach ( $order->get_items() as  $item_key => $item ) {
                $this->settle_item($item, $order);
            }

            foreach( $order->get_items( 'shipping' ) as $item_id => $item ){
                $this->settle_item($item, $order);
            }
        }
    }

    public function settle_item($item, $order){
        $settled = $item->get_meta('settled');
        if(empty($settled)) {
            $payment_method = $order->get_payment_method();
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            $gateway = 	$gateways[ $payment_method ];

            $line_item_data = [$this->get_item_data($item, $order)];
            $total = $item->get_data()['total'];

            try {
                if($gateway->reepay_settle( $order, $total, $line_item_data )){
                    $item->update_meta_data('settled',  $total);
                    $item->save(); // Save item
                }
            } catch ( Exception $e ) {
                $gateway->log( sprintf( '%s::%s Error: %s', __CLASS__, __METHOD__, $e->getMessage() ) );
            }
        }
    }

    public function add_item_capture_button($item_id, $item, $product){
        $order_id = wc_get_order_id_by_order_item_id( $item_id );
        $order = wc_get_order( $order_id );
        $payment_method = $order->get_payment_method();

        if(strpos($payment_method, 'reepay') === false){
            return;
        }
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $gateway = 	$gateways[ $payment_method ];
        $invoice_data = $gateway->get_invoice_data($order);

        $settled = $item->get_meta('settled');
        $data = $item->get_data();

        if(empty($settled) && floatval($data['total']) > 0 && $invoice_data['authorized_amount'] > $invoice_data['settled_amount']){
            echo '<button type="submit" class="button save_order button-primary capture-item-button" name="line_item_capture" value="'.$item_id.'">
                '.__( 'Capture', 'reepay-checkout-gateway' ).'
            </button>';
        }
    }

    public function process_item_capture(){
        if(isset($_POST['line_item_capture']) && isset($_POST['post_type']) && isset($_POST['post_ID'])){
            if($_POST['post_type'] == 'shop_order'){

                $order = wc_get_order( $_POST['post_ID'] );

                $item = WC_Order_Factory::get_order_item( $_POST['line_item_capture'] );
                $this->settle_item($item, $order);
            }
        }
    }

    public function get_item_data($item, $order){
        $cost = esc_attr( $order->get_item_subtotal( $item, false, true ) );
        $data = $item->get_data();

        $item_data = array(
            'ordertext' => $data['name'],
            'amount' => !empty($cost) ? floatval($cost) * 100 : floatval($data['total']) * 100,
            'vat' => floatval($data['total_tax']),
            'quantity' => !empty($data['quantity']) ? intval($data['quantity']) : 1,
            'amount_incl_vat' => 'true'
        );

        return $item_data;
    }
}

new WC_Reepay_Order_Capture();
