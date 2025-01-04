<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class Superwp_Cafe_Pos_Settings
 *
 * This class contains all of the plugin settings.
 * Here you can configure the whole plugin data.
 *
 * @package		SUPERWPCAF
 * @subpackage	Classes/Superwp_Cafe_Pos_Settings
 * @author		thiarara
 * @since		1.0.01
 */
class Superwp_Cafe_Pos_Settings{

	/**
	 * The plugin name
	 *
	 * @var		string
	 * @since   1.0.01
	 */
	private $plugin_name;

	/**
	 * Our Superwp_Cafe_Pos_Settings constructor 
	 * to run the plugin logic.
	 *
	 * @since 1.0.01
	 */
	function __construct(){

		$this->plugin_name = SUPERWPCAF_NAME;
	}

	/**
	 * ######################
	 * ###
	 * #### CALLABLE FUNCTIONS
	 * ###
	 * ######################
	 */

	/**
	 * Return the plugin name
	 *
	 * @access	public
	 * @since	1.0.01
	 * @return	string The plugin name
	 */
	public function get_plugin_name(){
		return apply_filters( 'SUPERWPCAF/settings/get_plugin_name', $this->plugin_name );
	}
}
