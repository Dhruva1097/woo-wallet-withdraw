<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$user_id = get_current_user_id();
if (empty(woo_wallet_withdrawal()->gateways->get_available_gateways())) {
    return;
}
$is_rendred_from_myaccount = wc_post_content_has_shortcode('woo-wallet') ? false : is_account_page();
$link = $is_rendred_from_myaccount ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal'))) : add_query_arg('wallet_action', 'wallet_withdrawal', get_permalink());
?>
<div class="woo_wallet_withdrawal_payment_settings"
     <fieldset>
        <select class="wallet-form-control" id="woo_wallet_withdraw_payment_gateway_dropdown">
            <?php foreach (woo_wallet_withdrawal()->gateways->get_available_gateways() as $gateway_id => $gateway): ?>
                <option value="<?php echo $gateway->get_method_id(); ?>"><?php echo $gateway->get_method_title(); ?></option>
            <?php endforeach; ?>
        </select>
    </fieldset>
    <form method="post" enctype="multipart/form-data" action="<?php echo $link . '#ww-payment-settings'; ?>">
        <?php
        if ('on' === woo_wallet()->settings_api->get_option('bacs', '_wallet_settings_withdrawal', 'on')) :
            $bank_account_details = woo_wallet_withdrawal()->get_bank_account_settings();
            ?>
            <fieldset class="woo_wallet_withdrawal_payment_fieldset bacs" id="woo_wallet_withdrawal_payment_bacs_settings">
                <legend><?php esc_html_e('Bank account details', 'woo-wallet-withdrawal'); ?></legend>
                <?php foreach ($bank_account_details as $details) : ?>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="<?php echo $details['name']; ?>" id="<?php echo $details['name']; ?>" value="<?php echo get_user_meta($user_id, '_'.$details['name'], true); ?>" placeholder="<?php echo $details['label']; ?>" />
                </p>
                <?php endforeach; ?>
                <?php do_action('woo_wallet_withdrawal_payment_gateway_bacs_settings_fields'); ?>
            </fieldset>
            <div class="clear"></div>
        <?php endif; ?>
        <?php if ('on' === woo_wallet()->settings_api->get_option('paypal', '_wallet_settings_withdrawal', 'on')) : ?>
            <fieldset class="woo_wallet_withdrawal_payment_fieldset paypal" id="woo_wallet_withdrawal_payment_paypal_settings">
                <legend><?php esc_html_e('PayPal email', 'woo-wallet-withdrawal'); ?></legend>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <input type="email" class="woocommerce-Input woocommerce-Input--email input-email" name="woo_wallet_withdrawal_paypal_email" id="woo_wallet_withdrawal_paypal_email" value="<?php echo get_user_meta($user_id, '_woo_wallet_withdrawal_paypal_email', true); ?>" placeholder="<?php _e('PayPal Email', 'woo-wallet-withdrawal') ?>" />
                </p>
            </fieldset>
            <div class="clear"></div>
        <?php endif; ?>

        <?php if ('on' === woo_wallet()->settings_api->get_option('cashfree', '_wallet_settings_withdrawal', 'off')) : ?>
            <fieldset class="woo_wallet_withdrawal_payment_fieldset cashfree" id="woo_wallet_withdrawal_payment_cashfree_settings">
                <legend><?php esc_html_e('Cashfree account details', 'woo-wallet-withdrawal'); ?></legend>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="cashfree_account_name" id="cashfree_account_name" value="<?php echo get_user_meta($user_id, '_cashfree_account_name', true); ?>" placeholder="<?php _e('Your bank account name', 'woo-wallet-withdrawal') ?>" />
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="cashfree_account_number" id="cashfree_account_number" value="<?php echo get_user_meta($user_id, '_cashfree_account_number', true); ?>" placeholder="<?php _e('Your bank account number', 'woo-wallet-withdrawal') ?>" />
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <textarea class="woocommerce-Input woocommerce-Input--textarea input-text" name="cashfree_bank_address" id="cashfree_bank_address" placeholder="<?php _e('Address of your bank', 'woo-wallet-withdrawal'); ?>" ><?php echo get_user_meta($user_id, '_cashfree_bank_address', true); ?></textarea>
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="cashfree_ifsc_code" id="cashfree_ifsc_code" value="<?php echo get_user_meta($user_id, '_cashfree_ifsc_code', true); ?>" placeholder="<?php _e('IFSC code', 'woo-wallet-withdrawal'); ?>" />
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="cashfree_phone" id="cashfree_phone" value="<?php echo get_user_meta($user_id, '_cashfree_phone', true); ?>" placeholder="<?php _e('Phone', 'woo-wallet-withdrawal'); ?>" />
                </p>
            </fieldset>
            <div class="clear"></div>
        <?php endif; ?>
        <?php if ('on' === woo_wallet()->settings_api->get_option('stripe', '_wallet_settings_withdrawal', 'on')) : ?>
            <fieldset class="woo_wallet_withdrawal_payment_fieldset cashfree" id="woo_wallet_withdrawal_payment_stripe_settings">
                <legend></legend>
                <?php
                $stripe_gateway = woo_wallet_withdrawal()->gateways->payment_gateways['stripe'];
                $stripe_button = woo_wallet_withdrawal()->plugin_url() . '/assets/images/blue-on-light.png';
                if ($stripe_gateway->is_user_connected_with_stripe(get_current_user_id())) {
                    ?>
                    <input type="submit" name="woo_wallet_withdrawal_disconnect_stripe" class="woocommerce-Button button" value="<?php _e('Disconnect stripe account', 'woo-wallet-withdrawal'); ?>" />
                <?php } else { ?>
                    <a href="<?php echo $stripe_gateway->get_stripe_connect_url(get_current_user_id(), $link); ?>"><img src="<?php echo $stripe_button; ?>" /></a>
                <?php } ?>
            </fieldset>
            <div class="clear"></div>
        <?php endif; ?>

        <?php do_action('woo_wallet_withdrawal_payment_gateway_settings_fields'); ?>
        <fieldset id="woo_wallet_withdrawal_payment_submit">
            <?php wp_nonce_field('woo_wallet_withdrawal_payment_option', 'woo_wallet_withdrawal_payment_option'); ?>
            <input type="submit" class="woocommerce-Button button" value="<?php _e('Save changes', 'woo-wallet-withdrawal'); ?>" />
        </fieldset>
        <div class="clear"></div>
    </form>
</div>
<style type="text/css">
    .woo_wallet_withdrawal_payment_settings fieldset{
        padding: 0;
        margin: 0;
    }
</style>