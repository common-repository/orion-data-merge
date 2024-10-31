<?php
/**
 * Database dump class.
 *
 * @package Wms
 * @subpackage Wms/Includes
 * @since 1.0.0
 */

/**
 * Database dump class.
 *
 * This class is used to dump a database and retrieve some information from
 * a database.
 *
 * @since 1.0.0
 */
class WMS_Dumper {
	/**
	 * Path to the database dump file.
	 *
	 * @var string
	 */
	private $sql_file_path;

	/**
	 * Path to the file who contains last information about a previous dump done.
	 *
	 * @var string
	 */
	private $last_infos_file;

	/**
	 * List of database table names.
	 *
	 * @var array
	 */
	private $tables_names;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the sql dump file path and the last infos file path.
	 *
	 * @param string $sql_file_path Path to the SQL file.
	 * @param string $last_infos_file Path to the TXT file.
	 */
	public function __construct( $sql_file_path, $last_infos_file ) {
		$this->set_sql_file_path( $sql_file_path );
		$this->set_last_infos_file( $last_infos_file );
		$this->set_tables_names();
	}


	/**
	 * Get the database dump file path.
	 *
	 * @return string
	 */
	public function get_sql_file_path() {
		return $this->sql_file_path;
	}

	/**
	 * Set the database dump sql file path.
	 *
	 * @param string $sql_file_path Path to the file.
	 * @return void
	 */
	public function set_sql_file_path( $sql_file_path ) {
		$this->sql_file_path = $sql_file_path;
	}

	/**
	 * Get path to the latest database information file.
	 *
	 * @return string
	 */
	private function get_last_infos_file() {
		return $this->last_infos_file;
	}

	/**
	 * Set path to the latest database information file.
	 *
	 * @param string $path Path to the file.
	 * @return void
	 */
	public function set_last_infos_file( $path ) {
		$this->last_infos_file = $path;
	}

	/**
	 * Get object of the library Mysqldump PHP.
	 *
	 * @param array $dump_settings Settings needed for creating the instance.
	 * @return mixed
	 */
	private function get_db_object( $dump_settings = array() ) {
		if ( class_exists( 'Ifsnop\Mysqldump\Mysqldump' ) ) {
			return new Ifsnop\Mysqldump\Mysqldump( 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, $dump_settings );
		} else {
			return new WP_Error( 'class_not_exists', __( 'The Mysqldump library isn\'t loaded.', 'wms' ) );
		}
	}

	/**
	 * Run ddl dump.
	 *
	 * @return mixed
	 */
	public function run_ddl_dump() {
		if ( ! $this->get_sql_file_path() ) {
			return new WP_Error( 'empty_sql_dump_file_path', __( 'The path to the dump sql file is empty.', 'wms' ) );
		}
		$db_object = $this->get_db_object( array( 'no-data' => true ) );
		if ( is_wp_error( $db_object ) ) {
			return $db_object;
		} else {
			$db_object->start( $this->get_sql_file_path() );
			if ( is_readable( $this->get_sql_file_path() ) ) {
				return true;
			}
		}
		return new WP_Error( 'cant_dump_ddl', __( 'The ddl can\'t be dumped.', 'wms' ) );
	}

	/**
	 * Dump whole database data.
	 *
	 * @return mixed
	 */
	public function dump_whole_db_data() {
		try {
			$db_object = $this->get_db_object( array( 'no-create-info' => true ) );
			$db_object->start( $this->get_sql_file_path() );
			return true;
		} catch ( Exception $errors ) {
			return new WP_Error( 'cant_dump_whole_db_data', $errors );
		}
	}

	/**
	 * Retrieves the number of rows of a table.
	 *
	 * @param string $table_name Table name.
	 * @return mixed
	 */
	private function get_nb_rows_of_table( $table_name ) {
		global $wpdb;
		//phpcs:ignore
		$query = $wpdb->get_results("SELECT count(*) FROM `$table_name`");
		return current( get_object_vars( current( $query ) ) );
	}

