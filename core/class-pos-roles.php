<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Superwp_Cafe_Pos_Roles')) :

    class Superwp_Cafe_Pos_Roles {
        
        private static $instance = null;
        private $pos_roles = array();

        public function __construct() {
            $this->setup_pos_roles();
            add_action('init', array($this, 'register_pos_roles'));
            add_action('add_user_role', array($this, 'add_pos_user_meta'), 10, 2);
            add_action('remove_user_role', array($this, 'remove_pos_user_meta'), 10, 2);
            add_filter('user_has_cap', array($this, 'add_pos_caps'), 10, 4);
            add_action('wp_ajax_get_pos_role', array($this, 'ajax_get_pos_role'));
            add_action('wp_ajax_save_pos_role', array($this, 'ajax_save_pos_role'));
            add_action('wp_ajax_delete_pos_role', array($this, 'ajax_delete_pos_role'));
            add_filter('authenticate', array($this, 'check_user_status'), 30, 3);
            add_action('wp_ajax_update_pos_user_role', array($this, 'ajax_update_pos_user_role'));
        }

        /**
         * Save POS roles to options table
         */
        private function save_pos_roles() {
            update_option('superwp_cafe_pos_roles', $this->pos_roles);
        }

        /**
         * Load POS roles from options table
         */
        private function load_pos_roles() {
            $saved_roles = get_option('superwp_cafe_pos_roles', array());
            if (!empty($saved_roles)) {
                $this->pos_roles = array_merge($this->pos_roles, $saved_roles);
            }
        }

        /**
         * Setup POS roles and their capabilities
         */
        private function setup_pos_roles() {
            // Default core roles
            $default_roles = array(
                'pos_manager' => array(
                    'label' => __('POS Manager', 'superwp-cafe-pos'),
                    'capabilities' => array(
                        'read' => true,
                        'manage_pos' => true,
                        'view_pos_reports' => true,
                        'manage_pos_settings' => true,
                        'manage_pos_users' => true,
                        'process_pos_refunds' => true,
                        'view_pos_orders' => true,
                        'create_pos_orders' => true,
                        'edit_pos_orders' => true,
                        'delete_pos_orders' => true,
                        'manage_pos_products' => true,
                    )
                ),
                'pos_cashier' => array(
                    'label' => __('POS Cashier', 'superwp-cafe-pos'),
                    'capabilities' => array(
                        'read' => true,
                        'view_pos_reports' => true,
                        'view_pos_orders' => true,
                        'create_pos_orders' => true,
                        'process_pos_sales' => true,
                        'process_pos_refunds' => true,
                    )
                ),
                'pos_waiter' => array(
                    'label' => __('POS Waiter', 'superwp-cafe-pos'),
                    'capabilities' => array(
                        'read' => true,
                        'create_pos_orders' => true,
                        'view_pos_orders' => true,
                        'edit_pos_orders' => true,
                    )
                ),
                'pos_kitchen' => array(
                    'label' => __('POS Kitchen Staff', 'superwp-cafe-pos'),
                    'capabilities' => array(
                        'read' => true,
                        'view_pos_orders' => true,
                        'update_order_status' => true,
                    )
                )
            );

            $this->pos_roles = $default_roles;
            
            // Load custom roles from options
            $this->load_pos_roles();
        }

        /**
         * Register POS roles
         */
        public function register_pos_roles() {
            foreach ($this->pos_roles as $role_id => $role) {
                if (!get_role($role_id)) {
                    add_role($role_id, $role['label'], $role['capabilities']);
                }
            }
        }

        /**
         * Check user status before login
         */
        public function check_user_status($user, $username, $password) {
            if (is_wp_error($user)) {
                return $user;
            }

            $status = get_user_meta($user->ID, 'pos_user_status', true);
            if ($status === 'inactive') {
                return new WP_Error('inactive_user', __('Your account is inactive.', 'superwp-cafe-pos'));
            }

            return $user;
        }

        /**
         * Get all POS capabilities
         */
        public static function get_all_pos_capabilities() {
            return array(
                'manage_pos',
                'view_pos_reports',
                'manage_pos_settings',
                'manage_pos_users',
                'process_pos_refunds',
                'view_pos_orders',
                'create_pos_orders',
                'edit_pos_orders',
                'delete_pos_orders',
                'manage_pos_products',
                'process_pos_sales',
                'update_order_status'
            );
        }

        /**
         * Add POS capabilities to users
         */
        public function add_pos_caps($allcaps, $caps, $args, $user) {
            // If user has no roles, return original caps
            if (!isset($user->roles) || !is_array($user->roles)) {
                return $allcaps;
            }

            // Check if user has any POS role
            $has_pos_role = false;
            foreach ($user->roles as $role) {
                if (isset($this->pos_roles[$role])) {
                    $has_pos_role = true;
                    break;
                }
            }

            // If user has no POS role, return original caps
            if (!$has_pos_role) {
                return $allcaps;
            }

            // Add POS capabilities based on user's role
            foreach ($user->roles as $role) {
                if (isset($this->pos_roles[$role])) {
                    foreach ($this->pos_roles[$role]['capabilities'] as $cap => $grant) {
                        if ($grant) {
                            $allcaps[$cap] = true;
                        }
                    }
                }
            }

            // Add POS capabilities to administrators
            if (in_array('administrator', $user->roles)) {
                foreach (self::get_all_pos_capabilities() as $cap) {
                    $allcaps[$cap] = true;
                }
            }

            return $allcaps;
        }

        /**
         * Get all POS roles
         * @return array Array of POS roles and their settings
         */
        public function get_all_pos_roles() {
            return $this->pos_roles;
        }

        /**
         * Get a specific POS role
         * @param string $role_id The role ID to retrieve
         * @return array|null Role data or null if not found
         */
        public function get_pos_role($role_id) {
            return isset($this->pos_roles[$role_id]) ? $this->pos_roles[$role_id] : null;
        }

        /**
         * Add a new POS role
         * @param string $role_id The role ID
         * @param array $role_data Role data including label and capabilities
         * @return bool Success status
         */
        public function add_pos_role($role_id, $role_data) {
            if (!isset($role_data['label']) || !isset($role_data['capabilities'])) {
                return false;
            }

            $this->pos_roles[$role_id] = $role_data;
            add_role($role_id, $role_data['label'], $role_data['capabilities']);
            return true;
        }

        /**
         * Update a POS role
         * @param string $role_id The role ID to update
         * @param array $role_data New role data
         * @return bool Success status
         */
        public function update_pos_role($role_id, $role_data) {
            if (!isset($this->pos_roles[$role_id])) {
                return false;
            }

            $this->pos_roles[$role_id] = $role_data;
            $role = get_role($role_id);
            
            // Remove existing capabilities
            foreach ($role->capabilities as $cap => $grant) {
                $role->remove_cap($cap);
            }
            
            // Add new capabilities
            foreach ($role_data['capabilities'] as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                }
            }
            
            return true;
        }

        /**
         * Handle AJAX request to get role data
         */
        public function ajax_get_pos_role() {
            // Verify nonce
            if (!check_ajax_referer('superwp-cafe-pos-admin', 'nonce', false)) {
                wp_send_json_error(array('message' => 'Invalid security token'));
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Permission denied'));
            }

            $role_id = sanitize_text_field($_POST['role']);
            $role = get_role($role_id);
            
            if (!$role || !isset($this->pos_roles[$role_id])) {
                wp_send_json_error(array('message' => 'Role not found'));
            }

            wp_send_json_success(array(
                'label' => $this->pos_roles[$role_id]['label'],
                'capabilities' => $role->capabilities
            ));
        }

        /**
         * Handle AJAX request to save role data
         */
        public function ajax_save_pos_role() {
            try {
                // Verify nonce and capabilities
                if (!check_ajax_referer('superwp-cafe-pos-admin', 'nonce', false)) {
                    throw new Exception('Invalid security token');
                }

                if (!current_user_can('manage_options')) {
                    throw new Exception('Permission denied');
                }

                $role_id = sanitize_text_field($_POST['role_id']);
                $role_name = sanitize_text_field($_POST['role_name']);
                
                if (empty($role_name)) {
                    throw new Exception('Role name is required');
                }

                // For new roles, generate role ID
                if (empty($role_id)) {
                    $role_id = 'pos_' . sanitize_title($role_name);
                    
                    // Check if role ID already exists
                    if ($this->role_name_exists($role_name)) {
                        throw new Exception('A role with this name already exists');
                    }
                }

                // Prepare capabilities
                $capabilities = isset($_POST['capabilities']) ? (array) $_POST['capabilities'] : array();
                $capabilities = array_map('sanitize_text_field', $capabilities);
                
                // Build capabilities array with defaults
                $caps = array('read' => true);
                foreach ($this->get_all_pos_capabilities() as $cap) {
                    $caps[$cap] = in_array($cap, $capabilities);
                }

                // Add or update role
                if (get_role($role_id)) {
                    $role = get_role($role_id);
                    // Remove all existing capabilities
                    foreach ($role->capabilities as $cap => $grant) {
                        $role->remove_cap($cap);
                    }
                    // Add new capabilities
                    foreach ($caps as $cap => $grant) {
                        if ($grant) {
                            $role->add_cap($cap);
                        }
                    }
                } else {
                    $result = add_role($role_id, $role_name, $caps);
                    if (!$result) {
                        throw new Exception('Failed to create new role');
                    }
                }

                // Update pos_roles array
                $this->pos_roles[$role_id] = array(
                    'label' => $role_name,
                    'capabilities' => $caps
                );

                // Save to options table
                $this->save_pos_roles();

                // Get updated role data for response
                $updated_role = array(
                    'id' => $role_id,
                    'name' => $role_name,
                    'capabilities' => array_keys(array_filter($caps)),
                    'user_count' => count(get_users(['role' => $role_id]))
                );

                wp_send_json_success(array(
                    'message' => 'Role saved successfully',
                    'role' => $updated_role
                ));

            } catch (Exception $e) {
                wp_send_json_error(array(
                    'message' => $e->getMessage()
                ));
            }
        }

        /**
         * Handle AJAX request to delete role
         */
        public function ajax_delete_pos_role() {
            // Verify nonce
            if (!check_ajax_referer('superwp-cafe-pos-admin', 'nonce', false)) {
                wp_send_json_error(array('message' => 'Invalid security token'));
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Permission denied'));
            }

            $role_id = sanitize_text_field($_POST['role']);
            
            // Don't allow deletion of core POS roles
            $core_roles = array('pos_manager', 'pos_cashier', 'pos_waiter', 'pos_kitchen');
            if (in_array($role_id, $core_roles)) {
                wp_send_json_error(array('message' => 'Cannot delete core POS roles'));
                return;
            }

            // Get users with this role and change their role to subscriber
            $users = get_users(array('role' => $role_id));
            foreach ($users as $user) {
                $user->set_role('subscriber');
            }

            // Remove the role
            remove_role($role_id);

            // Remove from pos_roles array
            if (isset($this->pos_roles[$role_id])) {
                unset($this->pos_roles[$role_id]);
                // Save updated roles to options
                $this->save_pos_roles();
            }

            wp_send_json_success(array('message' => 'Role deleted successfully'));
        }

        /**
         * Handle AJAX request to update user role
         */
        public function ajax_update_pos_user_role() {
            // Verify nonce
            if (!check_ajax_referer('superwp-cafe-pos-admin', 'nonce', false)) {
                wp_send_json_error(array('message' => 'Invalid security token'));
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Permission denied'));
            }

            $user_id = intval($_POST['user_id']);
            $new_role = sanitize_text_field($_POST['role']);

            // Verify role exists
            if (!isset($this->pos_roles[$new_role])) {
                wp_send_json_error(array('message' => 'Invalid role'));
            }

            $user = get_user_by('id', $user_id);
            if (!$user) {
                wp_send_json_error(array('message' => 'User not found'));
            }

            // Remove old POS roles
            foreach ($this->pos_roles as $role_id => $role_data) {
                $user->remove_role($role_id);
            }

            // Add new role
            $user->add_role($new_role);

            wp_send_json_success();
        }

        /**
         * Check if role name already exists
         */
        private function role_name_exists($role_name, $exclude_role_id = '') {
            foreach ($this->pos_roles as $role_id => $role_data) {
                if ($role_id !== $exclude_role_id && 
                    strtolower($role_data['label']) === strtolower($role_name)) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Get all users with POS roles
         */
        public function get_pos_users() {
            $pos_users = array();
            $role_ids = array_keys($this->pos_roles);
            
            // Get users with any POS role
            $users = get_users(array(
                'role__in' => $role_ids,
            ));

            foreach ($users as $user) {
                $user_roles = array_intersect($user->roles, $role_ids);
                if (!empty($user_roles)) {
                    $current_role = reset($user_roles); // Get the first POS role
                    $pos_users[] = array(
                        'id' => $user->ID,
                        'name' => $user->display_name,
                        'email' => $user->user_email,
                        'role' => $current_role,
                        'last_login' => get_user_meta($user->ID, 'last_login', true),
                        'status' => get_user_meta($user->ID, 'pos_status', true) ?: 'active'
                    );
                }
            }

            return $pos_users;
        }

        /**
         * Add POS user metadata when role is assigned
         * 
         * @param int $user_id The user ID
         * @param string $role The role being added
         */
        public function add_pos_user_meta($user_id, $role) {
            // Check if this is a POS role
            if (isset($this->pos_roles[$role])) {
                // Set default POS status if not already set
                if (!get_user_meta($user_id, 'pos_status', true)) {
                    update_user_meta($user_id, 'pos_status', 'active');
                }
                
                // Set last login if not already set
                if (!get_user_meta($user_id, 'last_login', true)) {
                    update_user_meta($user_id, 'last_login', '');
                }

                // Add any other POS-specific user meta here
                do_action('superwpcaf_pos_role_assigned', $user_id, $role);
            }
        }

        /**
         * Remove POS user metadata when role is removed
         * 
         * @param int $user_id The user ID
         * @param string $role The role being removed
         */
        public function remove_pos_user_meta($user_id, $role) {
            // Check if this is a POS role
            if (isset($this->pos_roles[$role])) {
                // Only remove POS metadata if user has no other POS roles
                $user = get_user_by('id', $user_id);
                $remaining_pos_roles = array_intersect($user->roles, array_keys($this->pos_roles));
                
                if (empty($remaining_pos_roles)) {
                    delete_user_meta($user_id, 'pos_status');
                    // Don't delete last_login as it might be useful for history
                    
                    do_action('superwpcaf_pos_role_removed', $user_id, $role);
                }
            }
        }

        /**
         * Get all POS roles
         * 
         * @return array Array of POS roles
         */
        public function get_pos_roles() {
            return $this->pos_roles;
        }

        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function save_role_settings($role_id, $data) {
            $role_settings = get_option('superwpcaf_pos_role_settings', array());
            
            $role_settings[$role_id] = array(
                'redirect_page' => sanitize_text_field($data['redirect_page']),
                'custom_redirect_url' => esc_url_raw($data['custom_redirect_url'])
            );
            
            update_option('superwpcaf_pos_role_settings', $role_settings);
        }

        public function get_role_settings($role_id) {
            $role_settings = get_option('superwpcaf_pos_role_settings', array());
            return isset($role_settings[$role_id]) ? $role_settings[$role_id] : array(
                'redirect_page' => 'pos_terminal',
                'custom_redirect_url' => ''
            );
        }
    }

endif;

// Initialize
Superwp_Cafe_Pos_Roles::instance(); 