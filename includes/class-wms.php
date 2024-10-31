<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://orionorigin.com
 * @since      1.0.0
 *
 * @package    Wms
 * @subpackage Wms/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wms
 * @subpackage Wms/includes
 * @author     Orion <support@orionorigin.com>
 */
class Wms {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wms_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'WMS_VERSION' ) ) {
			$this->version = WMS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'wms';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wms_Loader. Orchestrates the hooks of the plugin.
	 * - Wms_i18n. Defines internationalization functionality.
	 * - Wms_Admin. Defines all hooks for the admin area.
	 * - Wms_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wms-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wms-sqlite-db-reader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wms-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wms-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wms-public.php';

		$this->loader = new Wms_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wms_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wms_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Wms_Admin( $this->get_plugin_name(), $this->get_version() );
		$endpoints    = new WMS_Endpoints( false );
		$dump_obj     = new WMS_Dumper( false, false );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'init', $plugin_admin, 'set_website_post_type' );
		$this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'get_settings_metabox' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_wms_menu' );
		$this->loader->add_action( 'manage_wms-sites_posts_custom_column', $plugin_admin, 'add_columns_value', 5, 2 );
		$this->loader->add_action( 'wp_ajax_generate-wms-key', $plugin_admin, 'generate_wms_key' );
		$this->loader->add_action( 'save_post_wms-sites', $plugin_admin, 'edit_websites' );
		$this->loader->add_action( 'rest_api_init', $endpoints, 'create_custom_route' );
		$this->loader->add_action( 'wp_ajax_test_connection', $plugin_admin, 'test_connection' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'check_requirement' );
		$this->loader->add_action( 'wp_ajax_start_wms_sync', $plugin_admin, 'start_sync' );
		$this->loader->add_filter( 'manage_edit-wms-sites_columns', $plugin_admin, 'manage_sites_columns' );
		$this->loader->add_action( 'wp_ajax_check_if_dump_is_completed', $dump_obj, 'check_if_dump_is_completed' );
		$this->loader->add_action( 'wp_ajax_check_if_remote_dump_is_completed', $dump_obj, 'check_if_dump_is_completed' );
		$this->loader->add_action( 'wp_ajax_nopriv_check_if_remote_dump_is_completed', $dump_obj, 'check_if_dump_is_completed' );
		$this->loader->add_action( 'wms_schedule_dump_with_php', $dump_obj, 'schedule_dump_table_data_with_php', 10, 3 );
		$this->loader->add_action( 'wp_ajax_send_dump_to_kpax', $dump_obj, 'send_dump_to_kpax' );
		$this->loader->add_action( 'wp_ajax_get-decode-results', $plugin_admin, 'display_dump_results' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wms_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
