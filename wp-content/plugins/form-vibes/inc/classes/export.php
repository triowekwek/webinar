<?php
// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
namespace FormVibes\Classes;

use FormVibesPro\Classes\Helper;
use FormVibes\Classes\Utils;
class Export {

	/**
	 * __construct
	 *
	 * @param  mixed $params $params A var.
	 * @return void
	 */
	public function __construct( $params ) {
		if ( '' !== $params ) {

			$this->export_to_csv( $params );
		}

		add_action( 'wp_ajax_fv_export_data', [ $this, 'fv_export_data' ] );
		add_action( 'wp_ajax_fv_delete_export_file', [ $this, 'fv_delete_export_file' ] );
		add_action( 'wp_ajax_fv_set_export_meta_data', [ $this, 'fv_set_export_meta_data' ] );
		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'form-vibes';
		if ( ! file_exists( $uploads_dir ) ) {
			wp_mkdir_p( $uploads_dir );
		}
		add_action( 'init', [ $this, 'fv_export_csv' ] );
	}

	public function fv_export_csv() {

		if ( isset( $_POST['btnExport'] ) ) {

			if ( ! wp_verify_nonce( $_POST['fv_nonce'], 'fv_ajax_nonce' ) ) {
				die( 'Sorry, your nonce did not verify!' );
			}

			$params = (array) json_decode( stripslashes( $_REQUEST['fv_export_data'] ) );

			new Export( $params );
		}
	}

	/**
	 * Exports the data.
	 *
	 * @return void
	 */
	public function fv_export_data() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		$post_id = $_POST['post_id'];

		$file_name = $_POST['fileName'];

		$offset = $_POST['offset'];

		$total           = $_POST['total'];
		$profile         = $this->get_saved_data( $post_id );
		$filter_relation = 'OR';
		$fv_settings     = get_option( 'fvSettings' );

		if ( $fv_settings && array_key_exists( 'csv_export_reason', $fv_settings ) && $fv_settings['csv_export_reason'] && 0 === (int) $offset ) {
			$this->set_export_reason( $_POST['csv_reason'] );
		}
		if ( $fv_settings && array_key_exists( 'fv_filter_relation', $profile ) ) {
			$filter_relation = $profile['fv_filter_relation'];
		}
		if ( '' === $profile['fv_data_source'] ) {
			wp_send_json(
				[
					'error'      => true,
					'error_type' => 'no_data_source',
				]
			);
		}
		list($plugin, $form) = Helper::get_form_plugin( $profile['fv_data_source'] );
		$form_plugins        = apply_filters( 'fv_forms', [] );
		if ( ! array_key_exists( $plugin, $form_plugins ) ) {
			wp_send_json(
				[
					'error'      => true,
					'error_type' => 'plugin_disabled',
				]
			);
		}
		$submissions = new Submissions( $plugin );
		$limit       = 100;

		$limit = apply_filters( 'formvibes/export/batch_size', $limit );

		$fv_status = [];
		$temp      = $profile['fv_status'] === '' ? [] : $profile['fv_status'];
		foreach ( $temp as $status ) {
			array_push( $fv_status, $status->value );
		}
		$params = [
			'plugin'          => $plugin,
			'offset'          => (int) $offset,
			'formid'          => $form,
			'query_type'      => $profile['fv_date_range']['queryType'],
			'fromDate'        => $profile['fv_date_range']['fromDate'],
			'toDate'          => $profile['fv_date_range']['toDate'],
			'export'          => true,
			'export_profile'  => true,
			'query_filters'   => $profile['fv_filters'],
			'filter_relation' => $filter_relation,
			'limit'           => $limit,
			'per_page'        => $limit,
			'page_num'        => 1,
			'status'          => $fv_status,
		];

		$data              = $submissions->get_submissions( $params );
		$total_entry_count = $data['total_submission_count'];
		$submission_data   = $data['submissions'];
		$csv_filename      = $file_name;
		if ( '' === $csv_filename ) {
			$csv_filename = $plugin . '-' . $form . '-' . strtotime( 'now' );
		}

		$cols = $profile['fv_fields'];

		$columns = $this->prepare_columns( $cols );

