<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Plugin frontend class
 *
 * @author subrata
 */
class WOO_Wallet_Withdrawal_Frontend {

    /**
     * Class Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array(&$this, 'enqueue_frontend_scripts'));
        add_filter('woocommerce_get_query_vars', array($this, 'add_woocommerce_query_vars'));
        add_filter('woocommerce_endpoint_woo-wallet-withdrawal_title', array($this, 'woocommerce_endpoint_title'));
        add_action('woocommerce_account_woo-wallet-withdrawal_endpoint', array($this, 'woo_wallet_withdrawal_endpoint_content'));
        add_action('wp_loaded', array($this, 'init_wp_loaded'));
        add_action('woo_wallet_menu_items', array($this, 'woo_wallet_menu_items'));
        add_filter('woo_wallet_nav_menu_items', array($this, 'woo_wallet_nav_menu_items'), 10, 2);
        add_action('wp_loaded', array($this, 'save_woo_wallet_withdrawal_payment_details'), 20);
        add_action('woo_wallet_shortcode_action', array($this, 'woo_wallet_shortcode_action'));
    }

    public function enqueue_frontend_scripts() {
        wp_register_style('woo-wallet-jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_register_script('woo-wallet-withdrawal', woo_wallet_withdrawal()->plugin_url() . '/assets/js/wallet-withdrawal.js', array('jquery'), WOO_WALLET_WITHDRAWAL_VERSION);
        $is_rendred_from_myaccount = wc_post_content_has_shortcode('woo-wallet') ? false : is_account_page();
        $link = $is_rendred_from_myaccount ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal'))) : add_query_arg('wallet_action', 'wallet_withdrawal', get_permalink());
        wp_localize_script('woo-wallet-withdrawal', 'woo_wallet_withdrawal_param', array('ajax_url' => admin_url('admin-ajax.php'), 'validate_request_nonce' => wp_create_nonce('validate-woo-wallet-withdrawal'), 'settings_link' => $link . '#ww-payment-settings'));
    }

    /**
     * Add WooCommerce query vars.
     * @param type $query_vars
     * @return type
     */
    public function add_woocommerce_query_vars($query_vars) {
        $query_vars['woo-wallet-withdrawal'] = get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal');
        return $query_vars;
    }

    /**
     * Change WooCommerce endpoint title for wallet pages.
     */
    public function woocommerce_endpoint_title() {
        return apply_filters('woo_wallet_withdrawal_account_menu_title', __('Wallet Withdrawal', 'woo-wallet-withdrawal'));
    }

    public function woo_wallet_nav_menu_items($menu_items, $is_rendred_from_myaccount) {
        $menu_items['woo-wallet-withdrawal'] = array(
            'title' => apply_filters('woo_wallet_account_withdrawal_menu_title', __('Withdrawal', 'woo-wallet-withdrawal')),
            'url' => $is_rendred_from_myaccount ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal'))) : add_query_arg('wallet_action', 'wallet_withdrawal', get_permalink()),
            'icon' => 'dashicons dashicons-list-view'
        );
        return $menu_items;
    }

    public function woo_wallet_menu_items() {
        if (version_compare(WOO_WALLET_PLUGIN_VERSION, '1.3.2', '>')) {
            return;
        }
        $is_rendred_from_myaccount = wc_post_content_has_shortcode('woo-wallet') ? false : is_account_page();
        ?>
        <li class="card"><a href="<?php echo $is_rendred_from_myaccount ? esc_url(wc_get_account_endpoint_url(get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal'))) : add_query_arg('wallet_action', 'wallet_withdrawal', get_permalink()); ?>"><span class="dashicons dashicons-list-view"></span><p><?php echo apply_filters('woo_wallet_account_withdrawal_menu_title', __('Withdrawal', 'woo-wallet-withdrawal')); ?></p></a></li>
        <?php
    }

    public function woo_wallet_withdrawal_endpoint_content() {
        wp_enqueue_style('woo-wallet-jquery-ui-css');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('woo-wallet-withdrawal');
        woo_wallet_withdrawal()->get_template('woo-wallet-withdrawal.php');
    }

