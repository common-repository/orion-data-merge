<?php
/**
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 *
 * @package wms
 */

/**
 * This function generate a new key
 *
 * @param string $hash comment hash key.
 * @return string
 */
function wms_generate_new_key( $hash = 'sha256' ) {

	return hash( $hash, uniqid() );
}
/**
 * This function send dump to KPAX server.
 *
 * @param string $dump_folder_name Folder name.
 * @param string $encryption_key Encrypted key.
 * @param string $response Information of the site to dump.
 * @param string $local_site_url Local site url.
 * @return void
 */
function wms_send_dump_to_kpax( $dump_folder_name, $encryption_key, $response, $local_site_url ) {
	global $wpdb;

	$db_local_site_url = WMS_DATA_MERGE_DIR_BY_URL . $dump_folder_name . '.zip';

	$args = array(
		WMS_LOCAL_SITE_ARCHIVE_PARAM_KEY       => $db_local_site_url,
		WMS_REMOTE_SITE_ARCHIVE_PARAM_KEY      => $response['db_url'],
		WMS_LOCAL_SITE_URL_PARAM_KEY           => $local_site_url,
		WMS_REMOTE_SITE_URL_PARAM_KEY          => $response['site_url'],
		WMS_LOCAL_SITE_TABLE_PREFIX_PARAM_KEY  => $wpdb->prefix,
		WMS_REMOTE_SITE_TABLE_PREFIX_PARAM_KEY => $response['prefix'],
		WMS_KPAX_COMPARISON_ID_PARAM_KEY       => $dump_folder_name,
		WMS_LICENSE_KEY_PARAM_KEY              => uniqid( 'license_key' ),
		WMS_ENCRYPTION_KEY_PARAM_KEY           => $encryption_key,
	);

	$post_reponse = wp_remote_post(
		'https://wpdatamerge.com/index.php/wp-json/wpdms/v1/kpax',
		array(
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(),
			'body'        => $args,
			'cookies'     => array(),
		)
	);

	if ( wp_remote_retrieve_response_code( $post_reponse ) >= 400 ) {
		esc_html_e( 'Fail to call the server.', 'wms' );
		die;
	}

	if ( is_wp_error( $post_reponse ) && 'http_request_failed' === $post_reponse->get_error_code() ) {
		wp_die( '102' );
	}

	$result_url = stripslashes( html_entity_decode( wp_remote_retrieve_body( $post_reponse ) ) );

	$response_decoded = json_decode( wp_remote_retrieve_body( $post_reponse ) );

	if ( isset( $response_decoded->{'102'} ) ) {

		wp_die( '102' );

	} elseif ( isset( $response_decoded->{'200'} ) ) {

		$result_url = stripslashes( $response_decoded->{'200'} );

	} elseif ( isset( $response_decoded->{'404'} ) ) {

		_e( 'An error occurred : we are unable to sync both websites.', 'wms' );
		new WP_Error( 'sync_failed', $response_decoded->get_error_message() );
		wp_die();

	}

	$result_dir       = wms_get_dump_dir( $dump_folder_name . '/result' );
	$result_file_name = $result_dir . $dump_folder_name . '.zip';

	wpdms_dump_archive_download( $result_url, $result_file_name );
	wpdms_decompress_zip_dump( $result_file_name );
	wpdms_zip_archive_files_delete( $result_file_name );

	//$excel_result_url      = wp_get_upload_dir()['baseurl'] . '/WP-DATA-MERGE/' . $dump_folder_name . '/result/KPAX_ORION_COMP_LOCAL_REMOTE.xlsx';
	$wms_excel_result_path = wp_get_upload_dir()['basedir'] . '/WP-DATA-MERGE/' . $dump_folder_name . '/result/KPAX_ORION_COMP_LOCAL_REMOTE.db';
	set_transient( 'wms-results-sqlite-file-path', $wms_excel_result_path, 500 );

	/* translators: %s: kpax result url on client side */
	//printf( __( 'Syncronization successfully completed. Download the result <a href="%s" target="_blank">here</a>', 'wms' ), esc_attr( $excel_result_url ) );
}
/**
 * Dump db and make db zip.
 *
 * @param string $dump_folder_name comment unique id which will be the dump folder name.
 * @param string $encryption_key comment the key uses for encrypt.
 * @param string $dump_file_name comment the dump file name.
 * @return mixed
 */
function wms_dump_db( $dump_folder_name, $encryption_key, $dump_file_name = 'local' ) {
	$dump_dir        = wms_get_dump_dir( $dump_folder_name );
	$sql_path        = $dump_dir . $dump_file_name . '.sql';
	$last_infos_file = $dump_dir . $dump_file_name . '.txt';
	$dump_obj        = new WMS_Dumper( $sql_path, $last_infos_file );

	if ( true === wms_check_requirements() ) {
		if ( true === wms_is_mysqldump_available() ) {
			$path_to_dump = $dump_obj->dump_with_mysqldump();
			if ( is_wp_error( $path_to_dump ) ) {
				return 400;
			} else {
				wms_encrypt_dump_file( $sql_path, $dump_file_name, $dump_folder_name, $encryption_key );
				wms_remove_directory( $dump_dir );
				return 200;
			}
		} else {
			$path_to_dump = $dump_obj->dump_with_php();
			if ( 201 === $path_to_dump ) {
				return wp_json_encode(
					array(
						'dump_folder_name' => $dump_folder_name,
						'dump_file_name'   => $dump_file_name,
						'ajax_site_url'    => admin_url( 'admin-ajax.php' ),
					)
				);
			} else {
				wms_encrypt_dump_file( $sql_path, $dump_file_name, $dump_folder_name, $encryption_key );
				wms_remove_directory( $dump_dir );
				return 200;
			}
		}
	} else {
		return 400;
	}

	die();
}
/**
 * Encrypt dump file content.
 *
 * @param string $path_to_dump Path to the dump file.
 * @param string $dump_file_name Dump filename.
 * @param string $dump_folder_name Dump folder name.
 * @param string $encryption_key Encryption Key used.
 * @return void
 */

