/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    // wc_checkout_params is required to continue, ensure the object exists
    if ( typeof wc_checkout_params === 'undefined' ) {
        return false;
    }

    // Preload ModalCheckout
    var rp = new Reepay.ModalCheckout();

    $(document).ajaxComplete( function ( event, xhr, settings ) {
        if ( settings.url === wc_checkout_params.checkout_url ) {
            var data = xhr.responseText;

            // Parse
            try {
                var result = $.parseJSON( data );
            } catch ( e ) {
                return false;
            }

            // Check is response from payment gateway
            if ( ! result.hasOwnProperty( 'is_reepay_checkout' ) ) {
                return false;
            }

            var accept_url = result.accept_url,
                cancel_url = result.cancel_url;


            console.log(result);

            if (WC_Gateway_Reepay_Checkout.payment_type === 'OVERLAY') {
                // Show modal
                rp.show(result.reepay.id);
                //rp = new Reepay.ModalCheckout(result.reepay.id);
            } else {
                rp = new Reepay.WindowCheckout(result.reepay.id);
            }

            rp.addEventHandler(Reepay.Event.Accept, function(data) {
                console.log('Accept', data);

                var redirect_url = accept_url;
                for (let prop in data) {
                    redirect_url = setUrlParameter(redirect_url, prop, data[prop]);
                }

                window.location.href = redirect_url;
            });

            rp.addEventHandler(Reepay.Event.Cancel, function(data) {
                console.log('Cancel', data);
                window.location.href = cancel_url;
            });

            rp.addEventHandler(Reepay.Event.Close, function(data) {
                console.log('Close', data);
            });

            rp.addEventHandler(Reepay.Event.Error, function(data) {
                console.log('Error', data);
                window.location.href = cancel_url;
            });
        }
    } );

    function setUrlParameter(url, key, value) {
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
});

