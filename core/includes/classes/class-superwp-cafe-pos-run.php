<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class Superwp_Cafe_Pos_Run
 *
 * Thats where we bring the plugin to life
 *
 * @package		SUPERWPCAF
 * @subpackage	Classes/Superwp_Cafe_Pos_Run
 * @author		thiarara
 * @since		1.0.01
 */
class Superwp_Cafe_Pos_Run{

	/**
	 * Our Superwp_Cafe_Pos_Run constructor 
	 * to run the plugin logic.
	 *
	 * @since 1.0.01
	 */
	function __construct(){
		$this->add_hooks();
	}

	/**
	 * ######################
	 * ###
	 * #### WORDPRESS HOOKS
	 * ###
	 * ######################
	 */

	/**
	 * Registers all WordPress and plugin related hooks
	 *
	 * @access	private
	 * @since	1.0.01
	 * @return	void
	 */
	private function add_hooks(){
	
		add_action( 'plugin_action_links_' . SUPERWPCAF_PLUGIN_BASE, array( $this, 'add_plugin_action_link' ), 20 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu_items' ), 100, 1 );
	
	}

	/**
	 * ######################
	 * ###
	 * #### WORDPRESS HOOK CALLBACKS
	 * ###
	 * ######################
	 */

	/**
	* Adds action links to the plugin list table
	*
	* @access	public
	* @since	1.0.01
	*
	* @param	array	$links An array of plugin action links.
	*
	* @return	array	An array of plugin action links.
	*/
	public function add_plugin_action_link( $links ) {

		$links['our_shop'] = sprintf( '<a href="%s" title="Custom Link" style="font-weight:700;">%s</a>', 'https://test.test', __( 'Custom Link', 'superwp-cafe-pos' ) );

		return $links;
	}

	/**
	 * Add a new menu item to the WordPress topbar
	 *
	 * @access	public
	 * @since	1.0.01
	 *
	 * @param	object $admin_bar The WP_Admin_Bar object
	 *
	 * @return	void
	 */
	public function add_admin_bar_menu_items( $admin_bar ) {
		// Add the main menu item
		$admin_bar->add_menu(array(
			'id'    => 'superwp-cafe-pos',
			'title' => __('SuperWP POS', 'superwp-cafe-pos'),
			'href'  => admin_url('admin.php?page=superwp-cafe-pos-settings'),
		));

		// Add Open POS submenu (renamed from POS Terminal)
		$admin_bar->add_menu(array(
			'id'     => 'superwp-cafe-pos-terminal',
			'parent' => 'superwp-cafe-pos',
			'title'  => __('Open POS', 'superwp-cafe-pos'),
			'href'   => home_url('/pos-terminal/'),
			'meta'   => array(
				'target' => '_blank',
				'class'  => 'superwp-cafe-pos-terminal'
			)
		));

		// Add Settings submenu
		$admin_bar->add_menu(array(
			'id'     => 'superwp-cafe-pos-settings',
			'parent' => 'superwp-cafe-pos',
			'title'  => __('POS Settings', 'superwp-cafe-pos'),
			'href'   => admin_url('admin.php?page=superwp-cafe-pos-settings'),
		));
	}

}
