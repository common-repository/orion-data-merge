<?php

/**
 * Facilitates reading of the Sqlite DB as used to display results after database backup.
 */
class WMS_Sqlite_Db_Reader {

	protected $db_object;

	protected $report_ids;

	protected $tabs;

	private $allowed_posts;



	/**
	 * WMS_Sqlite_Db_Reader constructor.
	 *
	 * @param string $path Path to Sqlite file.
	 */
	public function __construct( $path ) {
		try {
			$this->db_object = new SQLite3( $path, SQLITE3_OPEN_READONLY );
			$this->db_object->enableExceptions( true );
			$this->init_report_ids();
			$this->set_tabs();
			$this->define_settings_options();
			$this->set_allowed_posts();
		} catch ( Exception $errors ) {
			// path of the log file where errors need to be logged.
			$log_file = WMS_LOG_FOLDER . gmdate( 'Ymd' ) . '.log';
			wms_create_dir(WMS_LOG_FOLDER);
			wms_create_file($log_file, '');

			// logging error message to given log file.
			error_log( $errors, 3, $log_file );

			die( esc_html__( 'no datas to displays', ' wms' ) );
		}
	}

	protected function set_tabs( $tabs = array() ) {
		if ( empty( $tabs ) ) {
			$this->tabs = array(
				'settings'  => 'options',
				'post'      => 'posts',
				'term'      => 'terms',
				'comment'   => 'comments',
				'user'      => 'users',
				'extension' => 'options',
			);
		} else {
			$this->tabs = $tabs;
		}
	}

	public function get_db_object() {
		return $this->db_object;
	}

	public function get_tabs() {
		return $this->tabs;
	}

	private function set_allowed_posts( $post_types = false ) {
		if ( false === $post_types ) {
			$post_types = array(
				'post',
				'page',
				//'revision',
				//'attachment',
			);
		}
		$this->allowed_posts = $post_types;
	}