function wms_encrypt_dump_file( $path_to_dump, $dump_file_name, $dump_folder_name, $encryption_key ) {
	$enc_file_path = WMS_DATA_MERGE_DIR . $dump_folder_name . '/' . $dump_file_name . '.enc';
	wms_encrypt_file( $path_to_dump, $encryption_key, $enc_file_path );
	$zip = new ZipArchive();
	if ( true === $zip->open( wms_get_root_dir() . $dump_folder_name . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		$zip->addFile( $enc_file_path, $dump_file_name . '.enc' );
		$zip->close();
	} else {
		esc_html_e( 'Zip failed.', 'wms' );
	}
	wp_delete_file( $path_to_dump );
	wp_delete_file( $enc_file_path );
}

/**
 * Encrypt the passed file and saves the result in a new file.
 *
 * @param string $source_file Path to file that should be encrypted.
 * @param string $encryption_key    The key used for the encryption.
 * @param string $destination_file   File name where the encryped file should be written to.
 * @return string|false  Returns the file name that has been created or FALSE if an error occured
 */
function wms_encrypt_file( $source_file, $encryption_key, $destination_file ) {
	$key                   = substr( sha1( $encryption_key, true ), 0, 16 );
	$initialization_vector = openssl_random_pseudo_bytes( 16 );
	if ( ! $initialization_vector ) {
		esc_html_e( 'initialization vector failed.', 'wms' );
		die;
	}

	$file_out = fopen( $destination_file, 'w' ); // write file.
	if ( $file_out ) {
		// Put the initialzation vector to the beginning of the file
		fwrite( $file_out, $initialization_vector );
		$file_in = fopen( $source_file, 'rb' ); // read file.
		if ( $file_in ) {
			while ( ! feof( $file_in ) ) {
				$plaintext = fread( $file_in, 16 * WMS_FILE_ENCRYPTION_BLOCKS );
				$encrypted = openssl_encrypt( $plaintext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initialization_vector );
				// Use the first 16 bytes of the ciphertext as the next initialization vector.
				$initialization_vector = substr( $encrypted, 0, 16 );
				fwrite( $file_out, $encrypted );
			}
			fclose( $file_in );
		} else {
			esc_html_e( 'Encryption failed. Can not open source file.', 'wms' );
			die;
		}
		fclose( $file_out );
	} else {
		esc_html_e( 'Encryption failed. Can not open destination file.', 'wms' );
		die;
	}

	return $destination_file;
}

/**
 * Remove dir and his whole content.
 *
 * @param string $path comment path of the dir.
 * @return void
 */
function wms_remove_directory( $path ) {
	WP_Filesystem();
	global $wp_filesystem;
	$rmdir = $wp_filesystem->rmdir( $path, true );
	if ( true !== $rmdir ) {
		esc_html_e( 'can not remove directory.', 'wms' );
		die;
	}

}
/**
 * Create a file with content provided.
 *
 * @param string $file_path comment path of the file that will content.
 * @param string $content comment content that will be set to the file.
 * @return void
 */
function wms_create_file( $file_path, $content ) {
	WP_Filesystem();
	global $wp_filesystem;
	if ( ! is_file( $file_path ) && ! $wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE ) ) {
		esc_html_e( 'can not write into file.', 'wms' );
		die;
	}
}
/**
 * Get file content.
 *
 * @param string $file comment file that will get the content.
 * @return string
 */
function wms_get_file_contents( $file ) {
	WP_Filesystem();
	global $wp_filesystem;
	$content = $wp_filesystem->get_contents( $file );
	if ( false === $content ) {
		esc_html_e( 'can not get file content.', 'wms' );
		die;
	}
	return $content;
}
/**
 * Create a folder with permissions 0777.
 *
 * @param string $dir comment folder that will be create  path.
 * @return void
 */
function wms_create_dir( $dir ) {
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) ) {
		esc_html_e( 'failed to create directory.', 'wms' );
		die;
	}
}
/**
 * Create and return the path to the dump folder.
 *
 * @return string
 */
function wms_get_root_dir() {

	wms_create_dir( WMS_DATA_MERGE_DIR );
	wms_create_file( WMS_DATA_MERGE_DIR . 'robots.txt', "User-agent: * \nDisallow: /wp-content/uploads/wp-data-merge/" );
	wms_create_file( WMS_DATA_MERGE_DIR . 'index.php', "<?php \n//" );

	return WMS_DATA_MERGE_DIR;
}
/**
 * Create && return a dir give in param.
 *
 * @param string $comparison_id comment the dir name which is a unique id.
 * @return string
 */
