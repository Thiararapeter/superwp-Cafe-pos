<?php
/**
 * Page Manager Class
 * 
 * Handles creation and management of plugin-specific pages
 */

if (!class_exists('Superwp_Cafe_Pos_Page_Manager')) :

class Superwp_Cafe_Pos_Page_Manager {
    
    private static $instance = null;

    /**
     * Get class instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create the POS page
     *
     * @return int|WP_Error The page ID on success, WP_Error on failure.
     */
    public function create_pos_page() {
        // Check if POS page already exists
        $existing_page = get_page_by_path('pos-terminal');
        
        if ($existing_page) {
            return $existing_page->ID;
        }
        
        // Create POS page
        $page_data = array(
            'post_title'    => 'POS Terminal',
            'post_name'     => 'pos-terminal',
            'post_content'  => '[superwpcaf_pos_terminal]', // Shortcode for POS terminal
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => get_current_user_id(),
        );
        
        $page_id = wp_insert_post($page_data);
        
        if (!is_wp_error($page_id)) {
            // Store the page ID in options for future reference
            update_option('superwpcaf_pos_page_id', $page_id);
        }
        
        return $page_id;
    }
    
    /**
     * Get the POS page URL
     *
     * @return string|false The POS page URL or false if not found
     */
    public function get_pos_page_url() {
        $page_id = get_option('superwpcaf_pos_page_id');
        if ($page_id) {
            return get_permalink($page_id);
        }
        return false;
    }
}

endif;