    public function woo_wallet_shortcode_action($action) {
        if ('wallet_withdrawal' === $action) {
            $this->woo_wallet_withdrawal_endpoint_content();
        }
    }

    public function init_wp_loaded() {
        if (isset($_POST['woo_wallet_withdraw_submit']) && isset($_POST['woo_wallet_withdrawal'])) {
            $response = $this->validate_withdrawal_request();
            if (!$response['is_valid']) {
                wc_add_notice($response['message'], 'error');
            } else {
                $withdrawal_id = WOO_Wallet_Withdrawal_Post_Type::create_post();
                if (!is_wp_error($withdrawal_id)) {
                    $this->process_withdrawal($withdrawal_id);
                    /** code for auto withdrawal * */
                    $payment_method_id = get_post_meta($withdrawal_id, '_wallet_withdrawal_method', true);
                    if (woo_wallet_withdrawal()->gateways->payment_gateways[$payment_method_id]->is_enable_auto_withdrawal()) {
                        WOO_Wallet_Withdrawal_Post_Type::approve_withdrawal($withdrawal_id);
                    }
                    wc_add_notice($response['message']);
                } else {
                    wc_add_notice(__('Something went wrong please try again later', 'woo-wallet-withdrawal'), 'error');
                }
            }
        }
    }

