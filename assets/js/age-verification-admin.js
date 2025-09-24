/**
 * Age Verification Admin JavaScript
 * Handles the conditional display of minimum age field based on enable age verification checkbox
 */

jQuery(document).ready(function($) {
    'use strict';

    /* Cache DOM elements */
    var $enableAgeVerification = $('#_reepay_enable_age_verification');
    var $minimumAgeField = $('.reepay-minimum-age-field');

    /**
     * Toggle minimum age field visibility based on enable age verification checkbox
     */
    function toggleMinimumAgeField() {
        if ($enableAgeVerification.is(':checked')) {
            $minimumAgeField.show();
        } else {
            $minimumAgeField.hide();
            /* Clear the minimum age value when disabled */
            $('#_reepay_minimum_age').val('');
        }
    } 

    /**
     * Initialize the age verification functionality
     */
    function initAgeVerification() {
        /* Set initial state */
        toggleMinimumAgeField();

        /* Bind change event to enable age verification checkbox */
        $enableAgeVerification.on('change', toggleMinimumAgeField);
    }

    /* Initialize when the page loads */
    initAgeVerification();

    /* Re-initialize when WooCommerce product data tabs are loaded */
    $(document).on('woocommerce_product_data_loaded', function() {
        /* Re-cache elements in case they were reloaded */
        $enableAgeVerification = $('#_reepay_enable_age_verification');
        $minimumAgeField = $('.reepay-minimum-age-field');
        
        initAgeVerification();
    });

    /* Handle tab switching to ensure proper display */
    $('.product_data_tabs li a').on('click', function() {
        var target = $(this).attr('href');
        
        if (target === '#age_verification_product_data') {
            /* Small delay to ensure the panel is visible before toggling */
            setTimeout(function() {
                toggleMinimumAgeField();
            }, 100);
        }
    });
});