		$path               = wp_get_upload_dir()['basedir'] . '/form-vibes/' . $csv_filename . '.csv';
		$download_file_path = '';
		$this->write_to_csv( $columns, $submission_data, $csv_filename, 0 === (int) $offset, $path );
		$processed = $limit + $offset;
		if ( $processed >= $total_entry_count ) {
			$processed          = (int) $total_entry_count;
			$download_file_path = wp_get_upload_dir()['baseurl'] . '/form-vibes/' . $csv_filename . '.csv';
		}
		wp_send_json(
			[
				'post_id'   => (int) $post_id,
				'total'     => (int) $total_entry_count,
				'processed' => $processed,
				'filename'  => $csv_filename,
				'path'      => $download_file_path,
				'error'     => false,
			]
		);
	}

	private function write_to_csv( $columns, $data, $csv_filename, $head, $path ) {
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Cache-Control: private', false );
		header( 'Content-Type: text/csv;charset=utf-8' );
		header( 'Content-Disposition: attachment;filename=' . $csv_filename . '.csv' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$fp        = fopen( $path, 'a' );
		$col_keys  = array_keys( $columns );
		$col_count = count( $col_keys );

		$fv_status_arr = Utils::get_fv_status();
		$fv_status     = [];
		foreach ( $fv_status_arr as $value ) {
			$fv_status[ $value['key'] ] = $value['label'];
		}

		if ( isset( $data ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
			fwrite( $fp, "\xEF\xBB\xBF" );
			if ( $head ) {
				fputcsv( $fp, array_values( $columns ) );
			}
			foreach ( $data as $value ) {
				$temp = [];
				for ( $i = 0; $i < $col_count; $i++ ) {
					if ( array_key_exists( $col_keys[ $i ], $value ) ) {
						$temp[ $col_keys[ $i ] ] = stripslashes( $value[ $col_keys[ $i ] ] );
					} else {
						$temp[] = '';
					}
				}
				if ( array_key_exists( 'fv_status', $temp ) ) {

					$status_key = $temp['fv_status'];
					if ( array_key_exists( $status_key, $fv_status ) ) {
						$temp['fv_status'] = $fv_status[ $status_key ];
					} else {
						$temp['fv_status'] = 'Unread';
					}
				}
				fputcsv( $fp, $temp, ',', '"' );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $fp );
	}

	private function prepare_columns( $columns ) {

		$new_columns = [];
		foreach ( $columns['cols'] as $key => $value ) {
			if ( array_key_exists( 'visible', $value ) ) {
				if ( $value['visible'] ) {
					$new_columns[ $key ] = $value['alias'];
				}
			}
		}
		return $new_columns;
	}

	public function fv_set_export_meta_data() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}
		$response      = [];
		$filename      = $_POST['filename'];
		$post_id       = $_POST['post_id'];
		$filepath      = $_POST['filepath'];
		$timestamp     = current_time( 'mysql', 0 );
		$timestamp_gmt = current_time( 'mysql', 1 );
		$user_id       = get_current_user_id();
		$data          = [
			'timestamp'     => $timestamp,
			'timestamp_gmt' => $timestamp_gmt,
			'file_name'     => $filename,
			'user_id'       => $user_id,
			'filepath'      => $filepath,
		];
		$post_meta     = get_post_meta( $post_id, 'export_files', true );
		if ( '' === $post_meta ) {
			$meta = update_post_meta( $post_id, 'export_files', [ $data ] );
			if ( $meta ) {
				$response = [
					'status'  => 'success',
					'message' => 'Data successfully added.',
				];
			} else {
				$response = [
					'status'  => 'failed',
					'message' => 'Could not add the data.',
				];
			}
		} else {
			array_push( $post_meta, $data );
			$meta = update_post_meta( $post_id, 'export_files', $post_meta );
			if ( $meta ) {
				$response = [
					'status'  => 'success',
					'message' => 'Data successfully added.',
				];
			} else {
				$response = [
					'status'  => 'failed',
					'message' => 'Could not add the data.',
				];
			}
		}
		wp_send_json( $response );
	}

	public function fv_delete_export_file() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}
		$filename = $_POST['fileName'];
		$post_id  = $_POST['post_id'];
		$response = [];
		if ( '' !== $filename ) {

			$path                  = wp_get_upload_dir()['basedir'] . '/form-vibes/' . $filename . '.csv';
			$export_file_post_meta = get_post_meta( $post_id, 'export_files', true );
			$key                   = array_search( $filename, array_column( $export_file_post_meta, 'file_name' ), true );
			unset( $export_file_post_meta[ $key ] );
			$export_file_post_meta = array_values( $export_file_post_meta );
			$meta                  = update_post_meta( $post_id, 'export_files', $export_file_post_meta );
			if ( $meta ) {
				$response = [
					'status'  => 'success',
					'message' => 'Deletion Successful.',
				];
			} else {
				$response = [
					'status'  => 'failed',
					'message' => 'Deletion Failed.',
				];
			}
		} else {
			$response = [
				'status'  => 'file_not_found',
				'message' => 'Deletion failed file not found.',
			];
		}

		if ( count( $response ) > 0 ) {
			wp_send_json( $response );
		}
		wp_send_json(
			[
				'status'  => 'fatal',
				'message' => 'Fatal Error! please try again.',
			]
		);
	}

	public function get_saved_data( $post_id ) {
		$data = [];

		if ( 0 !== $post_id ) {

			$data_keys = [
				'fv_data_source',
				'fv_date_range',
				'fv_filters',
				'fv_fields',
				'fv_export_settings',
				'fv_filter_relation',
				'fv_status',
			];

			foreach ( $data_keys as $data_key ) {
				$data[ $data_key ] = get_post_meta( $post_id, $data_key, true );
			}
		}

		return $data;
	}

	private function set_export_reason( $description ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'fv_logs',
			[
				'user_id'         => get_current_user_id(),
				'event'           => 'export',
				'description'     => sanitize_text_field( $description ),
				'export_time'     => current_time( 'mysql', 0 ),
				'export_time_gmt' => current_time( 'mysql', 1 ),
			]
		);
	}

	private function export_to_csv( $params ) {

		$fv_settings = get_option( 'fvSettings' );

		if ( $fv_settings && array_key_exists( 'csv_export_reason', $fv_settings ) && $fv_settings['csv_export_reason'] ) {
			$this->set_export_reason( $params['description'] );
		}

		$plugin = lcfirst( $params['plugin'] );

		$form_id = $params['formid'];

		$name = $plugin . '-' . $form_id . '-' . date( 'Y/m/d' );

		$name               = apply_filters( 'formvibes/quickexport/filename', $name, $params );
		$submissions        = new Submissions( $params['plugin'] );
		$temp_query_filters = $params['query_filters'];
		$query_filters      = [];
		foreach ( $temp_query_filters as $key => $value ) {
			$query_filters[] = (array) $temp_query_filters[ $key ];
		}
		$params['query_filters'] = $query_filters;

		$data          = $submissions->get_submissions( $params )['submissions'];
		$columns       = (array) $params['columns'];
		$col_keys      = array_keys( $columns );
		$fv_status_arr = Utils::get_fv_status();
		$fv_status     = [];
		foreach ( $fv_status_arr as $value ) {
			$fv_status[ $value['key'] ] = $value['label'];
		}

		/* Settings file headers */
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Cache-Control: private', false );
		header( 'Content-Type: text/csv;charset=utf-8' );
		header( 'Content-Disposition: attachment;filename=' . $name . '.csv' );

		$fp        = fopen( 'php://output', 'w' );
		$col_count = count( $col_keys );

		if ( isset( $data ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
			fwrite( $fp, "\xEF\xBB\xBF" );
			fputcsv( $fp, array_values( $columns ) );
			foreach ( $data as $value ) {
				$temp = [];
				for ( $i = 0; $i < $col_count; $i++ ) {
					if ( array_key_exists( $col_keys[ $i ], $value ) ) {
						$temp[ $col_keys[ $i ] ] = stripslashes( $value[ $col_keys[ $i ] ] );
					} else {
						$temp[] = '';
					}
				}

				if ( array_key_exists( 'fv_status', $temp ) ) {
					$status_key = $temp['fv_status'];
					if ( array_key_exists( $status_key, $fv_status ) ) {
						$temp['fv_status'] = $fv_status[ $status_key ];
					} else {
						$temp['fv_status'] = 'Unread';
					}
				}

				fputcsv( $fp, $temp, ',', '"' );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $fp );

		$exported_data = ob_get_contents();
		die();
	}
}