function wms_get_dump_dir( $comparison_id = '' ) {
	if ( substr( $comparison_id, -1, 1 ) !== '/' ) {
		$dump_dir = wms_get_root_dir() . $comparison_id . '/';
	} else {
		$dump_dir = wms_get_root_dir() . $comparison_id;
	}
	wms_create_dir( $dump_dir );
	return $dump_dir;
}

/**
 * Get parameters of the results of the comparison.
 *
 * @return array
 */
function wms_get_comparison_results_parameters() {
	global $wpdb;
	$local_site_url = site_url();

	$db_result_folder_key = get_option( 'wms-db-result-key' );
	$db_result_path       = wms_get_dump_dir( $db_result_folder_key . '/result' ) . 'KPAX_ORION_COMP_LOCAL_REMOTE.db';

	$remote_site_info = get_option( 'wms-remote-site-info' );

	$args = array(
		WMS_LOCAL_SITE_URL_PARAM_KEY           => $local_site_url,
		WMS_REMOTE_SITE_URL_PARAM_KEY          => $remote_site_info['site_url'],
		WMS_LOCAL_SITE_TABLE_PREFIX_PARAM_KEY  => $wpdb->prefix,
		WMS_REMOTE_SITE_TABLE_PREFIX_PARAM_KEY => $remote_site_info['prefix'],
		WMS_VIRTUAL_PREFIX                     => 'wpdm_siteurl',
		WMS_DB_RESULT_PATH_PARAM_KEY           => $db_result_path,
	);
	return $args;
}
/**
 * Downloads an archive containing encrypted dump file.
 *
 * @param string $url The url to download from.
 * @param string $downloaded_file_path The path to download to.
 *
 * @return void
 */
function wpdms_dump_archive_download( $url, $downloaded_file_path ) {
	$url      = str_replace( '"', '', $url );
	$tmp_file = download_url( $url, 3600, false );

	$copy_result = copy( $tmp_file, $downloaded_file_path );

	if ( false === $copy_result ) {

		$deletion_result = unlink( $tmp_file );

		if ( false === $deletion_result ) {
			esc_html_e( 'Fail to remove tmp_file after DF.', 'wms' );
			die;
		}
		esc_html_e( 'can\'t copy the download file.', 'wms' );
		die;
	}

	$deletion_result = unlink( $tmp_file );

	if ( false === $deletion_result ) {
		$error_message = __( 'Fail to remove tmp_file after DS.', 'wms' );
		new WP_Error( 'tmp_file_not_found', $error_message );
	}
}

/**
 * Decompresses a zip archive
 *
 * @param string $archive_file_path The zip archive path.
 *
 * @return void
 */
function wpdms_decompress_zip_dump( $archive_file_path ) {

	$request_working_dir_path = dirname( $archive_file_path );

	$zip      = new ZipArchive();
	$open_zip = $zip->open( $archive_file_path );

	if ( true !== $open_zip ) {
		esc_html_e( 'Unable to open archive.', 'wms' );
		die;
	}

	$extract_result = $zip->extractTo( $request_working_dir_path );

	if ( false === $extract_result ) {
		esc_html_e( 'Unable to extract archive.', 'wms' );
		die;
	}
}

/**
 * Deletes a zip archive
 *
 * @param string $archive_file_path The zip archive path.
 *
 * @return void
 */
function wpdms_zip_archive_files_delete( $archive_file_path ) {

	$zip_archive_deletion_result = unlink( $archive_file_path );

	if ( false === $zip_archive_deletion_result ) {
		$error_message = __( 'Unable to delete archive .', 'wms' );
		new WP_Error( 'wms_can_delete_archive', $error_message );
	}
}

/**
 * Check requirements by calling all check functions.
 *
 * @return string|true
 */
function wms_check_requirements() {
	if ( ! wms_check_zip_available() ) {
		return __( 'php zip extension is not enable. This extension is required.', 'wms' );
	}
	if ( ! wms_check_sqlite3_available() ) {
		return __( 'sqlite3 extension is not enable. This extension is required.', 'wms' );
	}
	if ( wms_is_localhost() ) {
		return wms_is_localhost();
	}
	return true;
}

/**
 * Checks if mysqldump is available.
 *
 * @return string|true
 */
function wms_is_mysqldump_available() {
	if ( ! function_exists( 'exec' ) ) {
		return __( 'exec function is not enable. This function is required.', 'wms' );
	}
	if ( ! wms_check_active_progams() ) {
		return __( 'Mysqldump is not available on your server, a php script will be used to export the database and this may consume a little more time.', 'wms' );
	}
	return true;
}
/**
 * Checks if the site is running into local.
 *
 * @return string|true
 */
function wms_is_localhost() {
	if ( ( isset( $_SERVER['SERVER_ADDR'] ) && ( strpos( $_SERVER['SERVER_ADDR'], '::1' ) !== false || strpos( $_SERVER['SERVER_ADDR'], '127.0.0.1' ) !== false ) ) ||
	( isset( $_SERVER['SERVER_NAME'] ) && ( strpos( $_SERVER['SERVER_NAME'], 'localhost' ) !== false || strpos( $_SERVER['SERVER_NAME'], '127.0.0.1' ) !== false ) ) ) {
		return __( 'Does not work with local servers', 'wms' );
	}
	return false;
}

/**
 * Check if zip extension is active
 *
 * @return boolean
 */
function wms_check_zip_available() {
	if ( extension_loaded( 'zip' ) ) {
		return true;
	}
	return false;
}
/**
 * Check if sqlite3 extension is active
 *
 * @return boolean
 */
