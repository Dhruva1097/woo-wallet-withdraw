<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Stripe\Stripe;
use Stripe\Transfer;

/**
 * Direct Bank transfer gateway for withdrawal 
 * from WooCommerce Wallet 
 *
 * @author subrata
 */
class WOO_Wallet_Gateway_Stripe extends WOO_Wallet_Payment_Gateway {

    private $withdrawal;
    private $withdrawal_amount;
    private $currency;

    /**
     * Is test mode enabled.
     * @var bool 
     */
    public $is_stripe_test_mode = false;

    /**
     * Stripe client id.
     * @var string
     */
    public $stripe_client_id = null;

    /**
     * Stripe publishable key.
     * @var string
     */
    public $stripe_publishable_key = null;

    /**
     * Stripe secret key.
     * @var string
     */
    public $stripe_secret_key = null;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id = 'stripe';
        $this->method_title = __('Stripe', 'woo-wallet-withdrawal');
        $this->is_stripe_test_mode = ('on' === woo_wallet()->settings_api->get_option('_is_stripe_test_mode', '_wallet_settings_withdrawal', 'off')) ? true : false;
        $this->stripe_client_id = woo_wallet()->settings_api->get_option('_stripe_client_id', '_wallet_settings_withdrawal', null);
        $this->stripe_publishable_key = woo_wallet()->settings_api->get_option('_stripe_publishable_key', '_wallet_settings_withdrawal', null);
        $this->stripe_secret_key = woo_wallet()->settings_api->get_option('_stripe_secret_key', '_wallet_settings_withdrawal', null);
        add_filter('woo_wallet_withdrawal_payment_gateway_settings', array($this, 'woo_wallet_withdrawal_payment_gateway_settings'));

