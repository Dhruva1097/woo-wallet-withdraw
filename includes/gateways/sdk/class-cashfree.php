<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('CashFreeAPI')) {

    class CashFreeAPI {

        /**
         * CashFree client id.
         * @var string 
         */
        private $client_id = null;

        /**
         * CashFree client secret.
         * @var string 
         */
        private $client_secret = null;

        /**
         * CashFree endpoint URL.
         * @var string 
         */
        private $endpoint = 'https://payout-api.cashfree.com';

        public function __construct($client_id, $client_secret, $test_mode = false) {
            $this->client_id = $client_id;
            $this->client_secret = $client_secret;
            if ($test_mode) {
                $this->endpoint = 'https://payout-gamma.cashfree.com';
            }
        }

        public function get_access_token() {
            if (get_option('_cashfree_access_token')) {
                if ($this->verify_access_token(get_option('_cashfree_access_token'))) {
                    return array('success' => true, 'token' => get_option('_cashfree_access_token'));
                }
            }
            $response = wp_remote_post($this->endpoint . '/payout/v1/authorize', array(
                'headers' => array(
                    'X-Client-Id' => $this->client_id,
                    'X-Client-Secret' => $this->client_secret,
                    'X-Cf-Signature' => $this->getSignature(),
                    'cache-control' => 'no-cache'
                )
            ));
            if (is_wp_error($response)) {
                return array('success' => false, 'error_message' => $response->get_error_message());
            } else {
                $resualt = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($resualt['status']) && 'SUCCESS' === $resualt['status']) {
                    update_option('_cashfree_access_token', $resualt['data']['token']);
                    return array('success' => true, 'token' => $resualt['data']['token']);
                }
                return array('success' => false, 'error_message' => $resualt['message']);
            }
        }

        public function getSignature() {
            $upload_dir = wp_upload_dir();
            if(file_exists($upload_dir['basedir']. 'public_key.pem')){
                $public_key = openssl_pkey_get_public(file_get_contents($upload_dir['basedir']. 'public_key.pem'));
                $encodedData = $this->client_id . "." . strtotime("now");
                return $this->encrypt_RSA($encodedData, $public_key);
            }
            return false;
        }

        private function encrypt_RSA($plainData, $publicKey) {
            if (openssl_public_encrypt($plainData, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
                $encryptedData = base64_encode($encrypted);
            } else {
                return NULL;
            }
            return $encryptedData;
        }

        private function verify_access_token($token) {
            $response = wp_remote_post($this->endpoint . '/payout/v1/verifyToken', array(
                'headers' => array(
                    "Authorization" => "Bearer $token"
                )
            ));
            if (is_wp_error($response)) {
                return false;
            } else {
                $resualt = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($resualt['status']) && 'SUCCESS' === $resualt['status']) {
                    return true;
                }
                return false;
            }
        }

        public function add_beneficiary($args = array()) {
            $token = $this->get_access_token();
            if (isset($token['token'])) {
                $access_token = $token['token'];
                $response = wp_remote_post($this->endpoint . '/payout/v1/addBeneficiary', array(
                    'headers' => array(
                        "Authorization" => "Bearer $access_token"
                    ),
                    'body' => json_encode($args)
                ));

                if (is_wp_error($response)) {
                    return false;
                } else {
                    $resualt = json_decode(wp_remote_retrieve_body($response), true);
                    return $resualt;
                }
            } else {
                return array('status' => 'ERROR', 'subCode' => '500', 'message' => __('Cashfree not setup properly please contact with administrator.', 'woo-wallet-withdrawal'));
            }
        }

        public function request_transfer($args = array()) {
            $token = $this->get_access_token();
            if (isset($token['token'])) {
                $access_token = $token['token'];
                $response = wp_remote_post($this->endpoint . '/payout/v1/requestTransfer', array(
                    'headers' => array(
                        "Authorization" => "Bearer $access_token"
                    ),
                    'body' => json_encode($args)
                ));

                if (is_wp_error($response)) {
                    return false;
                } else {
                    $resualt = json_decode(wp_remote_retrieve_body($response), true);
                    return $resualt;
                }
            } else {
                return array('status' => 'ERROR', 'subCode' => '500', 'message' => __('Cashfree not setup properly please contact with administrator.', 'woo-wallet-withdrawal'));
            }
        }

    }

}