function wms_check_sqlite3_available() {
	if ( extension_loaded( 'sqlite3' ) ) {
		return true;
	}
	return false;
}
/**
 * Check if programs(sqlite3, mysqldump) are actives
 *
 * @return boolean
 */
function wms_check_active_progams() {
	if ( function_exists( 'exec' ) ) {
		$mysqldump_path = get_option( 'msd_path' );
		exec( $mysqldump_path . ' --version', $output, $return_var );
		if ( 0 === $return_var ) {
			return true;
		}
	}
	return false;
}
/**
 * Function to load allowed lsd tags in order to properly escape outputs.
 *
 * @return array
 */
function wms_get_allowed_tags() {
	$allowed_tags = wp_kses_allowed_html( 'post' );

	$allowed_tags['li'] = array(
		'id'             => array(),
		'name'           => array(),
		'class'          => array(),
		'value'          => array(),
		'style'          => array(),
		'data-ttf'       => array(),
		'data-fonturl'   => array(),
		'data-fontname'  => array(),
		'data-color'     => array(),
		'data-minwidth'  => array(),
		'data-minheight' => array(),
	);

	$allowed_tags['br'] = array();

	$allowed_tags['input'] = array(
		'type'           => array(),
		'id'             => array(),
		'name'           => array(),
		'style'          => array(),
		'class'          => array(),
		'value'          => array(),
		'min'            => array(),
		'max'            => array(),
		'row_class'      => array(),
		'selected'       => array(),
		'checked'        => array(),
		'readonly'       => array(),
		'placeholder'    => array(),
		'step'           => array(),
		'data-fonturl'   => array(),
		'data-fontname'  => array(),
		'data-minwidth'  => array(),
		'data-minheight' => array(),
		'readonly'       => array(),
		'autocomplete'   => array(),
		'autocorrect'    => array(),
		'autocapitalize' => array(),
		'spellcheck'     => array(),
	);
	$allowed_tags['form']  = array(
		'accept-charset' => array(),
		'id'             => array(),
		'name'           => array(),
		'style'          => array(),
		'class'          => array(),
		'value'          => array(),
		'action'         => array(),
		'autocomplete'   => array(),
		'row_class'      => array(),
		'novalidate'     => array(),
		'method'         => array(),
		'readonly'       => array(),
		'target'         => array(),
		'data-fonturl'   => array(),
		'data-fontname'  => array(),
		'data-minwidth'  => array(),
		'data-minheight' => array(),
		'autocorrect'    => array(),
		'autocapitalize' => array(),
		'hidden'         => array(),
	);

	$allowed_tags['div'] = array(
		'id'                   => array(),
		'name'                 => array(),
		'style'                => array(),
		'data-id'              => array(),
		'class'                => array(),
		'row_class'            => array(),
		'role'                 => array(),
		'aria-labelledby'      => array(),
		'aria-hidden'          => array(),
		'role'                 => array(),
		'data-fonturl'         => array(),
		'data-minwidth'        => array(),
		'data-minheight'       => array(),
		'data-tooltip-content' => array(),
		'tabindex'             => array(),
		'style'                => array(),
		'data-tooltip-title'   => array(),
		'data-placement'       => array(),
	);

	$allowed_tags['button'] = array(
		'id'           => array(),
		'name'         => array(),
		'class'        => array(),
		'value'        => array(),
		'data-tpl'     => array(),
		'style'        => array(),
		'data-id'      => array(),
		'data-dismiss' => array(),
		'aria-hidden'  => array(),
	);

	$allowed_tags['body'] = array(
		'id'                 => array(),
		'name'               => array(),
		'class'              => array(),
		'data-gr-c-s-loaded' => array(),
	);

	$allowed_tags['a']      = array(
		'id'               => array(),
		'name'             => array(),
		'class'            => array(),
		'data-tpl'         => array(),
		'href'             => array(),
		'data-toggle'      => array(),
		'data-target'      => array(),
		'data-modalid'     => array(),
		'target'           => array(),
		'data-tpl'         => array(),
		'data-group'       => array(),
		'data-slide-index' => array(),
		'download'         => array(),
	);
	$allowed_tags['select'] = array(
		'id'       => array(),
		'name'     => array(),
		'class'    => array(),
		'data-tpl' => array(),
		'style'    => array(),
		'multiple' => array(),
		'tabindex' => array(),
	);
	$allowed_tags['option'] = array(
		'id'       => array(),
		'name'     => array(),
		'class'    => array(),
		'value'    => array(),
		'style'    => array(),
		'selected' => array(),
		'tabindex' => array(),
	);

	$allowed_tags['span'] = array(
		'id'                 => array(),
		'name'               => array(),
		'class'              => array(),
		'value'              => array(),
		'style'              => array(),
		'data-tooltip-title' => array(),
		'data-placement'     => array(),
	);

	$allowed_tags['style'] = array();

	$allowed_tags['textarea'] = array(
		'autocomplete'   => array(),
		'autocorrect'    => array(),
		'autocapitalize' => array(),
		'spellcheck'     => array(),
		'class'          => array(),
	);
	return $allowed_tags;
}

if ( ! function_exists( 'array_key_first' ) ) {
	/**
	 * Get the first key of the given array without affecting the internal array pointer.
	 * This is a polyfill of the function array_key_first.
	 *
	 * @param array $array An array.
	 * @return string|int|null
	 */
	function array_key_first( array $array ) {
		if ( count( $array ) ) {
			reset( $array );
			return key( $array );
		}
		return null;
	}
}




