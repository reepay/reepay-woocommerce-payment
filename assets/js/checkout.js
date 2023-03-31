/* global wc_checkout_params */
/* global Reepay */

// Preload ModalCheckout
window.rp = new Reepay.ModalCheckout();

jQuery(function ($) {
    'use strict';

    // wc_checkout_params is required to continue, ensure the object exists
    if (typeof wc_checkout_params === 'undefined') {
        return false;
    }

    jQuery('form.checkout').on('checkout_place_order_success', function (e, result) {

        if (result.hasOwnProperty('is_reepay_checkout')) {
            try {
                wc_reepay.buildModalCheckout(result.reepay.id, result.accept_url);
            } catch (e) {
                console.warn(e);

                return false;
            }
        }


        return true;
    });

    $(document).ready(function () {
        if (window.location.hash.indexOf('#!reepay-pay') > -1) {
            const url = document.location.hash.replace('#!reepay-pay', ''),
                params = new URLSearchParams(url);

            let rid = params.get('rid'),
                accept_url = params.get('accept_url');

            window.setTimeout(function () {
                wc_reepay.buildModalCheckout(rid, accept_url);
                history.pushState('', document.title, window.location.pathname);
            }, 300);
        }
    });
});

wc_reepay = {
    /**
     * Build Modal Checkout
     *
     * @param reepay_id
     * @param accept_url
     */
    buildModalCheckout: function (reepay_id, accept_url) {
        if (WC_Gateway_Reepay_Checkout.payment_type === 'WINDOW') {
            new Reepay.WindowCheckout(reepay_id); // redirect to payment page
            return;
        }

        window.rp.show(reepay_id);

        window.rp.addEventHandler(Reepay.Event.Accept, function (data) {
            console.log('Accept', data);

            let redirect_url = accept_url;
            for (let prop in data) {
                redirect_url = wc_reepay.setUrlParameter(redirect_url, prop, data[prop]);
            }

            window.location.href = redirect_url;
        });

        window.rp.addEventHandler(Reepay.Event.Cancel, function (data) {
            console.log('Cancel', data);
            wc_reepay.throw_error(WC_Gateway_Reepay_Checkout.cancel_text)
        });

        window.rp.addEventHandler(Reepay.Event.Close, function (data) {
            var form = jQuery('form.checkout');
            form.removeClass('processing').unblock();
            console.log('Close', data);
        });

        window.rp.addEventHandler(Reepay.Event.Error, function (data) {
            console.log('Error', data);
            wc_reepay.throw_error(WC_Gateway_Reepay_Checkout.error_text)
        });
    },

    throw_error: function (errorThrown) {
        jQuery(function ($) {
            var form = $('form.checkout');
            form.removeClass('processing').unblock();
            $('.woocommerce-NoticeGroup').remove();
            var message = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert"><li>' + errorThrown + '</li></ul></div>';
            form.before(message);
            var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
            if (!scrollElement.length) {
                scrollElement = $('.form.checkout');
            }
            if (scrollElement.length) {
                $('html, body').animate({
                    scrollTop: (scrollElement.offset().top - 100)
                }, 1000);
            }
        });
    },


    /**
     * Add parameter for Url
     *
     * @param url
     * @param key
     * @param value
     * @return {string}
     */
    setUrlParameter: function (url, key, value) {
        var baseUrl = url.split('?')[0],
            urlQueryString = '?' + url.split('?')[1],
            newParam = key + '=' + value,
            params = '?' + newParam;

        // If the "search" string exists, then build params from it
        if (urlQueryString) {
            var updateRegex = new RegExp('([\?&])' + key + '[^&]*');
            var removeRegex = new RegExp('([\?&])' + key + '=[^&;]+[&;]?');

            if (typeof value === 'undefined' || value === null || value === '') { // Remove param if value is empty
                params = urlQueryString.replace(removeRegex, "$1");
                params = params.replace(/[&;]$/, "");

            } else if (urlQueryString.match(updateRegex) !== null) { // If param exists already, update it
                params = urlQueryString.replace(updateRegex, "$1" + newParam);

            } else { // Otherwise, add it to end of query string
                params = urlQueryString + '&' + newParam;
            }
        }

        // no parameter was set so we don't need the question mark
        params = params === '?' ? '' : params;

        return baseUrl + params;
    }
};

