<?php

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('WOO_Wallet_Withdrawal_Request')) :

    /**
     *
     * An email sent to the admin when a new withdraw request submitted.
     *
     * @class       WOO_Wallet_Withdrawal_Request
     * @version     1.0.1
     * @author      WooWallet
     * @extends     WC_Email
     */
    class WOO_Wallet_Withdrawal_Request extends WC_Email {

        /**
         * Constructor.
         */
        public function __construct() {
            $this->id = 'woo_wallet_withdrawal_request';
            $this->title = __('New wallet Withdrawal Request', 'woo-wallet-withdrawal');
            $this->description = __('These emails are sent to chosen recipient(s) when a user send request to withdraw', 'woo-wallet-withdrawal');
            $this->template_html = 'emails/withdraw-request.php';
            $this->template_plain = 'emails/plain/withdraw-request.php';
            $this->template_base = WOO_WALLET_WITHDRAWAL_ABSPATH . 'templates/';

            // Triggers for this email
            add_action('woo_wallet_withdrawal_update_meta_data', array($this, 'trigger'), 30);

            // Call parent constructor
            parent::__construct();

            // Other settings
            $this->recipient = $this->get_option('recipient', get_option('admin_email'));
        }

        /**
         * Get email subject.
         *
         * @since  3.1.0
         * @return string
         */
        public function get_default_subject() {
            return __('[{site_name}] A New wallet withdrawal request is made by {user_name}', 'woo-wallet-withdrawal');
        }

        /**
         * Get email heading.
         *
         * @since  3.1.0
         * @return string
         */
        public function get_default_heading() {
            return __('New wallet withdraw', 'woo-wallet-withdrawal');
        }

        /**
         * Trigger the sending of this email.
         *
         * @param int $product_id The product ID.
         * @param array $postdata.
         */
        public function trigger($request_id) {

            if (!$this->is_enabled() || !$this->get_recipient()) {
                return;
            }
            $withdrawal = get_post($request_id);
            $requested_user = get_user_by('ID', $withdrawal->post_author);
            $gateways = woo_wallet_withdrawal()->gateways->payment_gateways;
            $this->find['username'] = '{user_name}';
            $this->find['amount'] = '{amount}';
            $this->find['method'] = '{method}';
            $this->find['profile_url'] = '{profile_url}';
            $this->find['withdraw_page'] = '{withdraw_page}';
            $this->find['site_name'] = '{site_name}';
            $this->find['site_url'] = '{site_url}';

            $this->replace['username'] = $requested_user->user_login;
            $this->replace['amount'] = wc_price(get_post_meta($withdrawal->ID, '_wallet_withdrawal_amount', true));
            $this->replace['method'] = isset($gateways[get_post_meta($withdrawal->ID, '_wallet_withdrawal_method', true)]) ? $gateways[get_post_meta($withdrawal->ID, '_wallet_withdrawal_method', true)]->get_method_title() : '';
            $this->replace['profile_url'] = admin_url('user-edit.php?user_id=' . $requested_user->ID);
            $this->replace['withdraw_page'] = admin_url('edit.php?post_status=ww-pending&post_type=wallet_withdrawal');
            $this->replace['site_name'] = $this->get_from_name();
            $this->replace['site_url'] = site_url();

            $this->setup_locale();
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            $this->restore_locale();
        }

        /**
         * Get content html.
         *
         * @access public
         * @return string
         */
        public function get_content_html() {
            ob_start();
            wc_get_template($this->template_html, array(
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => true,
                'plain_text' => false,
                'email' => $this,
                'data' => $this->replace
                    ), 'woo-wallet-withdrawal/', $this->template_base);
            return ob_get_clean();
        }

        /**
         * Get content plain.
         *
         * @access public
         * @return string
         */
        public function get_content_plain() {
            ob_start();
            wc_get_template($this->template_html, array(
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => true,
                'plain_text' => true,
                'email' => $this,
                'data' => $this->replace
                    ), 'woo-wallet-withdrawal/', $this->template_base);
            return ob_get_clean();
        }

        /**
         * Initialise settings form fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woo-wallet-withdrawal'),
                    'type' => 'checkbox',
                    'label' => __('Enable this email notification', 'woo-wallet-withdrawal'),
                    'default' => 'yes',
                ),
                'recipient' => array(
                    'title' => __('Recipient(s)', 'woo-wallet-withdrawal'),
                    'type' => 'text',
                    'description' => sprintf(__('Enter recipients (comma separated) for this email. Defaults to %s.', 'woo-wallet-withdrawal'), '<code>' . esc_attr(get_option('admin_email')) . '</code>'),
                    'placeholder' => '',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'subject' => array(
                    'title' => __('Subject', 'woo-wallet-withdrawal'),
                    'type' => 'text',
                    'desc_tip' => true,
                    /* translators: %s: list of placeholders */
                    'description' => sprintf(__('Available placeholders: %s', 'woo-wallet-withdrawal'), '<code>{site_name},{amount},{user_name}</code>'),
                    'placeholder' => $this->get_default_subject(),
                    'default' => '',
                ),
                'heading' => array(
                    'title' => __('Email heading', 'woo-wallet-withdrawal'),
                    'type' => 'text',
                    'desc_tip' => true,
                    /* translators: %s: list of placeholders */
                    'description' => sprintf(__('Available placeholders: %s', 'woo-wallet-withdrawal'), '<code>{site_name},{amount},{user_name}</code>'),
                    'placeholder' => $this->get_default_heading(),
                    'default' => '',
                ),
                'email_type' => array(
                    'title' => __('Email type', 'woo-wallet-withdrawal'),
                    'type' => 'select',
                    'description' => __('Choose which format of email to send.', 'woo-wallet-withdrawal'),
                    'default' => 'html',
                    'class' => 'email_type wc-enhanced-select',
                    'options' => $this->get_email_type_options(),
                    'desc_tip' => true,
                ),
            );
        }

    }

    endif;

return new WOO_Wallet_Withdrawal_Request();
