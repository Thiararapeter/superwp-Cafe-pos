<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Superwp_Cafe_Pos_Admin_Menu')) :

    class Superwp_Cafe_Pos_Admin_Menu {
        
        private static $instance = null;

        public function __construct() {
            // Check if we're on POS terminal before adding admin menus
            if (!$this->is_pos_terminal_page()) {
                add_action('admin_menu', array($this, 'add_pos_menu'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
                add_action('admin_init', array($this, 'register_pos_settings'));
                
                // Add settings link to plugin actions
                add_filter('plugin_action_links_' . plugin_basename(SUPERWPCAF_PLUGIN_FILE), array($this, 'add_plugin_action_links'), 10);
            }
            
            // Add AJAX handlers
            add_action('wp_ajax_superwpcaf_update_receipt_preview', array($this, 'ajax_update_receipt_preview'));
            add_action('wp_ajax_superwpcaf_save_receipt_settings', array($this, 'ajax_save_receipt_settings'));
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
                
                // Add media uploader scripts
                wp_enqueue_media();
                
                wp_enqueue_script(
                    'superwp-cafe-pos-admin-settings',
                    SUPERWPCAF_PLUGIN_URL . 'assets/js/admin-settings.js',
                    array('jquery'),
                    SUPERWPCAF_VERSION,
                    true
                );
                
                // Add data for the media uploader
                wp_localize_script('superwp-cafe-pos-admin-settings', 'superwpCafePosSettings', array(
                    'mediaTitle' => __('Select Receipt Logo', 'superwp-cafe-pos'),
                    'mediaButton' => __('Use this logo', 'superwp-cafe-pos')
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
                'receipt_header' => '',
                'receipt_footer' => ''
            ));
            ?>
            <div class="pos-settings-wrapper">
                <form method="post" action="options.php">
                    <?php settings_fields('superwp_cafe_pos_settings'); ?>
                    
                    <!-- General Settings Section -->
                    <div class="pos-settings-section">
                        <h2><?php _e('General Settings', 'superwp-cafe-pos'); ?></h2>
                        <p class="description">
                            <?php _e('Currency, tax, and inventory settings are managed through WooCommerce settings.', 'superwp-cafe-pos'); ?>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings'); ?>" class="button button-secondary">
                                <?php _e('Manage WooCommerce Settings', 'superwp-cafe-pos'); ?>
                            </a>
                        </p>
                    </div>

                    <!-- Receipt Settings -->
                    <?php $this->render_receipt_settings($options); ?>

                    <div class="submit-wrapper">
                        <button type="submit" class="button button-primary">
                            <?php _e('Save Settings', 'superwp-cafe-pos'); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php
        }

        private function render_receipt_settings($options) {
            ?>
            <div class="pos-settings-section">
                <h2><?php _e('Receipt Settings', 'superwp-cafe-pos'); ?></h2>
                
                <!-- Receipt Preview -->
                <div class="receipt-preview-wrapper">
                    <h3><?php _e('Receipt Preview', 'superwp-cafe-pos'); ?></h3>
                    <div class="receipt-preview" id="receipt-preview">
                        <?php include SUPERWPCAF_PLUGIN_DIR . 'templates/receipt-preview.php'; ?>
                    </div>
                    <button type="button" class="button" id="refresh-preview">
                        <?php _e('Refresh Preview', 'superwp-cafe-pos'); ?>
                    </button>
                </div>

                <table class="form-table">
                    <!-- Staff Information -->
                    <tr>
                        <th scope="row"><?php _e('Staff Information', 'superwp-cafe-pos'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="superwp_cafe_pos_options[show_cashier]" value="1" 
                                        class="preview-trigger" <?php checked(isset($options['show_cashier']) && $options['show_cashier']); ?>>
                                    <?php _e('Show Cashier Name', 'superwp-cafe-pos'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="superwp_cafe_pos_options[show_waiter]" value="1" 
                                        class="preview-trigger" <?php checked(isset($options['show_waiter']) && $options['show_waiter']); ?>>
                                    <?php _e('Show Waiter Name', 'superwp-cafe-pos'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- Header Settings -->
                    <tr>
                        <th scope="row">
                            <label for="receipt_header"><?php _e('Receipt Header', 'superwp-cafe-pos'); ?></label>
                        </th>
                        <td>
                            <textarea name="superwp_cafe_pos_options[receipt_header]" id="receipt_header" rows="3" class="large-text"><?php echo esc_textarea($options['receipt_header'] ?? ''); ?></textarea>
                            <p class="description"><?php _e('Text to appear at the top of the receipt. Supports basic HTML.', 'superwp-cafe-pos'); ?></p>
                        </td>
                    </tr>

                    <!-- Item Details Customization -->
                    <tr>
                        <th scope="row"><?php _e('Item Details', 'superwp-cafe-pos'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="superwp_cafe_pos_options[show_sku]" value="1" 
                                        <?php checked(isset($options['show_sku']) && $options['show_sku']); ?>>
                                    <?php _e('Show SKU', 'superwp-cafe-pos'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="superwp_cafe_pos_options[show_tax]" value="1" 
                                        <?php checked(isset($options['show_tax']) && $options['show_tax']); ?>>
                                    <?php _e('Show Tax per Item', 'superwp-cafe-pos'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="superwp_cafe_pos_options[show_discount]" value="1" 
                                        <?php checked(isset($options['show_discount']) && $options['show_discount']); ?>>
                                    <?php _e('Show Discounts', 'superwp-cafe-pos'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- Footer Settings -->
                    <tr>
                        <th scope="row">
                            <label for="receipt_footer"><?php _e('Receipt Footer', 'superwp-cafe-pos'); ?></label>
                        </th>
                        <td>
                            <textarea name="superwp_cafe_pos_options[receipt_footer]" id="receipt_footer" rows="3" class="large-text"><?php echo esc_textarea($options['receipt_footer'] ?? ''); ?></textarea>
                            <p class="description"><?php _e('Text to appear at the bottom of the receipt. Supports basic HTML.', 'superwp-cafe-pos'); ?></p>
                        </td>
                    </tr>
                </table>
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

        public function sanitize_pos_settings($input) {
            $sanitized = array();
            
            // Sanitize receipt settings only
            $sanitized['receipt_header'] = isset($input['receipt_header']) ? 
                                          wp_kses_post($input['receipt_header']) : '';
            $sanitized['receipt_footer'] = isset($input['receipt_footer']) ? 
                                          wp_kses_post($input['receipt_footer']) : '';
            $sanitized['receipt_logo'] = isset($input['receipt_logo']) ? 
                                      absint($input['receipt_logo']) : '';
            
            return $sanitized;
        }

        public function register_pos_settings() {
            register_setting(
                'superwp_cafe_pos_settings',
                'superwp_cafe_pos_options',
                array($this, 'sanitize_pos_settings')
            );
        }

        // Add helper method to get WooCommerce settings
        private function get_wc_settings() {
            return array(
                'currency' => get_woocommerce_currency(),
                'currency_position' => get_option('woocommerce_currency_pos'),
                'tax_calculation' => wc_tax_enabled() ? 'yes' : 'no',
                'tax_rates' => WC_Tax::get_rates()
            );
        }

        // Add settings link to plugin listing
        public function add_plugin_action_links($links) {
            // Keep the deactivate link if it exists
            $deactivate_link = isset($links['deactivate']) ? $links['deactivate'] : '';
            
            // Clear other links
            $links = array();
            
            // Add settings link
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=superwp-cafe-pos-settings')),
                esc_html__('Settings', 'superwp-cafe-pos')
            );
            
            // Add our links in desired order
            $links[] = $settings_link;
            if ($deactivate_link) {
                $links['deactivate'] = $deactivate_link;
            }
            
            return $links;
        }

        /**
         * Check if current page is POS terminal
         *
         * @return bool
         */
        private function is_pos_terminal_page() {
            if (is_admin()) {
                return false;
            }
            
            global $post;
            $pos_page_slug = 'pos-terminal'; // Adjust this if your slug is different
            
            return is_page($pos_page_slug) || 
                   (is_page() && $post && has_shortcode($post->post_content, 'superwp_cafe_pos_terminal'));
        }

        /**
         * AJAX handler for updating receipt preview
         */
        public function ajax_update_receipt_preview() {
            check_ajax_referer('superwp-cafe-pos-admin', 'nonce');
            
            $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
            
            // Update temporary preview settings
            update_option('superwp_cafe_pos_preview_settings', $settings);
            
            ob_start();
            include SUPERWPCAF_PLUGIN_DIR . 'templates/receipt-preview.php';
            $preview_html = ob_get_clean();
            
            wp_send_json_success(array('preview' => $preview_html));
        }

        /**
         * AJAX handler for saving receipt settings
         */
        public function ajax_save_receipt_settings() {
            check_ajax_referer('superwp-cafe-pos-admin', 'nonce');
            
            if (!isset($_POST['superwp_cafe_pos_options'])) {
                wp_send_json_error(array('message' => __('No settings data received', 'superwp-cafe-pos')));
                return;
            }

            // Get existing options
            $existing_options = get_option('superwp_cafe_pos_options', array());
            
            // Get posted data
            $posted_options = $_POST['superwp_cafe_pos_options'];
            
            // Ensure checkbox values are preserved
            $checkbox_fields = array(
                'show_cashier',
                'show_waiter',
                'show_sku',
                'show_tax',
                'show_discount'
            );

            foreach ($checkbox_fields as $field) {
                $existing_options[$field] = isset($posted_options[$field]) ? 1 : 0;
            }

            // Merge other settings
            $other_fields = array(
                'receipt_header',
                'receipt_footer'
            );

            foreach ($other_fields as $field) {
                if (isset($posted_options[$field])) {
                    $existing_options[$field] = sanitize_text_field($posted_options[$field]);
                }
            }

            // Ensure template is set
            if (empty($existing_options['receipt_template'])) {
                $existing_options['receipt_template'] = 'standard';
            }

            // Save the merged options
            update_option('superwp_cafe_pos_options', $existing_options);
            
            // Clear preview settings
            delete_option('superwp_cafe_pos_preview_settings');

            wp_send_json_success(array(
                'message' => __('Settings saved successfully', 'superwp-cafe-pos'),
                'options' => $existing_options
            ));
        }
    }

endif;