function wms_get_string_differences( $local, $remote ) {
	$from_start  = strspn( $local ^ $remote, "\0" );
	$from_end    = strspn( strrev( $local ) ^ strrev( $remote ), "\0" );
	$local_end   = strlen( $local ) - $from_end;
	$remote_end  = strlen( $remote ) - $from_end;
	$start       = substr( $remote, 0, $from_start );
	$end         = substr( $remote, $remote_end );
	$remote_diff = substr( $remote, $from_start, $remote_end - $from_start );
	$local_diff  = substr( $local, $from_start, $local_end - $from_start );
	$remote      = "$start<span style='color:red'>$remote_diff</span>$end";
	$local       = "$start<span style='color:blue'>$local_diff</span>$end";

	return array(
		'local'  => $local,
		'remote' => $remote,
	);
}

function wms_get_linked_post_display( $post_id, $data_type = 'missing' ) {
	$linked_post = '';
	if ( 'error' === $data_type ) {
		$post        = get_post( $post_id );
		$linked_post = '<strong>' . strtoupper( $post->post_type ) . '</strong>: <a href="' . $post->guid . '">' . $post->post_title . '</a><br/>';
	} else {
		global $sqlite_obj;
		$report_id       = $sqlite_obj->get_report_id( 'posts' );
		$kpxt_post_table = 'kpxtsys_' . $report_id . '__posts';
		$post_query      = " SELECT post_title, guid, post_type FROM $kpxt_post_table WHERE ID = $post_id ";
		$post            = $sqlite_obj->get_db_object()->query( $post_query );
		$rows            = $post->fetchArray( SQLITE3_ASSOC );
		$linked_post     = '<strong>' . strtoupper( $rows['post_type'] ) . '</strong>: <a href="' . $rows['guid'] . '">' . $rows['post_title'] . '</a><br/>';
	}
	return $linked_post;
}

function wms_get_data_from_missing_table( $id, $tab ) {
	global $sqlite_obj;
	$tabs      = $sqlite_obj->get_tabs();
	$report_id = $sqlite_obj->get_report_id( $tabs[ $tab ] );
	$table     = 'kpxtsys_' . $report_id . '__' . strtolower( $tabs[ $tab ] );
	if ( 'post' === $tab || 'user' === $tab ) {
		$id_identifier = 'ID';
	} elseif ( 'term' === $tab ) {
		$id_identifier = 'term_id';
	} elseif ( 'comment' === $tab ) {
		$id_identifier = 'comment_ID';
	} else {
		$id_identifier = 'option_id';
	}
	$query    = " SELECT * FROM $table WHERE $id_identifier = $id ";
	$response = $sqlite_obj->get_db_object()->query( $query );
	$rows     = $response->fetchArray( SQLITE3_ASSOC );
	return $rows;
}

function wms_get_term_posts( $term_id, $taxonomy, $data_type = 'error' ) {
	$linked_posts = '';
	if ( 'error' === $data_type ) {
		$args  = array(
			'post_type' => array( 'post', 'page' ),
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);
		$posts = get_posts( $args );
		foreach ( $posts as $post ) {
			$linked_posts .= '<strong>' . strtoupper( $post->post_type ) . '</strong>: <a href="' . $post->guid . '">' . $post->post_title . '</a><br/>';
		}
	} else {
		global $sqlite_obj;
		$report_id = $sqlite_obj->get_report_id( 'term_relationships' );
		if ( $report_id ) {
			$relationship_table = 'kpxtsys_' . $report_id . '__term_relationships';
			$post_query         = " SELECT object_id FROM $relationship_table WHERE term_taxonomy_id = $term_id ";
			$post               = $sqlite_obj->get_db_object()->query( $post_query );
			$rows               = $post->fetchArray( SQLITE3_ASSOC );
			if ( isset( $rows['object_id'] ) ) {
				$post_id      = $rows['object_id'];
				$linked_posts = wms_get_linked_post_display( $post_id );
			}
		}
	}
	return $linked_posts;
}

function wms_get_local_tag_details( $id, $tab, $field = false ) {
	if ( 'post' === $tab ) {
		$post    = get_post( $id );
		$details = '<strong>' . $post->post_type . '</strong>: <a href="' . $post->guid . '">' . $post->post_title . '</a>';
	} elseif ( 'term' === $tab ) {
		$term    = get_term( $id, $tab );
		$details = '<strong>' . strtoupper( $tab ) . '</strong>: ' . $term->name;
	} elseif ( 'comment' === $tab ) {
		$comment  = get_comment( $id );
		$details  = '<strong> ' . __( 'Author', 'wms' ) . '</strong>: ' . $comment->comment_author . ' ( ' . $comment->comment_author_email . ' )<br/>';
		$details .= '<strong>' . __( 'Posted date', 'wms' ) . '</strong>: ' . $comment->comment_date;
	} elseif ( 'user' === $tab ) {
		$user    = get_user_by( 'id', $id );
		$details = '<strong>' . strtoupper( $tab ) . '</strong>: ' . $user->display_name . ' ( ' . $user->user_email . ' )';
	} elseif ( 'template' === $tab ) {
		$details = __( 'Active Theme', 'wms' );
	} else {
		$field_name = wms_get_field_name( $field );
		$details    = $field_name;
	}
	return $details;
}

