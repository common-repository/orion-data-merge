<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://orionorigin.com
 * @since      1.0.0
 *
 * @package    Wms
 * @subpackage Wms/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Wms
 * @subpackage Wms/includes
 * @author     Orion <support@orionorigin.com>
 */
class Wms_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		wp_unschedule_hook( 'wms_schedule_dump_with_php' );
	}

}
