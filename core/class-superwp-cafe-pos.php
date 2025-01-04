<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'Superwp_Cafe_Pos' ) ) :

	/**
	 * Main Superwp_Cafe_Pos Class.
	 *
	 * @package		SUPERWPCAF
	 * @subpackage	Classes/Superwp_Cafe_Pos
	 * @since		1.0.01
	 * @author		thiarara
	 */
	final class Superwp_Cafe_Pos {

		/**
		 * The real instance
		 *
		 * @access	private
		 * @since	1.0.01
		 * @var		object|Superwp_Cafe_Pos
		 */
		private static $instance;

		/**
		 * SUPERWPCAF helpers object.
		 *
		 * @access	public
		 * @since	1.0.01
		 * @var		object|Superwp_Cafe_Pos_Helpers
		 */
		public $helpers;

		/**
		 * SUPERWPCAF settings object.
		 *
		 * @access	public
		 * @since	1.0.01
		 * @var		object|Superwp_Cafe_Pos_Settings
		 */
		public $settings;

        /**
         * SUPERWPCAF admin menu object.
         *
         * @access  public
         * @since   1.0.01
         * @var     object|Superwp_Cafe_Pos_Admin_Menu
         */
        public $admin_menu;

		/**
		 * Throw error on object clone.
		 *
		 * Cloning instances of the class is forbidden.
		 *
		 * @access	public
		 * @since	1.0.01
		 * @return	void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'You are not allowed to clone this class.', 'superwp-cafe-pos' ), '1.0.01' );
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @access	public
		 * @since	1.0.01
		 * @return	void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'You are not allowed to unserialize this class.', 'superwp-cafe-pos' ), '1.0.01' );
		}

		/**
		 * Main Superwp_Cafe_Pos Instance.
		 *
		 * Insures that only one instance of Superwp_Cafe_Pos exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @access		public
		 * @since		1.0.01
		 * @static
		 * @return		object|Superwp_Cafe_Pos	The one true Superwp_Cafe_Pos
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Superwp_Cafe_Pos ) ) {
				self::$instance					= new Superwp_Cafe_Pos;
				self::$instance->base_hooks();
				self::$instance->includes();
				self::$instance->helpers		= new Superwp_Cafe_Pos_Helpers();
				self::$instance->settings		= new Superwp_Cafe_Pos_Settings();

				//Fire the plugin logic
				new Superwp_Cafe_Pos_Run();

                // Initialize admin menu
                if (is_admin()) {
                    require_once SUPERWPCAF_PLUGIN_DIR . 'core/admin/class-admin-menu.php';
                    self::$instance->admin_menu = Superwp_Cafe_Pos_Admin_Menu::instance();
                }

				/**
				 * Fire a custom action to allow dependencies
				 * after the successful plugin setup
				 */
				do_action( 'SUPERWPCAF/plugin_loaded' );
			}

			return self::$instance;
		}

		/**
		 * Include required files.
		 *
		 * @access  private
		 * @since   1.0.01
		 * @return  void
		 */
		private function includes() {
			require_once SUPERWPCAF_PLUGIN_DIR . 'core/includes/classes/class-superwp-cafe-pos-helpers.php';
			require_once SUPERWPCAF_PLUGIN_DIR . 'core/includes/classes/class-superwp-cafe-pos-settings.php';

			require_once SUPERWPCAF_PLUGIN_DIR . 'core/includes/classes/class-superwp-cafe-pos-run.php';
		}

		/**
		 * Add base hooks for the core functionality
		 *
		 * @access  private
		 * @since   1.0.01
		 * @return  void
		 */
		private function base_hooks() {
			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );
		}

		/**
		 * Loads the plugin language files.
		 *
		 * @access  public
		 * @since   1.0.01
		 * @return  void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'superwp-cafe-pos', FALSE, dirname( plugin_basename( SUPERWPCAF_PLUGIN_FILE ) ) . '/languages/' );
		}

	}

endif; // End if class_exists check.