<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://orionorigin.com
 * @since      1.0.0
 *
 * @package    Wms
 * @subpackage Wms/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wms
 * @subpackage Wms/admin
 * @author     Orion <support@orionorigin.com>
 */
class Wms_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wms_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wms_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wms-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'o-ui', plugin_dir_url( __FILE__ ) . 'css/UI.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name . '-datatables', plugin_dir_url( __FILE__ ) . '/css/datatables.min.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wms_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wms_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name . '-jquery-tabs', plugin_dir_url( __FILE__ ) . 'js/jquery-tab.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name . '-block-ui', plugin_dir_url( __FILE__ ) . 'js/jquery-block-ui.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name . '-datatables', plugin_dir_url( __FILE__ ) . 'js/datatables.min.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wms-admin.js', array( 'jquery', $this->plugin_name . '-block-ui', $this->plugin_name . '-jquery-tabs', $this->plugin_name . '-datatables' ), $this->version, false );


		$data = array(
			'wms_ajax_security'       => wp_create_nonce( 'wms-ajax-nonce' ),
			'dump_start_message'      => $this->get_dump_status_message( 'start' ),
			'dump_completed_message'  => $this->get_dump_status_message( 'success' ),
			'dump_failed_message'     => $this->get_dump_status_message( 'fail' ),
			'loading_message'         => __( 'Loading ...', 'wms' ),
			'test_connection_message' => __( 'Test connection', 'wms' )
		);

		wp_add_inline_script( $this->plugin_name, ' var wms_object=' . wp_json_encode( $data ) . ';' );
	}

	/**
	 * Provide dump status message.
	 *
	 * @param string $message_type Dump status.
	 * @return string
	 */
	private function get_dump_status_message( $message_type ) {
		switch ( $message_type ) {
			case 'start':
				$message = __( 'Synchronization started, please keep this window open to be informed of the progress.', 'wms' );
				break;

			case 'success':
				$message = __( 'Synchronization completed successfully.', 'wms' );
				break;

			case 'fail':
				$message = __( 'Synchronization failed, please contact plugin support.', 'wms' );
				break;

			default:
				$message = '';
				break;
		}
		return $message;
	}
	/**
	 * Create site post type.
	 *
	 * @return void
	 */
	public function set_website_post_type() {

			$labels = array(
				'name'               => _x( 'Sites', 'wms' ),
				'singular_name'      => _x( 'Site', 'wms' ),
				'menu_name'          => __( 'WP Merge & Sync', 'wms' ),
				'all_items'          => __( 'Sites', 'wms' ),
				'view_item'          => __( 'See all sites', 'wms' ),
				'add_new_item'       => __( 'Add site', 'wms' ),
				'add_new'            => __( 'Add a site', 'wms' ),
				'edit_item'          => __( 'Edit sites', 'wms' ),
				'update_item'        => __( 'Edit site', 'wms' ),
				'search_items'       => __( 'Search site', 'wms' ),
				'not_found'          => __( 'Not found', 'wms' ),
				'not_found_in_trash' => __( 'Not found in trash', 'wms' ),
			);

			$args = array(
				'label'               => __( 'WMS', 'wms' ),
				'description'         => __( 'All Sites', 'wms' ),
				'labels'              => $labels,
				'supports'            => array( 'title' ),
				'hierarchical'        => false,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-update',

			);
			register_post_type( 'wms-sites', $args );
	}
	/**
	 * Add meta box with settings page.
	 *
	 * @return void
	 */
	public function get_settings_metabox() {

		$screens = array( 'wms-sites' );

		foreach ( $screens as $screen ) {

			add_meta_box(
				'wms-sites-settings-box',
				__( 'Site settings', 'wms' ),
				array( $this, 'get_site_settings_page' ),
				$screen
			);
		}
	}
	/**
	 * Add submenu settings to wms menu.
	 *
	 * @return void
	 */
	public function add_wms_menu() {

		$parent_slug = 'edit.php?post_type=wms-sites';

		add_submenu_page( $parent_slug, __( 'Synchronization', 'wms' ), __( 'New sync / merge', 'wms' ), 'manage_options', 'wms-manage-synchronization', array( $this, 'add_wms_synchronization_page' ) );
		add_submenu_page( $parent_slug, __( 'Settings', 'wms' ), __( 'Settings', 'wms' ), 'manage_options', 'wms-manage-settings', array( $this, 'add_wms_setting_page' ) );

	}
	/**
	 * Get site settings page that will be added to metabox.
	 *
	 * @return void
	 */
	public function get_site_settings_page() {
		$begin = array(
			'type' => 'sectionbegin',
			'id'   => 'wms-datasource-container',
		);
		$url   = array(
			'title'    => __( 'Site URL', 'wms' ),
			'type'     => 'text',
			'id'       => 'site_url',
			'name'     => 'wms-site[url]',
			'required' => true,
			'default'  => '',
		);
		$key   = array(
			'title'    => __( 'Connection key', 'wms' ),
			'type'     => 'text',
			'id'       => 'connection_key',
			'name'     => 'wms-site[site-key]',
			'required' => true,
			'desc'     => __( 'Secret key that guarantees the integrity of the exchanges between this site and the remote one. The value here must match the one defined in the remote site settings.', 'wms' ),
		);

		$test_button = array(
			'title'             => __( 'Test connection', 'wms' ),
			'type'              => 'button',
			'id'                => 'wms-test-key',
			'class'             => 'button button-primary button-large',
			'custom_attributes' => array( 'site_id' => get_the_id() ),
		);

		$end = array( 'type' => 'sectionend' );

		$settings = array(
			$begin,
			$url,
			$key,
			$test_button,
			$end,
		);

		$raw_output = o_admin_fields( $settings );
		echo wp_kses( $raw_output, WMS_ALLOWED_TAGS );
		?>
		<input type="hidden" name="securite_nonce" value="<?php echo esc_html( wp_create_nonce( 'securite-nonce' ) ); ?>"/>
		<?php
		global $o_row_templates;
		?>
		<script>
			var o_rows_tpl =<?php echo wp_json_encode( $o_row_templates ); ?>;
		</script>
		<?php
	}
	/**
	 * Generate wms key call generate new key function in functions.php.
	 *
	 * @return void
	 */
	public function generate_wms_key() {
		die( esc_html( wms_generate_new_key() ) );
	}
	/**
	 * Update site option when they are edited.
	 *
	 * @param [type] $post_id comment post id.
	 * @return void
	 */
	public function edit_websites( $post_id ) {
		$website = new WMS_Website( $post_id );
		if ( isset( $_POST['wms-site'], $_POST['securite_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['securite_nonce'] ), 'securite-nonce' ) ) {
			$meta = array_map( 'sanitize_text_field', wp_unslash( $_POST['wms-site'] ) );
			$website->update( $meta );
		}
	}
	/**
	 * Manage sites columns on sites page.
	 *
	 * @param array $columns comment list of column.
	 * @return array
	 */
	public function manage_sites_columns( $columns ) {
		unset( $columns['date'] );
		$columns['url'] = __( 'URL', 'wms' );
		return $columns;
	}
	/**
	 * Add columns value to site page.
	 *
	 * @param string $column_name comment column name.
	 * @param [type] $id comment id of site.
	 */
	public function add_columns_value( $column_name, $id ) {

		$to_display = '';
		$website    = new WMS_Website( $id );
		$url        = $website->get_url();

		if ( 'url' === $column_name ) {
			$to_display = $url;
		}
		echo wp_kses( $to_display, WMS_ALLOWED_TAGS );
	}
	/**
	 * Create settings page.
	 *
	 * @return void
	 */
	public function add_wms_setting_page() {
		?>
		<h1 style="margin-top: 30px;" ><?php esc_html_e( 'WP Data Merge & Sync Settings' ); ?></h1>
		<div class="o-wrap cf">
			<form method="POST" action="" class="mg-top">
			<div class="postbox" id="wad-options-container">
			<?php
			$begin     = array(
				'type' => 'sectionbegin',
				'id'   => 'wms-datasource-container',
			);
			$key       = array(
				'title'    => __( 'Connection key', 'wms' ),
				'type'     => 'custom',
				'callback' => array( $this, 'get_key_field' ),
				'required' => true,
				'desc'     => __( 'Secret key that guarantees the integrity of the exchanges between this site and the remote one. The value here must match the one defined in the remote site settings.', 'wms' ),
			);
			$mysqldump = array(
				'title'    => __( 'Mysqldump binary path', 'wms' ),
				'type'     => 'custom',
				'callback' => array( $this, 'get_mysqldump_path_field' ),
				'required' => true,
				'desc'     => __( 'If mysqldump is publicly usable please leave this field to "mysqldump". If not, please set the full path to the binary including the binary itself', 'wms' ),
			);
			$end       = array( 'type' => 'sectionend' );

			$settings   = array(
				$begin,
				$key,
				$mysqldump,
				$end,
			);
			$raw_output = o_admin_fields( $settings );
			echo wp_kses( $raw_output, WMS_ALLOWED_TAGS );
			?>
		</div>
			<input type="hidden" name="securite_nonce" value="<?php echo esc_html( wp_create_nonce( 'securite-nonce' ) ); ?>"/>
			<input style="margin-top: 5px;" type="submit" class="button button-primary button-large" value="<?php esc_html_e( 'Save', 'wms' ); ?>">

		</form>
		</div>

		<?php
		global $o_row_templates;
		?>
		<script>
			var o_rows_tpl =<?php echo wp_json_encode( $o_row_templates ); ?>;
		</script>
		<?php

	}
	/**
	 * Create synchronization page.
	 *
	 * @return void
	 */
	public function add_wms_synchronization_page() {
		$posts = get_posts(
			array(
				'post_type' => 'wms-sites',
			)
		);
		?>
		<h1 style="margin-top: 30px;" ><?php esc_html_e( 'WP Data Merge & Sync New Synchronization' ); ?></h1>
		<div class="o-wrap cf">
		<form method="POST" action="" class="mg-top">

		<select id="wms_sync_select" name="wms-sync-name" style="margin-bottom: 10px;" requied>
		<?php
		foreach ( $posts as $key => $value ) {
			?>
		<option value="<?php echo esc_html( $value->ID ); ?>"> <?php echo esc_html( $value->post_title ); ?> </option>
			<?php
		}
		?>
		</select>
		<br />

		<input type="hidden" name="securite_nonce" value="<?php echo esc_html( wp_create_nonce( 'securite-nonce' ) ); ?>"/>
		<input id="wms_sync_btn" style="margin-top: 5px;" type="submit" class="button button-primary button-large" value="<?php esc_html_e( 'launch synchronization', 'wms' ); ?>"
		<?php true !== wms_check_requirements() ? esc_html_e( 'disabled' ) : ''; ?> >
		</form>
		</div>
		<div id="wms_rslt"></div>
		<div id="wms_comparison_rslt"></div>
		<?php
	}
	/**
	 * Start sync between two sites.
	 *
	 * @return void
	 */
	public function start_sync() {
		if ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'wms-ajax-nonce' ) ) {

			$local_site_url   = site_url();
			$dump_folder_name = uniqid();
			update_option( 'wms-db-result-key', $dump_folder_name );

			$site_id  = filter_input( INPUT_POST, 'site_id' );
			$website  = new WMS_Website( $site_id );
			$response = $website->get_remote_site_db_zip( $dump_folder_name );
			update_option( 'wms-remote-site-info', $response );
			update_option( 'wms-last-remote-site', $website ); // Save the current remote site for use after viewing the comparison results.

			$encryption_key = $website->get_key();

			$dump_status = wms_dump_db( $dump_folder_name, $encryption_key );

			if ( 200 === $dump_status ) {
				$local_dump_status_informations = $this->get_dump_status_message( 'success' );
			} elseif ( 400 === $dump_status ) {
				$local_dump_status_informations = $this->get_dump_status_message( 'fail' );
			} else {
				$local_dump_status_informations = $dump_status;
			}
			$dump_informations = array(
				'local' => $local_dump_status_informations,
			);
			if ( isset( $response['remote_dump_informations'] ) && ! empty( $response['remote_dump_informations'] ) ) {
				$dump_informations['remote'] = $response;
			}
			// return informations about the local and remote site to AJAX in JSON format.
			echo wp_json_encode( $dump_informations );
		}
		die;
	}

	/**
	 * Get and add the key field to the setting page with the key inside.
	 *
	 * @return void
	 */
	public function get_key_field() {
		if ( isset( $_POST['wms-site-key'], $_POST['securite_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['securite_nonce'] ), 'securite-nonce' ) ) {
				$site_key = sanitize_text_field( wp_unslash( $_POST['wms-site-key'] ) );
				update_option( 'wms-site-key', $site_key );
		}
		$get_key = get_option( 'wms-site-key' );
		if ( isset( $get_key ) && ! empty( $get_key ) ) {
			$key = $get_key;
		} else {
				$key = wms_generate_new_key();
				update_option( 'wms-site-key', $key );
		}
		?>
		<input id="wmskey" name="wms-site-key" style="margin-bottom: 10px;" type="text" value="<?php echo esc_attr( $key ); ?>">
		<span id="wms-key">Click <a id="generate-key" href="#" >here</a> to generate new key</span>
		<input type="hidden" name="securite_nonce" value="<?php echo esc_html( wp_create_nonce( 'securite-nonce' ) ); ?>"/>
		<?php
	}
	/**
	 * Get and add the mysqldump path field to the setting page.
	 *
	 * @return void
	 */
	public function get_mysqldump_path_field() {
		if ( isset( $_POST['wms_msdp'], $_POST['securite_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['securite_nonce'] ), 'securite-nonce' ) ) {
			$msd_path = sanitize_text_field( wp_unslash( $_POST['wms_msdp'] ) );
			if ( ! isset( $msd_path ) || empty( $msd_path ) ) {
				$msd_path = 'mysqldump';
			}
			update_option( 'msd_path', $msd_path );
		}
		$get_msd_path = get_option( 'msd_path' );
		if ( ! isset( $get_msd_path ) || empty( $get_msd_path ) ) {
			$get_msd_path = 'mysqldump';
			update_option( 'msd_path', $get_msd_path );
		}
		?>
		<input id="wms_msd_path" name="wms_msdp" style="margin-bottom: 10px;" type="text" value="<?php echo esc_attr( $get_msd_path ); ?>">
		<?php
	}
	/**
	 * Test the connection between two sites.
	 *
	 * @return void
	 */
	public function test_connection() {
		$site_url       = filter_input( INPUT_POST, 'site_url' );
		$connection_key = filter_input( INPUT_POST, 'connection_key' );
		if ( empty( $site_url ) ) {
			esc_html_e( 'No url found.', 'wms' );
			die;
		}
		if ( empty( $connection_key ) ) {
			esc_html_e( 'No key found.', 'wms' );
			die;
		}
		$site = new WMS_Website();
		$site->set_url( $site_url );
		$site->set_key( $connection_key );
		$test_connection = $site->init_handshake();
		echo wp_kses( $test_connection[1], WMS_ALLOWED_TAGS );
		die();
	}
	/**
	 * Check requirement
	 *
	 * @return void
	 */
	public function check_requirement() {
		$msg = wms_check_requirements();
		if ( true !== $msg ) {
			$this->get_requirement_notice( $msg, 'error' );
		}
		$msg = wms_is_mysqldump_available();
		if ( true !== $msg ) {
			$this->get_requirement_notice( $msg, 'warning' );
		}
	}
	/**
	 * Show requirement notice
	 *
	 * @param string $message what will be show.
	 * @return void
	 */
	public function get_requirement_notice( $message, $error_type ) {
		?>
			<div class="notice notice-<?php echo esc_attr( $error_type ); ?>" >
				<p><b>WordPress Data Merge & Sync: </b><?php echo esc_attr( $message ); ?></p>
				<p></p>
			</div>
			<?php
	}

	public function display_dump_results() {
		global $sqlite_obj;
		$wms_results_sqlite_path = get_transient( 'wms-results-sqlite-file-path' );
		$sqlite_obj              = new WMS_Sqlite_Db_Reader( $wms_results_sqlite_path );
		$container_html          = '<div class="wms-container"><div class="wms-content wms-content-light"><div class="wms-title wms-title-light" style="text-align: center;">';
		$container_html         .= '<h3>' . __( 'Comparison Results', 'wms' ) . '</h3></div>';
		$container_html         .= '<div class="wms-table-section" id="tabs" ><nav class="wms-result-display" style=" position: relative;"><ul class="wms-results-title-block list-group" id="tabs-nav">';
		$tabs                    = $sqlite_obj->get_tabs();
		$tables                  = '';
		foreach ( $tabs as $tab => $wp_table ) {
			$report_id       = $sqlite_obj->get_report_id( $wp_table );
			if ( ! $report_id ) {
				continue;
			}
			$data_errors     = $sqlite_obj->get_datas( $report_id, $tab );
			$nbr             = ( isset( $data_errors[ $tab ] ) && ! empty( $data_errors[ $tab ] ) ) ? count( $data_errors[ $tab ] ) : 0;
			$tab_infos       = wms_get_tag_header_infos( $tab );
			$container_html .= "<li class='wms-table-" . strtolower( $tab ) . "-tb wms-nav-block'><a href='#wms-table-" . strtolower( $tab ) . "-tb' data-block='wms-" . $tab . "' id='wms-table-" . $tab . "-tb' class='wms-head'> " . strtoupper( wms_get_tab_name( $tab ) ) . ' ( ' . $nbr . ' ) </a></li>';
			$tables         .= "<div  class='tab wms-table-" . strtolower( $tab ) . "-tb'>";
			$tables         .= wms_build_table_tag_header( $tab, $tab_infos, true );
			$tables         .= wms_build_table_tag_body( $tab_infos, $data_errors, true );
			$tables         .= '</div>';
		}
		$container_html .= '</ul></nav>' . $tables . '</div></div>';

		echo wp_kses_post( $container_html );
		delete_option( 'wms-last-remote-site' );
		die;
	}
}
