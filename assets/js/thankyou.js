jQuery(function ($) {
    'use strict';

    window.wc_reepay_thankyou = {
        xhr: false,
        attempts: 0,

        /**
         * Initialize the checking
         */
        init: function () {
            this.checkPayment(function (err, data) {
                var status_elm = $('#order-status-checking'),
                    success_elm = $('#order-success'),
                    failed_elm = $('#order-failed');

                switch (data.state) {
                    case 'paid':
                        status_elm.hide();
                        success_elm.show();
                        break;
                    case 'reload':
                        $('.woocommerce-order').block({
                            message: WC_Reepay_Thankyou.check_message,
                            overlayCSS: {
                                background: '#fff',
                                opacity: 0.6
                            }
                        });
                        setTimeout(function () {
                            location.reload();
                        }, 2000);

                        break;
                    case 'failed':
                    case 'aborted':
                        status_elm.hide();
                        failed_elm.append('<p class="transaction-error">' + data.message + "</p>");
                        failed_elm.show();
                        break;
                    default:
                        window.wc_reepay_thankyou.attempts++;

                        if (window.wc_reepay_thankyou.attempts > 6) {
                            return;
                        }

                        setTimeout(function () {
                            window.wc_reepay_thankyou.init();
                        }, 10000);
                }
            });
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
            });

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
                $('.woocommerce-order').unblock();
            }).done(function (response) {
                callback(null, response.data);
            });
        },
    };

    $(document).ready(function () {
        window.wc_reepay_thankyou.init();
    });
});