function wms_get_tab_details( $id, $tag, $data_type = false, $remote_post_url = null ) {
	$details = '';
	$data    = wms_get_data_from_missing_table( $id, $tag );

	if ( 'post' === $tag ) {
		if ( 2 === $data['kpxtsys_row_type'] && $remote_post_url) {
			$post_url = $remote_post_url . '?p=' . $id;
		} else {
			$post_url = $data['guid'];
		}
		$details = '<a href="' . $post_url . '" target="_blank">' . $data['post_title'] . '</a>';
	} elseif ( 'comment' === $tag ) {
		$details  = $data['comment_author'] . ' ( ' . $data['comment_author_email'] . ' )<br/>';
		$details .= $data['comment_date'];
	} elseif ( 'user' === $tag ) {
		$details = '<strong>User</strong>: ' . $data['display_name'] . ' ( ' . $data['user_email'] . ' )';
	} elseif ( 'extension' === $tag ) {
		$details = __( 'Active ', 'wms' ) . $tag;
	} elseif ( 'settings' === $tag ) {
		$details = '';
	} else {
		$data    = wms_get_taxonomy( $id, $data_type );
		$details = $data['taxonomy'];
	}
	return $details;
}

function wms_get_taxonomy( $term_id, $data_type ) {
	global $sqlite_obj;
	$data = array();

	if ( 'error' === $data_type ) {
		$term             = get_term( $term_id );
		$data['taxonomy'] = $term->taxonomy;
		$data['name']     = $term->name;
	} else {
		$report_id1       = $sqlite_obj->get_report_id( 'term_taxonomy' );
		$table1           = 'kpxtsys_' . $report_id1 . '__term_taxonomy';
		$report_id2       = $sqlite_obj->get_report_id( 'terms' );
		$table2           = 'kpxtsys_' . $report_id2 . '__terms';
		$term_query       = " SELECT taxonomy, name FROM $table1 as A INNER JOIN $table2 as B on B.term_id = A.term_taxonomy_id WHERE term_taxonomy_id = $term_id";
		$term             = $sqlite_obj->get_db_object()->query( $term_query );
		$rows             = $term->fetchArray( SQLITE3_ASSOC );
		$data['taxonomy'] = $rows['taxonomy'];
		$data['name']     = $rows['name'];
	}
	return $data;
}



function wms_get_value( $db_object, $sql_query ) {
	$tables_query = $db_object->query( $sql_query );
	$query_result = $tables_query->fetchArray( SQLITE3_ASSOC );
	if ( ! is_array( $query_result ) ) {
		$query_result = array();
	}
	return $query_result;
}

function wms_get_tag_header_infos( $tab ) {
	$default_fields = array(
		'diff_type' => __( 'Difference Type', 'wms' ),
	);
	if ( 'post' === $tab ) {
		$default_fields['title'] = __( 'Title', 'wms' );
	} elseif ( 'term' === $tab ) {
		$default_fields[ $tab ]         = __( ' Name', 'wms' );
		$default_fields['linked_posts'] = __( 'Linked Posts', 'wms' );
	} elseif ( 'comment' === $tab ) {
		$default_fields[ $tab ]         = __( 'Comment Details', 'wms' );
		$default_fields['linked_posts'] = __( 'Linked Posts', 'wms' );
	} elseif ( 'settings' === $tab ) {
		$default_fields['option_name'] = __( 'Name', 'wms' );
	} elseif ( 'user' === $tab ) {
		$default_fields['user_data'] = __( 'User datails' );
	} elseif ( 'extension' === $tab ) {
		$default_fields['ext_name'] = __( 'Name' );
	}
	$default_fields['local']  = __( 'Local site', 'wms' );
	$default_fields['remote'] = __( 'Remote site', 'wms' );
	//$default_fields['actions'] = __( 'Actions', 'wms' );
	return $default_fields;
}


function wms_build_table_tag_header( $tab, $tab_infos, $display = false ) {
	$html  = "<table id='wms-table-" . $tab . "-tb' class='display table table-striped table-bordered cell-border	row-border order-column' style='width:100%'><thead>";
	$html .= "<tr class='wms-$tab'>";
	foreach ( $tab_infos as $header_attr => $header_name ) {
		$html .= '<th>' . $header_name . '</th>';
	}
	$html .= '</tr></thead>';
	return $html;
}

