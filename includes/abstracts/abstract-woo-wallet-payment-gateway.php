<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class WOO_Wallet_Payment_Gateway {

    /**
     * Gateway title.
     * @var string
     */
    public $method_title = '';

    /**
     * Gateway ID
     * @var string 
     */
    public $id = '';

    /**
     * Return the title for admin screens.
     * @return string
     */
    public function get_method_title() {
        return apply_filters('woo_wallet_gateway_method_title', $this->method_title, $this);
    }

    /**
     * Return the id for admin screens.
     * @return string
     */
    public function get_method_id() {
        return apply_filters('woo_wallet_gateway_method_id', $this->id, $this);
    }

    public function is_available() {
        return apply_filters('woo_wallet_gateway_is_available', ('on' === woo_wallet()->settings_api->get_option($this->id, '_wallet_settings_withdrawal', 'on')), $this->id);
    }
    
    public function is_enable_auto_withdrawal() {
        return apply_filters('woo_wallet_gateway_is_enable_auto_withdrawal', ('on' === woo_wallet()->settings_api->get_option('_is_auto_withdrawal', '_wallet_settings_withdrawal', 'off')), $this->id);
    }

    public function validate_request() {
        return true;
    }

    /**
     * Payment gateway charge
     * @return array
     */
    public function gateway_charge() {
        $charge = array(
            'amount' => 0,
            'type' => 'percent'
        );
        if ('on' === woo_wallet()->settings_api->get_option('_is_enable_gateway_charge', '_wallet_settings_withdrawal', 'off')) {
            $type = woo_wallet()->settings_api->get_option('_withdrawal_gateway_charge_type', '_wallet_settings_withdrawal', 'percent');
            $charge['amount'] = woo_wallet()->settings_api->get_option('_charge_' . $this->get_method_id(), '_wallet_settings_withdrawal', 0);
            if ('percent' === $type) {
                $charge['type'] = 'percent';
            } else {
                $charge['type'] = 'fixed';
            }
        }
        return $charge;
    }

    /**
     * Process Payment.
     * @return array
     */
    public function process_payment($withdrawal) {
        $transaction_id = get_post_meta($withdrawal->ID, '_wallet_withdrawal_transaction_id', true);
        update_wallet_transaction($transaction_id, $withdrawal->post_author, array('deleted' => 0), array('%d'));
        return true;
    }

}