        $this->connect_stripe_user();
    }

    public function woo_wallet_withdrawal_payment_gateway_settings($settings) {
        $settings[] = array(
            'name' => '_is_stripe_test_mode',
            'label' => __('Enable stripe sandbox', 'woo-wallet-withdrawal'),
            'type' => 'checkbox',
            'class' => "_is_cashfree_test_mode settings_{$this->id}"
        );
        $settings[] = array(
            'name' => '_stripe_client_id',
            'label' => __('Stripe client ID', 'woo-wallet-withdrawal'),
            'type' => 'text',
            'class' => "_stripe_client_id settings_{$this->id}"
        );
        $settings[] = array(
            'name' => '_stripe_publishable_key',
            'label' => __('Stripe publishable key', 'woo-wallet-withdrawal'),
            'type' => 'text',
            'class' => "_stripe_publishable_key settings_{$this->id}"
        );
        $settings[] = array(
            'name' => '_stripe_secret_key',
            'label' => __('Stripe secret key', 'woo-wallet-withdrawal'),
            'type' => 'text',
            'class' => "_stripe_secret_key settings_{$this->id}"
        );
        return $settings;
    }

    public function is_user_connected_with_stripe($user_id) {
        if (get_user_meta($user_id, 'stripe_user_id', true)) {
            return true;
        }
        return false;
    }

    public function get_stripe_connect_url($user_id, $redirect_uri) {
        $authorize_request_body = array(
            'response_type' => 'code',
            'scope' => 'read_write',
            'client_id' => $this->stripe_client_id,
            'redirect_uri' => $redirect_uri,
            'state' => $user_id
        );
        return add_query_arg($authorize_request_body, 'https://connect.stripe.com/oauth/authorize');
    }

    public function connect_stripe_user() {
        if (is_admin()) {
            return;
        }
        if ($this->stripe_client_id && $this->stripe_secret_key) {
            if (isset($_GET['code']) && !empty($_GET['code'])) {
                $user_id = $_GET['state'];
                $user = new WP_User($user_id);
                if ($user && !get_user_meta($user_id, 'stripe_user_id', true)) {
                    $token_request_body = array(
                        'client_secret' => $this->stripe_secret_key,
                        'code' => $_GET['code'],
                        'grant_type' => 'authorization_code',
                    );

                    $req = curl_init('https://connect.stripe.com/oauth/token');
                    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($req, CURLOPT_POST, true);
                    curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($token_request_body));
                    curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($req, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($req, CURLOPT_VERBOSE, true);

                    $resualt = json_decode(curl_exec($req), true);
                    curl_close($req);
                    $is_rendred_from_myaccount = wc_post_content_has_shortcode('woo-wallet') ? false : is_account_page();
                    $link = $is_rendred_from_myaccount ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal'))) : add_query_arg('wallet_action', 'wallet_withdrawal', get_permalink());
                    if (isset($resualt['stripe_user_id'])) {
                        update_user_meta($user->ID, 'stripe_user_id', $resualt['stripe_user_id']);
                        wc_add_notice(__('You are now connected with stripe', 'woo-wallet-withdrawal'));
                    } else {
                        wc_add_notice($resualt['error_description'], 'error');
                    }
                }
            }
        }
    }

    public function disconnect_stripe_account($user_id = '') {
        if (is_admin()) {
            return;
        }
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $stripe_user_id = get_user_meta($user_id, 'stripe_user_id', true);
        if (!$stripe_user_id) {
            return;
        }

        $token_request_body = array(
            'client_id' => $this->stripe_client_id,
            'stripe_user_id' => $stripe_user_id,
            'client_secret' => $this->stripe_secret_key
        );

        $req = curl_init('https://connect.stripe.com/oauth/deauthorize');
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($token_request_body));
        curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($req, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($req, CURLOPT_VERBOSE, true);

        $resp = json_decode(curl_exec($req), true);
        curl_close($req);
        if (isset($resp['stripe_user_id'])) {
            delete_user_meta($user_id, 'stripe_user_id');
        }
    }

    public function validate_request() {
        if (!get_user_meta($this->withdrawal->post_author, 'stripe_user_id', true)) {
            return false;
        }
        return parent::validate_request();
    }

    private function get_stripe_amount() {
        switch (strtoupper($this->currency)) {
            // Zero decimal currencies.
            case 'BIF' :
            case 'CLP' :
            case 'DJF' :
            case 'GNF' :
            case 'JPY' :
            case 'KMF' :
            case 'KRW' :
            case 'MGA' :
            case 'PYG' :
            case 'RWF' :
            case 'VND' :
            case 'VUV' :
            case 'XAF' :
            case 'XOF' :
            case 'XPF' :
                $amount_to_pay = absint($this->withdrawal_amount);
                break;
            default :
                $amount_to_pay = round($this->withdrawal_amount, 2) * 100; // In cents.
                break;
        }
        return $amount_to_pay;
    }

    public function process_payment($withdrawal) {
        $this->withdrawal = $withdrawal;
        $this->withdrawal_amount = get_post_meta($withdrawal->ID, '_wallet_withdrawal_amount', true) - get_post_meta($withdrawal->ID, '_wallet_withdrawal_transaction_charge', true);
        $this->currency = get_woocommerce_currency();
        if ($this->validate_request()) {
            if ($this->process_stripe_payment()) {
                return true;
            } else {
                return false;
            }
        }
        return parent::process_payment($withdrawal);
    }

    private function process_stripe_payment() {
        try {
            Stripe::setApiKey($this->stripe_secret_key);
            $transfer_args = array(
                'amount' => $this->get_stripe_amount(),
                'currency' => $this->currency,
                'destination' => get_user_meta($this->withdrawal->post_author, 'stripe_user_id', true),
            );
            $transfer = Transfer::create($transfer_args);
            $result_array = $transfer->jsonSerialize();
            
            update_post_meta($this->withdrawal->ID, '_stripe_transaction_id', $result_array['id']);
            update_post_meta($this->withdrawal->ID, '_stripe_transaction_ref', $result_array['balance_transaction']);
            return true;
        } catch (\Stripe\Error\InvalidRequest $e) {
            woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($e->getMessage(), true));
            return false;
        } catch (\Stripe\Error\Authentication $e) {
            woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($e->getMessage(), true));
            return false;
        } catch (\Stripe\Error\ApiConnection $e) {
            woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($e->getMessage(), true));
            return false;
        } catch (\Stripe\Error\Base $e) {
            woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($e->getMessage(), true));
            return false;
        } catch (Exception $e) {
            woo_wallet_withdrawal()->woo_wallet_withdrawal_logger(print_r($e->getMessage(), true));
            return false;
        }
        return false;
    }

}