function wms_build_table_tag_body( $tab_infos, $data_errors, $display = false ) {
	$linked_posts   = '';
	$ignore_action  = __( 'Ignore', 'wms' );
	$local_action   = __( 'Copy from local site to remote site', 'wms' );
	$remote_action  = __( 'Copy from remote site to local site', 'wms' );
	$action_display = '<select name="wpdms-action"><option value="no-action">' . $ignore_action . '</option><option value="remote-local">' . $local_action . '</option><option value="remote-local">' . $remote_action . '</option></select>';

	$display_code = 'display : none;';
	if ( $display ) {
		$display_code = 'display : table-row;';
	}

	$html = '<tbody>';
	foreach ( $data_errors as $tab => $datas ) {
		$remote_site = get_option('wms-last-remote-site');
		foreach ( $datas as $id => $data ) {
			if ( $remote_site ) {
				$details = wms_get_tab_details( $id, $tab, $data['type'], $remote_site->get_url() );
			} else {
				$details = wms_get_tab_details( $id, $tab, $data['type'] );
			}

			$html .= '<tr>';

			$local_display  = '<ul>';
			$remote_display = '<ul>';

			if ( 'extension' === $tab ) {
				if ( isset( $data['data']['option_value'] ) ) {
					$local_values  = maybe_unserialize( $data['data']['option_value']['LOCAL'] );
					$remote_values = maybe_unserialize( $data['data']['option_value']['REMOTE'] );
					if ( is_array( $local_values ) ) {
						$diffences  = array_diff( $local_values, $remote_values );
						$diffences += array_diff( $remote_values, $local_values ); // To be sure that the differences on both sides are present because array_diff only takes into account the differences of the first parameter.
						foreach ( $diffences as $value ) {
							if ( in_array( $value, $local_values, true ) ) {
								$local_display .= '<span style="color:blue;">' . $value . '<br/>';
							} elseif ( in_array( $value, $remote_values, true ) ) {
								$remote_display .= '<span style="color:red;">' . $value . '<br/>';
							}
							$diff_type = __( 'Different plugins', 'wms' );
							$details   = __( 'Active plugins', 'wms' );
						}
					} else {
						$local_value  = $data['data']['option_value']['LOCAL'];
						$remote_value = $data['data']['option_value']['REMOTE'];
						$diff_type    = __( 'Different theme', 'wms' );
						$details      = __( 'Active theme', 'wms' );

						$local_display  = '<span style="color:blue;">' . $local_value . '</span><br/>';
						$remote_display = '<span style="color:red;">' . $remote_value . '</span><br/>';
					}
				}
			} else {
				if ( 'settings' === $tab ) {
					$option      = wms_get_option_name( $id, $data );
					$option_name = wms_get_field_name( $option );
					$details     = $option_name;
					if ( isset( $data['data']['option_value'] ) ) {
						$local       = maybe_unserialize( $data['data']['option_value']['LOCAL'] );
						$remote      = maybe_unserialize( $data['data']['option_value']['REMOTE'] );
						$diff_type   = __( 'Different ', 'wms' ) . $option;

						$data['data'][ $option_name ] = array(
							'LOCAL'  => $local,
							'REMOTE' => $remote,
						);
						unset( $data['data']['option_value'] );
					}
				}
				if ( 'error' === $data['type'] ) {
					if ( ! isset( $option ) ) {
						if ( 'post' === $tab ) {
							$diff_type = __( 'Different ', 'wms' ) . $data['post_type'];
						} else {
							$diff_type = __( 'Different ', 'wms' ) . $tab;
						}
					}
					foreach ( $data['data'] as $field_name => $value ) {
						if ( 'post_content' === $field_name || 'post_excerpt' === $field_name ) {
							$post_url     = get_post_permalink( $id );
							if ( $remote_site ) {
								$remote_post_url = $remote_site->get_url() . '?post_type=post&p=' . $id;
							} else {
								$remote_post_url = $post_url;
							}
							$local_value         = "<a href='$post_url' target='_blank'>" . __( 'click to view', 'wms' ) . '</a>';
							$remote_value        = "<a href='$remote_post_url' target='_blank'>" . __( 'Different here', 'wms' ) . '</a>';
							$diffs_string_local  = $local_value;
							$diffs_string_remote = $remote_value;
						} else {
							$local_value  = $value['LOCAL'];
							$remote_value = $value['REMOTE'];

							$diffs_string        = wms_get_string_differences( trim( $local_value ), trim( $remote_value ) );
							$diffs_string_local  = $diffs_string['local'];
							$diffs_string_remote = $diffs_string['remote'];
						}
						$local_display  .= '<li><strong>' . wms_get_field_name( $field_name ) . '</strong>: ' . $diffs_string_local . '</li><br/>';
						$remote_display .= '<li><strong>' . wms_get_field_name( $field_name ) . '</strong>: ' . $diffs_string_remote . '</li><br/>';
					}
					$local_display  .= '</ul>';
					$remote_display .= '</ul>';
				} else {
					if ( 'post' === $tab ) {
						$rows      = $data['data']['rows'];
						$diff_type = __( 'Missing ', 'wms' ) . $rows['post_type'];
					} else {
						$diff_type = __( 'Missing ', 'wms' ) . $tab;
					}
					if ( 1 === $data['data']['row_type'] ) {
						$local_display  .= __( 'Detected here', 'wms' );
						$remote_display .= __( 'Missing here', 'wms' );
					} else {
						$remote_display .= __( 'Detected here', 'wms' );
						$local_display  .= __( 'Missing here', 'wms' );
					}
				}
			}
			if ( 4 < count( $tab_infos ) ) { // Terms and comments
				$linked_data = wms_get_data_from_missing_table( $id, $tab );
				if ( 'post_tag' === $details ) {
					$name = 'tag';
				} else {
					$name      = $details;
					$term_name = $details;
				}
				if ( isset( $linked_data['comment_post_ID'] ) ) {
					$linked_posts = wms_get_linked_post_display( $linked_data['comment_post_ID'], $data['type'] );
				} else {
					$details      = wms_get_taxonomy( $id, $data['type'] );
					$linked_posts = wms_get_term_posts( $id, $details['taxonomy'], $data['type'] );
					if ( 'missing' === $data['type'] ) {
						$diff_type = __( 'Missing ', 'wms' ) . $name;
					} else {
						$diff_type = __( 'Different ', 'wms' ) . $name;
					}
					$term_name = $details['name'];
				}
				if ( empty( $linked_posts ) ) {
					$linked_posts = __( 'None', 'wms' );
				}
				$html .= '<td>' . $diff_type . '</td>';
				$html .= '<td>' . $term_name . '</td>';
				$html .= '<td>' . $linked_posts . '</td>';
			} else {
				$html .= '<td>' . $diff_type . '</td>';
				$html .= '<td>' . $details . '</td>';
			}
			$html .= '<td>' . $local_display . '</td>';
			$html .= '<td>' . $remote_display . '</td>';
			//$html .= '<td>' . $action_display . '</td>';
			$html .= '</tr>';
		}
	}
	$html .= '</tbody></table>';
	return $html;
}

