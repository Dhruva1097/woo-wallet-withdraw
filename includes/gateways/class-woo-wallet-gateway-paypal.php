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
class WOO_Wallet_Gateway_Paypal extends WOO_Wallet_Payment_Gateway {

    private $is_testmode = false;
    private $client_id;
    private $client_secret;
    private $payout_mode = 'false';
    private $reciver_email;
    private $api_endpoint;
    private $token_endpoint;
    private $access_token;
    private $token_type;
    private $withdrawal;
    private $withdrawal_amount;
    private $currency;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id = 'paypal';
        $this->method_title = __('PayPal', 'woo-wallet-withdrawal');
        $this->is_testmode = 'on' === woo_wallet()->settings_api->get_option('_is_paypal_test_mode', '_wallet_settings_withdrawal', 'off') ? true : false;
        $this->client_id = woo_wallet()->settings_api->get_option('_paypal_client_id', '_wallet_settings_withdrawal');
        $this->client_secret = woo_wallet()->settings_api->get_option('_paypal_secret_key', '_wallet_settings_withdrawal');
        $this->api_endpoint = 'https://api.paypal.com/v1/payments/payouts?sync_mode=' . $this->payout_mode;
        $this->token_endpoint = 'https://api.paypal.com/v1/oauth2/token';

        if ($this->is_testmode) {
            $this->api_endpoint = 'https://api.sandbox.paypal.com/v1/payments/payouts?sync_mode=' . $this->payout_mode;
            $this->token_endpoint = 'https://api.sandbox.paypal.com/v1/oauth2/token';
        }
        add_filter('woo_wallet_withdrawal_payment_gateway_settings', array($this, 'woo_wallet_withdrawal_payment_gateway_settings'));
    }

    public function woo_wallet_withdrawal_payment_gateway_settings($settings) {
        $settings[] = array(
            'name' => '_is_paypal_test_mode',
            'label' => __('Enable PayPal sandbox', 'woo-wallet-withdrawal'),
            'type' => 'checkbox',
            'class' => "_is_paypal_test_mode settings_{$this->id}"
        );
        $settings[] = array(
            'name' => '_paypal_client_id',
            'label' => __('PayPal Client ID', 'woo-wallet-withdrawal'),
            'type' => 'text',
            'class' => "_paypal_client_id settings_{$this->id}"
        );
        $settings[] = array(
            'name' => '_paypal_secret_key',
            'label' => __('PayPal Secret Key', 'woo-wallet-withdrawal'),
            'type' => 'text',
            'class' => "_paypal_secret_key settings_{$this->id}"
        );
        return $settings;
    }

    public function validate_request() {
        if (!$this->client_id && !$this->client_secret) {
            return false;
        } else if (!$this->reciver_email) {
            return false;
        }
        return parent::validate_request();
    }

    public function process_payment($withdrawal) {
        $this->withdrawal = $withdrawal;
        $this->withdrawal_amount = get_post_meta($withdrawal->ID, '_wallet_withdrawal_amount', true) - get_post_meta($withdrawal->ID, '_wallet_withdrawal_transaction_charge', true);
        $this->currency = get_woocommerce_currency();
        $this->reciver_email = get_user_meta($withdrawal->post_author, '_woo_wallet_withdrawal_paypal_email', true);
        if ($this->validate_request()) {
            $this->generate_access_token();
            $paypal_response = $this->process_paypal_payout();
            if ($paypal_response) {
                return true;
            } else {
                return false;
            }
        }
        return parent::process_payment($withdrawal);
    }

    private function generate_access_token() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Accept-Language: en_US'));
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_URL, $this->token_endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, $this->client_id . ':' . $this->client_secret);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($curl, CURLOPT_SSLVERSION, 6);
        $response = curl_exec($curl);
        curl_close($curl);
        $response_array = json_decode($response, true);
        woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($response_array, true));
        $this->access_token = isset($response_array['access_token']) ? $response_array['access_token'] : '';
        $this->token_type = isset($response_array['token_type']) ? $response_array['token_type'] : '';
    }

    private function process_paypal_payout() {
        $api_authorization = "Authorization: {$this->token_type} {$this->access_token}";
        $note = sprintf(__('Recieved payout from %1$s as wallet amount at %2$s on %3$s', 'woo-wallet-withdrawal'), get_bloginfo('name'), date('H:i:s'), date('d-m-Y'));
        $request_params = array(
            'sender_batch_header' => array(
                'sender_batch_id' => uniqid(),
                'email_subject' => 'You have a payment',
                'recipient_type' => 'EMAIL'
            ),
            'items' => array(
                array(
                    'recipient_type' => 'EMAIL',
                    'amount' => array(
                        'value' => $this->withdrawal_amount,
                        'currency' => $this->currency
                    ),
                    'receiver' => $this->reciver_email,
                    'note' => $note,
                    'sender_item_id' => $this->withdrawal->ID
                )
            )
        );
        $request_params = json_encode($request_params);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type:application/json', $api_authorization));
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_URL, $this->api_endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request_params);
        curl_setopt($curl, CURLOPT_SSLVERSION, 6);
        $result = curl_exec($curl);
        curl_close($curl);
        $result_array = json_decode($result, true);
        woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($result_array, true));
        $batch_status = $result_array['batch_header']['batch_status'];

        $batch_payout_status = apply_filters('woo_wallet_withdrawal_payout_batch_status', array('PENDING', 'PROCESSING', 'SUCCESS', 'NEW'));
        if (in_array($batch_status, $batch_payout_status)) {
            // Updating withdrawal meta
            return $result_array;
        } else {
            return false;
        }
    }

}
