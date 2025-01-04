<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Superwp_Cafe_Pos_Roles_Manager')) :

    class Superwp_Cafe_Pos_Roles_Manager {
        private static $instance = null;

        public function __construct() {
            add_action('init', array($this, 'register_pos_roles'));
            add_action('init', array($this, 'register_pos_capabilities'));
            add_filter('authenticate', array($this, 'check_user_status'), 30, 3);
            // Add login redirect filter
            add_filter('login_redirect', array($this, 'pos_login_redirect'), 10, 3);
            add_action('wp_ajax_toggle_pos_user_status', array($this, 'ajax_toggle_user_status'));
        }

        /**
         * Handle login redirect for POS users
         */
        public function pos_login_redirect($redirect_to, $requested_redirect_to, $user) {
            if (!$user || is_wp_error($user)) {
                return $redirect_to;
            }

            // Get POS roles
            $pos_roles = array('pos_manager', 'pos_cashier');
            
            // Check if user has any POS role
            $has_pos_role = array_intersect($pos_roles, (array) $user->roles);
            
            if (!empty($has_pos_role)) {
                // Get the user's primary role
                $user_role = reset($has_pos_role);
                
                // Get role settings with default values
                $role_settings = get_option('superwpcaf_pos_role_settings', array());
                $role_redirect = 'pos_terminal'; // Default redirect

                if (isset($role_settings[$user_role]) && isset($role_settings[$user_role]['redirect_page'])) {
                    $role_redirect = $role_settings[$user_role]['redirect_page'];
                }

                // Get the POS terminal page URL
                $page_manager = Superwp_Cafe_Pos_Page_Manager::instance();
                $pos_url = $page_manager->get_pos_page_url();
                
                if (!$pos_url) {
                    $pos_url = home_url('/pos-terminal/');
                }
                
                return $pos_url;
            }

            return $redirect_to;
        }

        /**
         * Register POS roles
         */
        public function register_pos_roles() {
            global $wp_roles;
            
            if (!class_exists('WP_Roles')) {
                return;
            }

            if (!isset($wp_roles)) {
                $wp_roles = new WP_Roles();
            }

            // Get all POS roles and their capabilities
            $pos_roles = $this->get_pos_roles();
            
            // Register each role and its capabilities
            foreach ($pos_roles as $role_id => $role_data) {
                // Remove the role first to ensure clean capability assignment
                remove_role($role_id);
                
                // Add the role with its capabilities
                add_role($role_id, $role_data['label'], $role_data['capabilities']);
                
                // Get the role object
                $role = get_role($role_id);
                
                // Ensure WooCommerce specific capabilities are added
                if ($role) {
                    // Basic WooCommerce capabilities
                    $role->add_cap('read');
                    $role->add_cap('view_admin_dashboard');
                    $role->add_cap('access_pos_terminal');
                    
                    // Add WooCommerce order capabilities for managers
                    if ($role_id === 'pos_manager') {
                        $role->add_cap('edit_shop_orders');
                        $role->add_cap('read_shop_orders');
                        $role->add_cap('delete_shop_orders');
                        $role->add_cap('publish_shop_orders');
                        $role->add_cap('edit_published_shop_orders');
                        $role->add_cap('delete_published_shop_orders');
                    }
                    
                    // Add basic order capabilities for cashiers
                    if ($role_id === 'pos_cashier') {
                        $role->add_cap('edit_shop_orders');
                        $role->add_cap('read_shop_orders');
                        $role->add_cap('publish_shop_orders');
                    }
                }
            }

            // Ensure administrator has all POS capabilities
            $admin_role = get_role('administrator');
            if ($admin_role) {
                foreach ($pos_roles as $role_data) {
                    foreach ($role_data['capabilities'] as $cap => $grant) {
                        $admin_role->add_cap($cap);
                    }
                }
            }
        }

        /**
         * Check user status before authentication
         */
        public function check_user_status($user, $username, $password) {
            if (!$user || is_wp_error($user)) {
                return $user;
            }

            // Check if user has POS role
            $pos_roles = array_keys($this->get_pos_roles());
            $has_pos_role = false;
            
            foreach ($pos_roles as $role) {
                if (in_array($role, (array) $user->roles)) {
                    $has_pos_role = true;
                    break;
                }
            }

            // Only check status for POS users
            if ($has_pos_role) {
                $status = get_user_meta($user->ID, 'pos_user_status', true);
                
                if ($status === 'inactive') {
                    return new WP_Error(
                        'inactive_user',
                        __('Your account is currently inactive. Please contact the administrator.', 'superwp-cafe-pos')
                    );
                }
            }

            return $user;
        }

        /**
         * Handle user activation/deactivation
         */
        public function ajax_toggle_user_status() {
            // Verify nonce and capabilities
            check_ajax_referer('superwp-cafe-pos-admin', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('You do not have permission to perform this action.', 'superwp-cafe-pos')
                ));
            }

            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

            if (!$user_id || !in_array($status, ['active', 'inactive'])) {
                wp_send_json_error(array(
                    'message' => __('Invalid request parameters.', 'superwp-cafe-pos')
                ));
            }

            // Get user and verify they exist
            $user = get_user_by('id', $user_id);
            if (!$user) {
                wp_send_json_error(array(
                    'message' => __('User not found.', 'superwp-cafe-pos')
                ));
            }

            // Force create meta if it doesn't exist
            if (get_user_meta($user_id, 'pos_user_status', true) === '') {
                add_user_meta($user_id, 'pos_user_status', 'active', true);
            }

            // Update user status
            $updated = update_user_meta($user_id, 'pos_user_status', $status);

            if ($updated || get_user_meta($user_id, 'pos_user_status', true) === $status) {
                wp_send_json_success(array(
                    'message' => sprintf(
                        __('User has been %s successfully.', 'superwp-cafe-pos'),
                        $status === 'active' ? 'activated' : 'deactivated'
                    ),
                    'status' => $status
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Error updating user status. Please try again.', 'superwp-cafe-pos')
                ));
            }
        }

        /**
         * Register custom capabilities for POS roles
         */
        public function register_pos_capabilities() {
            // Get administrator role
            $admin = get_role('administrator');
            
            // Add POS capabilities to administrator
            $admin->add_cap('access_pos_terminal');
            $admin->add_cap('manage_pos_settings');
            $admin->add_cap('view_pos_reports');
            $admin->add_cap('manage_pos_users');
            $admin->add_cap('process_pos_refunds');
        }

        /**
         * Get instance of the class
         */
        public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Get POS roles
         */
        public function get_pos_roles() {
            return array(
                'pos_manager' => array(
                    'label' => __('POS Manager', 'superwp-cafe-pos'),
                    'capabilities' => array(
                        'read' => true,
                        'access_pos_terminal' => true,
                        'manage_pos_settings' => true,
                        'view_pos_reports' => true,
                        'manage_pos_users' => true,
                        'process_pos_refunds' => true,
                        'edit_shop_orders' => true,
                        'read_shop_orders' => true,
                        'delete_shop_orders' => true,
                        'publish_shop_orders' => true,
                        'edit_published_shop_orders' => true,
                        'delete_published_shop_orders' => true,
                    )
                ),
                'pos_cashier' => array(
                    'label' => __('POS Cashier', 'superwp-cafe-pos'),
                    'capabilities' => array(
                        'read' => true,
                        'access_pos_terminal' => true,
                        'read_shop_orders' => true,
                        'edit_shop_orders' => true,
                        'publish_shop_orders' => true,
                    )
                )
            );
        }
    }

endif;
