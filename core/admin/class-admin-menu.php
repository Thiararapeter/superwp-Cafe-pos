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
                
                wp_localize_script('superwp-cafe-pos-admin', 'superwpCafePosSettings', array(
                    'nonce' => wp_create_nonce('superwp-cafe-pos-admin'),
                    'i18n' => array(
                        'syncing' => __('Syncing...', 'superwp-cafe-pos'),
                        'syncError' => __('Error syncing products. Please try again.', 'superwp-cafe-pos'),
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

            // Add Analytics submenu
            add_submenu_page(
                'superwp-cafe-pos',                          // Parent slug
                __('POS Analytics', 'superwp-cafe-pos'),     // Page title
                __('Analytics', 'superwp-cafe-pos'),         // Menu title
                'manage_woocommerce',                        // Capability
                'superwp-cafe-pos-analytics',                // Menu slug
                array($this, 'render_analytics_page')        // Callback function
            );
        }

        public function render_pos_page() {
            // Get the POS terminal page URL
            $page_manager = Superwp_Cafe_Pos_Page_Manager::instance();
            $pos_url = $page_manager->get_pos_page_url();

            if (!$pos_url) {
                $pos_url = home_url('/pos-terminal/');
            }

            // Get today's stats
            $today_stats = $this->get_today_stats();
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                <!-- Launch Button Section -->
                <div class="launch-pos-section">
                        <a href="<?php echo esc_url($pos_url); ?>" 
                       class="launch-pos-button" 
                           target="_blank" 
                           rel="noopener noreferrer">
                        <span class="icon">
                            <i class="fas fa-cash-register"></i>
                        </span>
                        <span class="text">
                            <?php _e('Launch POS Terminal', 'superwp-cafe-pos'); ?>
                        </span>
                        </a>
                    </div>

                <!-- Quick Actions -->
                <div class="other-actions">
                    <a href="<?php echo admin_url('admin.php?page=superwp-cafe-pos-settings'); ?>" 
                       class="button">
                        <i class="fas fa-cog"></i>
                        <?php _e('POS Settings', 'superwp-cafe-pos'); ?>
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" 
                       class="button">
                        <i class="fas fa-plus"></i>
                        <?php _e('Add Product', 'superwp-cafe-pos'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" 
                       class="button">
                        <i class="fas fa-box"></i>
                        <?php _e('View Products', 'superwp-cafe-pos'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=superwp-cafe-pos-analytics'); ?>" 
                       class="button">
                        <i class="fas fa-chart-bar"></i>
                        <?php _e('Analytics', 'superwp-cafe-pos'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>" 
                       class="button">
                        <i class="fas fa-receipt"></i>
                        <?php _e('View Orders', 'superwp-cafe-pos'); ?>
                    </a>
                    </div>
                
                <!-- Today's Stats -->
                <div class="pos-stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon sales">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php _e("Today's Sales", 'superwp-cafe-pos'); ?></h3>
                            <div class="stat-value"><?php echo wc_price($today_stats['sales']); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php _e("Today's Orders", 'superwp-cafe-pos'); ?></h3>
                            <div class="stat-value"><?php echo esc_html($today_stats['orders']); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon items">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php _e('Items Sold', 'superwp-cafe-pos'); ?></h3>
                            <div class="stat-value"><?php echo esc_html($today_stats['items']); ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon average">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php _e('Average Order', 'superwp-cafe-pos'); ?></h3>
                            <div class="stat-value"><?php echo wc_price($today_stats['average']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="pos-recent-orders">
                    <h2><?php _e('Recent POS Orders', 'superwp-cafe-pos'); ?></h2>
                    <?php $this->render_recent_orders(); ?>
                </div>

                <!-- Low Stock Alerts -->
                <div class="pos-stock-alerts">
                    <h2><?php _e('Low Stock Alerts', 'superwp-cafe-pos'); ?></h2>
                    <?php $this->render_low_stock_products(); ?>
                </div>
            </div>
            <?php
        }

        /**
         * Get today's statistics
         */
        private function get_today_stats() {
            $today_start = strtotime('today midnight');
            $now = current_time('timestamp');

            $args = array(
                'post_type' => 'shop_order',
                'post_status' => array('wc-completed', 'wc-processing'),
                'date_query' => array(
                    array(
                        'after' => date('Y-m-d H:i:s', $today_start),
                        'before' => date('Y-m-d H:i:s', $now),
                        'inclusive' => true,
                    ),
                ),
                'meta_query' => array(
                    array(
                        'key' => '_created_via',
                        'value' => 'pos',
                    ),
                ),
                'posts_per_page' => -1,
            );

            $orders = wc_get_orders($args);
            
            $total_sales = 0;
            $total_items = 0;
            
            foreach ($orders as $order) {
                $total_sales += $order->get_total();
                $total_items += count($order->get_items());
            }

            $order_count = count($orders);
            $average_order = $order_count > 0 ? $total_sales / $order_count : 0;

            return array(
                'sales' => $total_sales,
                'orders' => $order_count,
                'items' => $total_items,
                'average' => $average_order
            );
        }

        /**
         * Render recent orders table
         */
        private function render_recent_orders() {
            // Get current page
            $current_page = isset($_GET['order_page']) ? absint($_GET['order_page']) : 1;
            $per_page = 20;
            
            $args = array(
                'post_type' => 'shop_order',
                'post_status' => array('wc-completed', 'wc-processing'),
                'meta_query' => array(
                    array(
                        'key' => '_created_via',
                        'value' => 'pos'
                    )
                ),
                'posts_per_page' => $per_page,
                'paged' => $current_page,
                'orderby' => 'date',
                'order' => 'DESC'
            );

            $orders_query = new WP_Query($args);
            $total_pages = $orders_query->max_num_pages;
            
            ?>
            <div class="pos-section-header">
                <h2>
                    <?php _e('Recent POS Orders', 'superwp-cafe-pos'); ?>
                    <span class="count">(<?php echo $orders_query->found_posts; ?>)</span>
                </h2>
                <?php if ($orders_query->found_posts > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=wc-orders&created_via=pos'); ?>" 
                       class="button view-all">
                        <?php _e('View All', 'superwp-cafe-pos'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php
            if (!$orders_query->have_posts()) {
                echo '<p class="no-items">' . __('No recent POS orders found.', 'superwp-cafe-pos') . '</p>';
                return;
            }
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('Date', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('Status', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('Total', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('Actions', 'superwp-cafe-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($orders_query->have_posts()): $orders_query->the_post(); 
                        $order = wc_get_order($orders_query->post);
                        ?>
                        <tr>
                            <td>#<?php echo $order->get_order_number(); ?></td>
                            <td><?php echo wc_format_datetime($order->get_date_created()); ?></td>
                            <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                            <td><?php echo $order->get_formatted_order_total(); ?></td>
                            <td>
                                <a href="<?php echo $order->get_edit_order_url(); ?>" 
                                   class="button button-small">
                                    <?php _e('View', 'superwp-cafe-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('order_page', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif;

            wp_reset_postdata();
        }

        /**
         * Render low stock products
         */
        private function render_low_stock_products() {
            // Set threshold to 5
            $low_stock_amount = 5;
            
            // Get current page
            $current_page = isset($_GET['stock_page']) ? absint($_GET['stock_page']) : 1;
            $per_page = 20;
            
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => $per_page,
                'paged' => $current_page,
                'meta_query' => array(
                    array(
                        'key' => '_manage_stock',
                        'value' => 'yes'
                    ),
                    array(
                        'key' => '_stock',
                        'value' => $low_stock_amount,
                        'type' => 'numeric',
                        'compare' => '<='
                    ),
                    array(
                        'key' => '_stock',
                        'value' => 0,
                        'type' => 'numeric',
                        'compare' => '>'
                    )
                )
            );

            $products = new WP_Query($args);
            $total_pages = $products->max_num_pages;

            ?>
            <div class="pos-section-header">
                <h2>
                    <?php _e('Products Below 5pcs', 'superwp-cafe-pos'); ?>
                    <span class="count">(<?php echo $products->found_posts; ?>)</span>
                </h2>
                <?php if ($products->found_posts > 0): ?>
                    <a href="<?php echo admin_url('edit.php?post_type=product&stock_status=low'); ?>" 
                       class="button view-all">
                        <?php _e('View All', 'superwp-cafe-pos'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php
            if (!$products->have_posts()) {
                echo '<p class="no-items">' . __('No products below 5pcs in stock.', 'superwp-cafe-pos') . '</p>';
                return;
            }
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('SKU', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('Current Stock', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('Regular Price', 'superwp-cafe-pos'); ?></th>
                        <th><?php _e('Actions', 'superwp-cafe-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($products->have_posts()): $products->the_post(); 
                        $product = wc_get_product(get_the_ID());
                        ?>
                        <tr>
                            <td>
                                <?php 
                                if ($product->get_image_id()) {
                                    echo '<img src="' . wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') . '" class="product-thumb" />';
                                }
                                echo '<strong>' . $product->get_name() . '</strong>'; 
                                ?>
                            </td>
                            <td><?php echo $product->get_sku() ?: '-'; ?></td>
                            <td>
                                <span class="stock-count <?php echo ($product->get_stock_quantity() <= 5) ? 'critical-stock' : 'low-stock'; ?>">
                                    <?php echo $product->get_stock_quantity(); ?> pcs
                                </span>
                            </td>
                            <td><?php echo $product->get_regular_price() ? wc_price($product->get_regular_price()) : '-'; ?></td>
                            <td class="actions">
                                <a href="<?php echo get_edit_post_link(); ?>" 
                                   class="button button-small">
                                    <i class="fas fa-edit"></i>
                                    <?php _e('Edit', 'superwp-cafe-pos'); ?>
                                </a>
                                <a href="<?php echo admin_url('post.php?post=' . $product->get_id() . '&action=edit#inventory_product_data'); ?>" 
                                   class="button button-small">
                                    <i class="fas fa-box"></i>
                                    <?php _e('Stock', 'superwp-cafe-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('stock_page', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif;

            wp_reset_postdata();
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
                    <div class="receipt-actions">
                        <button type="button" class="button" id="refresh-preview">
                            <i class="fas fa-sync"></i> <?php _e('Refresh Preview', 'superwp-cafe-pos'); ?>
                        </button>
                        <button type="button" class="button print-preview">
                            <i class="fas fa-print"></i> <?php _e('Print Preview', 'superwp-cafe-pos'); ?>
                        </button>
                    </div>
                    <div class="receipt-preview" id="receipt-preview">
                        <?php include SUPERWPCAF_PLUGIN_DIR . 'templates/receipt-preview.php'; ?>
                    </div>
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
                                        class="preview-trigger" <?php checked(!empty($options['show_sku'])); ?>>
                                    <?php _e('Show SKU', 'superwp-cafe-pos'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="superwp_cafe_pos_options[show_tax]" value="1" 
                                        class="preview-trigger" <?php checked(!empty($options['show_tax'])); ?>>
                                    <?php _e('Show Tax per Item', 'superwp-cafe-pos'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="superwp_cafe_pos_options[show_discount]" value="1" 
                                        class="preview-trigger" <?php checked(!empty($options['show_discount'])); ?>>
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
            
            if (!isset($_POST['settings'])) {
                wp_send_json_error(array('message' => __('No settings data received', 'superwp-cafe-pos')));
                return;
            }

            // Get existing options
            $existing_options = get_option('superwp_cafe_pos_options', array());
            
            // Get posted data
            $settings = $_POST['settings'];
            
            // Update checkbox fields
            $existing_options['show_sku'] = !empty($settings['show_sku']) ? 1 : 0;
            $existing_options['show_tax'] = !empty($settings['show_tax']) ? 1 : 0;
            $existing_options['show_discount'] = !empty($settings['show_discount']) ? 1 : 0;
            $existing_options['show_cashier'] = !empty($settings['show_cashier']) ? 1 : 0;
            $existing_options['show_waiter'] = !empty($settings['show_waiter']) ? 1 : 0;

            // Update text fields
            if (isset($settings['header'])) {
                $existing_options['receipt_header'] = wp_kses_post($settings['header']);
            }
            if (isset($settings['footer'])) {
                $existing_options['receipt_footer'] = wp_kses_post($settings['footer']);
            }

            // Save the updated options
            update_option('superwp_cafe_pos_options', $existing_options);
            
            wp_send_json_success(array(
                'message' => __('Settings saved successfully', 'superwp-cafe-pos'),
                'options' => $existing_options
            ));
        }

        /**
         * AJAX handler for syncing products
         */
        public function ajax_sync_products() {
            check_ajax_referer('superwp-cafe-pos-admin', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(array('message' => __('Permission denied', 'superwp-cafe-pos')));
                return;
            }

            try {
                // Get batch information
                $batch_size = 50; // Process 50 products at a time
                $current_batch = isset($_POST['batch']) ? absint($_POST['batch']) : 0;
                
                // Query products for current batch
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => $batch_size,
                    'offset' => $current_batch * $batch_size,
                    'fields' => 'ids', // Only get IDs for better performance
                    'post_status' => 'publish'
                );
                
                $products = get_posts($args);
                
                // Get total number of products
                $total_products = wp_count_posts('product')->publish;
                $total_batches = ceil($total_products / $batch_size);
                
                if (!empty($products)) {
                    foreach ($products as $product_id) {
                        // Clear product caches
                        wp_cache_delete($product_id, 'post_meta');
                        wp_cache_delete('product-' . $product_id, 'products');
                        clean_post_cache($product_id);
                    }
                    
                    // Calculate progress
                    $progress = min(100, round(($current_batch + 1) / $total_batches * 100));
                    
                    if ($current_batch + 1 < $total_batches) {
                        // More batches to process
            wp_send_json_success(array(
                            'complete' => false,
                            'batch' => $current_batch + 1,
                            'progress' => $progress,
                            'message' => sprintf(__('Syncing products: %d%%', 'superwp-cafe-pos'), $progress)
                        ));
                    } else {
                        // All batches complete
                        wp_send_json_success(array(
                            'complete' => true,
                            'progress' => 100,
                            'message' => __('Products synchronized successfully', 'superwp-cafe-pos')
                        ));
                    }
                } else {
                    // No more products to process
                    wp_send_json_success(array(
                        'complete' => true,
                        'progress' => 100,
                        'message' => __('Products synchronized successfully', 'superwp-cafe-pos')
                    ));
                }
            } catch (Exception $e) {
                wp_send_json_error(array(
                    'message' => $e->getMessage()
                ));
            }
        }

        /**
         * Render analytics page
         */
        public function render_analytics_page() {
            // Get date range from URL parameters or use defaults
            $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
            
            // Get staff sales data
            $staff_sales = $this->get_staff_sales($start_date, $end_date);
            ?>
            <div class="wrap">
                <h1><?php _e('POS Analytics', 'superwp-cafe-pos'); ?></h1>

                <!-- Date Range Filter -->
                <div class="analytics-filters">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="superwp-cafe-pos-analytics">
                        <div class="date-range">
                            <label for="start_date"><?php _e('From:', 'superwp-cafe-pos'); ?></label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                            
                            <label for="end_date"><?php _e('To:', 'superwp-cafe-pos'); ?></label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                            
                            <button type="submit" class="button"><?php _e('Filter', 'superwp-cafe-pos'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Staff Sales Reports -->
                <div class="analytics-grid">
                    <!-- Cashier Sales -->
                    <div class="analytics-card">
                        <h2><?php _e('Sales by Cashier', 'superwp-cafe-pos'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Cashier', 'superwp-cafe-pos'); ?></th>
                                    <th><?php _e('Orders', 'superwp-cafe-pos'); ?></th>
                                    <th><?php _e('Total Sales', 'superwp-cafe-pos'); ?></th>
                                    <th><?php _e('Average Order', 'superwp-cafe-pos'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_sales['cashiers'] as $cashier): ?>
                                    <tr>
                                        <td><?php echo esc_html($cashier['name']); ?></td>
                                        <td><?php echo esc_html($cashier['orders']); ?></td>
                                        <td><?php echo wc_price($cashier['total']); ?></td>
                                        <td><?php echo wc_price($cashier['average']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Waiter Sales -->
                    <div class="analytics-card">
                        <h2><?php _e('Sales by Waiter', 'superwp-cafe-pos'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Waiter', 'superwp-cafe-pos'); ?></th>
                                    <th><?php _e('Orders', 'superwp-cafe-pos'); ?></th>
                                    <th><?php _e('Total Sales', 'superwp-cafe-pos'); ?></th>
                                    <th><?php _e('Average Order', 'superwp-cafe-pos'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_sales['waiters'] as $waiter): ?>
                                    <tr>
                                        <td><?php echo esc_html($waiter['name']); ?></td>
                                        <td><?php echo esc_html($waiter['orders']); ?></td>
                                        <td><?php echo wc_price($waiter['total']); ?></td>
                                        <td><?php echo wc_price($waiter['average']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Get sales data by staff members
         */
        private function get_staff_sales($start_date, $end_date) {
            $args = array(
                'post_type' => 'shop_order',
                'post_status' => array('wc-completed', 'wc-processing'),
                'date_query' => array(
                    array(
                        'after' => $start_date,
                        'before' => $end_date . ' 23:59:59',
                        'inclusive' => true,
                    ),
                ),
                'meta_query' => array(
                    array(
                        'key' => '_created_via',
                        'value' => 'pos',
                    ),
                ),
                'posts_per_page' => -1,
            );

            $orders = wc_get_orders($args);
            
            $cashiers = array();
            $waiters = array();
            
            foreach ($orders as $order) {
                // Get staff info from order meta
                $cashier_name = $order->get_meta('_pos_cashier_name');
                $waiter_name = $order->get_meta('_pos_waiter_name');
                $order_total = $order->get_total();
                
                // Track cashier stats
                if ($cashier_name) {
                    if (!isset($cashiers[$cashier_name])) {
                        $cashiers[$cashier_name] = array(
                            'name' => $cashier_name,
                            'orders' => 0,
                            'total' => 0,
                        );
                    }
                    $cashiers[$cashier_name]['orders']++;
                    $cashiers[$cashier_name]['total'] += $order_total;
                }
                
                // Track waiter stats
                if ($waiter_name) {
                    if (!isset($waiters[$waiter_name])) {
                        $waiters[$waiter_name] = array(
                            'name' => $waiter_name,
                            'orders' => 0,
                            'total' => 0,
                        );
                    }
                    $waiters[$waiter_name]['orders']++;
                    $waiters[$waiter_name]['total'] += $order_total;
                }
            }

            // Calculate averages
            foreach ($cashiers as &$cashier) {
                $cashier['average'] = $cashier['orders'] > 0 ? $cashier['total'] / $cashier['orders'] : 0;
            }
            foreach ($waiters as &$waiter) {
                $waiter['average'] = $waiter['orders'] > 0 ? $waiter['total'] / $waiter['orders'] : 0;
            }

            return array(
                'cashiers' => array_values($cashiers),
                'waiters' => array_values($waiters)
            );
        }
    }

endif;