function wms_get_option_name( $id, $data = null ) {
	global $sqlite_obj;
	$report_id    = $sqlite_obj->get_report_id( 'options' );
	$option_table = 'kpxtsys_' . $report_id . '__options';
	if ( 'missing' === $data['type'] && $data ) {
		$kpxtsys_row_type = $data['data']["rows"]['kpxtsys_row_type'];
		$query            = " SELECT option_name FROM $option_table WHERE option_id = $id AND kpxtsys_row_type = $kpxtsys_row_type";
	} else {
		$query = " SELECT option_name FROM $option_table WHERE option_id = $id";
	}
	$options = $sqlite_obj->get_db_object()->query( $query );
	$rows    = $options->fetchArray( SQLITE3_ASSOC );
	return $rows['option_name'];
}

function wms_get_tab_name( $tab ) {
	if ( 'post' === $tab ) {
		$tab = __( 'Posts / Pages', 'wms' );
	} elseif ( 'term' === $tab ) {
		$tab = __( 'Categories / Tags', 'wms' );
	} elseif ( 'extension' === $tab ) {
		$tab = __( 'Plugins / Themes', 'wms' );
	} elseif ( 'comment' === $tab ) {
		$tab = __( 'Comments', 'wms' );
	} elseif ( 'user' === $tab ) {
		$tab = __( 'USERS', 'wms' );
	}
	return $tab;
}

function wms_get_field_name( $field_name ) {
	switch ( $field_name ) {
		case 'post_author':
		case 'comment_author':
			$field_name = __( 'Author', 'wms' );
			break;
		case 'post_content':
		case 'comment_content':
			$field_name = __( 'Content', 'wms' );
			break;
		case 'post_title':
			$field_name = __( 'Title', 'wms' );
			break;
		case 'post_excerpt':
			$field_name = __( 'Excerpt', 'wms' );
			break;
		case 'post_status':
			$field_name = __( 'Status', 'wms' );
			break;
		case 'post_password':
			$field_name = __( 'Password', 'wms' );
			break;
		case 'guid':
			$field_name = __( 'URL', 'wms' );
			break;
		case 'comment_author_email':
			$field_name = __( 'Author email', 'wms' );
			break;
		case 'blogname':
			$field_name = __( 'Site title', 'wms' );
			break;
		case 'blogdescription':
			$field_name = __( 'Tagline', 'wms' );
			break;
		case 'new_admin_email':
			$field_name = __( 'Administration Email Address', 'wms' );
			break;
		case 'default_role':
			$field_name = __( 'New User Default Role', 'wms' );
			break;
		case 'WPLANG':
			$field_name = __( 'Site language', 'wms' );
			break;
		case 'timezone_string':
			$field_name = __( 'Timezone', 'wms' );
			break;
		case 'date_format_custom':
			$field_name = __( 'Date Format', 'wms' );
			break;
		case 'time_format_custom':
			$field_name = __( 'Time Format', 'wms' );
			break;
		case 'start_of_week':
			$field_name = __( 'Week Starts On', 'wms' );
			break;
		case 'default_category':
			$field_name = __( 'Default Post Category', 'wms' );
			break;
		case 'default_post_format':
			$field_name = __( 'Default Post Format', 'wms' );
			break;
		case 'mailserver_url':
			$field_name = __( 'Mail Server URL', 'wms' );
			break;
		case 'mailserver_port':
			$field_name = __( 'Mail Server Port', 'wms' );
			break;
		case 'mailserver_pass':
			$field_name = __( 'Mail Server Password', 'wms' );
			break;
		case 'mailserver_login':
			$field_name = __( 'Mail Server login', 'wms' );
			break;
		case 'default_email_category':
			$field_name = __( 'Default Mail Category', 'wms' );
			break;
		case 'ping_sites':
			$field_name = __( 'Ping', 'wms' );
			break;
		case 'show_on_front':
			$field_name = __( 'Your homepage displays', 'wms' );
			break;
		case 'page_on_front':
			$field_name = __( 'Homepage', 'wms' );
			break;
		case 'page_for_posts':
			$field_name = __( 'Posts page', 'wms' );
			break;
		case 'posts_per_page':
			$field_name = __( 'Blog pages show at most', 'wms' );
			break;
		case 'posts_per_rss':
			$field_name = __( 'Syndication feeds show the most recent', 'wms' );
			break;
		case 'rss_use_excerpt':
			$field_name = __( 'For each post in a feed, include', 'wms' );
			break;
		case 'blog_public':
			$field_name = __( 'Search engine visibility', 'wms' );
			break;
		default:
			$field_name = ucfirst( str_replace( '_', ' ', $field_name ) );
			break;
	}
	return $field_name;
}
