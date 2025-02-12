<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WOO_Wallet_Withdrawal_Post_Type')) {

    /**
     * Withdrawal post type class
     */
    class WOO_Wallet_Withdrawal_Post_Type {

        public static $post_type = 'wallet_withdrawal';

        public function __construct() {
            add_action('init', array(__CLASS__, 'register_post_types'), 5);
            add_action('init', array(__CLASS__, 'register_post_status'));
            add_filter('manage_' . self::$post_type . '_posts_columns', array($this, 'manage_posts_columns'));
            add_action('manage_' . self::$post_type . '_posts_custom_column', array($this, 'manage_posts_custom_column'), 10, 2);
            add_filter('post_row_actions', array($this, 'post_row_actions'), 10, 2);
            add_filter('bulk_actions-edit-wallet_withdrawal', array($this, 'bulk_actions'));
            add_filter('handle_bulk_actions-edit-wallet_withdrawal', array($this, 'handle_bulk_actions'), 10, 3);
        }

        public static function register_post_types() {
            if (!is_blog_installed() || post_type_exists(self::$post_type)) {
                return;
            }
            register_post_type(self::$post_type, apply_filters('woo_wallet_register_post_type_wallet_withdrawal', array(
                'labels' => array(
                    'name' => __('Withdrawal Requests', 'woo-wallet-withdrawal'),
                    'singular_name' => __('Withdrawal', 'woo-wallet-withdrawal'),
                    'all_items' => __('All Withdrawal', 'woo-wallet-withdrawal'),
                    'menu_name' => _x('Withdrawal', 'Admin menu name', 'woo-wallet-withdrawal'),
                    'search_items' => __('Search', 'woo-wallet-withdrawal'),
                    'not_found' => __('No requests found', 'woo-wallet-withdrawal'),
                    'not_found_in_trash' => __('No requests found in trash', 'woo-wallet-withdrawal')
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'capability_type' => 'post',
                'capabilities' => array('create_posts' => false, 'delete_posts' => false),
                'map_meta_cap' => true,
                'hierarchical' => false,
                'supports' => false,
                'rewrite' => false
                            )
                    )
            );
        }

        public static function register_post_status() {
            $withdrawal_statuses = apply_filters('woo_wallet_register_wallet_withdrawal_post_statuses', array(
                'ww-pending' => array(
                    'label' => _x('Pending', 'Withdrawal status', 'woo-wallet-withdrawal'),
                    'public' => true,
                    'exclude_from_search' => false,
                    'show_in_admin_all_list' => true,
                    'show_in_admin_status_list' => true,
                    'label_count' => _n_noop('Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'woo-wallet-withdrawal'),
                ),
                'ww-approved' => array(
                    'label' => _x('Approved', 'Withdrawal status', 'woo-wallet-withdrawal'),
                    'public' => true,
                    'exclude_from_search' => false,
                    'show_in_admin_all_list' => true,
                    'show_in_admin_status_list' => true,
                    'label_count' => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>', 'woo-wallet-withdrawal'),
                ),
                'ww-cancelled' => array(
                    'label' => _x('Cancelled', 'Withdrawal status', 'woo-wallet-withdrawal'),
                    'public' => true,
                    'exclude_from_search' => false,
                    'show_in_admin_all_list' => true,
                    'show_in_admin_status_list' => true,
                    'label_count' => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'woo-wallet-withdrawal'),
                )
            ));

            foreach ($withdrawal_statuses as $withdrawal_status => $values) {
                register_post_status($withdrawal_status, $values);
            }
        }

        public function manage_posts_columns($columns) {
            $columns = apply_filters('woo_wallet_withdrawal_posts_columns', array(
                'cb' => __('CB'),
                'user' => __('User', 'woo-wallet-withdrawal'),
                'amount' => __('Amount', 'woo-wallet-withdrawal'),
                'charge' => __('Gateway Charge', 'woo-wallet-withdrawal'),
                'status' => __('Status', 'woo-wallet-withdrawal'),
                'method' => __('Method', 'woo-wallet-withdrawal'),
                'date' => __('Date', 'woo-wallet-withdrawal'),
                'actions' => __('Actions', 'woo-wallet-withdrawal'),
            ));

            return $columns;
        }

        public function manage_posts_custom_column($column, $post_id) {
            $post = get_post($post_id);
            $user = get_user_by('ID', $post->post_author);
            $thikbox_url = add_query_arg(
                    array(
                'action' => 'get_woo_wallet_withdrawal_details',
                'security' => wp_create_nonce('woo-wallet-withdrawal-details'),
                'withdraw_id' => $post_id,
                'TB_iframe' => true,
                'width' => 450,
                'height' => 450,
                    ), admin_url('admin-ajax.php')
            );
            switch ($column) {
                case 'user':
                    echo $user->display_name . '(#' . $user->ID . ' &ndash; ' . $user->user_email . ')';
                    break;
                case 'amount':
                    echo wc_price(get_post_meta($post_id, '_wallet_withdrawal_amount', true), array('currency' => get_post_meta($post_id, '_wallet_withdrawal_currency', true)));
                    break;
                case 'charge':
                    echo wc_price(get_post_meta($post_id, '_wallet_withdrawal_transaction_charge', true), array('currency' => get_post_meta($post_id, '_wallet_withdrawal_currency', true)));
                    break;
                case 'status':
                    echo get_post_status_object(get_post_status($post_id))->label;
                    break;
                case 'method':
                    echo woo_wallet_withdrawal()->gateways->payment_gateways[get_post_meta($post_id, '_wallet_withdrawal_method', true)]->get_method_title();
                    break;
                case 'actions':
                    if ('ww-approved' === $post->post_status) {
                        echo '<a href="' . $thikbox_url . '" class="thickbox" title="' . __('Request details #', 'woo-wallwet-withdrawal') . $post_id . '"><span class="dashicons dashicons-visibility"></span></a> <a href="#" class="woo_wallet_withdrawal_action" data-action="delete" data-post_id="' . $post_id . '" title="' . __('Delete', 'woo-wallet-withdrawal') . '"><span class="dashicons dashicons-trash"></span></a>';
                    } else if ('ww-cancelled' === $post->post_status) {
                        echo '<a href="' . $thikbox_url . '" class="thickbox" title="' . __('Request details #', 'woo-wallwet-withdrawal') . $post_id . '"><span class="dashicons dashicons-visibility"></span></a> <a href="#" class="woo_wallet_withdrawal_action" data-action="approve" data-post_id="' . $post_id . '" title="' . __('Approve', 'woo-wallet-withdrawal') . '"><span class="dashicons dashicons-yes"></span></a> <a href="#" class="woo_wallet_withdrawal_action" data-action="pending" data-post_id="' . $post_id . '" title="' . __('Pending', 'woo-wallet-withdrawal') . '"><span class="dashicons dashicons-admin-post"></span></a> <a href="#" class="woo_wallet_withdrawal_action" data-action="delete" data-post_id="' . $post_id . '" title="' . __('Delete', 'woo-wallet-withdrawal') . '"><span class="dashicons dashicons-trash"></span></a>';
                    } else {
                        echo '<a href="' . $thikbox_url . '" class="thickbox" title="' . __('Request details #', 'woo-wallwet-withdrawal') . $post_id . '"><span class="dashicons dashicons-visibility"></span></a> <a href="#" class="woo_wallet_withdrawal_action" data-action="approve" data-post_id="' . $post_id . '" title="' . __('Approve', 'woo-wallet-withdrawal') . '"><span class="dashicons dashicons-yes"></span></a> <a href="#" class="woo_wallet_withdrawal_action" data-action="reject" data-post_id="' . $post_id . '" title="' . __('Reject', 'woo-wallet-withdrawal') . '"><span class="dashicons dashicons-no"></span></a> <a href="#" class="woo_wallet_withdrawal_action" data-action="delete" data-post_id="' . $post_id . '" title="' . __('Delete', 'woo-wallet-withdrawal') . '"><span class="dashicons dashicons-trash"></span></a>';
                    }
                    break;
                default :

                    break;
            }
        }

        public function post_row_actions($actions, $post) {
            if ($post->post_type === self::$post_type) {
                return array();
            }
            return $actions;
        }

        public function bulk_actions($actions) {
            $actions = array(
                'approve' => __('Approve', 'woo-wallet-withdrawal'),
                'reject' => __('Reject', 'woo-wallet-withdrawal'),
                'delete' => __('Delete', 'woo-wallet-withdrawal')
            );
            if (isset(woo_wallet_withdrawal()->gateways->payment_gateways['paypal']) && woo_wallet_withdrawal()->gateways->payment_gateways['paypal']->is_available()) {
                $actions['download_paypal_file'] = __('Download PayPal MassPay CSV', 'woo-wallet-withdrawal');
            }

            return apply_filters('woo_wallet_withdrawal_post_type_bulk_actions', $actions);
        }

        public function handle_bulk_actions($sendback, $doaction, $post_ids) {
            if ('approve' === $doaction) {
                foreach ($post_ids as $post_id) {
                    self::approve_withdrawal($post_id);
                }
            } else if ('reject' === $doaction) {
                foreach ($post_ids as $post_id) {
                    self::reject_withdrawal($post_id);
                }
            } else if ('delete' === $doaction) {
                foreach ($post_ids as $post_id) {
                    self::delete_withdrawal($post_id);
                }
            } else if ('download_paypal_file' === $doaction) {
                self::generate_csv($post_ids);
            }
            return $sendback;
        }

        public static function create_post() {
            $new_request_args = array(
                'post_status' => 'ww-pending',
                'post_type' => self::$post_type,
            );
            $id = wp_insert_post($new_request_args);
            do_action('woo_wallet_new_withdrawal_request', $id);
            return $id;
        }

        public static function approve_withdrawal($post_id) {
            $withdrawal = get_post($post_id);
            if ($withdrawal) {
                $payment_method_id = get_post_meta($post_id, '_wallet_withdrawal_method', true);
                $response = woo_wallet_withdrawal()->gateways->payment_gateways[$payment_method_id]->process_payment($withdrawal);
                if ($response) {
                    wp_update_post(array('ID' => $post_id, 'post_status' => 'ww-approved'));
                    do_action('woo_wallet_approved_withdrawal_request', $post_id);
                    $email_notification = WC()->mailer()->emails['WOO_Wallet_Withdrawal_Approved'];
                    $email_notification->trigger($post_id);
                    return array('status' => true);
                }
            }
            return array('status' => false);
        }

        public static function reject_withdrawal($post_id) {
            $withdrawal = get_post($post_id);
            if ($withdrawal) {
                $transaction_id = get_post_meta($withdrawal->ID, '_wallet_withdrawal_transaction_id', true);
                update_wallet_transaction($transaction_id, $withdrawal->post_author, array('deleted' => 1), array('%d'));
                wp_update_post(array('ID' => $post_id, 'post_status' => 'ww-cancelled'));
                do_action('woo_wallet_withdraw_rejected_request', $post_id);
                $email_notification = WC()->mailer()->emails['WOO_Wallet_Withdrawal_Reject'];
                $email_notification->trigger($post_id);
                return array('status' => true);
            }
            return array('status' => false);
        }

        public static function pending_withdrawal($post_id) {
            $withdrawal = get_post($post_id);
            if ($withdrawal) {
                $transaction_id = get_post_meta($withdrawal->ID, '_wallet_withdrawal_transaction_id', true);
                update_wallet_transaction($transaction_id, $withdrawal->post_author, array('deleted' => 0), array('%d'));
                wp_update_post(array('ID' => $post_id, 'post_status' => 'ww-pending'));
                return array('status' => true);
            }
            return array('status' => false);
        }

        public static function delete_withdrawal($post_id) {
            $withdrawal = get_post($post_id);
            if ($withdrawal) {
                if ($withdrawal->post_status !== 'ww-approved') {
                    $transaction_id = get_post_meta($withdrawal->ID, '_wallet_withdrawal_transaction_id', true);
                    update_wallet_transaction($transaction_id, $withdrawal->post_author, array('deleted' => 1), array('%d'));
                    wp_delete_post($withdrawal->ID, true);
                    return array('status' => true);
                } else {
                    wp_delete_post($withdrawal->ID, true);
                    return array('status' => true);
                }
            }
            return array('status' => false);
        }

        public static function generate_csv($post_ids) {
            foreach ($post_ids as $post_id) {
                if ('paypal' === get_post_meta($post_id, '_wallet_withdrawal_method', true)) {
                    $withdrawal_post = get_post($post_id);
                    $amount = floatval(get_post_meta($post_id, '_wallet_withdrawal_amount', true)) - floatval(get_post_meta($post_id, '_wallet_withdrawal_transaction_charge', true));
                    $data[] = array(
                        'email' => get_user_meta($withdrawal_post->post_author, '_woo_wallet_withdrawal_paypal_email', true),
                        'amount' => $amount,
                        'currency' => get_post_meta($post_id, '_wallet_withdrawal_currency', true) ? get_post_meta($post_id, '_wallet_withdrawal_currency', true) : get_woocommerce_currency()
                    );
                }
            }
            if ($data) {

                header('Content-type: html/csv');
                header('Content-Disposition: attachment; filename="withdraw-' . date('d-m-y') . '.csv"');

                foreach ($data as $fields) {
                    echo esc_html($fields['email']) . ',';
                    echo esc_html($fields['amount']) . ',';
                    echo esc_html($fields['currency']) . "\n";
                }

                die();
            }
        }

    }

}

new WOO_Wallet_Withdrawal_Post_Type();
