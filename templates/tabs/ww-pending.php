<?php
/**
 * The Template for displaying wallet recharge form
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet-withdrawal/tabs/ww-pending.php.
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

$args = array(
    'posts_per_page' => -1,
    'author' => get_current_user_id(),
    'post_type' => WOO_Wallet_Withdrawal_Post_Type::$post_type,
    'post_status' => 'ww-pending',
    'suppress_filters' => true
);
$withdrawal_requests = get_posts($args);
if ($withdrawal_requests) {
    echo '<div class="woocommerce-info">' . __('You have pending withdrawal', 'woo-wallet-withdrawal') . '</div>';
    woo_wallet_withdrawal()->get_template('woo-wallet-withdrawal-details.php', array('withdrawal_requests' => $withdrawal_requests));
} else if (woo_wallet()->settings_api->get_option('_min_withdrawal_limit', '_wallet_settings_withdrawal', 0) > woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), 'edit')) {
    echo '<div class="woocommerce-info">' . sprintf(__('Minimum withdrawal limit is %s', 'woo-wallet-withdrawal'), wc_price(woo_wallet()->settings_api->get_option('_min_withdrawal_limit', '_wallet_settings_withdrawal', 0))) . '</div>';
} else {
    $is_rendred_from_myaccount = wc_post_content_has_shortcode('woo-wallet') ? false : is_account_page();
    $link = $is_rendred_from_myaccount ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal'))) : add_query_arg('wallet_action', 'wallet_withdrawal', get_permalink());
    ?>
    <form action="<?php echo $link; ?>" method="post" name="wallet_withdraw_request" class="wallet_withdraw_request">
        <div id="woo_wallet_withdrawal_notice"></div>
        <div class="wallet-inputs">
            <label for="wallet_withdrawal_amount"><?php _e('Withdraw Amount', 'woo-wallet-withdrawal'); ?></label>
            <div class ="currency_input">
                <div>
                    <span class="wallet_input_group_addon"><?php echo get_woocommerce_currency(); ?></span>
                    <input type="number" required="" step="0.01" min="<?php echo woo_wallet()->settings_api->get_option('_min_withdrawal_limit', '_wallet_settings_withdrawal', 0); ?>" <?php echo woo_wallet()->settings_api->get_option('_max_withdrawal_limit', '_wallet_settings_withdrawal', false) ? 'max="'. woo_wallet()->settings_api->get_option('_max_withdrawal_limit', '_wallet_settings_withdrawal').'"' : '';  ?> name="wallet_withdrawal_amount" id="wallet_withdrawal_amount" class="wallet-form-control">
                </div>
            </div>
        </div>
        <div style="clear:both"></div>
        <div class="wallet-inputs">
            <label for="wallet_withdrawal_method"><?php _e('Payment Method', 'woo-wallet-withdrawal'); ?></label>
            <div class ="currency_input">
                <div>
                    <select name="wallet_withdrawal_method" id="wallet_withdrawal_method" class="wallet-form-control" required="">
                        <?php foreach (woo_wallet_withdrawal()->gateways->get_available_gateways() as $gateways) : ?>
                            <?php
                            $gateway_charge_text = '';
                            $gateway_charge = $gateways->gateway_charge();
                            if ($gateway_charge['amount']) {
                                if ('percent' === $gateway_charge['type']) {
                                    $gateway_charge_text = ' (' . $gateway_charge['amount'] . '% ' . __('transaction fee', 'woo-wallet-withdrawal') . ')';
                                } else {
                                    $gateway_charge_text = ' (' . wc_price($gateway_charge['amount']) . ' ' . __('transaction fee', 'woo-wallet-withdrawal') . ')';
                                }
                            }
                            ?>
                            <option value="<?php echo $gateways->get_method_id(); ?>"><?php echo $gateways->get_method_title() . $gateway_charge_text; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div style="clear:both"></div>
        <div class="wallet-inputs">
            <?php wp_nonce_field('woo_wallet_withdrawal', 'woo_wallet_withdrawal'); ?>
            <input type="submit" name="woo_wallet_withdraw_submit" id="woo_wallet_withdraw_submit" value="<?php _e('Submit Request', 'woo-wallet-withdrawal'); ?>" />
        </div>
    </form>

    <style type="text/css">
        .wallet_withdraw_request label{
            display: inline-block;
            max-width: 100%;
            text-align: left;
            width: 30%;
            float: left;
            font-weight: bold;
            padding-right: 10px;
        }
        .currency_input {
            float: left;
            width: 50%;
        }
        .wallet-inputs{
            margin-top: 10px;
        }
        .currency_input div {
            display: table;
            width: 100%;
        }
        .currency_input .currency_input_paypal {
            display: block;
        }
        .wallet_input_group_addon {
            padding: 6px 12px;
            font-weight: normal;
            line-height: 1;
            vertical-align: middle;
            color: #555;
            text-align: center;
            background-color: #eee;
            border: 1px solid #EDEDED;

            display: table-cell;
            border-top-left-radius: 4px;
            border-bottom-left-radius: 4px;
        }
        .wallet-form-control {
            background-color: #ffffff;
            background-image: none;
            border: 1px solid #EDEDED;
            padding: 4px 6px;
            border-radius: 0;
            color: #555555;
            display: block;
            font-size: 14px;
            min-height: 26px;
            line-height: 26px;
            vertical-align: middle;
            width: 100%;
            margin: 0;
        }
    </style>

    <?php
}