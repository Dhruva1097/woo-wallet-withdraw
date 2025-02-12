<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Woo_Wallet_License')) {

    class Woo_Wallet_License {

        public $plugin_token;
        public $plugin_name;
        public $upgrade_url;
        public $license_software_product_id;
        public $license_plugin_name;
        public $license_renew_license_url;
        public $api_manager_license_version_name;
        public $license_update_check;
        public $license_data_key;
        public $license_product_id_key;
        public $license_instance_key;
        public $license_activated_key;
        public $license_options = array();
        public $license_software_version = '1.0.0';

        /**
         * API license manager class.
         * @var Class object 
         */
        public $api_manager_calss = null;

        public function __construct($plugin = array()) {

            $defaults = array(
                'plugin_server_url' => 'https://woowallet.in/',
                'plugin_token' => '',
                'version' => '1.0.0'
            );
            $plugin = wp_parse_args($plugin, $defaults);
            $this->plugin_token = $plugin['plugin_token'];
            $this->plugin_name = str_replace('-', '_', $this->plugin_token);
            $this->upgrade_url = $plugin['plugin_server_url'];
            $this->license_software_product_id = $plugin['plugin_token'];
            $this->license_plugin_name = $plugin['plugin_token'] . '/' . $plugin['plugin_token'] . '.php';
            $this->license_software_version = $plugin['version'];
            $this->license_renew_license_url = $plugin['plugin_server_url'] . 'my-account';
            $this->api_manager_license_version_name = $this->plugin_name . '_license_version';


            $this->license_update_check = $this->plugin_name . '_update_check';
            $this->license_data_key = '_wallet_settings_extensions_' . $this->plugin_name . '_license';
            $this->license_product_id_key = $this->plugin_name . '_license_product_id';
            $this->license_instance_key = $this->plugin_name . '_license_instance';
            $this->license_activated_key = $this->plugin_name . '_license_activated';


            /**
             * Set all software update data here
             */
            $this->license_options = get_option($this->license_data_key);

            if (!get_option($this->license_product_id_key)) {
                $this->activation();
            }

            // Performs activations and deactivations of API License Keys
            self::load_class('class-woo-wallet-api.php');
            $this->api_manager_calss = new Woo_Wallet_Plugin_Api();

            // Checks for software updatess
            self::load_class('class-woo-wallet-updater.php');

            /**
             * Check for software updates
             */
            if (!empty($this->license_options) && $this->license_options !== false) {

                new Woo_Wallet_Plugin_Update_Check(
                        $this->upgrade_url, $this->license_plugin_name, get_option($this->license_product_id_key), $this->license_options['licence_key'], $this->license_options['license_product_id'], $this->license_renew_license_url, get_option($this->license_instance_key), $this->get_site_url(), $this->license_software_version, 'plugin', $this->plugin_token
                );
            }
        }

        /**
         * Generate the default data arrays
         */
        public function activation() {

            $global_options = array(
                'licence_key' => '',
                'license_product_id' => '',
                'is_activate' => ''
            );

            update_option($this->license_data_key, $global_options);
            // Generate a unique installation $instance id
            $instance = $this->generate_password(12, false);

            $single_options = array(
                $this->license_product_id_key => $this->license_software_product_id,
                $this->license_instance_key => $instance,
                $this->license_activated_key => 'Deactivated',
            );

            foreach ($single_options as $key => $value) {
                update_option($key, $value);
            }

            $curr_ver = get_option($this->api_manager_license_version_name);

            // checks if the current plugin version is lower than the version being installed
            if (version_compare($this->license_software_version, $curr_ver, '>')) {
                // update the version
                update_option($this->api_manager_license_version_name, $this->license_software_version);
            }
        }

        /**
         * Deletes all data if plugin deactivated
         * @return void
         */
        public function uninstall() {
            global $blog_id;
            $this->license_key_deactivation();
            // Remove options
            if (is_multisite()) {
                switch_to_blog($blog_id);
                foreach (array($this->license_data_key, $this->license_product_id_key, $this->license_instance_key, $this->license_activated_key) as $option) {
                    delete_option($option);
                }

                restore_current_blog();
            } else {
                foreach (array($this->license_data_key, $this->license_product_id_key, $this->license_instance_key, $this->license_activated_key) as $option) {
                    delete_option($option);
                }
            }
        }

        /**
         * Deactivates the license on the API server
         * @return void
         */
        public function license_key_deactivation() {
            $activation_status = get_option($this->license_activated_key);
            $api_product_id = $this->license_options['license_product_id'];
            $api_key = $this->license_options['licence_key'];
            $args = array(
                'request' => 'deactivation',
                'product_id' => $api_product_id,
                'instance' => get_option($this->license_instance_key),
                'api_key' => $api_key,
                'object' => $this->get_site_url(),
                'version' => $this->license_software_version,
                'slug' => $this->license_plugin_name,
                'server_url' => $this->upgrade_url
            );

            if ($activation_status == 'Activated' && $api_key != '' && $api_product_id != '') {
                $this->api_manager_calss->deactivate($args); // reset license key activation
            }
        }

        /**
         * Load API classes
         * @param string $class_name
         */
        public static function load_class($class_name = '') {
            if ('' != $class_name) {
                require_once (dirname(__FILE__) . '/license/' . esc_attr($class_name));
            }
        }

        /**
         * Manage API license 
         * @param array $args
         */
        public function manage_api_license($args = array()) {

            $defaults = array(
                'request' => 'activation',
                'product_id' => get_option($this->license_product_id_key),
                'instance' => get_option($this->license_instance_key),
                'object' => $this->get_site_url(),
                'version' => $this->license_software_version,
                'slug' => $this->license_plugin_name,
                'server_url' => $this->upgrade_url
            );
            $args = wp_parse_args($args, $defaults);
            // Performs activations and deactivations of API License Keys
            $activation_status = get_option($this->license_activated_key);

            if ('off' == $args['activation_status']) {

                // Plugin Activation
                if ($activation_status == 'Deactivated' || $activation_status == '') {

                    $activate_results = json_decode($this->api_manager_calss->activate($args), true);

                    if ($activate_results['activated'] == true) {
                        add_settings_error("", esc_attr("settings_admin_error"), __('Plugin activated. ') . "{$activate_results['message']}.", 'updated');
                        update_option($this->license_activated_key, 'Activated');
                    }

                    if ($activate_results == false) {
                        add_settings_error("", esc_attr("settings_admin_error"), __('Connection failed to the License Key API server. Try again later.'), 'error');
                        update_option($this->license_activated_key, 'Deactivated');
                    }

                    if (isset($activate_results['code'])) {

                        switch ($activate_results['code']) {
                            case '100':
                                add_settings_error("", esc_attr("settings_admin_error"), "{$activate_results['error']}. {$activate_results['additional info']}", 'error');
                                update_option($this->license_activated_key, 'Deactivated');
                                break;
                            case '101':
                                add_settings_error("", esc_attr("settings_admin_error"), "{$activate_results['error']}. {$activate_results['additional info']}", 'error');
                                update_option($this->license_activated_key, 'Deactivated');
                                break;
                            case '102':
                                add_settings_error("", esc_attr("settings_admin_error"), "{$activate_results['error']}. {$activate_results['additional info']}", 'error');
                                update_option($this->license_activated_key, 'Deactivated');
                                break;
                            case '103':
                                add_settings_error("", esc_attr("settings_admin_error"), "{$activate_results['error']}. {$activate_results['additional info']}", 'error');
                                update_option($this->license_activated_key, 'Deactivated');
                                break;
                            case '104':
                                add_settings_error("", esc_attr("settings_admin_error"), "{$activate_results['error']}. {$activate_results['additional info']}", 'error');
                                update_option($this->license_activated_key, 'Deactivated');
                                break;
                            case '105':
                                add_settings_error("", esc_attr("settings_admin_error"), "{$activate_results['error']}. {$activate_results['additional info']}", 'error');
                                update_option($this->license_activated_key, 'Deactivated');
                                break;
                            case '106':
                                add_settings_error("", esc_attr("settings_admin_error"), "{$activate_results['error']}. {$activate_results['additional info']}", 'error');
                                update_option($this->license_activated_key, 'Deactivated');
                                break;
                        }
                    }
                } else {
                    add_settings_error("", esc_attr("settings_admin_error"), __('Plugin activated.'), 'updated');
                    update_option($this->license_activated_key, 'Activated');
                }
            } else {
                if ($activation_status == 'Activated') {
                    $args['request'] = 'deactivation';
                    if ($this->api_manager_calss->deactivate($args)) {
                        update_option($this->license_activated_key, 'Deactivated');
                        delete_option($this->license_data_key);
                        add_settings_error("", esc_attr("settings_admin_error"), __('Plugin license deactivated.'), 'updated');
                    }
                }
            }
        }

        private function rand($min = 0, $max = 0) {
            global $rnd_value;

            // Reset $rnd_value after 14 uses
            // 32(md5) + 40(sha1) + 40(sha1) / 8 = 14 random numbers from $rnd_value
            if (strlen($rnd_value) < 8) {
                if (defined('WP_SETUP_CONFIG')) {
                    static $seed = '';
                } else {
                    $seed = get_transient('random_seed');
                }
                $rnd_value = md5(uniqid(microtime() . mt_rand(), true) . $seed);
                $rnd_value .= sha1($rnd_value);
                $rnd_value .= sha1($rnd_value . $seed);
                $seed = md5($seed . $rnd_value);
                if (!defined('WP_SETUP_CONFIG')) {
                    set_transient('random_seed', $seed);
                }
            }

            // Take the first 8 digits for our value
            $value = substr($rnd_value, 0, 8);

            // Strip the first eight, leaving the remainder for the next call to wp_rand().
            $rnd_value = substr($rnd_value, 8);

            $value = abs(hexdec($value));

            // Some misconfigured 32bit environments (Entropy PHP, for example) truncate integers larger than PHP_INT_MAX to PHP_INT_MAX rather than overflowing them to floats.
            $max_random_number = 3000000000 === 2147483647 ? (float) "4294967295" : 4294967295; // 4294967295 = 0xffffffff
            // Reduce the value to be within the min - max range
            if ($max != 0)
                $value = $min + ( $max - $min + 1 ) * $value / ( $max_random_number + 1 );

            return abs(intval($value));
        }

        // Creates a unique instance ID
        protected function generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            if ($special_chars) {
                $chars .= '!@#$%^&*()';
            }
            if ($extra_special_chars) {
                $chars .= '-_ []{}<>~`+=,.;:/?|';
            }

            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= substr($chars, $this->rand(0, strlen($chars) - 1), 1);
            }

            // random_password filter was previously in random_password function which was deprecated
            return $password;
        }

        public function get_site_url() {
            $url = site_url();
            $disallowed = array('http://', 'https://');
            foreach ($disallowed as $d) {
                if (strpos($url, $d) === 0) {
                    return str_replace($d, '', $url);
                }
            }
            return $url;
        }

    }

}