	/**
	 * Retrieves the number of cols of a table.
	 *
	 * @param string $table_name Table name.
	 * @return mixed
	 */
	private function get_nb_cols_of_table( $table_name ) {
		global $wpdb;
		if ( ! wp_cache_get( 'wms_' . $table_name . '_cols', 'wms' ) ) {
			// phpcs:disable
			$query = $wpdb->get_results($wpdb->prepare('SELECT count(*) FROM information_schema.columns WHERE table_name = %s', $table_name));
			// phpcs:enable
			wp_cache_set( 'wms_' . $table_name . '_cols', $query, 3600 );
		} else {
			$query = wp_cache_get( 'wms_' . $table_name . '_cols', 'wms' );
		}
		return current( get_object_vars( current( $query ) ) );
	}

	/**
	 * Get tables names.
	 *
	 * @return array
	 */
	public function get_tables_names() {
		return $this->tables_names;
	}

	/**
	 * Set tables names.
	 *
	 * @param array $table_names List of database table names.
	 * @return void
	 */
	public function set_tables_names( $table_names = array() ) {
		global $wpdb;
		$tables       = array(
			'commentmeta',
			'comments',
			'links',
			'options',
			'postmeta',
			'posts',
			'terms',
			'termmeta',
			'term_relationships',
			'term_taxonomy',
			'usermeta',
			'users',
		);
		$table_prefix = $wpdb->prefix;
		foreach ( $tables as $table_name ) {
			$table_names[] = $table_prefix . $table_name;
		}
		$this->tables_names = $table_names;
	}


	/**
	 * Dump data from database tables for a certain number of rows.
	 *
	 * @param string $table  Table name.
	 * @param int    $offset Line number, from where starting export.
	 * @param int    $limit  Number of line to export.
	 * @return mixed
	 */
	public function dump_db_data_set( $table, $offset, $limit ) {
		try {
			$db_object = $this->get_db_object(
				array(
					'where'          => '1 limit ' . $offset . ',' . $limit,
					'include-tables' => array( $table ),
					'no-create-info' => true,
				)
			);
			$db_object->start( $this->get_sql_file_path() );
			return true;
		} catch ( Exception $errors ) {
			return new WP_Error( 'failed_to_dump_table_data', $errors );
		}
	}

	/**
	 * Save last infos to a txt file.
	 *
	 * @param string $table_name Table name.
	 * @param int    $offset     Last Line Exported number.
	 * @return bool
	 */
	public function save_last_table_dump_infos( $table_name, $offset ) {
		// phpcs:disable
		$serialized_data = serialize(
			array(
				$table_name => $offset,
			)
		);
		$is_written = file_put_contents($this->get_last_infos_file(), $serialized_data . PHP_EOL, LOCK_EX);
		if ($is_written) {
			return true;
		}
		// phpcs:enable
		return new WP_Error( 'save_last_infos_failed', __( 'Failed to save last infos to a txt file.', 'wms' ) );
	}

	/**
	 * Retrieve last table dump informations from a stored txt file.
	 *
	 * @return array
	 */
	public function get_last_table_dump_infos() {
		$last_infos_file = $this->get_last_infos_file();
		$file_object     = @fopen( $last_infos_file, 'a+' ) or die( esc_attr__( 'Unable to open file!', 'wms' ) );
		if ( ! $file_object ) {
			wp_unschedule_event(
				time(),
				'wms_schedule_dump_with_php',
				array(
					'last_table_name'        => $this->get_sql_file_path(),
					'last_table_line_number' => $this->get_last_infos_file(),
					'run_in_schedule_mode'   => true,
				)
			);
			return array(
				'last_table_name'        => '',
				'last_table_line_number' => 0,
			);
		}
		$unserialized_data = array();
		while ( ! feof( $file_object ) ) {
			$line                = fgets( $file_object );
			$unserialized_data[] = unserialize( $line );
		}
		fclose( $file_object );
		return $unserialized_data;
	}


