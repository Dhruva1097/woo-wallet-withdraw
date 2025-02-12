<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Direct Bank transfer gateway for withdrawal 
 * from WooCommerce Wallet 
 *
 * @author subrata
 */
class WOO_Wallet_Gateway_BACS extends WOO_Wallet_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id = 'bacs';
        $this->method_title = __('Bank transfer', 'woo-wallet-withdrawal');
    }

    public function is_enable_auto_withdrawal() {
        return false;
    }

    public function process_payment($withdrawal) {
        return parent::process_payment($withdrawal);
    }

}