    private function validate_withdrawal_request() {
        $response = array('is_valid' => true, 'message' => '');
        if (wp_verify_nonce($_POST['woo_wallet_withdrawal'], 'woo_wallet_withdrawal')) {
            $wallet_withdrawal_amount = floatval($_POST['wallet_withdrawal_amount']);
            $wallet_withdrawal_method = $_POST['wallet_withdrawal_method'];
            $transaction_charge = WOO_Wallet_Withdrawal_Payment_gateways::get_gateway_charge($wallet_withdrawal_amount, $wallet_withdrawal_method);
            if ($wallet_withdrawal_amount + $transaction_charge > woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), 'edit')) {
                $response = array(
                    'is_valid' => false,
                    'message' => __('You don\'t have enough balance for this request', 'woo-wallet')
                );
            } else if (empty($wallet_withdrawal_method)) {
                $response = array(
                    'is_valid' => false,
                    'message' => __('Invalid payment gateway', 'woo-wallet')
                );
            } else {
                $response = array(
                    'is_valid' => true,
                    'message' => __('Request submitted successfully', 'woo-wallet')
                );
            }
        } else {
            $response = array(
                'is_valid' => false,
                'message' => __('Cheatin&#8217; huh?', 'woo-wallet')
            );
        }
        return $response;
    }

    private function process_withdrawal($withdrawal_id) {
        $wallet_withdrawal_amount = apply_filters('woo_wallet_withdrawal_requested_amount', floatval($_POST['wallet_withdrawal_amount']));
        $wallet_withdrawal_method = $_POST['wallet_withdrawal_method'];
        $transaction_charge = WOO_Wallet_Withdrawal_Payment_gateways::get_gateway_charge($wallet_withdrawal_amount, $wallet_withdrawal_method);
        update_post_meta($withdrawal_id, '_wallet_withdrawal_amount', $wallet_withdrawal_amount);
        update_post_meta($withdrawal_id, '_wallet_withdrawal_currency', get_woocommerce_currency());
        update_post_meta($withdrawal_id, '_wallet_withdrawal_transaction_charge', $transaction_charge);
        update_post_meta($withdrawal_id, '_wallet_withdrawal_method', $wallet_withdrawal_method);
        $withdrawal_transaction_id = woo_wallet()->wallet->debit(get_current_user_id(), ($wallet_withdrawal_amount + $transaction_charge), __('Wallet withdrawal request #', 'woo-wallet-withdrawal') . $withdrawal_id);
        update_wallet_transaction_meta($withdrawal_transaction_id, '_withdrawal_request_id', $withdrawal_id);
        update_post_meta($withdrawal_id, '_wallet_withdrawal_transaction_id', $withdrawal_transaction_id);
        do_action('woo_wallet_withdrawal_update_meta_data', $withdrawal_id);
    }

    public function save_woo_wallet_withdrawal_payment_details() {
        if(isset($_POST['woo_wallet_withdrawal_disconnect_stripe'])){
            $stripe_gateway = woo_wallet_withdrawal()->gateways->payment_gateways['stripe'];
            $stripe_gateway->disconnect_stripe_account(get_current_user_id());
        }
        
        if (isset($_POST['woo_wallet_withdrawal_payment_option']) && wp_verify_nonce($_POST['woo_wallet_withdrawal_payment_option'], 'woo_wallet_withdrawal_payment_option')) {
            $user_id = get_current_user_id();
            $user = new WP_User($user_id);
            
            $bank_account_details = woo_wallet_withdrawal()->get_bank_account_settings();
            
            foreach ($bank_account_details as $details){
                $meta_value = isset($_POST[$details['name']]) && !empty($_POST[$details['name']]) ? wc_clean($_POST[$details['name']]) : '';
                update_user_meta($user_id, '_'.$details['name'], $meta_value);
            }

            $woo_wallet_withdrawal_paypal_email = !empty($_POST['woo_wallet_withdrawal_paypal_email']) ? wc_clean($_POST['woo_wallet_withdrawal_paypal_email']) : '';
            update_user_meta($user_id, '_woo_wallet_withdrawal_paypal_email', $woo_wallet_withdrawal_paypal_email);
            if ('on' === woo_wallet()->settings_api->get_option('cashfree', '_wallet_settings_withdrawal', 'off') && !get_user_meta($user_id, '_cashfree_beneid', true)) {
                $cashfree_account_name = !empty($_POST['cashfree_account_name']) ? wc_clean($_POST['cashfree_account_name']) : '';
                $cashfree_account_number = !empty($_POST['cashfree_account_number']) ? wc_clean($_POST['cashfree_account_number']) : '';
                $cashfree_bank_address = !empty($_POST['cashfree_bank_address']) ? wc_clean($_POST['cashfree_bank_address']) : '';
                $cashfree_ifsc_code = !empty($_POST['cashfree_ifsc_code']) ? wc_clean($_POST['cashfree_ifsc_code']) : '';
                $cashfree_phone = !empty($_POST['cashfree_phone']) ? wc_clean($_POST['cashfree_phone']) : '';

                update_user_meta($user_id, '_cashfree_account_name', $cashfree_account_name);
                update_user_meta($user_id, '_cashfree_account_number', $cashfree_account_number);
                update_user_meta($user_id, '_cashfree_bank_address', $cashfree_bank_address);
                update_user_meta($user_id, '_cashfree_ifsc_code', $cashfree_ifsc_code);
                update_user_meta($user_id, '_cashfree_phone', $cashfree_phone);
                $beneficiary_data = array(
                    "beneId" => $user->user_login,
                    "name" => $cashfree_account_name,
                    "email" => $user->user_email,
                    "phone" => $cashfree_phone,
                    "bankAccount" => $cashfree_account_number,
                    "ifsc" => $cashfree_ifsc_code,
                    "address1" => $cashfree_bank_address
                );
                $client_id = woo_wallet()->settings_api->get_option('_cashfree_client_id', '_wallet_settings_withdrawal');
                $client_secret = woo_wallet()->settings_api->get_option('_cashfree_client_secret', '_wallet_settings_withdrawal');
                $is_test_mode = 'on' === woo_wallet()->settings_api->get_option('_is_cashfree_test_mode', '_wallet_settings_withdrawal', 'off') ? true : false;
                $cashfree_api = new CashFreeAPI($client_id, $client_secret, $is_test_mode);
                $response = $cashfree_api->add_beneficiary($beneficiary_data);
                if('ERROR' === $response['status']){
                    wc_add_notice($response['message'], 'error');
                } else{
                    wc_add_notice($response['message']);
                    update_user_meta($user_id, '_cashfree_beneid', $user->user_login);
                }
            }

            wc_add_notice(__('Payment details changed successfully.', 'woo-wallet-withdrawal'));
        }
    }

}

new WOO_Wallet_Withdrawal_Frontend();
