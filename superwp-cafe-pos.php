<?php
/**
 * SuperWp Cafe POS
 *
 * @package       SUPERWPCAF
 * @author        thiarara
 * @license       gplv2-or-later
 * @version       1.0.01
 *
 * @wordpress-plugin
 * Plugin Name:   SuperWp Cafe POS
 * Plugin URI:    https://mydomain.com
 * Description:   cafe system
 * Version:       1.0.01
 * Author:        thiarara
 * Author URI:    thiarara.co.ke
 * Text Domain:   superwp-cafe-pos
 * Domain Path:   /languages
 * License:       GPLv2 or later
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with SuperWp Cafe POS. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants - only if not already defined
if (!defined('SUPERWPCAF_NAME')) {
    define('SUPERWPCAF_NAME', 'SuperWp Cafe POS');
}

if (!defined('SUPERWPCAF_VERSION')) {
    define('SUPERWPCAF_VERSION', '1.0.01');
}

if (!defined('SUPERWPCAF_PLUGIN_FILE')) {
    define('SUPERWPCAF_PLUGIN_FILE', __FILE__);
}

if (!defined('SUPERWPCAF_PLUGIN_BASE')) {
    define('SUPERWPCAF_PLUGIN_BASE', plugin_basename(__FILE__));
}

if (!defined('SUPERWPCAF_PLUGIN_DIR')) {
    define('SUPERWPCAF_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('SUPERWPCAF_PLUGIN_URL')) {
    define('SUPERWPCAF_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Remove custom link and manage plugin actions
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
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
}, 99);

// Check if WooCommerce is active
function superwpcaf_check_woocommerce() {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    
    $woocommerce_path = 'woocommerce/woocommerce.php';
    
    if (!is_plugin_active($woocommerce_path)) {
        add_action('admin_notices', 'superwpcaf_woocommerce_notice');
        return false;
    }
    return true;
}

// Admin notice for WooCommerce requirement
function superwpcaf_woocommerce_notice() {
    $screen = get_current_screen();
    
    if (isset($screen->parent_file) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id) {
        return;
    }
    
    if (!is_plugin_installed('woocommerce/woocommerce.php')) {
        $install_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'install-plugin',
                    'plugin' => 'woocommerce'
                ),
                admin_url('update.php')
            ),
            'install-plugin_woocommerce'
        );
        $message = sprintf(
            __('SuperWp Cafe POS requires WooCommerce to be installed and activated. %s', 'superwp-cafe-pos'),
            '<a href="' . esc_url($install_url) . '" class="button button-primary">' . __('Install WooCommerce', 'superwp-cafe-pos') . '</a>'
        );
    } else {
        $activation_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'activate',
                    'plugin' => 'woocommerce/woocommerce.php'
                ),
                admin_url('plugins.php')
            ),
            'activate-plugin_woocommerce/woocommerce.php'
        );
        $message = sprintf(
            __('SuperWp Cafe POS requires WooCommerce to be activated. %s', 'superwp-cafe-pos'),
            '<a href="' . esc_url($activation_url) . '" class="button button-primary">' . __('Activate WooCommerce', 'superwp-cafe-pos') . '</a>'
        );
    }
    ?>
    <div class="notice notice-error">
        <p><?php echo wp_kses_post($message); ?></p>
    </div>
    <?php
}

/**
 * Check if a plugin is installed
 */
function is_plugin_installed($plugin_path) {
    $plugins = get_plugins();
    return isset($plugins[$plugin_path]);
}

/**
 * Load the main class for the core functionality
 */
