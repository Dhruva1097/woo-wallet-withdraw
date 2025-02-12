<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
$withdrawal = get_post($withdraw_id);
$amount = get_post_meta($withdraw_id, '_wallet_withdrawal_amount', true);
$currency = get_post_meta($withdraw_id, '_wallet_withdrawal_currency', true);
$charge = get_post_meta($withdraw_id, '_wallet_withdrawal_transaction_charge', true);
$gateways = woo_wallet_withdrawal()->gateways->payment_gateways;
$request_method = get_post_meta($withdrawal->ID, '_wallet_withdrawal_method', true);
?>
<table id="woo_wallet_withdraw_details">
    <tr>
        <td><?php _e('Amount', 'woo-wallet-withdrawal'); ?></td>
        <td><?php echo wc_price($amount, array('currency' => $currency)); ?></td>
    </tr>
    <tr>
        <td><?php _e('Gateway charge', 'woo-wallet-withdrawal'); ?></td>
        <td><?php echo wc_price($charge, array('currency' => $currency)); ?></td>
    </tr>
    <tr>
        <td><?php _e('Payment method', 'woo-wallet-withdrawal'); ?></td>
        <td><?php echo isset($gateways[$request_method]) ? $gateways[$request_method]->get_method_title() : ''; ?></td>
    </tr>
    <?php if ('paypal' === $request_method) : ?>
        <tr>
            <td><?php _e('PayPal email', 'woo-wallet-withdrawal'); ?></td>
            <td><?php echo get_user_meta($withdrawal->post_author, '_woo_wallet_withdrawal_paypal_email', true); ?></td>
        </tr>
    <?php endif; ?>
</table>
<?php
if ('bacs' === $request_method) :
    $bank_account_details = woo_wallet_withdrawal()->get_bank_account_settings();
    ?>
    <h4><?php _e('Bank account details', 'woo-wallet-withdrawal'); ?></h4>
    <table id="woo_wallet_withdraw_details">
    <?php foreach ($bank_account_details as $details) : ?>
            <tr>
                <td><?php echo $details['label']; ?></td>
                <td><?php echo get_user_meta($withdrawal->post_author, '_' . $details['name'], true) ? get_user_meta($withdrawal->post_author, '_' . $details['name'], true) : 'NULL'; ?></td>
            </tr>
        <?php endforeach; ?>
    <?php do_action('woo_wallet_withdrawal_payment_gateway_bacs_fields', $withdrawal); ?>
    </table>
<?php endif; ?>
<style>
    #woo_wallet_withdraw_details {
        border-collapse: collapse;
        width: 100%;
    }

    #woo_wallet_withdraw_details td, #woo_wallet_withdraw_details th {
        border: 1px solid #ddd;
        padding: 8px;
    }

    #woo_wallet_withdraw_details tr:nth-child(even){background-color: #f2f2f2;}

    #woo_wallet_withdraw_details tr:hover {background-color: #ddd;}

    #woo_wallet_withdraw_details th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #4CAF50;
        color: white;
    }
</style>