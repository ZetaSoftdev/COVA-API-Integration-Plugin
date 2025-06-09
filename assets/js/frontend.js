/**
 * Cova Integration Frontend Scripts
 */
(function($) {
    'use strict';

    // Age Verification Modal
    function initAgeVerification() {
        // Skip if already verified or not enabled
        if (getCookie('cova_age_verified') === 'true' || 
            typeof covaParams === 'undefined' || 
            !covaParams.age_verification_enabled) {
            return;
        }

        // Create modal HTML
        var modalHTML = '<div class="cova-age-verification-overlay">' +
            '<div class="cova-age-verification-modal">' +
                '<h2 class="cova-age-verification-title">' + covaParams.age_verification_title + '</h2>' +
                '<div class="cova-age-verification-content">' + covaParams.age_verification_message + '</div>' +
                '<div class="cova-age-verification-buttons">' +
                    '<button class="cova-age-verification-button cova-age-verification-confirm">' + covaParams.age_verification_confirm + '</button>' +
                    '<button class="cova-age-verification-button cova-age-verification-decline">' + covaParams.age_verification_decline + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        // Add modal to body
        $('body').append(modalHTML);

        // Handle confirm click
        $('.cova-age-verification-confirm').on('click', function() {
            setCookie('cova_age_verified', 'true', 30); // Store for 30 days
            $('.cova-age-verification-overlay').fadeOut(function() {
                $(this).remove();
            });
        });

        // Handle decline click
        $('.cova-age-verification-decline').on('click', function() {
            window.location.href = covaParams.age_verification_redirect_url || 'https://www.google.com';
        });
    }

    // Helper: Set cookie
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (value || '') + expires + '; path=/';
    }

    // Helper: Get cookie
    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Inventory refresh function (global for use in product shortcodes)
    window.covaRefreshInventory = function(productId) {
        $.ajax({
            url: covaParams.ajaxurl,
            type: 'POST',
            data: {
                action: 'cova_get_inventory',
                nonce: covaParams.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    $('.cova-product-inventory[data-product-id="' + productId + '"]').html(response.data.html);
                }
            }
        });
    };

    // Initialize once DOM is ready
    $(document).ready(function() {
        initAgeVerification();
    });

})(jQuery); 