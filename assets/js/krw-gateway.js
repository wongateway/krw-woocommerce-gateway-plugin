jQuery(function($) {
    'use strict';

    var krw_gateway = {
        init: function() {
            $(document.body).on('click', '#krw-send-auth-code', this.sendAuthCode);
            $(document.body).on('change', '#krw-bank-name', this.validateBankSelection);
            $(document.body).on('input', '#krw-account-number', this.formatAccountNumber);
            $(document.body).on('input', '#krw-phone-number', this.formatPhoneNumber);
        },

        sendAuthCode: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $phoneInput = $('#krw-phone-number');
            var phoneNumber = $phoneInput.val();
            
            if (!phoneNumber || !krw_gateway.validatePhoneNumber(phoneNumber)) {
                alert(krw_gateway_params.i18n_invalid_phone);
                return;
            }
            
            $button.prop('disabled', true);
            $button.text(krw_gateway_params.i18n_sending);
            
            $.ajax({
                type: 'POST',
                url: krw_gateway_params.ajax_url,
                data: {
                    action: 'krw_send_auth_code',
                    phone_number: phoneNumber,
                    nonce: krw_gateway_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(krw_gateway_params.i18n_code_sent);
                        krw_gateway.startCountdown($button);
                    } else {
                        alert(response.data.message || krw_gateway_params.i18n_error);
                        $button.prop('disabled', false);
                        $button.text(krw_gateway_params.i18n_send_code);
                    }
                },
                error: function() {
                    alert(krw_gateway_params.i18n_error);
                    $button.prop('disabled', false);
                    $button.text(krw_gateway_params.i18n_send_code);
                }
            });
        },

        validatePhoneNumber: function(phone) {
            var phoneRegex = /^01[0-9]-?[0-9]{3,4}-?[0-9]{4}$/;
            return phoneRegex.test(phone.replace(/-/g, ''));
        },

        formatPhoneNumber: function() {
            var $input = $(this);
            var value = $input.val().replace(/[^0-9]/g, '');
            var formatted = '';
            
            if (value.length <= 3) {
                formatted = value;
            } else if (value.length <= 7) {
                formatted = value.slice(0, 3) + '-' + value.slice(3);
            } else if (value.length <= 11) {
                formatted = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7);
            } else {
                formatted = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7, 11);
            }
            
            $input.val(formatted);
        },

        formatAccountNumber: function() {
            var $input = $(this);
            var value = $input.val().replace(/[^0-9-]/g, '');
            $input.val(value);
        },

        validateBankSelection: function() {
            var $select = $(this);
            var $accountInput = $('#krw-account-number');
            
            if ($select.val()) {
                $accountInput.prop('disabled', false);
            } else {
                $accountInput.prop('disabled', true);
                $accountInput.val('');
            }
        },

        startCountdown: function($button) {
            var seconds = 180; // 3 minutes
            var interval = setInterval(function() {
                seconds--;
                
                if (seconds <= 0) {
                    clearInterval(interval);
                    $button.prop('disabled', false);
                    $button.text(krw_gateway_params.i18n_resend_code);
                } else {
                    var minutes = Math.floor(seconds / 60);
                    var remainingSeconds = seconds % 60;
                    $button.text(minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds);
                }
            }, 1000);
        }
    };

    krw_gateway.init();

    // Handle checkout validation
    $(document.body).on('checkout_error', function() {
        $('.woocommerce-error').each(function() {
            if ($(this).text().indexOf('KRW') !== -1) {
                $('html, body').animate({
                    scrollTop: $('#payment').offset().top - 100
                }, 1000);
            }
        });
    });
});