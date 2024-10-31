<?php
/**
 * Class used to create endpoints for rest api.
 *
 * @package wms
 */

/**
 * Description of class-wms-endpoints  Class used to create endpoints for rest api
 *
 * @author Orion
 */
class WMS_Endpoints {
	/**
	 * Create_custom_route function
	 *
	 * @return void
	 */
	public function create_custom_route() {
		register_rest_route(
			'wms/v2',
			'/check_key/(?P<key>[/\w-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_key' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'wms/v2',
			'/get_remote_site_db_zip/(?P<key>[/\w-]+)/(?P<dump_folder_name>[/\w-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_db_zip' ),
				'permission_callback' => '__return_true',
			)
		);
	}
	/**
	 * Check the conformity of the keys
	 *
	 * @param [type] $data comment request data.
	 * @return string
	 */
	public function check_key( $data ) {
		$key     = $data['key'];
		$get_key = get_option( 'wms-site-key' );
		if ( $key === $get_key ) {
			$message = array( true, __( 'Successful connection.', 'wms' ) );
		} else {
			$message = array( false, __( 'Failed to connect. Please check that the key here matches the one defined in the remote site settings.', 'wms' ) );
		}
		return $message;
	}
	/**
	 * This function make azip and send information to remote site.
	 *
	 * @param string $data comment request data.
	 * @return string
	 */
	public function get_db_zip( $data ) {
		$check_key = $this->check_key( $data );
		if ( true !== $check_key[0] ) {
			return $check_key;
		}

		global $wpdb;
		$dump_folder_name = $data['dump_folder_name'];
		$encryption_key   = $data['key'];
		$site_url         = site_url();

		$dump_status_informations = wms_dump_db( $dump_folder_name, $encryption_key, 'remote' );
		$db_url                   = WMS_DATA_MERGE_DIR_BY_URL . $dump_folder_name . '.zip';

		$response = array(
			'prefix'                   => $wpdb->prefix,
			'site_url'                 => $site_url,
			'db_url'                   => $db_url,
			'dump_folder_name'         => $dump_folder_name,
			'remote_dump_informations' => $dump_status_informations,
		);

		return $response;
	}
}
