<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Superwp_Cafe_Pos_Admin_Menu')) :

    class Superwp_Cafe_Pos_Admin_Menu {
        
        private static $instance = null;

        public function __construct() {
            add_action('admin_menu', array($this, 'add_pos_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        }

        public function enqueue_admin_scripts($hook) {
            if ('toplevel_page_superwp-cafe-pos' === $hook || 'cafe-pos_page_superwp-cafe-pos-settings' === $hook) {
                wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
                wp_enqueue_style('superwp-cafe-pos-admin', SUPERWPCAF_PLUGIN_URL . 'core/admin/css/admin.css');
                wp_enqueue_script('superwp-cafe-pos-admin', SUPERWPCAF_PLUGIN_URL . 'core/admin/js/admin.js', array('jquery'), SUPERWPCAF_VERSION, true);
                
                // Add nonce and translations for AJAX
                wp_localize_script('superwp-cafe-pos-admin', 'superwpCafePosAdmin', array(
                    'nonce' => wp_create_nonce('superwp-cafe-pos-admin'),
                    'i18n' => array(
                        'edit' => __('Edit', 'superwp-cafe-pos'),
                        'delete' => __('Delete', 'superwp-cafe-pos'),
                        'confirm_delete' => __('Are you sure you want to delete this role?', 'superwp-cafe-pos')
                    )
                ));
            }
        }

        public function enqueue_admin_styles($hook) {
            // Only load on our plugin's admin page
            if ('toplevel_page_superwp-cafe-pos' !== $hook) {
                return;
            }
            
            wp_enqueue_style(
                'superwpcaf-admin-style',
                plugins_url('assets/css/admin-style.css', dirname(dirname(__FILE__))),
                array(),
                SUPERWPCAF_VERSION
            );
        }

        public function add_pos_menu() {
            add_menu_page(
                __('Cafe POS', 'superwp-cafe-pos'),          // Page title
                __('Cafe POS', 'superwp-cafe-pos'),          // Menu title
                'manage_woocommerce',                         // Capability
                'superwp-cafe-pos',                          // Menu slug
                array($this, 'render_pos_page'),             // Callback function
                'dashicons-store',                           // Icon
                55                                           // Position after WooCommerce
            );

            // Add submenu items
            add_submenu_page(
                'superwp-cafe-pos',                          // Parent slug
                __('POS Terminal', 'superwp-cafe-pos'),      // Page title
                __('POS Terminal', 'superwp-cafe-pos'),      // Menu title
                'manage_woocommerce',                        // Capability
                'superwp-cafe-pos',                          // Menu slug (same as parent)
                array($this, 'render_pos_page')              // Callback function
            );

            add_submenu_page(
                'superwp-cafe-pos',                          // Parent slug
                __('POS Settings', 'superwp-cafe-pos'),      // Page title
                __('Settings', 'superwp-cafe-pos'),          // Menu title
                'manage_woocommerce',                        // Capability
                'superwp-cafe-pos-settings',                 // Menu slug
                array($this, 'render_settings_page')         // Callback function
            );
        }

        public function render_pos_page() {
            // Get the POS terminal page URL
            $page_manager = Superwp_Cafe_Pos_Page_Manager::instance();
            $pos_url = $page_manager->get_pos_page_url();

            if (!$pos_url) {
                $pos_url = home_url('/pos-terminal/');
            }
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="superwp-cafe-pos-container">
                    <div class="pos-launch-button">
                        <a href="<?php echo esc_url($pos_url); ?>" 
                           class="button button-primary button-hero" 
                           target="_blank" 
                           rel="noopener noreferrer">
                            <?php _e('Launch POS Terminal', 'superwp-cafe-pos'); ?>
                        </a>
                    </div>
                    <div class="pos-stats">
                        <!-- Add POS statistics here -->
                    </div>
                </div>
            </div>
            <?php
        }

        public function render_settings_page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                
                <h2 class="nav-tab-wrapper">
                    <a href="?page=superwp-cafe-pos-settings&tab=general" 
                       class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('General', 'superwp-cafe-pos'); ?>
                    </a>
                    <a href="?page=superwp-cafe-pos-settings&tab=roles" 
                       class="nav-tab <?php echo $active_tab == 'roles' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('POS Roles', 'superwp-cafe-pos'); ?>
                    </a>
                    <a href="?page=superwp-cafe-pos-settings&tab=system_settings" 
                       class="nav-tab <?php echo $active_tab == 'system_settings' ? 'nav-tab-active' : ''; ?>">
                        <i class="fas fa-cogs"></i> <?php _e('System Settings', 'superwp-cafe-pos'); ?>
                    </a>
                </h2>

                <div class="tab-content">
                <?php
                switch ($active_tab) {
                        case 'roles':
                            $this->render_roles_tab();
                            break;
                        case 'system_settings':
                            do_action('superwpcaf_settings_content', 'system_settings');
                            break;
                        default:
                            $this->render_general_tab();
                        break;
                    }
                    ?>
                </div>
            </div>
            <?php
        }

        private function render_roles_tab() {
            $roles_manager = Superwp_Cafe_Pos_Roles::instance();
            $pos_roles = $roles_manager->get_all_pos_roles();
            ?>
            <div class="pos-roles-management">
                <!-- Role Management Section -->
                <div class="pos-roles-section">
                    <div class="pos-roles-header">
                        <h3><?php _e('POS Roles', 'superwp-cafe-pos'); ?></h3>
                        <button type="button" class="button button-primary add-new-role">
                            <?php _e('Add New Role', 'superwp-cafe-pos'); ?>
                        </button>
                    </div>

                    <table class="wp-list-table widefat fixed striped roles-table">
                        <thead>
                            <tr>
                                <th><?php _e('Role Name', 'superwp-cafe-pos'); ?></th>
                                <th><?php _e('Capabilities', 'superwp-cafe-pos'); ?></th>
                                <th><?php _e('Users', 'superwp-cafe-pos'); ?></th>
                                <th style="width: 150px;"><?php _e('Actions', 'superwp-cafe-pos'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pos_roles as $role_id => $role_data) : 
                                $user_count = count(get_users(['role' => $role_id]));
                            ?>
                                <tr id="role-<?php echo esc_attr($role_id); ?>">
                                    <td><?php echo esc_html($role_data['label']); ?></td>
                                    <td>
                                        <?php 
                                        $caps = array_filter($role_data['capabilities']);
                                        echo esc_html(implode(', ', array_keys($caps)));
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($user_count); ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <button type="button" 
                                                    class="button button-small edit-role"
                                                    data-role="<?php echo esc_attr($role_id); ?>">
                                                <?php _e('Edit', 'superwp-cafe-pos'); ?>
                                            </button>
                                            <?php if (!in_array($role_id, ['pos_manager', 'pos_cashier', 'pos_waiter', 'pos_kitchen'])) : ?>
                                                <button type="button" 
                                                        class="button button-small delete-role"
                                                        data-role="<?php echo esc_attr($role_id); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                    <?php _e('Delete', 'superwp-cafe-pos'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Users Section -->
                <div class="pos-users-section">
                    <div class="pos-roles-header">
                        <h3><?php _e('POS Users', 'superwp-cafe-pos'); ?></h3>
                        <div class="header-actions">
                            <input type="text" id="user-search" placeholder="<?php esc_attr_e('Search users...', 'superwp-cafe-pos'); ?>" class="regular-text">
                            <a href="<?php echo admin_url('user-new.php'); ?>" class="button button-primary">
                                <?php _e('Add New User', 'superwp-cafe-pos'); ?>
                            </a>
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped users-table">
                        <thead>
                            <tr>
                                <th><?php _e('User', 'superwp-cafe-pos'); ?></th>
                                <th><?php _e('Role', 'superwp-cafe-pos'); ?></th>
                                <th><?php _e('Last Login', 'superwp-cafe-pos'); ?></th>
                                <th><?php _e('Status', 'superwp-cafe-pos'); ?></th>
                                <th><?php _e('Actions', 'superwp-cafe-pos'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $pos_users = get_users(array(
                                'role__in' => array_keys($pos_roles)
                            ));

                            foreach ($pos_users as $user) :
                                $user_roles = array_intersect(array_keys($pos_roles), $user->roles);
                                $current_role = reset($user_roles);
                                $status = get_user_meta($user->ID, 'pos_user_status', true) ?: 'active';
                                $last_login = get_user_meta($user->ID, 'last_login', true);
                            ?>
                                <tr>
                                    <td class="user-info">
                                        <?php echo get_avatar($user->ID, 32); ?>
                                        <div class="user-details">
                                            <strong><?php echo esc_html($user->display_name); ?></strong>
                                            <span class="user-email"><?php echo esc_html($user->user_email); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="user-role-select" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                            <?php foreach ($pos_roles as $role_id => $role_data) : ?>
                                                <option value="<?php echo esc_attr($role_id); ?>" 
                                                        <?php selected($current_role, $role_id); ?>>
                                                    <?php echo esc_html($role_data['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($last_login) {
                                            echo esc_html(human_time_diff(strtotime($last_login), current_time('timestamp'))) . ' ago';
                                        } else {
                                            _e('Never', 'superwp-cafe-pos');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="pos-status pos-status-<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <button type="button" 
                                                    class="button button-small toggle-status"
                                                    data-user-id="<?php echo esc_attr($user->ID); ?>"
                                                    data-status="<?php echo esc_attr($status); ?>">
                                                <?php echo $status === 'active' ? __('Deactivate', 'superwp-cafe-pos') : __('Activate', 'superwp-cafe-pos'); ?>
                                            </button>
                                            <a href="<?php echo get_edit_user_link($user->ID); ?>" 
                                               class="button button-small">
                                                <?php _e('Edit', 'superwp-cafe-pos'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Role Edit Modal -->
                <div id="role-modal" class="pos-modal">
                    <div class="pos-modal-content">
                        <span class="pos-modal-close">&times;</span>
                        <h2 class="modal-title"><?php _e('Edit Role', 'superwp-cafe-pos'); ?></h2>
                        <div class="modal-loading" style="display: none;">
                            <span class="spinner is-active"></span>
                            <?php _e('Loading role data...', 'superwp-cafe-pos'); ?>
                        </div>
                        <form id="role-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="role_name"><?php _e('Role Name', 'superwp-cafe-pos'); ?></label></th>
                                    <td>
                                        <input type="text" id="role_name" name="role_name" class="regular-text" required>
                                        <input type="hidden" id="role_id" name="role_id">
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Capabilities', 'superwp-cafe-pos'); ?></th>
                                    <td class="capabilities-list">
                                        <?php 
                                        $all_caps = array(
                                            'manage_pos' => __('Manage POS', 'superwp-cafe-pos'),
                                            'view_pos_reports' => __('View Reports', 'superwp-cafe-pos'),
                                            'manage_pos_settings' => __('Manage Settings', 'superwp-cafe-pos'),
                                            'manage_pos_users' => __('Manage Users', 'superwp-cafe-pos'),
                                            'process_pos_refunds' => __('Process Refunds', 'superwp-cafe-pos'),
                                            'view_pos_orders' => __('View Orders', 'superwp-cafe-pos'),
                                            'create_pos_orders' => __('Create Orders', 'superwp-cafe-pos'),
                                            'edit_pos_orders' => __('Edit Orders', 'superwp-cafe-pos'),
                                            'delete_pos_orders' => __('Delete Orders', 'superwp-cafe-pos'),
                                            'manage_pos_products' => __('Manage Products', 'superwp-cafe-pos'),
                                            'process_pos_sales' => __('Process Sales', 'superwp-cafe-pos'),
                                            'update_order_status' => __('Update Order Status', 'superwp-cafe-pos')
                                        );
                                        foreach ($all_caps as $cap => $label) : 
                                        ?>
                                            <label class="capability-checkbox">
                                                <input type="checkbox" name="capabilities[]" value="<?php echo esc_attr($cap); ?>">
                                                <?php echo esc_html($label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="redirect_page"><?php _e('Login Redirect', 'superwp-cafe-pos'); ?></label></th>
                                    <td>
                                        <select name="redirect_page" id="redirect_page" class="regular-text">
                                            <option value="pos_terminal" <?php selected(isset($role_data['redirect_page']) ? $role_data['redirect_page'] : 'pos_terminal', 'pos_terminal'); ?>>
                                                <?php _e('POS Terminal', 'superwp-cafe-pos'); ?>
                                            </option>
                                            <option value="dashboard" <?php selected(isset($role_data['redirect_page']) ? $role_data['redirect_page'] : '', 'dashboard'); ?>>
                                                <?php _e('WordPress Dashboard', 'superwp-cafe-pos'); ?>
                                            </option>
                                            <option value="custom" <?php selected(isset($role_data['redirect_page']) ? $role_data['redirect_page'] : '', 'custom'); ?>>
                                                <?php _e('Custom URL', 'superwp-cafe-pos'); ?>
                                            </option>
                                        </select>
                                        <div id="custom_redirect_url_wrapper" style="<?php echo (isset($role_data['redirect_page']) && $role_data['redirect_page'] === 'custom') ? 'display: block;' : 'display: none;'; ?>">
                                            <input type="url" 
                                                   name="custom_redirect_url" 
                                                   id="custom_redirect_url" 
                                                   class="regular-text" 
                                                   value="<?php echo esc_url(isset($role_data['custom_redirect_url']) ? $role_data['custom_redirect_url'] : ''); ?>"
                                                   placeholder="https://example.com/custom-page">
                                        </div>
                                        <p class="description">
                                            <?php _e('Select where users with this role should be redirected after login.', 'superwp-cafe-pos'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <div class="submit-wrapper">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Save Role', 'superwp-cafe-pos'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php
        }

        private function render_general_tab() {
            // Register settings
            register_setting('superwp_cafe_pos_settings', 'superwp_cafe_pos_options');
            
            $options = get_option('superwp_cafe_pos_options', array(
                'currency_position' => 'left',
                'tax_calculation' => 'yes',
                'default_tax_rate' => '10',
                'receipt_header' => '',
                'receipt_footer' => '',
                'enable_kitchen_print' => 'no',
                'enable_customer_display' => 'no',
                'table_management' => 'no',
                'low_stock_alert' => '5'
            ));
            ?>
            <div class="pos-settings-wrapper">
                <form method="post" action="options.php">
                    <?php settings_fields('superwp_cafe_pos_settings'); ?>
                    
                    <!-- General Settings Section -->
                    <div class="pos-settings-section">
                        <h2><?php _e('General Settings', 'superwp-cafe-pos'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="currency_position"><?php _e('Currency Position', 'superwp-cafe-pos'); ?></label>
                                </th>
                                <td>
                                    <select name="superwp_cafe_pos_options[currency_position]" id="currency_position">
                                        <option value="left" <?php selected($options['currency_position'], 'left'); ?>><?php _e('Left ($99.99)', 'superwp-cafe-pos'); ?></option>
                                        <option value="right" <?php selected($options['currency_position'], 'right'); ?>><?php _e('Right (99.99$)', 'superwp-cafe-pos'); ?></option>
                                        <option value="left_space" <?php selected($options['currency_position'], 'left_space'); ?>><?php _e('Left with space ($ 99.99)', 'superwp-cafe-pos'); ?></option>
                                        <option value="right_space" <?php selected($options['currency_position'], 'right_space'); ?>><?php _e('Right with space (99.99 $)', 'superwp-cafe-pos'); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="tax_calculation"><?php _e('Enable Tax Calculation', 'superwp-cafe-pos'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="superwp_cafe_pos_options[tax_calculation]" 
                                               id="tax_calculation" 
                                               value="yes" 
                                               <?php checked($options['tax_calculation'], 'yes'); ?>>
                                        <?php _e('Enable tax calculation in POS', 'superwp-cafe-pos'); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="default_tax_rate"><?php _e('Default Tax Rate (%)', 'superwp-cafe-pos'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           name="superwp_cafe_pos_options[default_tax_rate]" 
                                           id="default_tax_rate" 
                                           value="<?php echo esc_attr($options['default_tax_rate']); ?>"
                                           min="0"
                                           max="100"
                                           step="0.01">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Receipt Settings Section -->
                    <div class="pos-settings-section">
                        <h2><?php _e('Receipt Settings', 'superwp-cafe-pos'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="receipt_header"><?php _e('Receipt Header', 'superwp-cafe-pos'); ?></label>
                                </th>
                                <td>
                                    <textarea name="superwp_cafe_pos_options[receipt_header]" 
                                              id="receipt_header" 
                                              rows="4" 
                                              class="large-text"><?php echo esc_textarea($options['receipt_header']); ?></textarea>
                                    <p class="description"><?php _e('This text will appear at the top of the receipt', 'superwp-cafe-pos'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="receipt_footer"><?php _e('Receipt Footer', 'superwp-cafe-pos'); ?></label>
                                </th>
                                <td>
                                    <textarea name="superwp_cafe_pos_options[receipt_footer]" 
                                              id="receipt_footer" 
                                              rows="4" 
                                              class="large-text"><?php echo esc_textarea($options['receipt_footer']); ?></textarea>
                                    <p class="description"><?php _e('This text will appear at the bottom of the receipt', 'superwp-cafe-pos'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Advanced Features Section -->
                    <div class="pos-settings-section">
                        <h2><?php _e('Advanced Features', 'superwp-cafe-pos'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Additional Features', 'superwp-cafe-pos'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="superwp_cafe_pos_options[enable_kitchen_print]" 
                                               value="yes" 
                                               <?php checked($options['enable_kitchen_print'], 'yes'); ?>>
                                        <?php _e('Enable Kitchen Printing', 'superwp-cafe-pos'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" 
                                               name="superwp_cafe_pos_options[enable_customer_display]" 
                                               value="yes" 
                                               <?php checked($options['enable_customer_display'], 'yes'); ?>>
                                        <?php _e('Enable Customer Display', 'superwp-cafe-pos'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" 
                                               name="superwp_cafe_pos_options[table_management]" 
                                               value="yes" 
                                               <?php checked($options['table_management'], 'yes'); ?>>
                                        <?php _e('Enable Table Management', 'superwp-cafe-pos'); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="low_stock_alert"><?php _e('Low Stock Alert', 'superwp-cafe-pos'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           name="superwp_cafe_pos_options[low_stock_alert]" 
                                           id="low_stock_alert" 
                                           value="<?php echo esc_attr($options['low_stock_alert']); ?>"
                                           min="0">
                                    <p class="description"><?php _e('Show alert when product stock falls below this number', 'superwp-cafe-pos'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }

        /**
         * Render POS Users tab
         */
        private function render_users_tab() {
            $roles_manager = Superwp_Cafe_Pos_Roles::instance();
            $pos_users = $roles_manager->get_pos_users();
            $pos_roles = $roles_manager->get_pos_roles();
            ?>
            <div class="pos-users-section">
                <div class="pos-users-header">
                    <div class="header-actions">
                        <input type="text" id="user-search" placeholder="Search users...">
                        <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="button button-primary">
                            <?php _e('Add New User', 'superwp-cafe-pos'); ?>
                        </a>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped users-table">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'superwp-cafe-pos'); ?></th>
                            <th><?php _e('Role', 'superwp-cafe-pos'); ?></th>
                            <th><?php _e('Last Login', 'superwp-cafe-pos'); ?></th>
                            <th><?php _e('Status', 'superwp-cafe-pos'); ?></th>
                            <th><?php _e('Actions', 'superwp-cafe-pos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pos_users)) : ?>
                            <tr>
                                <td colspan="5"><?php _e('No POS users found.', 'superwp-cafe-pos'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($pos_users as $user) : ?>
                                <tr>
                                    <td class="user-info">
                                        <div class="user-details">
                                            <strong><?php echo esc_html($user['name']); ?></strong>
                                            <span class="user-email"><?php echo esc_html($user['email']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="user-role-select" data-user-id="<?php echo esc_attr($user['id']); ?>">
                                            <?php foreach ($pos_roles as $role_id => $role_data) : ?>
                                                <option value="<?php echo esc_attr($role_id); ?>" 
                                                        <?php selected($user['role'], $role_id); ?>>
                                                    <?php echo esc_html($role_data['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php 
                                        echo $user['last_login'] 
                                            ? esc_html(date('Y-m-d H:i:s', strtotime($user['last_login']))) 
                                            : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="pos-status pos-status-<?php echo esc_attr($user['status']); ?>">
                                            <?php echo esc_html(ucfirst($user['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user['id'])); ?>" 
                                           class="button button-small">
                                            <?php _e('Edit', 'superwp-cafe-pos'); ?>
                                        </a>
                                        <button type="button" 
                                                class="button button-small toggle-status" 
                                                data-user-id="<?php echo esc_attr($user['id']); ?>"
                                                data-status="<?php echo esc_attr($user['status']); ?>">
                                            <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }

endif;
