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

		$admin_bar->add_menu( array(
			'id'		=> 'superwp-cafe-pos-id', // The ID of the node.
			'title'		=> __( 'SupwerWP POS', 'superwp-cafe-pos' ), // The text that will be visible in the Toolbar. Including html tags is allowed.
			'parent'	=> false, // The ID of the parent node.
			'href'		=> '#', // The ‘href’ attribute for the link. If ‘href’ is not set the node will be a text node.
			'group'		=> false, // This will make the node a group (node) if set to ‘true’. Group nodes are not visible in the Toolbar, but nodes added to it are.
			'meta'		=> array(
				'title'		=> __( 'SupwerWP POS', 'superwp-cafe-pos' ), // The title attribute. Will be set to the link or to a div containing a text node.
				'target'	=> '_blank', // The target attribute for the link. This will only be set if the ‘href’ argument is present.
				'class'		=> 'superwp-cafe-pos-class', // The class attribute for the list item containing the link or text node.
				'html'		=> false, // The html used for the node.
				'rel'		=> false, // The rel attribute.
				'onclick'	=> false, // The onclick attribute for the link. This will only be set if the ‘href’ argument is present.
				'tabindex'	=> false, // The tabindex attribute. Will be set to the link or to a div containing a text node.
			),
		));

		$admin_bar->add_menu( array(
			'id'		=> 'superwp-cafe-pos-sub-id',
			'title'		=> __( 'POS', 'superwp-cafe-pos' ),
			'parent'	=> 'superwp-cafe-pos-id',
			'href'		=> '#',
			'group'		=> false,
			'meta'		=> array(
				'title'		=> __( 'POS', 'superwp-cafe-pos' ),
				'target'	=> '_blank',
				'class'		=> 'superwp-cafe-pos-sub-class',
				'html'		=> false,    
				'rel'		=> false,
				'onclick'	=> false,
				'tabindex'	=> false,
			),
		));

	}

}
