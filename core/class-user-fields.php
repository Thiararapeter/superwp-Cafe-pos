<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Superwp_Cafe_Pos_User_Fields')) :

    class Superwp_Cafe_Pos_User_Fields {
        private static $instance = null;

        public function __construct() {
            // Add payroll field to name section
            add_filter('user_contactmethods', array($this, 'add_payroll_contact_method'));
            
            // Save payroll field
            add_action('user_register', array($this, 'save_payroll_field'));
            add_action('personal_options_update', array($this, 'save_payroll_field'));
            add_action('edit_user_profile_update', array($this, 'save_payroll_field'));

            // Add payroll column to users list
            add_filter('manage_users_columns', array($this, 'add_payroll_column'));
            add_filter('manage_users_custom_column', array($this, 'display_payroll_column_content'), 10, 3);
            add_filter('manage_users_sortable_columns', array($this, 'make_payroll_column_sortable'));
            add_action('pre_get_users', array($this, 'sort_by_payroll_number'));

            // Remove WooCommerce fields for POS users
            add_action('admin_head', array($this, 'maybe_hide_wc_fields'));
            add_filter('woocommerce_customer_meta_fields', array($this, 'filter_wc_user_fields'), 10, 1);
            add_filter('woocommerce_admin_billing_fields', array($this, 'remove_wc_billing_fields'), 10, 1);
            add_filter('woocommerce_admin_shipping_fields', array($this, 'remove_wc_shipping_fields'), 10, 1);
        }

        /**
         * Check if current user is a POS user
         */
        private function is_pos_user($user_id = null) {
            if (!$user_id && isset($_GET['user_id'])) {
                $user_id = intval($_GET['user_id']);
            }
            
            if (!$user_id) {
                return false;
            }

            $user = get_user_by('id', $user_id);
            if (!$user) {
                return false;
            }

            $roles_manager = Superwp_Cafe_Pos_Roles::instance();
            $pos_roles = array_keys($roles_manager->get_pos_roles());
            
            return !empty(array_intersect($user->roles, $pos_roles));
        }

        /**
         * Hide WooCommerce fields via CSS if needed
         */
        public function maybe_hide_wc_fields() {
            if (!$this->is_pos_user()) {
                return;
            }
            ?>
            <style type="text/css">
                .woocommerce-customer-meta-fields,
                h2.woocommerce-customer-meta-fields__title,
                .user-billing-address-wrap,
                .user-shipping-address-wrap {
                    display: none !important;
                }
            </style>
            <?php
        }

        /**
         * Filter WooCommerce user fields
         */
        public function filter_wc_user_fields($fields) {
            if ($this->is_pos_user()) {
                return array(); // Remove all WC fields
            }
            return $fields;
        }

        /**
         * Remove WooCommerce billing fields
         */
        public function remove_wc_billing_fields($fields) {
            if ($this->is_pos_user()) {
                return array();
            }
            return $fields;
        }

        /**
         * Remove WooCommerce shipping fields
         */
        public function remove_wc_shipping_fields($fields) {
            if ($this->is_pos_user()) {
                return array();
            }
            return $fields;
        }

        /**
         * Add payroll field to contact methods (appears in name section)
         */
        public function add_payroll_contact_method($methods) {
            $methods['payroll_number'] = __('Payroll Number', 'superwp-cafe-pos');
            return $methods;
        }

        /**
         * Save payroll field
         */
        public function save_payroll_field($user_id) {
            if (!current_user_can('edit_user', $user_id)) {
                return false;
            }

            if (isset($_POST['payroll_number'])) {
                update_user_meta($user_id, 'payroll_number', sanitize_text_field($_POST['payroll_number']));
            }
        }

        /**
         * Add payroll column to users list
         */
        public function add_payroll_column($columns) {
            $columns['payroll_number'] = __('Payroll Number', 'superwp-cafe-pos');
            return $columns;
        }

        /**
         * Display payroll column content
         */
        public function display_payroll_column_content($value, $column_name, $user_id) {
            if ('payroll_number' === $column_name) {
                $payroll_number = get_user_meta($user_id, 'payroll_number', true);
                return $payroll_number ? esc_html($payroll_number) : '—';
            }
            return $value;
        }

        /**
         * Make payroll column sortable
         */
        public function make_payroll_column_sortable($columns) {
            $columns['payroll_number'] = 'payroll_number';
            return $columns;
        }

        /**
         * Handle sorting by payroll number
         */
        public function sort_by_payroll_number($query) {
            if (!is_admin()) {
                return;
            }

            $orderby = $query->get('orderby');
            if ('payroll_number' === $orderby) {
                $query->set('meta_key', 'payroll_number');
                $query->set('orderby', 'meta_value');
            }
        }

        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }

endif;

// Initialize
Superwp_Cafe_Pos_User_Fields::instance(); 