	/**
	 * Decide if the whole database can be dumped.
	 *
	 * @return bool
	 */
	public function can_dump_whole_db_in_php() {
		global $wpdb;

		if ( ! wp_cache_get( 'wms_database_information', 'wms' ) ) {
			// phpcs:disable
			$database_information = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT table_schema AS 'database_name', 
						ROUND(SUM(data_length + index_length) / 1024 , 2) AS 'size'
						FROM information_schema.TABLES 
						WHERE table_schema = %s;",
					DB_NAME
				)
			);
			// phpcs:enable
			wp_cache_set( 'wms_database_information', $database_information, 'wms', 3600 );
		} else {
			$database_information = wp_cache_get( 'wms_database_information', 'wms' );
		}

		$database_decoded_information = get_object_vars( current( $database_information ) );
		$database_size                = $database_decoded_information['size'];
		if ( $database_size <= WMS_MAX_DUMP_SIZE * WMS_PHP_MAX_EXECUTION_TIME ) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * Dump database using php library.
	 *
	 * @return string
	 */
	public function dump_with_php() {
		$ddl_dump = $this->run_ddl_dump();
		$message  = 400;
		if ( ! is_wp_error( $ddl_dump ) ) {
			$can_dump_whole_db = $this->can_dump_whole_db_in_php();
			if ( $can_dump_whole_db ) {
				$whole_db_dump = $this->dump_whole_db_data();
				if ( ! is_wp_error( $whole_db_dump ) ) {
					$message = 200;
				}
			} else {
				wp_unschedule_hook( 'wms_schedule_dump_with_php' );
				$this->schedule_dump_table_data_with_php( $this->get_sql_file_path(), $this->get_last_infos_file() );
				$message = 201;
			}
		}
		return $message;
	}


	/**
	 * Schedule and dump table using php library.
	 *
	 * @param string $sql_file_path Path to the sql dump file.
	 * @param string $last_infos_file Path to the last infos txt file.
	 * @param bool   $run_in_schedule_mode Whether if the function is called in schedule mode.
	 * @return mixed
	 */
	public function schedule_dump_table_data_with_php( $sql_file_path, $last_infos_file, $run_in_schedule_mode = false ) {
		if ( $run_in_schedule_mode ) {
			// we set the sql and txt file when the method is called from scheduler environment.
			$this->set_sql_file_path( $sql_file_path );
			$this->set_last_infos_file( $last_infos_file );
		}

		$offset = 0;

		$dump_informations = array(
			'sql_file_path'        => $sql_file_path,
			'last_infos_file'      => $last_infos_file,
			'run_in_schedule_mode' => true,
		);

		$table_names       = $this->get_tables_names();
		$last_table_name   = end( $table_names );
		$dumped_data       = $this->get_decoded_last_table_dump_infos();
		$dump_is_completed = false;
		if ( ! empty( $dumped_data ) ) {
			$saved_table_name = $dumped_data['last_table_name'];
			$offset           = $dumped_data['last_table_line_number'];

			if ( $saved_table_name ) {
				// contains all table names and numbers of rows and cols ( the table start from the last table names).
				$table_names = array_slice( $table_names, array_search( $saved_table_name, array_values( $table_names ), true ) );
			}
		}

		$nb_cels = WMS_TOTAL_CEL;

		foreach ( $table_names as $table_name ) {
			$table_rows       = $this->get_nb_rows_of_table( $table_name ); // number of rows of the table.
			$table_cols       = $this->get_nb_cols_of_table( $table_name ); // number of cols of the table.
			$table_rows      -= $offset;
			$table_cel_number = $table_rows * $table_cols; // number of cel of the table.

			if ( $last_table_name === $table_name || ( $last_table_name === $table_name && 0 === ( $table_rows - $offset ) ) ) {
				$this->save_last_table_dump_infos( '', 0 );
				$this->unschedule_dump( $dump_informations );
				$dump_is_completed = true;
			}

			if ( $table_cel_number < $nb_cels ) {
				try {
					$dump_table = $this->dump_db_data_set( $table_name, $offset, $table_rows );
					if ( ! is_wp_error( $dump_table ) ) {
						$nb_cels -= $table_cel_number;
					}
					$offset = 0;
				} catch ( Exception $errors ) {
					$this->unschedule_dump( $dump_informations );
					return;
				}
			} else {
				try {
					$nb_rows_dumpable = intdiv( $nb_cels, $table_cols );
					$dump_table       = $this->dump_db_data_set( $table_name, $offset, $nb_rows_dumpable );
					if ( ! is_wp_error( $dump_table ) ) {
						$this->save_last_table_dump_infos( $table_name, $nb_rows_dumpable + $offset );
					}
				} catch ( Exception $errors ) {
					$this->unschedule_dump( $dump_informations );
					return;
				}
				break;
			}
		}
		if ( ! $dump_is_completed ) {
			wp_schedule_single_event(
				time(),
				'wms_schedule_dump_with_php',
				$dump_informations
			);
		}
	}

	/**
	 * Unschedule dump.
	 *
	 * @param array $args Dump arguments.
	 * @return void
	 */
	private function unschedule_dump( $args = array() ) {
		wp_unschedule_event(
			time(),
			'wms_schedule_dump_with_php',
			$args
		);
	}

	/**
	 * Decode information about the last dumped table.
	 *
	 * @return array
	 */
	private function get_decoded_last_table_dump_infos() {
		$last_table_infos = current( $this->get_last_table_dump_infos() );
		$saved_data       = array();
		if ( $last_table_infos ) {
			// we retrieve the name of the last table name dumped.
			$last_table_name               = array_key_first( $last_table_infos );
			$saved_data['last_table_name'] = $last_table_name;
			// we retriece the line number where the last dump stopped.
			$last_table_rows                      = array_shift( $last_table_infos );
			$saved_data['last_table_line_number'] = $last_table_rows;
		}
		return $saved_data;
	}

	/**
	 * Dump database using mysqldump binary.
	 *
	 * @return mixed
	 */
	public function dump_with_mysqldump() {
		global $wpdb;
		$mysqldump_path = get_option( 'msd_path' );
		if ( function_exists( 'exec' ) ) {
			$table_names = $this->get_tables_names();
			// phpcs:disable
			exec(
				$mysqldump_path . " --no-tablespaces --single-transaction --user='" . $wpdb->dbuser . "' --password='" . $wpdb->dbpassword . "' --databases '" .
					$wpdb->dbname . "' --tables " . implode('  ', $table_names) . ">" . $this->get_sql_file_path(),
				$output,
				$return_var
			);
			// phpcs:enable
			if ( 0 !== $return_var ) {
				return new WP_Error( 'dump_with_mysqldump_failed', __( 'Failed to dump with mysqldump', 'wms' ) );
			}
			return true;
		}
		return new WP_Error( 'exec_function_is_disabled', __( 'Function exec is disabled', 'wms' ) );
	}

	/**
	 * Check if any export process is running in background.
	 */
	public function check_if_dump_is_completed() {
		if ( isset( $_POST['data'] ) ) {
			$message        = 201;
			$data           = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$folder_name    = $data['dump_folder_name'];
			$file_name      = $data['file_name'];
			$site_id        = $data['site_id'];
			$website        = new WMS_Website( $site_id );
			$dump_dir       = WMS_DATA_MERGE_DIR . $folder_name;
			$file_path_root = $dump_dir . '/' . $file_name;
			$sql_file_path  = $file_path_root . '.sql';
			$this->set_sql_file_path( $file_path_root . '.sql' );
			$this->set_last_infos_file( $file_path_root . '.txt' );
			$dumped_data = $this->get_decoded_last_table_dump_infos();
			if ( ! empty( $dumped_data ) ) {
				$table_name = $dumped_data['last_table_name'];
				if ( '' === $table_name ) {
					$message = 200;
					unlink( $file_path_root . '.txt' );
					$encryption_key = $website->get_key();
					wms_encrypt_dump_file( $sql_file_path, $file_name, $folder_name, $encryption_key );
					wms_remove_directory( $dump_dir );
				}
			}
		}
		echo wp_kses( $message, array() );
		die();
	}

	/**
	 * Send dump informations to KPAX.
	 *
	 * @return void
	 */
	public function send_dump_to_kpax() {
		if ( isset( $_POST['data'] ) ) {
			$data             = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$dump_folder_name = $data['dump_folder_name'];
			$site_id          = $data['site_id'];
			$website          = new WMS_Website( $site_id );
			$encryption_key   = base64_encode( $website->get_key() );
			$response         = array(
				'prefix'   => $data['remote_db_table_prefix'],
				'site_url' => $data['remote_site_url'],
				'db_url'   => $data['remote_db_url'],
			);
			wms_send_dump_to_kpax( $dump_folder_name, $encryption_key, $response, site_url() );
		}
		wp_die();
	}
}
