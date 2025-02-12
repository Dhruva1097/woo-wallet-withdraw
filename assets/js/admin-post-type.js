/* global withdrawal_post_type_param */

jQuery(function ($) {
    var withdrawal_post_type = {
        init: function () {
            $('.woo_wallet_withdrawal_action').on('click', function (event) {
                event.preventDefault();
                var row_action = $(this).data('action');
                var post_id = $(this).data('post_id');
                if (row_action === 'approve' || row_action === 'reject' || row_action === 'pending' || (row_action === 'delete' && confirm(withdrawal_post_type_param.confirmation_message))) {
                    $(this).css('cursor', 'progress');
                    $(this).attr('disabled', 'disabled');
                    var data = {
                        action: 'woo_wallet_withdrawal_post_action',
                        security: withdrawal_post_type_param.security_nonce,
                        post_id: post_id,
                        row_action: row_action
                    };
                    $.post(withdrawal_post_type_param.ajax_url, data, function (response) {
                        if (response.status) {
                            if (response.redirect_url) {
                                window.location.href = response.redirect_url;
                            } else {
                                window.location.reload();
                            }
                        } else {
                            alert(withdrawal_post_type_param.error_message);
                        }
                    });
                }
            });
        }
    };
    withdrawal_post_type.init();
});