/* global woo_wallet_withdrawal_param */

jQuery(function ($) {
    $("#woo-wallet-withdrawal-tabs").tabs();
    $('#wallet_withdrawal_method').on('change', function () {
        var gateway = $(this).val();
        var data = {
            action: 'validate_woo_wallet_withdrawal',
            security: woo_wallet_withdrawal_param.validate_request_nonce,
            settings_link: woo_wallet_withdrawal_param.settings_link,
            gateway: gateway
        };
        $.post(woo_wallet_withdrawal_param.ajax_url, data, function (response) {
            if (response) {
                $('#woo_wallet_withdrawal_notice').html('');
                $('#woo_wallet_withdraw_submit').removeAttr('disabled');
                if (response.notices.length > 0) {
                    $.each(response.notices, function (index, value) {
                        $('#woo_wallet_withdrawal_notice').append('<div class="woocommerce-error">' + value + '</div>');
                    });
                    if (!response.is_valid) {
                        $('#woo_wallet_withdraw_submit').attr('disabled', 'disabled');
                    }
                }
            }
        });
    }).change();

    $('#woo_wallet_withdraw_payment_gateway_dropdown').on('change', function () {
        var gateway = $(this).val();
        $('.woo_wallet_withdrawal_payment_fieldset').hide();
        $('#woo_wallet_withdrawal_payment_' + gateway + '_settings').slideDown();
        if('stripe' === gateway){
            $('#woo_wallet_withdrawal_payment_submit').hide();
        } else{
            $('#woo_wallet_withdrawal_payment_submit').show();
        }
        $('body').trigger('woo_wallet_withdraw_payment_gateway_dropdown_change', [gateway]);
    }).change();
});


