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
class WOO_Wallet_Gateway_Cashfree extends WOO_Wallet_Payment_Gateway {

    private $is_testmode = false;
    private $client_id;
    private $client_secret;
    private $withdrawal;
    private $withdrawal_amount;
    private $currency;
    private $cashfree_api = null;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id = 'cashfree';
        $this->method_title = __('Cashfree', 'woo-wallet-withdrawal');
        $this->is_testmode = 'on' === woo_wallet()->settings_api->get_option('_is_cashfree_test_mode', '_wallet_settings_withdrawal', 'off') ? true : false;
        $this->client_id = woo_wallet()->settings_api->get_option('_cashfree_client_id', '_wallet_settings_withdrawal');
        $this->client_secret = woo_wallet()->settings_api->get_option('_cashfree_client_secret', '_wallet_settings_withdrawal');
        $this->cashfree_api = new CashFreeAPI($this->client_id, $this->client_secret, $this->is_testmode);
        add_filter('woo_wallet_withdrawal_payment_gateway_settings', array($this, 'woo_wallet_withdrawal_payment_gateway_settings'));
        
        add_action('update_option__wallet_settings_withdrawal', array($this, 'check_cashfree_access_token'), 10, 3);
    }
    
    public function is_available() {
        if ('INR' != get_woocommerce_currency()) {
            return false;
        }
        return parent::is_available();
    }

    public function woo_wallet_withdrawal_payment_gateway_settings($settings) {
        $settings[] = array(
            'name' => '_is_cashfree_test_mode',
            'label' => __('Enable cashfree sandbox', 'woo-wallet-withdrawal'),
            'type' => 'checkbox',
            'class' => "_is_cashfree_test_mode settings_{$this->id}"
        );
        $settings[] = array(
            'name' => '_cashfree_client_id',
            'label' => __('Cashfree Client ID', 'woo-wallet-withdrawal'),
            'type' => 'text',
            'class' => "_cashfree_client_id settings_{$this->id}"
        );
        $settings[] = array(
            'name' => '_cashfree_client_secret',
            'label' => __('Cashfree client secret', 'woo-wallet-withdrawal'),
            'type' => 'text',
            'class' => "_cashfree_client_secret settings_{$this->id}"
        );
        $settings[] = array(
            'name' => "{$this->id}_rand",
            'type' => 'rand'
        );
        return $settings;
    }
    
    public function check_cashfree_access_token($old_value, $value, $option){
        if(!$this->is_available()){
            return;
        }
        if(!empty($value['_cashfree_client_id']) || !empty($value['_cashfree_client_secret'])){
            $is_test_mode = 'on' === $value['_is_cashfree_test_mode'] ? true : false;
            $cashfree_api = new CashFreeAPI($value['_cashfree_client_id'], $value['_cashfree_client_secret'], $is_test_mode);
            $response = $cashfree_api->get_access_token();
            if(!$response['success']){
                add_settings_error('', esc_attr("settings_admin_error"), 'Cashfree error: '. $response['error_message']);
            }
        }
    }

    public function validate_request() {
        if (!get_user_meta($this->withdrawal->post_author, '_cashfree_beneid', true)) {
            return false;
        }
        return parent::validate_request();
    }

    public function process_payment($withdrawal) {
        $this->withdrawal = $withdrawal;
        $this->withdrawal_amount = get_post_meta($withdrawal->ID, '_wallet_withdrawal_amount', true) - get_post_meta($withdrawal->ID, '_wallet_withdrawal_transaction_charge', true);
        $this->currency = get_woocommerce_currency();
        if ($this->validate_request()) {
            $transfer_args = array(
                'beneId' => get_user_meta($this->withdrawal->post_author, '_cashfree_beneid', true),
                'amount' => $this->withdrawal_amount,
                'transferId' => rand(0, 10) . time(),
                'remarks' => sprintf(__('Withdrawal request %s', 'woo-wallet-withdrawal'), $withdrawal->ID)
            );
            $cashfree_response = $this->cashfree_api->request_transfer($transfer_args);
            if ('SUCCESS' === $cashfree_response['status']) {
                update_post_meta($withdrawal->ID, '_cashfree_referenceid', $cashfree_response['data']['referenceId']);
                update_post_meta($withdrawal->ID, '_cashfree_utr', $cashfree_response['data']['utr']);
                update_post_meta($withdrawal->ID, '_cashfree_acknowledged', $cashfree_response['data']['acknowledged']);
                return true;
            } else {
                woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($cashfree_response, true));
                return false;
            }
        }
        return parent::process_payment($withdrawal);
    }

}
