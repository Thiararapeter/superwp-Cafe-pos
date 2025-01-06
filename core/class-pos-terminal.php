<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Superwp_Cafe_Pos_Terminal')) :

class Superwp_Cafe_Pos_Terminal {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Remove admin bar and access completely for POS terminal
        add_action('init', array($this, 'handle_pos_admin_access'));
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_in_pos'));
        
        add_shortcode('superwpcaf_pos_terminal', array($this, 'render_pos_terminal'));
        add_action('template_redirect', array($this, 'check_pos_access'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_pos_assets'));
        $this->register_ajax_handlers();
        add_action('init', array($this, 'init_wc_session'));
        
        // Add filter to remove page title
        add_filter('the_title', array($this, 'remove_pos_page_title'), 10, 2);
        
        // Add custom login page styling
        add_action('login_enqueue_scripts', array($this, 'custom_login_styles'));
        add_filter('login_headerurl', array($this, 'custom_login_header_url'));
        add_filter('login_headertext', array($this, 'custom_login_header_text'));
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
        add_filter('login_message', array($this, 'custom_login_message'));
        
        // Add filter for logout redirect
        add_filter('logout_redirect', array($this, 'custom_logout_redirect'), 10, 3);
        
        // Add particles to login page
        add_action('login_header', array($this, 'add_login_particles'));
        
        // Add option for POS logo
        add_action('admin_init', array($this, 'register_pos_settings'));
        
        // Add settings tab and fields
        add_filter('superwpcaf_settings_tabs', array($this, 'add_system_settings_tab'));
        add_action('superwpcaf_settings_content', array($this, 'render_system_settings_content'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add sync products handler
        add_action('wp_ajax_superwpcaf_sync_products', array($this, 'sync_products'));
        
        // Add new AJAX handler for payment fields
        add_action('wp_ajax_superwpcaf_get_payment_fields', array($this, 'get_payment_fields'));
    }

    /**
     * Handle admin access for POS terminal
     */
    public function handle_pos_admin_access() {
        if ($this->is_pos_terminal_page()) {
            // Remove admin bar completely
            add_filter('show_admin_bar', '__return_false');
            
            // Remove admin bar styles and scripts
            add_action('wp_print_styles', function() {
                wp_deregister_style('admin-bar');
                wp_deregister_style('dashicons');
            }, 99);
            
            add_action('wp_print_scripts', function() {
                wp_deregister_script('admin-bar');
            }, 99);
            
            // Remove the admin bar margin from html
            add_action('get_header', function() {
                remove_action('wp_head', '_admin_bar_bump_cb');
            });
            
            // Remove the admin bar init
            remove_action('init', '_wp_admin_bar_init');
            remove_action('wp_head', 'wp_admin_bar_header');
            remove_action('wp_footer', 'wp_admin_bar_render', 1000);
        }
    }

    /**
     * Hide admin bar in POS terminal
     *
     * @return bool
     */
    public function hide_admin_bar_in_pos() {
        // Check if we're on the POS terminal page
        if ($this->is_pos_terminal_page()) {
            return false;
        }
        return true;
    }

    /**
     * Check if current page is POS terminal
     *
     * @return bool
     */
    private function is_pos_terminal_page() {
        // Check if we're on the POS terminal page
        if (is_admin()) {
            return false;
        }
        
        global $post;
        $pos_page_slug = 'pos-terminal'; // Adjust this if your slug is different
        
        return is_page($pos_page_slug) || 
               (is_page() && $post && has_shortcode($post->post_content, 'superwp_cafe_pos_terminal'));
    }

    public function remove_pos_page_title($title, $id = null) {
        // Check if we're on the POS terminal page
        if (is_page('pos-terminal') && in_the_loop()) {
            return '';
        }
        return $title;
    }

    private function register_ajax_handlers() {
        add_action('wp_ajax_superwpcaf_get_categories', array($this, 'get_categories'));
        add_action('wp_ajax_superwpcaf_get_products', array($this, 'get_products'));
        add_action('wp_ajax_superwpcaf_add_to_cart', array($this, 'add_to_cart'));
        add_action('wp_ajax_superwpcaf_update_cart', array($this, 'update_cart'));
        add_action('wp_ajax_superwpcaf_process_sale', array($this, 'process_sale'));
        add_action('wp_ajax_superwpcaf_clear_cart', array($this, 'clear_cart'));
        add_action('wp_ajax_superwpcaf_search_products', array($this, 'search_products'));
    }

    public function enqueue_pos_assets() {
        if (is_page('pos-terminal')) {
            // Add viewport meta tag
            add_action('wp_head', function() {
                ?>
                <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="mobile-web-app-capable" content="yes">
                <?php
            });
            
            // Enqueue existing styles and scripts
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
            wp_enqueue_style('superwpcaf-pos-style', plugins_url('assets/css/pos-terminal.css', dirname(__FILE__)));
            wp_enqueue_style('superwpcaf-floating-cart', plugins_url('assets/css/floating-cart.css', dirname(__FILE__)));
            
            // Add receipt styles
            wp_enqueue_style('superwpcaf-receipt-style', plugins_url('assets/css/receipt.css', dirname(__FILE__)), [], SUPERWPCAF_VERSION);
            
            // Enqueue scripts
            wp_enqueue_script('superwpcaf-pos-script', plugins_url('assets/js/pos-terminal.js', dirname(__FILE__)), array('jquery'), '1.0.0', true);
            wp_enqueue_script('superwpcaf-receipt-script', plugins_url('assets/js/receipt.js', dirname(__FILE__)), ['jquery'], SUPERWPCAF_VERSION, true);
            
            // Get POS options
            $pos_options = get_option('superwp_cafe_pos_options', array());
            
            wp_localize_script('superwpcaf-pos-script', 'superwpcafPOS', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('superwpcaf_pos_nonce'),
                'auto_print_receipt' => get_option('superwpcaf_auto_print_receipt', 'no'),
                'receipt_options' => array(
                    'printer_type' => isset($pos_options['printer_type']) ? $pos_options['printer_type'] : '80mm',
                    'print_copies' => isset($pos_options['print_copies']) ? intval($pos_options['print_copies']) : 1
                )
            ));
        }
    }

    public function render_pos_terminal() {
        // Remove the login check from here since it's now handled by check_pos_access
        ob_start();
        ?>
        <div class="pos-container">
            <div class="pos-header">
                <div class="pos-controls">
                    <button id="fullscreen-toggle" class="pos-control-button">
                        <i class="fas fa-expand"></i>
                        <span class="control-label"><?php _e('Fullscreen', 'superwp-cafe-pos'); ?></span>
                    </button>
                    <!-- Other controls... -->
                </div>
                <h2><?php _e('POS Terminal', 'superwp-cafe-pos'); ?></h2>
                <div class="pos-search">
                    <input type="text" id="product-search" placeholder="Search products...">
                    <i class="fas fa-search search-icon"></i>
                    <div class="search-suggestions"></div>
                </div>
                <div class="pos-header-actions">
                    <button class="theme-toggle" id="theme-toggle" title="Toggle theme">
                        <i class="fas fa-moon"></i>
                    </button>
                    <?php if (current_user_can('manage_options')): ?>
                    <a href="<?php echo admin_url(); ?>" class="admin-button" title="<?php _e('WP Admin', 'superwp-cafe-pos'); ?>" target="_blank">
                        <i class="fas fa-cog"></i>
                        <span><?php _e('WP Admin', 'superwp-cafe-pos'); ?></span>
                    </a>
                    <?php endif; ?>
                    <div class="cashier-info">
                        <?php 
                        $user = wp_get_current_user();
                        printf(__('Cashier: %s', 'superwp-cafe-pos'), esc_html($user->display_name));
                        ?>
                    </div>
                    <button class="sync-button" title="<?php _e('Sync Products', 'superwp-cafe-pos'); ?>">
                        <i class="fas fa-sync-alt"></i>
                        <span class="screen-reader-text"><?php _e('Sync Products', 'superwp-cafe-pos'); ?></span>
                    </button>
                    <a href="<?php echo wp_logout_url(home_url('/pos-terminal/')); ?>" class="logout-button" title="<?php _e('Logout', 'superwp-cafe-pos'); ?>">
                        <i class="fas fa-sign-out-alt"></i>
                        <span><?php _e('Logout', 'superwp-cafe-pos'); ?></span>
                    </a>
                </div>
            </div>
            
            <div class="pos-main">
                <div class="pos-categories">
                    <!-- Categories will be loaded dynamically -->
                </div>
                
                <div class="pos-products">
                    <div class="products-grid">
                        <!-- Products will be loaded here -->
                    </div>
                </div>
            </div>

            <div class="floating-cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count">0</span>
                <span class="cart-total"><?php echo wc_price(0); ?></span>
            </div>

            <div class="cart-modal">
                <div class="cart-modal-header">
                    <h3><?php _e('Shopping Cart', 'superwp-cafe-pos'); ?></h3>
                    <button class="cart-modal-close">&times;</button>
                </div>
                <div class="cart-modal-content">
                    <!-- Cart items will be loaded here -->
                </div>
                <div class="cart-modal-footer">
                    <div class="payment-methods">
                        <?php echo $this->render_waiter_selection(); ?>
                        
                        <div class="payment-method-selector">
                            <?php
                            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                            
                            foreach ($available_gateways as $gateway) {
                                if ($gateway->enabled === 'yes') {
                                    ?>
                                    <label>
                                        <input type="radio" name="payment_method" value="<?php echo esc_attr($gateway->id); ?>">
                                        <div class="payment-label">
                                            <i class="<?php echo esc_attr($this->get_payment_icon($gateway->id)); ?>"></i>
                                            <?php echo esc_html($gateway->get_title()); ?>
                                        </div>
                                    </label>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        
                        <!-- Add dynamic payment fields container -->
                        <div class="payment-fields-container">
                            <div class="cash-payment-fields" style="display: none;">
                                <div class="amount-field">
                                    <label for="cash-amount"><?php _e('Cash Given', 'superwp-cafe-pos'); ?></label>
                                    <div class="cash-input-wrapper">
                                        <span class="currency-symbol"><?php echo get_woocommerce_currency_symbol(); ?></span>
                                        <input type="number" 
                                               id="cash-amount" 
                                               class="cash-amount-input" 
                                               step="0.01" 
                                               min="0" 
                                               placeholder="0.00"
                                               data-payment-field="cash">
                                    </div>
                                </div>
                                <div class="change-amount">
                                    <span class="change-label"><?php _e('Change Due:', 'superwp-cafe-pos'); ?></span>
                                    <span class="change-value"><?php echo get_woocommerce_currency_symbol(); ?><span>0.00</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cart-total-section">
                        <strong><?php _e('Total:', 'superwp-cafe-pos'); ?></strong>
                        <span class="modal-cart-total"><?php echo wc_price(0); ?></span>
                    </div>

                    <button class="button button-primary checkout-button" disabled>
                        <?php _e('Complete Payment', 'superwp-cafe-pos'); ?>
                    </button>
                </div>
            </div>
            <div class="cart-overlay"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_categories() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');

        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => 0 // Only get top-level categories
        ));

        $html = '<ul class="pos-category-list">';
        // Add "All Products" option
        $html .= '<li class="category-item active" data-category-id="0">All Products</li>';
        
        if (!empty($categories) && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            $html .= sprintf(
                '<li class="category-item" data-category-id="%d">%s</li>',
                $category->term_id,
                esc_html($category->name)
            );
        }
        }
        
        $html .= '</ul>';

        wp_send_json_success($html);
    }

    public function get_products() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');

        $category = isset($_POST['category']) ? absint($_POST['category']) : 0;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $per_page = 15;

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC'
        );