if (superwpcaf_check_woocommerce()) {
    // Load core classes
    require_once SUPERWPCAF_PLUGIN_DIR . 'core/class-superwp-cafe-pos.php';
    require_once SUPERWPCAF_PLUGIN_DIR . 'core/class-page-manager.php';
    require_once SUPERWPCAF_PLUGIN_DIR . 'core/class-pos-terminal.php';
    require_once SUPERWPCAF_PLUGIN_DIR . 'core/class-pos-roles.php';
    require_once SUPERWPCAF_PLUGIN_DIR . 'core/class-user-fields.php';

    // Initialize roles manager first
    $roles_manager = Superwp_Cafe_Pos_Roles::instance();

    // Initialize page manager
    $page_manager = Superwp_Cafe_Pos_Page_Manager::instance();

    // Initialize POS terminal
    $pos_terminal = Superwp_Cafe_Pos_Terminal::instance();

    // Initialize WooCommerce session and cart
    add_action('init', function() {
        if (!is_admin() || defined('DOING_AJAX')) {
            // Include frontend dependencies
            WC()->frontend_includes();
            
            // Initialize session if not set
            if (!WC()->session) {
                $session_handler = new WC_Session_Handler();
                $session_handler->init();
                WC()->session = $session_handler;
            }
            
            // Initialize customer
            if (!WC()->customer) {
                WC()->customer = new WC_Customer(get_current_user_id(), true);
            }
            
            // Initialize cart if not set
            if (!WC()->cart) {
                WC()->cart = new WC_Cart();
            }
        }
    }, 5);

    // Add shortcode for POS launch button
    add_shortcode('superwpcaf_pos_button', function() {
        $page_manager = Superwp_Cafe_Pos_Page_Manager::instance();
        $pos_url = $page_manager->get_pos_page_url();
        
        if (!$pos_url) {
            return 'POS Terminal page not found.';
        }
        
        return sprintf(
            '<a href="%s" class="button button-primary">Launch POS Terminal</a>',
            esc_url($pos_url)
        );
    });

    // Initialize main plugin class
    function SUPERWPCAF() {
        return Superwp_Cafe_Pos::instance();
    }

    SUPERWPCAF();

    // Register activation hook
    register_activation_hook(__FILE__, function() {
        // Create roles and capabilities
        $roles_manager = Superwp_Cafe_Pos_Roles::instance();
        
        // Load saved custom roles
        $saved_roles = get_option('superwp_cafe_pos_roles', array());
        
        // Register default and custom roles
        $roles_manager->register_pos_roles();
        
        // Create POS page
        $page_manager = Superwp_Cafe_Pos_Page_Manager::instance();
        $page_manager->create_pos_page();
        
        // Flush rewrite rules
        flush_rewrite_rules();

        // In your activation function
        $default_options = array(
            'receipt_header' => '',
            'receipt_footer' => '',
            'auto_print_receipt' => 'no',
            'printer_type' => '80mm',
            'print_copies' => 1
        );

        if (!get_option('superwp_cafe_pos_options')) {
            add_option('superwp_cafe_pos_options', $default_options);
        }
    });
}

// Add this near the top of the file after the plugin header
register_activation_hook(__FILE__, 'superwpcaf_activate_plugin');

function superwpcaf_activate_plugin() {
    // Clear the permalinks
    flush_rewrite_rules();
    
    // Register POS roles
    require_once plugin_dir_path(__FILE__) . 'core/class-roles-manager.php';
    $roles_manager = Superwp_Cafe_Pos_Roles_Manager::instance();
    $roles_manager->register_pos_roles();
    
    // Create POS terminal page if it doesn't exist
    require_once plugin_dir_path(__FILE__) . 'core/class-page-manager.php';
    $page_manager = Superwp_Cafe_Pos_Page_Manager::instance();
    $page_manager->create_pos_page();
    
    // Set default options
    add_option('superwpcaf_pos_initialized', true);
}

// Add a deactivation hook to clean up if needed
register_deactivation_hook(__FILE__, 'superwpcaf_deactivate_plugin');

function superwpcaf_deactivate_plugin() {
    // Optionally remove roles on deactivation
    // Be careful with this as it will remove access for existing users
    /*
    remove_role('pos_manager');
    remove_role('pos_cashier');
    */
    
    // Clear the permalinks
    flush_rewrite_rules();
}
