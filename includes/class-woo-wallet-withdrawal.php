<?php
/**
 * Withdrawal plugin main class
 *
 * @author subrata
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

final class WOO_WALLET_WITHDRAWAL {

    /**
     * The single instance of the class.
     *
     * @var WOO_WALLET_WITHDRAWAL
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Plugin license API class.
     * @var Woo_Wallet_License
     */
    public $licence = null;

    /**
     * WOO_Wallet_Withdrawal_Payment_gateways class instance
     * @var WOO_Wallet_Withdrawal_Payment_gateways
     */
    public $gateways = null;
    public $logger = null;

    /**
     * Main instance
     * @return class object
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Class constructor
     */
    public function __construct() {
        if (Woo_Wallet_Dependencies::is_woo_wallet_active()) {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
            do_action('woo_wallet_withdrawal_loaded');
        } else {
            add_action('admin_notices', array($this, 'admin_notices'), 15);
        }
    }

    /**
     * Constants define
     */
    private function define_constants() {
        $this->define('WOO_WALLET_WITHDRAWAL_ABSPATH', dirname(WOO_WALLET_WITHDRAWAL_PLUGIN_FILE) . '/');
        $this->define('WOO_WALLET_WITHDRAWAL_PLUGIN_SERVER_URL', 'https://woowallet.in/');
        $this->define('WOO_WALLET_WITHDRAWAL_PLUGIN_TOKEN', 'woo-wallet-withdrawal');
        $this->define('WOO_WALLET_WITHDRAWAL_VERSION', '1.0.6');
    }

    /**
     * 
     * @param string $name
     * @param mixed $value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Check request
     * @param string $type
     * @return bool
     */
    private function is_request($type) {
        switch ($type) {
            case 'admin' :
                return is_admin();
            case 'ajax' :
                return defined('DOING_AJAX');
            case 'cron' :
                return defined('DOING_CRON');
            case 'frontend' :
                return (!is_admin() || defined('DOING_AJAX') ) && !defined('DOING_CRON');
        }
    }

    /**
     * load plugin files
     */
    public function includes() {
        include_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/class-woo-wallet-withdrawal-post-type.php');
        if ($this->is_request('admin')) {
            include_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/class-woo-wallet-withdrawal-admin.php');
            include_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/class-woo-wallet-license.php');
        }
        if ($this->is_request('frontend')) {
            include_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/class-woo-wallet-withdrawal-frontend.php');
        }
        if ($this->is_request('ajax')) {
            include_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/class-woo-wallet-withdrawal-ajax.php');
        }
    }

    /**
     * Plugin init
     */
    private function init_hooks() {
        // Set up localisation.
        $this->load_plugin_textdomain();
        add_action('init', array($this, 'init'), 5);
        add_action('admin_init', array($this, 'admin_init'));
    }

    public function admin_init() {
        if (get_option('_wallet_settings_extensions_withdrawal_license')) {
            update_option('_wallet_settings_extensions_woo_wallet_withdrawal_license', get_option('_wallet_settings_extensions_withdrawal_license'));
            delete_option('_wallet_settings_extensions_withdrawal_license');
        }
        $plugin = array(
            'plugin_server_url' => WOO_WALLET_WITHDRAWAL_PLUGIN_SERVER_URL,
            'plugin_token' => WOO_WALLET_WITHDRAWAL_PLUGIN_TOKEN,
            'version' => WOO_WALLET_WITHDRAWAL_VERSION
        );
        $this->licence = new Woo_Wallet_License($plugin);
        if (get_option('woo_wallet_withdrawal_license_activated') != 'Activated') {
            add_action('admin_notices', array($this, 'license_inactive_notice'));
        }
    }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any following ones if the same translation is present.
     *
     */
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'woo-wallet-withdrawal');

        unload_textdomain('woo-wallet-withdrawal');
        load_textdomain('woo-wallet-withdrawal', WP_LANG_DIR . '/woo-wallet-withdrawal/woo-wallet-withdrawal-' . $locale . '.mo');
        load_plugin_textdomain('woo-wallet-withdrawal', false, plugin_basename(dirname(WOO_WALLET_WITHDRAWAL_PLUGIN_FILE)) . '/languages');
    }

    public function init() {
        include_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/abstracts/abstract-woo-wallet-payment-gateway.php');
        include_once(WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/class-woo-wallet-withdrawal-payment-gateways.php');
        $this->gateways = WOO_Wallet_Withdrawal_Payment_gateways::instance();
        add_rewrite_endpoint(get_option('woocommerce_woo_wallet_withdrawal_endpoint', 'woo-wallet-withdrawal'), EP_PAGES);
        if (!get_option('_wallet_withdrawal_enpoint_added')) {
            flush_rewrite_rules();
            update_option('_wallet_withdrawal_enpoint_added', true);
        }
        add_filter('woocommerce_email_classes', array($this, 'woocommerce_email_classes'));
    }

    public function woo_wallet_withdrawal_logger($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->logger = wc_get_logger();
            $this->logger->debug($message, array('source' => 'woo-wallet-withdrawal'));
        }
    }

    /**
     * WooCommerce email loader
     * @param array $emails
     * @return array
     */
    public function woocommerce_email_classes($emails) {
        $emails['WOO_Wallet_Withdrawal_Request'] = include WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/emails/class-woo-wallet-withdrawal-request.php';
        $emails['WOO_Wallet_Withdrawal_Reject'] = include WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/emails/class-woo-wallet-withdrawal-reject.php';
        $emails['WOO_Wallet_Withdrawal_Approved'] = include WOO_WALLET_WITHDRAWAL_ABSPATH . 'includes/emails/class-woo-wallet-withdrawal-approve.php';
        return $emails;
    }

    /**
     * Load template
     * @param string $template_name
     * @param array $args
     * @param string $template_path
     * @param string $default_path
     */
    public function get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
        if ($args && is_array($args)) {
            extract($args);
        }
        $located = $this->locate_template($template_name, $template_path, $default_path);
        include ($located);
    }

    /**
     * Locate template file
     * @param string $template_name
     * @param string $template_path
     * @param string $default_path
     * @return string
     */
    public function locate_template($template_name, $template_path = '', $default_path = '') {
        $default_path = apply_filters('woo_wallet_withdrawal_template_path', $default_path);
        if (!$template_path) {
            $template_path = 'woo-wallet-withdrawal';
        }
        if (!$default_path) {
            $default_path = WOO_WALLET_WITHDRAWAL_ABSPATH . 'templates/';
        }
        // Look within passed path within the theme - this is priority
        $template = locate_template(array(trailingslashit($template_path) . $template_name, $template_name));
        // Add support of third perty plugin
        $template = apply_filters('woo_wallet_locate_template', $template, $template_name, $template_path, $default_path);
        // Get default template
        if (!$template) {
            $template = $default_path . $template_name;
        }
        return $template;
    }

    /**
     * Plugin url
     * @return string path
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', WOO_WALLET_WITHDRAWAL_PLUGIN_FILE));
    }

    public function deactivation_hook() {
        woo_wallet_withdrawal()->licence->uninstall();
    }

    /**
     * Display admin notice
     */
    public function admin_notices() {
        echo '<div class="error"><p>';
        _e('WooCommerce Wallet Withdrawal plugin requires <a href="http://wordpress.org/extend/plugins/woo-wallet/">WooCommerce Wallet</a> plugins to be active!', 'woo-wallet-withdrawal');
        echo '</p></div>';
    }

    public function license_inactive_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_GET['page']) && 'woo-wallet-extensions' == $_GET['page']) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php printf(__('%sClick here%s to activate WooCommerce Wallet Withdrawal license key to receive updates and support.', 'woo-wallet-withdrawal'), '<a href="' . esc_url(admin_url('admin.php?page=woo-wallet-extensions&activewwtab=_wallet_settings_extensions_woo_wallet_withdrawal_license')) . '">', '</a>'); ?></p>
        </div>
        <?php
    }

    public function get_bank_account_settings() {
        $country = WC()->countries->get_base_country();
        $locale = $this->get_country_locale();

        // Get sortcode label in the $locale array and use appropriate one.
        $sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce');
        $bank_account_details = apply_filters('woo_wallet_withdrawal_bacs_account', array(
            array(
                'label' => __('Account name', 'woo-wallet-withdrawal'),
                'name' => 'bacs_account_name'
            ),
            array(
                'label' => __('Account number', 'woo-wallet-withdrawal'),
                'name' => 'bacs_account_number'
            ),
            array(
                'label' => $sortcode,
                'name' => 'bacs_sort_code'
            ),
            array(
                'label' => __('Bank name', 'woo-wallet-withdrawal'),
                'name' => 'bacs_bank_name'
            ),
            array(
                'label' => __('Routing number', 'woo-wallet-withdrawal'),
                'name' => 'bacs_bank_routing_number'
            ),
            array(
                'label' => __('IBAN', 'woo-wallet-withdrawal'),
                'name' => 'bacs_bank_iban'
            ),
            array(
                'label' => __('Swift code', 'woo-wallet-withdrawal'),
                'name' => 'bacs_bank_swift_code'
            )
        ));
        return $bank_account_details;
    }

    public function get_country_locale() {

        if (empty($this->locale)) {

            // Locale information to be used - only those that are not 'Sort Code'.
            $this->locale = apply_filters(
                    'woocommerce_get_bacs_locale', array(
                'AU' => array(
                    'sortcode' => array(
                        'label' => __('BSB', 'woocommerce'),
                    ),
                ),
                'CA' => array(
                    'sortcode' => array(
                        'label' => __('Bank transit number', 'woocommerce'),
                    ),
                ),
                'IN' => array(
                    'sortcode' => array(
                        'label' => __('IFSC', 'woocommerce'),
                    ),
                ),
                'IT' => array(
                    'sortcode' => array(
                        'label' => __('Branch sort', 'woocommerce'),
                    ),
                ),
                'NZ' => array(
                    'sortcode' => array(
                        'label' => __('Bank code', 'woocommerce'),
                    ),
                ),
                'SE' => array(
                    'sortcode' => array(
                        'label' => __('Bank code', 'woocommerce'),
                    ),
                ),
                'US' => array(
                    'sortcode' => array(
                        'label' => __('Routing number', 'woocommerce'),
                    ),
                ),
                'ZA' => array(
                    'sortcode' => array(
                        'label' => __('Branch code', 'woocommerce'),
                    ),
                ),
                    )
            );
        }

        return $this->locale;
    }

}
