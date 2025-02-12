<?php
/**
 * The Template for displaying wallet recharge form
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet-withdrawal/woo-wallet-withdrawal-details.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author 	Subrata Mal
 * @version     1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<table class="woo-wallet-withdrawal-detals">
    <thead>
        <tr>
            <th><?php _e('Amount', 'woo-wallet-withdrawal'); ?></th>
            <th><?php _e('Status', 'woo-wallet-withdrawal'); ?></th>
            <th><?php _e('Method', 'woo-wallet-withdrawal'); ?></th>
            <th><?php _e('Date', 'woo-wallet-withdrawal'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($withdrawal_requests as $withdrawal_request) : ?>
        <tr>
            <td><?php echo wc_price(get_post_meta($withdrawal_request->ID, '_wallet_withdrawal_amount', true)); ?></td>
            <td><?php echo get_post_status_object(get_post_status($withdrawal_request->ID))->label; ?></td>
            <td><?php echo woo_wallet_withdrawal()->gateways->payment_gateways[get_post_meta($withdrawal_request->ID, '_wallet_withdrawal_method', true)]->get_method_title(); ?></td>
            <td><?php echo wc_string_to_datetime($withdrawal_request->post_date)->date_i18n(wc_date_format()); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>