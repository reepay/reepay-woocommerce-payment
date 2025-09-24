/**
 * Reepay Add Payment Method Protection
 * Prevents duplicate payment method submissions
 */
jQuery(function($) {
    'use strict';

    // Track if form is being processed
    let isProcessing = false;
    let processedTokens = new Set();

    // Enhanced form submission protection
    $('#add_payment_method').on('submit', function(e) {
        const $form = $(this);
        const $submitButton = $form.find('button[type="submit"], input[type="submit"]');
        
        // Check if already processing
        if (isProcessing) {
            e.preventDefault();
            return false;
        }

        // Get selected payment method
        const selectedMethod = $form.find('input[name="payment_method"]:checked').val();
        
        // For Reepay methods, add extra protection
        if (selectedMethod && selectedMethod.indexOf('reepay') !== -1) {
            // Mark as processing
            isProcessing = true;
            
            // Disable submit button
            $submitButton.prop('disabled', true).addClass('processing');
            
            // Add visual feedback
            if (!$submitButton.hasClass('blocked')) {
                $submitButton.text($submitButton.text() + '...');
            }
            
            // Set timeout to re-enable (fallback)
            setTimeout(function() {
                isProcessing = false;
                $submitButton.prop('disabled', false).removeClass('processing');
            }, 10000); // 10 seconds timeout
        }
    });

    // Monitor for successful redirects and reset state
    $(window).on('beforeunload', function() {
        isProcessing = false;
    });

    // Additional protection for AJAX-based submissions
    $(document).ajaxSend(function(event, xhr, settings) {
        // Check if this is a Reepay payment method addition
        if (settings.url && settings.url.indexOf('reepay_card_store') !== -1) {
            const urlParams = new URLSearchParams(settings.url.split('?')[1]);
            const paymentMethod = urlParams.get('payment_method');
            
            if (paymentMethod && processedTokens.has(paymentMethod)) {
                // Abort duplicate AJAX request
                xhr.abort();
                console.warn('Reepay: Duplicate payment method submission prevented');
                return false;
            }
            
            if (paymentMethod) {
                processedTokens.add(paymentMethod);
                
                // Remove from processed list after delay
                setTimeout(function() {
                    processedTokens.delete(paymentMethod);
                }, 30000); // 30 seconds
            }
        }
    });

    // Handle AJAX completion
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url && settings.url.indexOf('reepay_card_store') !== -1) {
            isProcessing = false;
            
            // Re-enable submit button
            const $submitButton = $('#add_payment_method').find('button[type="submit"], input[type="submit"]');
            $submitButton.prop('disabled', false).removeClass('processing');
        }
    });

    // Enhanced button click protection (similar to WooCommerce core)
    $('.woocommerce .payment-method-actions .button').on('click', function(event) {
        const $button = $(this);
        
        if ($button.hasClass('disabled') || $button.hasClass('processing')) {
            event.preventDefault();
            return false;
        }
        
        // Add processing class to prevent multiple clicks
        $button.addClass('processing disabled');
        
        // Remove processing class after delay (fallback)
        setTimeout(function() {
            $button.removeClass('processing disabled');
        }, 5000);
    });

    // Monitor for WooCommerce notices that might indicate success/failure
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                const $notices = $('.woocommerce-message, .woocommerce-error');
                if ($notices.length > 0) {
                    // Reset processing state when notices appear
                    isProcessing = false;
                    processedTokens.clear();
                    
                    // Re-enable buttons
                    $('.button.processing').removeClass('processing disabled').prop('disabled', false);
                }
            }
        });
    });

    // Start observing
    const noticeContainer = document.querySelector('.woocommerce-notices-wrapper, .woocommerce');
    if (noticeContainer) {
        observer.observe(noticeContainer, {
            childList: true,
            subtree: true
        });
    }

    // Debug logging (remove in production)
    if (window.console && window.console.log) {
        console.log('Reepay: Add Payment Method Protection loaded');
    }
});
