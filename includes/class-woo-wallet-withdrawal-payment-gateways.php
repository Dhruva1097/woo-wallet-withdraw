<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Wallet withdrawal payment gateways.
 *
 * @author subrata
 */
class WOO_Wallet_Withdrawal_Payment_gateways {

    /** @var array Array of payment gateway classes. */
    public $payment_gateways;

    /**
     * @var WOO_Wallet_Withdrawal_Payment_gateways The single instance of the class
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Main WOO_Wallet_Withdrawal_Payment_gateways Instance.
     *
     * Ensures only one instance of WOO_Wallet_Withdrawal_Payment_gateways is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return WOO_Wallet_Withdrawal_Payment_gateways Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Class Constructor
     */
    public function __construct() {
        $this->load_gateways_sdk();
        $this->load_default_gateways();
        $this->init();
    }

    public function init() {
        $load_gateways = array(
            'WOO_Wallet_Gateway_BACS',
            'WOO_Wallet_Gateway_Paypal',
            'WOO_Wallet_Gateway_Stripe'
        );
        if ('INR' === get_woocommerce_currency()) {
            $load_gateways[] = 'WOO_Wallet_Gateway_Cashfree';
        }
        $load_gateways = apply_filters('woo_wallet_withdrawal_payment_gateways', $load_gateways);
        foreach ($load_gateways as $gateway) {
            $load_gateway = is_string($gateway) ? new $gateway() : $gateway;
            $this->payment_gateways[$load_gateway->id] = $load_gateway;
        }
    }

    public function load_gateways_sdk() {
        require_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/gateways/sdk/class-cashfree.php');
        if (!class_exists("Stripe\Stripe")) {
            require_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/gateways/sdk/Stripe/init.php');
        }
        do_action('woo_wallet_withdrawal_load_gateways_sdk');
    }

    public function load_default_gateways() {
        require_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/gateways/class-woo-wallet-gateway-bacs.php');
        require_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/gateways/class-woo-wallet-gateway-paypal.php');
        require_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/gateways/class-woo-wallet-gateway-stripe.php');
        require_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/gateways/class-woo-wallet-gateway-cashfree.php');
        do_action('woo_wallet_withdrawal_load_gateways');
    }

    public function get_available_gateways() {
        $gateways = array();
        foreach ($this->payment_gateways as $gateway) {
            if ($gateway->is_available()) {
                $gateways[] = $gateway;
            }
        }
        return $gateways;
    }

    public static function get_gateway_charge($amount, $id) {
        $charge_amount = 0;
        if ('on' === woo_wallet()->settings_api->get_option('_is_enable_gateway_charge', '_wallet_settings_withdrawal', 'off')) {
            $type = woo_wallet()->settings_api->get_option('_withdrawal_gateway_charge_type', '_wallet_settings_withdrawal', 'percent');
            if ('percent' === $type) {
                $charge_amount = $amount * (woo_wallet()->settings_api->get_option('_charge_' . $id, '_wallet_settings_withdrawal', 0) / 100);
            } else {
                $charge_amount = woo_wallet()->settings_api->get_option('_charge_' . $id, '_wallet_settings_withdrawal', 0);
            }
        }
        return apply_filters('woo_wallet_withdrawal_gateway_charge', $charge_amount, $amount, $id);
    }

}
