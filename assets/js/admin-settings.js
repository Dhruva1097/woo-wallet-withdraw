/* global woo_wallet_withdrawal_admin_settings_param */

jQuery(function ($) {
    var admin_settings = {
        init: function () {
            $('#wcwp-_wallet_settings_withdrawal-_is_enable_gateway_charge').on('change', function () {
                if ($(this).is(':checked')) {
                    $('._withdrawal_gateway_charge_type').show();
                    $.each(woo_wallet_withdrawal_admin_settings_param.gateways, function (index, value) {
                        $('#_wallet_settings_withdrawal ._charge_' + value).show();
                    });
                } else {
                    $('._withdrawal_gateway_charge_type').hide();
                    $.each(woo_wallet_withdrawal_admin_settings_param.gateways, function (index, value) {
                        $('#_wallet_settings_withdrawal ._charge_' + value).hide();
                    });
                }
            }).change();

            $.each(woo_wallet_withdrawal_admin_settings_param.gateways, function (index, value) {
                $('#wcwp-_wallet_settings_withdrawal-' + value).on('change', function () {
                    if ($(this).is(':checked')) {
                        $('.settings_' + value).show();
                    } else {
                        $('.settings_' + value).hide();
                    }
                }).change();
            });
        }
    };
    admin_settings.init();
});