	private function define_settings_options( $options = false ) {
		if ( false === $options ) {
			$options = "(
				'blogname',
				'blogdescription',
				'new_admin_email',
				'default_role',
				'users_can_register',
				'WPLANG',
				'timezone_string',
				'date_format_custom',
				'time_format_custom',
				'start_of_week',
				'default_category',
				'default_post_format',
				'mailserver_url',
				'mailserver_port',
				'mailserver_pass',
				'mailserver_login',
				'default_email_category',
				'ping_sites',
				'show_on_front',
				'posts_per_page',
				'posts_per_rss',
				'rss_use_excerpt',
				'blog_public',
				'selection',
				'category_base',
				'tag_base',
				'thumbnail_size_w',
				'thumbnail_size_h',
				'medium_size_w',
				'medium_size_h',
				'large_size_w',
				'large_size_h',
				'uploads_use_yearmonth_folders',
				'avatar_default',
				'avatar_rating',
				'show_avatars',
				'disallowed_keys',
				'moderation_keys',
				'comment_max_links',
				'comment_moderation',
				'comment_previously_approved',
				'moderation_notify',
				'comments_notify',
				'require_name_email',
				'comment_registration',
				'show_comments_cookies_opt_in',
				'close_comments_for_old_posts',
				'thread_comments',
				'page_comments',
				'comments_per_page',
				'default_comments_page',
				'comment_order'
			)";
		}
		$this->default_settings_option = $options;
	}

	/**
	 * Initialise report ids.
	 */
	public function init_report_ids() {
		$tables_sql      = ' SELECT report_id, domain_name AS WP_TABLE FROM kpxt_summary WHERE status = 0 ';
		$tables_query    = $this->db_object->query( $tables_sql );
		$wp_tables_array = array();
		while ( $wp_tables = $tables_query->fetchArray( SQLITE3_ASSOC ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$wp_tables_array[ $wp_tables['report_id'] ] = $wp_tables['WP_TABLE'];
		}
		$this->report_ids = $wp_tables_array;
	}

	protected function get_extra_data_query( $report_id, $wp_table, $filters = '' ) {
		//use $filters = " AND post_type in ('post','page') " to retrieve the missings posts and pages
		$table = 'kpxtsys_' . $report_id . '__' . $wp_table;
		return " SELECT * FROM $table WHERE kpxtsys_row_type in (1,2) $filters ";
	}

	protected function get_errors_data_query( $filters = '', $tab = '', $report_id = null ) {
		//use $filters = " AND report_id = 'ID' "; to get only the errors related to the table with report_id = ID
		//$imploded_allowed_fields = "('" . implode( "','", $this->allowed_fields ) . "')";
		if ( empty( $filters ) ) {
			$imploded_report_ids = "('" . implode( "','", $this->report_ids ) . "')";
			$filters             = " AND report_id in $imploded_report_ids ORDER BY report_id, rowid_origin ";
		}
		if ( 'post' === $tab ) {
			$post_table = 'kpxtsys_' . $report_id . '__posts';
			return " SELECT report_id, rowid_origin, field_name, value1 AS LOCAL, value2 AS REMOTE, post_type FROM kpxt_comperrors as A INNER JOIN $post_table as B on B.id = A.rowid_origin WHERE field_type <> 'datetime' $filters ";
		}
		//$allowed_fields = "AND field_name in $imploded_allowed_fields"
		return " SELECT report_id, rowid_origin, field_name, value1 AS LOCAL, value2 AS REMOTE FROM kpxt_comperrors WHERE field_type <> 'datetime' $filters ";
	}

	private function extract_datas( $sqlite_queries ) {
		$datas     = array();
		$data_type = 'post';

		if ( isset( $sqlite_queries['data_type'] ) ) {
			$data_type           = $sqlite_queries['data_type'];
			$datas[ $data_type ] = array();
			unset( $sqlite_queries['data_type'] );
		}

		foreach ( $sqlite_queries as $error_type => $sqlite_query ) {

			$query_response = $this->db_object->query( $sqlite_query );

			while ( $rows = $query_response->fetchArray( SQLITE3_ASSOC ) ) {

				if ( 'error' === $error_type ) {
					if ( 'post' === $data_type ) {
						$post_type = $rows['post_type'];
					}

					$field_name    = $rows['field_name'];
					$row_id_origin = $rows['rowid_origin'];
					$local_value   = $rows['LOCAL'];
					$remote_value  = $rows['REMOTE'];
					$data          = array(
						$field_name => array(
							'LOCAL'  => $local_value,
							'REMOTE' => $remote_value,
						),
					);
				} elseif ( 'missing' === $error_type ) {

					$row_id_origin = $rows['kpxtsys_rowid_origin'];
					$data          = array(
						'rows'     => $rows,
						'row_type' => $rows['kpxtsys_row_type'],
					);
				}

				if ( isset( $datas[ $data_type ][ $row_id_origin ] ) ) {
					if ( isset( $datas[ $data_type ][ $row_id_origin ]['data'] ) ) {
						if ( 'error' === $datas[ $data_type ][ $row_id_origin ]['type'] ) {
							$datas[ $data_type ][ $row_id_origin ]['data'][ $field_name ] = array(
								'LOCAL'  => $local_value,
								'REMOTE' => $remote_value,
							);
						} else {
							$datas[ $data_type ][ $row_id_origin ]['data']['rows']     = $rows;
							$datas[ $data_type ][ $row_id_origin ]['data']['row_type'] = $rows['kpxtsys_row_type'];
						}
					} else {
						$datas[ $data_type ][ $row_id_origin ]['type'] = $error_type;
						$datas[ $data_type ][ $row_id_origin ]['data'] = $data;
					}
				} else {
					$datas[ $data_type ][ $row_id_origin ] = array(
						'type' => $error_type,
						'data' => $data,
					);
					if ( 'post' === $data_type && 'error' === $error_type ) { // The second part of the check, because for the missing the data is already in the rows.
						$datas[ $data_type ][ $row_id_origin ]['post_type'] = $post_type;
					}
				}
			}
			$query_response->finalize();
		}
		return $datas;
	}


	/**
	 * Get missing and different data after reading the Sqlite file.
	 *
	 * @param int    $report_id Table report id.
	 * @param string $data_type Data identifier type.
	 *
	 * @return array
	 */
	public function get_datas( $report_id, $tab ) {
		$error_filter   = '';
		$missing_filter = '';
		$missings_query = '';
		switch ( $tab ) {
			case 'post':
				$wp_table               = 'posts';
				$imploded_allowed_posts = "('" . implode( "','", $this->allowed_posts ) . "')";
				$allowed_fields         = "( 'post_content','post_title','post_excerpt','post_status','post_parent' )";
				$post_table             = 'kpxtsys_' . $report_id . '__posts';
				$error_filter           = " AND field_name in $allowed_fields AND report_id = $report_id AND rowid_origin IN ( SELECT kpxtsys_rowid_origin FROM $post_table WHERE post_type IN $imploded_allowed_posts AND kpxtsys_row_type = 3 AND kpxtsys_row_has_diffs = 1 AND post_status != 'auto-draft' ) ORDER BY report_id, rowid_origin ";
				$missing_filter         = " AND post_type IN $imploded_allowed_posts AND post_status != 'auto-draft' ";
				break;
			case 'comment':
				$wp_table       = 'comments';
				$allowed_fields = "( 'comment_author','comment_author_email','comment_content','comment_approved','comment_type','comment_parent' )";
				$comment_table  = 'kpxtsys_' . $report_id . '__comments';
				$error_filter   = " AND field_name in $allowed_fields AND report_id = $report_id AND rowid_origin IN ( SELECT kpxtsys_rowid_origin FROM $comment_table WHERE kpxtsys_row_type = 3 AND kpxtsys_row_has_diffs = 1 ) ORDER BY report_id, rowid_origin ";
				break;
			case 'user':
				$wp_table       = 'users';
				$allowed_fields = "( 'user_nicename','user_email','user_url','display_name' )";
				$user_table     = 'kpxtsys_' . $report_id . '__users';
				$error_filter   = " AND field_name in $allowed_fields AND report_id = $report_id ";
				break;
			case 'term':
				$wp_table     = 'terms';
				$term_table   = 'kpxtsys_' . $report_id . '__terms';
				$error_filter = " AND report_id = $report_id  "; // AND rowid_origin IN ( SELECT kpxtsys_rowid_origin  FROM $term_table WHERE kpxtsys_row_type= 3 AND kpxtsys_row_has_diffs = 1  ) ORDER BY rowid_origin ";
				break;
			case 'extension': // plugins, themes ...
				$wp_table       = 'options';
				$option_table   = 'kpxtsys_' . $report_id . '__options';
				$error_filter   = " AND rowid_origin IN ( SELECT kpxtsys_rowid_origin FROM $option_table WHERE kpxtsys_row_type= 3 AND kpxtsys_row_has_diffs = 1 AND option_name in ('active_plugins','template') ) ORDER BY rowid_origin ";
				$missing_filter = " AND option_name IN ('active_plugins','template') ";
				$missings_query = $this->get_extra_data_query( $report_id, $wp_table, $missing_filter );
				break;
			default: // settings
				$wp_table       = 'options';
				$option_table   = 'kpxtsys_' . $report_id . '__options';
				$error_filter   = " AND field_name IN ('option_name', 'option_value') AND rowid_origin IN ( SELECT kpxtsys_rowid_origin FROM $option_table WHERE kpxtsys_row_type= 3 AND kpxtsys_row_has_diffs = 1 AND option_name IN $this->default_settings_option )  ORDER BY rowid_origin ";
				$missing_filter = " AND option_name IN $this->default_settings_option ";
				$missings_query = $this->get_extra_data_query( $report_id, $wp_table, $missing_filter );
				break;
		}
		if ( ! $missings_query ) {
			$missings_query = $this->get_extra_data_query( $report_id, $wp_table, $missing_filter );
		}
		$errors_query   = $this->get_errors_data_query( $error_filter, $tab, $report_id );
		$sqlite_queries = array(
			'data_type' => $tab,
			'error'     => $errors_query,
			'missing'   => $missings_query,
		);
		return $this->extract_datas( $sqlite_queries );
	}

	/**
	 * Get a report id based on the name of the table.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return false|int|string
	 */
	public function get_report_id( $table_name ) {
		return array_search( $table_name, $this->report_ids, true );
	}

	public function get_report_ids() {
		return array_keys( $this->report_ids );
	}

	/**
	 * Get table name based on the table report id.
	 *
	 * @param int $report_id Report id.
	 *
	 * @return mixed
	 */
	public function get_kpxt_table_name( $report_id ) {
		return $this->report_ids[ $report_id ];
	}
}