        // Add search query if search term exists
        if (!empty($search)) {
            $args['s'] = $search;
        }

        // Add category filter if category is selected
        if ($category > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category
                )
            );
        }

        $products_query = new WP_Query($args);

        ob_start();
        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if (!$product || !$product->exists()) {
                    continue;
                }
                ?>
                <div class="product-item">
                    <div class="product-image">
                        <?php echo $product->get_image(); ?>
                    </div>
                        <div class="product-details">
                        <h3 class="product-name"><?php echo esc_html($product->get_name()); ?></h3>
                        <div class="product-price"><?php echo $product->get_price_html(); ?></div>
                        <button class="add-to-cart" data-product-id="<?php echo $product->get_id(); ?>">
                            <i class="fas fa-cart-plus"></i> Add
                            </button>
                        </div>
                </div>
                <?php
            }
        } else {
            echo '<div class="no-products">' . __('No products found in this category', 'superwp-cafe-pos') . '</div>';
        }

        $html = ob_get_clean();
        wp_reset_postdata();

        wp_send_json_success(array(
            'html' => $html,
            'current_page' => $page,
            'total_pages' => $products_query->max_num_pages
        ));
    }

    /**
     * Add to cart handler
     */
    public function add_to_cart() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $variation = isset($_POST['variation']) ? (array) $_POST['variation'] : array();

        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product', 'superwp-cafe-pos')));
            return;
        }

            $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found', 'superwp-cafe-pos')));
            return;
            }

            // Add to cart
        $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);

        if ($added) {
            $cart_data = $this->get_cart_data();
            $cart_data['added_item'] = array(
                'product_id' => $product_id,
                'name' => $product->get_name(),
                'price' => $product->get_price_html(),
                'quantity' => $quantity
            );
            wp_send_json_success($cart_data);
        } else {
            wp_send_json_error(array('message' => __('Failed to add product to cart', 'superwp-cafe-pos')));
        }
    }

    /**
     * Get cart items HTML
     * 
     * @param array|null $cart_contents Optional cart contents array
     * @return string HTML output of cart items
     */
    private function get_cart_items_html($cart_contents = null) {
        // If no cart contents provided, get them from WC cart
        if ($cart_contents === null) {
            $cart_items = WC()->cart->get_cart();
            if (empty($cart_items)) {
                return '<div class="cart-empty">' . __('Cart is empty', 'superwp-cafe-pos') . '</div>';
            }

            $html = '';
            foreach ($cart_items as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                $quantity = $cart_item['quantity'];
                $html .= $this->get_cart_item_html($cart_item_key, $product, $quantity);
            }
            return $html;
        }

        // If cart contents provided, use them
        if (empty($cart_contents)) {
            return '<div class="cart-empty">' . __('Cart is empty', 'superwp-cafe-pos') . '</div>';
        }

        $html = '';
        foreach ($cart_contents as $item) {
            $html .= $item['html'];
        }
        return $html;
    }

    /**
     * Get cart data
     */
    private function get_cart_data() {
        $cart_contents = array();

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $cart_contents[] = array(
                'key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'name' => $product->get_name(),
                'price' => $product->get_price_html(),
                'quantity' => $cart_item['quantity'],
                'subtotal' => WC()->cart->get_product_subtotal($product, $cart_item['quantity'])
            );
        }

        return array(
            'items' => $cart_contents,
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'subtotal' => WC()->cart->get_cart_subtotal(),
            'total' => WC()->cart->get_total(),
            'cart_contents' => $this->get_cart_contents_html()
        );
    }

    /**
     * Get HTML for a single cart item
     */
    private function get_cart_item_html($cart_item_key, $product, $quantity) {
        ob_start();
        ?>
        <div class="cart-item" data-key="<?php echo esc_attr($cart_item_key); ?>">
            <div class="cart-item-details">
                <span class="cart-item-name"><?php echo esc_html($product->get_name()); ?></span>
                <div class="cart-item-quantity">
                    <button class="quantity-minus" data-key="<?php echo esc_attr($cart_item_key); ?>">-</button>
                    <input type="number" class="quantity-input" 
                           value="<?php echo esc_attr($quantity); ?>" 
                           min="1" 
                           max="<?php echo esc_attr($product->get_stock_quantity()); ?>"
                           data-key="<?php echo esc_attr($cart_item_key); ?>">
                    <button class="quantity-plus" data-key="<?php echo esc_attr($cart_item_key); ?>">+</button>
                </div>
            </div>
            <div class="cart-item-price">
                <?php echo wc_price($product->get_price() * $quantity); ?>
                <button class="remove-item" data-key="<?php echo esc_attr($cart_item_key); ?>">&times;</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Process the sale and create WooCommerce order
     */
    public function process_sale() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');
        
        $payment_data = isset($_POST['payment_data']) ? $_POST['payment_data'] : array();
        $payment_method = isset($payment_data['payment_method']) ? sanitize_text_field($payment_data['payment_method']) : '';
        
        try {
            // Get the payment gateway
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $gateway = isset($available_gateways[$payment_method]) ? $available_gateways[$payment_method] : null;
            
            if (!$gateway) {
                throw new Exception('Invalid payment method');
            }
            
            // Create the order
            $order = wc_create_order();
            
            // Add cart items to order
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $order->add_product(
                    $cart_item['data'],
                    $cart_item['quantity']
                );
            }
            
            // Calculate totals
            $order->calculate_totals();
            
            // Add cash payment details if applicable
            if ($payment_method === 'cod' && isset($payment_data['payment_details'])) {
                $cash_details = $payment_data['payment_details'];
                $order->update_meta_data('_cash_amount', floatval($cash_details['cash_amount']));
                $order->update_meta_data('_change_amount', floatval($cash_details['change_amount']));
            }
            
            // Process payment
            $result = $gateway->process_payment($order->get_id());
            
            if ($result['result'] === 'success') {
                WC()->cart->empty_cart();
                wp_send_json_success(array(
                    'order_id' => $order->get_id(),
                    'redirect' => $result['redirect'],
                    'payment_details' => $payment_data['payment_details'] ?? null
                ));
            } else {
                throw new Exception('Payment processing failed');
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Update cart item quantity
     */
    public function update_cart() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

        if (!$cart_item_key) {
            wp_send_json_error(array('message' => __('Invalid cart item', 'superwp-cafe-pos')));
        }

        if ($quantity > 0) {
            WC()->cart->set_quantity($cart_item_key, $quantity);
        } else {
            WC()->cart->remove_cart_item($cart_item_key);
        }

        wp_send_json_success($this->get_cart_data());
    }

    public function init_wc_session() {
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
        
        if (!WC()->cart) {
            WC()->cart = new WC_Cart();
        }
    }

    public function search_products() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_success(array());
            return;
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 5, // Limit suggestions
            's' => $search,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $products = new WP_Query($args);
        $suggestions = array();
        
        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product = wc_get_product(get_the_ID());
                
                if (!$product || !$product->exists()) {
                    continue;
                }
                
                $suggestions[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price_html(),
                    'image' => $product->get_image(array(50, 50))
                );
            }
        }
        
        wp_reset_postdata();
        wp_send_json_success($suggestions);
    }

    private function get_waiters() {
        // Get users with only POS waiter role
        $waiters = get_users(array(
            'role' => 'pos_waiter', // Changed to only look for pos_waiter role
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        // No need for additional filtering since we're only getting pos_waiter role
        return $waiters;
    }

    public function render_waiter_selection() {
        $waiters = $this->get_waiters();
        
        ob_start();
        ?>
        <div class="waiter-selection">
            <label for="waiter-select"><?php _e('Select POS Waiter', 'superwp-cafe-pos'); ?></label>
            <select id="waiter-select" required>
                <option value=""><?php _e('-- Select POS Waiter --', 'superwp-cafe-pos'); ?></option>
                <?php
                if (!empty($waiters)) {
                    foreach ($waiters as $waiter) {
                        printf(
                            '<option value="%d">%s</option>',
                            $waiter->ID,
                            esc_html($waiter->display_name)
                        );
                    }
                } else {
                    echo '<option value="" disabled>' . __('No POS waiters available', 'superwp-cafe-pos') . '</option>';
                }
                ?>
            </select>
            <?php if (empty($waiters)): ?>
            <div class="waiter-selection-error">
                <?php _e('Please add users with POS Waiter role first.', 'superwp-cafe-pos'); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function custom_login_styles() {
        $logo_url = get_option('superwpcaf_pos_logo');
        if (empty($logo_url)) {
            $logo_url = plugins_url('assets/images/default-logo.png', dirname(__FILE__));
        }
        
        wp_enqueue_style(
            'superwpcaf-custom-login',
            plugins_url('assets/css/custom-login.css', dirname(__FILE__))
        );
        
        // Add inline CSS for logo
        $custom_css = "
            .login h1 a {
                background-image: url('{$logo_url}') !important;
            }
        ";
        wp_add_inline_style('superwpcaf-custom-login', $custom_css);
    }

    public function custom_login_header_url() {
        return home_url();
    }

    public function custom_login_header_text() {
        $cafe_name = get_bloginfo('name');
        return sprintf('%s - POS System', $cafe_name);
    }

    public function custom_login_redirect($redirect_to, $requested_redirect_to, $user) {
        // Check if there's a specific redirect request
        if (!empty($requested_redirect_to)) {
            return $requested_redirect_to;
        }

        // Check if user has POS roles
        if (is_a($user, 'WP_User')) {
            $allowed_roles = array('administrator', 'pos_manager', 'pos_cashier', 'pos_waiter');
            if (array_intersect($allowed_roles, $user->roles)) {
                // Redirect to POS terminal page
                return home_url('/pos-terminal/');
            }
        }

        // Default redirect
        return $redirect_to;
    }

    public function custom_login_message($message) {
        if (isset($_GET['redirect_to']) && strpos($_GET['redirect_to'], 'pos-terminal') !== false) {
            $cafe_name = get_bloginfo('name');
            $message = sprintf(
                '<div class="cafe-brand"><h2>%s</h2><p>POS Login</p></div>', 
                esc_html($cafe_name)
            );
        }
        return $message;
    }

    public function check_pos_access() {
        // Check if we're on the POS terminal page
        if (is_page('pos-terminal')) {
            if (!is_user_logged_in()) {
                // Get the current page URL
                $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                
                // Redirect to login page with the current page as redirect_to
                wp_safe_redirect(wp_login_url($current_url));
                exit;
            }

            // Check if user has required roles
            $user = wp_get_current_user();
            $allowed_roles = array('administrator', 'pos_manager', 'pos_cashier', 'pos_waiter');
            if (!array_intersect($allowed_roles, $user->roles)) {
                wp_die(__('You do not have permission to access the POS terminal.', 'superwp-cafe-pos'));
            }
        }
    }

    public function custom_logout_redirect($redirect_to, $requested_redirect_to, $user) {
        // Check if logging out from POS terminal
        if (is_page('pos-terminal')) {
            return wp_login_url(home_url('/pos-terminal/'));
        }
        return $redirect_to;
    }

    public function add_login_particles() {
        ?>
        <div class="login-particles">
            <?php for ($i = 0; $i < 20; $i++): ?>
                <div class="particle"></div>
            <?php endfor; ?>
        </div>
        <?php
    }

    public function register_pos_settings() {
        register_setting('superwpcaf_pos_settings', 'superwpcaf_pos_logo');
    }

    public function pos_logo_field() {
        $logo_url = get_option('superwpcaf_pos_logo');
        $default_logo = plugins_url('assets/images/default-logo.png', dirname(__FILE__));
        ?>
        <div class="pos-logo-upload">
            <input type="hidden" name="superwpcaf_pos_logo" id="pos_logo_url" 
                   value="<?php echo esc_attr($logo_url); ?>">
            
            <div class="logo-preview-container">
                <label><?php _e('Current Logo', 'superwp-cafe-pos'); ?></label>
                <div class="logo-preview">
                    <img src="<?php echo !empty($logo_url) ? esc_url($logo_url) : esc_url($default_logo); ?>" 
                         id="pos_logo_preview" 
                         alt="POS Logo Preview">
                </div>
            </div>

            <div class="logo-actions">
                <button type="button" class="button button-primary" id="select_pos_logo">
                    <i class="dashicons dashicons-format-image"></i>
                    <?php _e('Select from Media Library', 'superwp-cafe-pos'); ?>
                </button>
                
                <button type="button" class="button <?php echo empty($logo_url) ? 'hidden' : ''; ?>" 
                        id="remove_pos_logo" 
                        data-default-logo="<?php echo esc_url($default_logo); ?>">
                    <i class="dashicons dashicons-trash"></i>
                    <?php _e('Remove Logo', 'superwp-cafe-pos'); ?>
                </button>
            </div>

            <p class="description">
                <?php _e('Select a logo from your Media Library. Recommended size: 200x80px.', 'superwp-cafe-pos'); ?>
            </p>
        </div>
        <?php
    }

    public function add_system_settings_tab($tabs) {
        $tabs['system_settings'] = array(
            'title' => __('System Settings', 'superwp-cafe-pos'),
            'icon' => 'fas fa-cogs',
            'priority' => 15 // This will place it after General and POS Roles
        );
        return $tabs;
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_superwp-cafe-pos' === $hook) {
            wp_enqueue_media();
            wp_enqueue_script(
                'superwpcaf-admin-settings',
                plugins_url('assets/js/admin-settings.js', dirname(__FILE__)),
                array('jquery'),
                SUPERWPCAF_VERSION,
                true
            );
        }
    }

    public function render_system_settings_content($current_tab) {
        if ($current_tab !== 'system_settings') {
            return;
        }
        
        // Save settings if form is submitted
        if (isset($_POST['save_pos_settings'])) {
            check_admin_referer('superwpcaf_pos_settings');
            
            $logo_url = sanitize_text_field($_POST['superwpcaf_pos_logo']);
            update_option('superwpcaf_pos_logo', $logo_url);
            
            echo '<div class="updated"><p>' . __('Settings saved successfully!', 'superwp-cafe-pos') . '</p></div>';
        }
        
        ?>
        <div class="superwpcaf-settings-content">
            <form method="post" action="" id="pos-settings-form">
                <?php wp_nonce_field('superwpcaf_pos_settings'); ?>
                
                <div class="settings-section">
                    <h3><?php _e('POS System Branding', 'superwp-cafe-pos'); ?></h3>
                    <?php $this->pos_logo_field(); ?>
                </div>
                
                <div class="settings-footer">
                    <button type="submit" name="save_pos_settings" class="button button-primary">
                        <?php _e('Save Settings', 'superwp-cafe-pos'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    public function sync_products() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');

        try {
            // Clear transients/cache
            delete_transient('superwpcaf_products_cache');
            
            // Force WooCommerce to refresh its product cache
            wc_delete_product_transients();
            
            // Clear any custom product caches
            do_action('superwpcaf_clear_product_cache');
            
            wp_send_json_success(array(
                'message' => __('Products synchronized successfully', 'superwp-cafe-pos')
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    // Add helper method for payment icons
    private function get_payment_icon($gateway_id) {
        $icons = array(
            'cod' => 'fas fa-money-bill-wave',
            'bacs' => 'fas fa-university',
            'cheque' => 'fas fa-money-check',
            'paypal' => 'fab fa-paypal',
            'stripe' => 'fab fa-cc-stripe',
            'mpesa' => 'fas fa-mobile-alt',
        );
        
        return isset($icons[$gateway_id]) ? $icons[$gateway_id] : 'fas fa-credit-card';
    }

    public function get_payment_fields() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');
        
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        $gateway = WC()->payment_gateways()->get_available_payment_gateways()[$payment_method] ?? null;
        
        if (!$gateway) {
            wp_send_json_error(array('message' => 'Invalid payment method'));
        }
        
        ob_start();
        $gateway->payment_fields();
        $fields = ob_get_clean();
        
        wp_send_json_success(array(
            'fields' => $fields
        ));
    }

    /**
     * Generate receipt HTML
     */
    public function get_receipt() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');
        
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
            return;
        }

        // Prepare receipt data
        $data = [
            'order_id' => $order_id,
            'cashier_name' => $order->get_meta('_pos_cashier_name'),
            'items' => [],
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'total' => $order->get_total(),
            'payment_method' => $order->get_payment_method(),
            'cash_given' => $order->get_meta('_pos_cash_given'),
            'change' => $order->get_meta('_pos_change_amount')
        ];

        // Get items
        foreach ($order->get_items() as $item) {
            $data['items'][] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_subtotal() / $item->get_quantity(),
                'total' => $item->get_subtotal()
            ];
        }

        // Generate receipt HTML
        ob_start();
        include SUPERWPCAF_PLUGIN_DIR . 'templates/receipt-template.php';
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html
        ]);
    }

    private function get_pos_settings() {
        $wc_settings = array(
            'currency' => get_woocommerce_currency(),
            'currency_position' => get_option('woocommerce_currency_pos'),
            'tax_enabled' => wc_tax_enabled(),
            'tax_rates' => WC_Tax::get_rates(),
            'manage_stock' => get_option('woocommerce_manage_stock'),
            'low_stock_amount' => get_option('woocommerce_notify_low_stock_amount')
        );
        
        $pos_options = get_option('superwp_cafe_pos_options', array());
        
        // Only include receipt settings
        $filtered_options = array_intersect_key($pos_options, array_flip(['receipt_header', 'receipt_footer', 'receipt_logo']));
        
        return array_merge($wc_settings, $filtered_options);
    }

    public function enqueue_pos_scripts() {
        wp_enqueue_script('superwpcaf-pos-terminal', SUPERWPCAF_PLUGIN_URL . 'assets/js/pos-terminal.js', array('jquery'), SUPERWPCAF_VERSION, true);
        
        wp_localize_script('superwpcaf-pos-terminal', 'superwpcafPOS', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('superwpcaf_pos_nonce'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals' => wc_get_price_decimals(),
            'i18n' => array(
                'add_to_cart' => __('Add to Cart', 'superwp-cafe-pos'),
                'added_to_cart' => __('Added to Cart', 'superwp-cafe-pos'),
                'error_adding' => __('Error adding to cart', 'superwp-cafe-pos')
            )
        ));
    }

    public function render_product_item($product) {
        ?>
        <div class="product-item" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
            <div class="product-image">
                <?php echo $product->get_image('thumbnail'); ?>
            </div>
            <div class="product-details">
                <h4 class="product-name"><?php echo esc_html($product->get_name()); ?></h4>
                <div class="product-price"><?php echo wc_price($product->get_price()); ?></div>
                <button type="button" class="add-to-cart-btn button">
                    <span class="dashicons dashicons-plus"></span>
                    <?php _e('Add', 'superwp-cafe-pos'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    public function add_ajax_handlers() {
        add_action('wp_ajax_superwpcaf_add_to_cart', array($this, 'ajax_add_to_cart'));
    }

    public function ajax_add_to_cart() {
        check_ajax_referer('superwpcaf_pos_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product', 'superwp-cafe-pos')));
        }
        
        // Add to cart
        $added = WC()->cart->add_to_cart($product_id, 1);
        
        if ($added) {
            // Get updated cart data
            $cart_data = array(
                'items' => array(),
                'subtotal' => WC()->cart->get_subtotal(),
                'tax' => WC()->cart->get_tax_total(),
                'total' => WC()->cart->get_total()
            );
            
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                $cart_data['items'][] = array(
                    'key' => $cart_item_key,
                    'name' => $product->get_name(),
                    'quantity' => $cart_item['quantity'],
                    'price' => wc_price($product->get_price() * $cart_item['quantity'])
                );
            }
            
            wp_send_json_success($cart_data);
        } else {
            wp_send_json_error(array('message' => __('Failed to add item to cart', 'superwp-cafe-pos')));
        }
    }
}

endif;
