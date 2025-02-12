<?php

/**
 * The Template for displaying wallet recharge form
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet-withdrawal/tabs/ww-approved.php.
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
    'post_status' => 'ww-approved',
    'suppress_filters' => true
);
$withdrawal_requests = get_posts($args);
if ($withdrawal_requests) {
    woo_wallet_withdrawal()->get_template('woo-wallet-withdrawal-details.php', array('withdrawal_requests' => $withdrawal_requests));
} else {
    echo '<div class="woocommerce-info">'.__('Sorry, no transactions were found!', 'woo-wallet-withdrawal').'</div>';
}