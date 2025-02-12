<?php

/**
 * WooCommerce API Manager API Key Class
 *
 * @package Update API Manager/Key Handler
 * @since 1.3
 *
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Woo_Wallet_Plugin_Api')) {

    class Woo_Wallet_Plugin_Api {

        // API Key URL
        public function create_software_api_url($args) {

            $api_url = add_query_arg('wc-api', 'am-software-api', $args['server_url']);
            
            if(isset($args['server_url'])){
                unset($args['server_url']);
            }

            return $api_url . '&' . http_build_query($args);
        }

        public function activate($args) {

            $target_url = self::create_software_api_url($args);

            $request = wp_remote_get($target_url, array('sslverify' => false));

            if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
                // Request failed
                return false;
            }

            $response = wp_remote_retrieve_body($request);

            return $response;
        }

        public function deactivate($args) {
            $target_url = $this->create_software_api_url($args);
            $request = wp_remote_get($target_url, array('sslverify' => false));
            if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
                // Request failed
                return false;
            }
            $response = wp_remote_retrieve_body($request);
            return $response;
        }

        /**
         * Checks if the software is activated or deactivated
         * @param  array $args
         * @return array
         */
        public function status($args) {

            $defaults = array(
                'request' => 'status',
                'product_id' => '', // $license_product_id_key
                'instance' => '', //license_instance_key
                'platform' => site_url()
            );

            $args = wp_parse_args($args, $defaults );

            $target_url = self::create_software_api_url($args);

            $request = wp_remote_get($target_url, array('sslverify' => false));

            if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
                // Request failed
                return false;
            }

            $response = wp_remote_retrieve_body($request);

            return $response;
        }

    }

}