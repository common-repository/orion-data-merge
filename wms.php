<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://orionorigin.com
 * @since             1.0.0
 * @package           Wms
 *
 * @wordpress-plugin
 * Plugin Name:       Orion Data Merge
 * Plugin URI:        https://wpdatamerge.com/
 * Description:       The ideal companion to merge and synchronize 2 WordPress websites
 * Version:           1.0.0
 * Author:            Orion
 * Author URI:        https://orionorigin.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wms
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WMS_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wms-activator.php
 */
function activate_wms() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wms-activator.php';
	Wms_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wms-deactivator.php
 */
function deactivate_wms() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wms-deactivator.php';
	Wms_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wms' );
register_deactivation_hook( __FILE__, 'deactivate_wms' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once ABSPATH . 'wp-admin/includes/file.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-wms.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-wms-endpoints.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-wms-dumper.php';
if ( ! function_exists( 'o_admin_fields' ) ) {
	require plugin_dir_path( __FILE__ ) . 'includes/utils.php';
}
if ( ! class_exists( 'Ifsnop\Mysqldump\Mysqldump' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/mysqldump.php';
}
require plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-wms-website.php';

define( 'WMS_ALLOWED_TAGS', wms_get_allowed_tags() );

define( 'WMS_LOCAL_SITE_ARCHIVE_PARAM_KEY', 'local_site_archive_url' );
define( 'WMS_REMOTE_SITE_ARCHIVE_PARAM_KEY', 'remote_site_archive_url' );
define( 'WMS_LOCAL_SITE_URL_PARAM_KEY', 'local_site_url' );
define( 'WMS_REMOTE_SITE_URL_PARAM_KEY', 'remote_site_url' );
define( 'WMS_LOCAL_SITE_TABLE_PREFIX_PARAM_KEY', 'local_site_table_prefix' );
define( 'WMS_REMOTE_SITE_TABLE_PREFIX_PARAM_KEY', 'remote_site_table_prefix' );
define( 'WMS_KPAX_COMPARISON_ID_PARAM_KEY', 'kpax_comparison_id' );
define( 'WMS_LICENSE_KEY_PARAM_KEY', 'license_key' );
define( 'WMS_ENCRYPTION_KEY_PARAM_KEY', 'encryption_key' );
define( 'WMS_DB_RESULT_PATH_PARAM_KEY', 'db_result_path' );
define( 'WMS_VIRTUAL_PREFIX', 'virtual_prefix' );

define( 'WMS_DATA_MERGE_DIR_BY_URL', wp_get_upload_dir()['baseurl'] . '/WP-DATA-MERGE/' );

define( 'WMS_DATA_MERGE_DIR', wp_get_upload_dir()['basedir'] . '/WP-DATA-MERGE/' );
define( 'WMS_TOTAL_CEL', 500000 );
define( 'WMS_LOG_FOLDER', WMS_DATA_MERGE_DIR . 'logs/' );

define( 'WMS_MAX_DUMP_SIZE', 5000 );

define( 'WMS_PHP_MAX_EXECUTION_TIME', ini_get( 'max_execution_time' ) );

/**
* Define the number of blocks that should be read from the source file for each chunk.
* For 'AES-128-CBC' each block consist of 16 bytes.
* So if we read 10,000 blocks we load 160kb into memory.
*/
define( 'WMS_FILE_ENCRYPTION_BLOCKS', 10000 );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wms() {

	$plugin = new Wms();
	$plugin->run();

}
run_wms();
