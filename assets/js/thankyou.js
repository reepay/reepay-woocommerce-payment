jQuery(function ($) {
    'use strict'

    let attempts = 0;
    const maxAttempts = 10;
    const delayBetweenRequests = 2000;

    var status_elm = $('#order-status-checking'),
        success_elm = $('#order-success'),
        failed_elm = $('#order-failed')

    function checkOrderStatus() {
        if (attempts >= maxAttempts) {
            $('.woocommerce-order').unblock();
            return;
        }
        
        $.ajax({
            type: 'POST',
            url: WC_Reepay_Thankyou.ajax_url,
            data: {
                action: 'reepay_order_descriptions',
                order_id: WC_Reepay_Thankyou.order_id,
                order_key: WC_Reepay_Thankyou.order_key,
            },
            success: function(response) {
                if (response.success) {
                    $('.woocommerce-order').unblock();
                    status_elm.hide()
                    success_elm.show()
                    success_elm.find('#reepay-order-details').html(response.data);
                } else {
                    attempts++;
                    setTimeout(checkOrderStatus, delayBetweenRequests);
                }
            },
            error: function() {
                attempts++;
                setTimeout(checkOrderStatus, delayBetweenRequests);
            }
        });
    }

    window.wc_reepay_thankyou = {
        xhr: false,
        attempts: 0,

        /**
         * Initialize the checking
         */
        init: function () {
            this.checkPayment(function (err, data) {
                switch (data.state) {
                    case 'paid':
                        /*
                        if(WC_Reepay_Thankyou.order_contain_rp_subscription == true && WC_Reepay_Thankyou.order_is_rp_subscription == false){
                            checkOrderStatus();
                        }else{
                            status_elm.hide()
                            success_elm.show()
                        }
                        */
                        setTimeout(function() {
                            checkOrderStatus();
                        }, 4000);
                        break
                    case 'reload':
                        setTimeout(function () {
                            location.reload()
                        }, 2000)

                        break
                    case 'failed':
                    case 'aborted':
                        status_elm.hide()
                        failed_elm.append('<p class="transaction-error">' + data.message + "</p>")
                        failed_elm.show()
                        break
                    default:
                        window.wc_reepay_thankyou.attempts++

                        if (window.wc_reepay_thankyou.attempts > 6) {
                            return
                        }

                        setTimeout(function () {
                            window.wc_reepay_thankyou.init()
                        }, 10000)
                }
            })
        },

        /**
         * Check payment
         * @return {JQueryPromise<any>}
         */
        checkPayment: function (callback) {
            $('.woocommerce-order').block({
                message: WC_Reepay_Thankyou.check_message,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            })

            return $.ajax({
                type: 'POST',
                url: WC_Reepay_Thankyou.ajax_url,
                data: {
                    action: 'reepay_check_payment',
                    nonce: WC_Reepay_Thankyou.nonce,
                    order_id: WC_Reepay_Thankyou.order_id,
                    order_key: WC_Reepay_Thankyou.order_key,
                },
                dataType: 'json'
            }).always(function () {
                
            }).done(function (response) {
                callback(null, response.data)
            })
        },
    }

    $(document).ready(function () {
        if($('.woocommerce-order--thankyou').length) {
            window.wc_reepay_thankyou.init()
        }
    })
})
