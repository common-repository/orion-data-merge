<?php
/**
 * Class WMS_website
 * Allow to manage site keys and urls
 *
 * @package wms
 */

/**
 * Description of class-wms-endpoints  Class used to create endpoints for rest api
 *
 * @author Orion
 */
class WMS_Website {
	/**
	 * Id site id
	 *
	 * @var int
	 */
	private $id;
	/**
	 * Url site url
	 *
	 * @var [type]
	 */
	private $url;
	/**
	 * Key key associate to site
	 *
	 * @var [type]
	 */
	private $key;
	/**
	 * Meta_key site meta
	 *
	 * @var string
	 */
	private $meta_key = 'wms-site';
	/**
	 * Construct class constructor
	 *
	 * @param [type] $id comment site id.
	 */
	public function __construct( $id = false ) {
		if ( false !== $id ) {
			$this->id  = $id;
			$data      = get_post_meta( $id, $this->meta_key, true );
			$this->url = isset( $data['url'] ) ? $data['url'] : '';
			$this->key = isset( $data['site-key'] ) ? $data['site-key'] : '';
		}
	}
	/**
	 * Set_url url setter
	 *
	 * @param [type] $url comment site url.
	 * @return void
	 */
	public function set_url( $url ) {
		$this->url = $url;
	}
	/**
	 * Set_key key setter
	 *
	 * @param [type] $key comment site key.
	 * @return void
	 */
	public function set_key( $key ) {
		$this->key = $key;
	}
	/**
	 * Get_url getter
	 *
	 * @return string
	 */
	public function get_url() {
		return $this->url;
	}
	/**
	 * Get_key getter
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}
	/**
	 * This function add or update site POST
	 *
	 * @param [type] $meta comment meta value.
	 * @return void
	 */
	public function update( $meta ) {
		if ( isset( $meta ) ) {
			update_post_meta( $this->id, $this->meta_key, $meta );
		}
	}
	/**
	 * This function makes the call to the API
	 *
	 * @param string $cmd comment  route name.
	 * @param string $parameter comment route parameter.
	 * @return string
	 */
	public function call_the_api( $cmd, $parameter = '' ) {
		$wp_request_url = $this->create_route( $cmd, $parameter );

		$response      = wp_remote_get( $wp_request_url, array( 'timeout' => 30 ) );
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			esc_html_e( 'The URL cannot be reached.' );
			die;
		} else {
			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );

			$response = $api_response;
		}
		return $response;
	}
	/**
	 * THis function generate a route for api's request.
	 *
	 * @param string $cmd comment route name.
	 * @param string $parameter comment route parameter.
	 * @return string
	 */
	public function create_route( $cmd, $parameter = '' ) {
		$rest_base_url = 'wp-json/wms/v2/' . $cmd . '/';
		$local_route   = '';
		$key           = $this->key;
		if ( strpos( $this->url, 'index.php' ) === false ) {
			$local_route = 'index.php/';
		}
		if ( substr( $this->url, -1, 1 ) !== '/' ) {
				$wp_request_url = $this->url . '/' . $local_route . $rest_base_url . $key . '/' . $parameter;
		} else {
			$wp_request_url = $this->url . $local_route . $rest_base_url . $key . '/' . $parameter;
		}
		return $wp_request_url;
	}
	/**
	 * This function does the handshake by calling the call_the_api function
	 *
	 * @return string
	 */
	public function init_handshake() {
		$handshake_response = $this->call_the_api( 'check_key' );
		return $handshake_response;
	}
	/**
	 * This function get the siteb informations(sql zip, site url).
	 *
	 * @param string $dump_folder_name comment dump folder name.
	 * @return string
	 */
	public function get_remote_site_db_zip( $dump_folder_name ) {
		$response = $this->call_the_api( 'get_remote_site_db_zip', $dump_folder_name );
		return $response;
	}

}
