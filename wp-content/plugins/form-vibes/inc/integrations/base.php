<?php
// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
namespace FormVibes\Integrations;

use FormVibes\Classes\Utils;
use FormVibes\Classes\Settings;

abstract class Base {


	protected $plugin_name;
	protected $ip;
	/**
	 * @param $args
	 */
	public function make_entry( $data ) {

		$args = [
			'post_type'   => 'fv_leads',
			'post_status' => 'publish',
		];

		// Insert Post
		$post_id = wp_insert_post( $args );

		// Add Meta Data
		$this->add_meta_entries( $post_id, $data );
	}

	public function get_submissions( $params ) {
		$data                 = [];
		$forms                = [];
		$data['forms_plugin'] = apply_filters( 'fv_forms', $forms );
		$export               = false;
		$query_filters        = [];
		$filter_relation      = 'OR';
		$export_profile       = false;
		$offset               = 0;
		$limit                = 0;
		if ( array_key_exists( 'export', $params ) ) {
			$export = $params['export'];
		}
		if ( array_key_exists( 'query_filters', $params ) ) {
			$query_filters = $params['query_filters'];
		}
		if ( array_key_exists( 'filter_relation', $params ) ) {
			$filter_relation = $params['filter_relation'];
		}
		if ( array_key_exists( 'export_profile', $params ) ) {
			$export_profile = $params['export_profile'];
		}
		if ( array_key_exists( 'offset', $params ) ) {
			$offset = $params['offset'];
		}
		if ( array_key_exists( 'limit', $params ) ) {
			$limit = $params['limit'];
		}
		$fv_status = [];
		if ( array_key_exists( 'status', $params ) ) {
			$fv_status = $params['status'];
		}
		$temp_params = [
			'plugin'          => $params['plugin'],
			'per_page'        => $params['per_page'],
			'page_num'        => $params['page_num'] === '' ? 1 : $params['page_num'],
			'form_id'         => $params['formid'],
			'queryType'       => $params['query_type'],
			'fromDate'        => $params['fromDate'],
			'toDate'          => $params['toDate'],
			'export'          => $export,
			'query_filters'   => $query_filters,
			'filter_relation' => $filter_relation,
			'export_profile'  => $export_profile,
			'offset'          => $offset,
			'limit'           => $limit,
			'status'          => $fv_status,
		];
		$data        = self::get_data( $temp_params );
		return $data;
	}
	public static function get_data( $params ) {
		global $wpdb;
		$settings                        = get_option( 'fvSettings' );
		list($save_ip, $save_user_agent) = self::is_save_ip_user_agent( $settings );
		$status                          = $params['status'];

		// Start Entry Query
		$query_cols = [ 'entry.id', 'fv_status', 'entry.url', "DATE_FORMAT(captured, '%Y/%m/%d %H:%i:%S') as captured,form_id,form_plugin" ];
		if ( $save_user_agent ) {
			$query_cols[] = 'entry.user_agent';
		}

		$entry_query = 'SELECT distinct ' . implode( ',', $query_cols ) . " FROM {$wpdb->prefix}fv_enteries as entry";
		// Add Joins
		$joins       = [ 'INNER JOIN ' . $wpdb->prefix . 'fv_entry_meta as e1 ON (entry.id = e1.data_id )' ];
		$joins       = apply_filters( 'formvibes/submissions/query/join', $joins, $params );
		$entry_query = $entry_query . ' ' . implode( ' ', $joins );

		// Where Clauses
		$where   = [];
		$where[] = '1 = 1';

		if ( count( $status ) > 0 ) {
			if ( in_array( 'unread', $status, true ) ) {
				$status[] = 'undefined';
			}
			$where[] = "fv_status IN ('" . implode( "', '", $status ) . "')";
		}

				$where = apply_filters( 'formvibes/submissions/query/where', $where, $params );
		// Form Plugin and Form Id
		if ( '' !== $params['plugin'] && null !== $params['plugin'] ) {
			$where[] = "form_plugin='" . $params['plugin'] . "'";
			if ( '' !== $params['form_id'] && null !== $params['form_id'] ) {
				$where[] = "form_id='" . $params['form_id'] . "'";
			}
		}
		// Date Conditions
		if ( '' !== $params['fromDate'] && null !== $params['fromDate'] ) {
			$where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
		}
		if ( '' !== $params['toDate'] && null !== $params['toDate'] ) {
			if ( '' !== $params['fromDate'] ) {
				$where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
			} else {
				$where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
			}
		}
		// Order By
		$orderby = ' order by captured desc';
		// Limit
		$limit = '';
		if ( false === $params['export'] ) {
			if ( $params['page_num'] > 1 ) {
				$limit = ' limit ' . $params['per_page'] * ( $params['page_num'] - 1 ) . ',' . $params['per_page'];
			} else {
				$limit = ' limit ' . $params['per_page'];
			}
		} else {
			if ( true === $params['export_profile'] ) {
				$limit = ' limit ' . $params['limit'] . ' OFFSET ' . $params['offset'];
			}
		}
		if ( true === $params['export'] ) {
			if ( true === $params['export_profile'] ) {
				$entry_query .= 'WHERE ' . implode( ' and ', $where ) . $orderby . $limit;
			} else {
				// Quick Export Query.
				$export_limit     = apply_filters( 'formvibes/quickexport/export_limit', 1000 );
				$export_limit_str = 'LIMIT ' . $export_limit;
				if ( ! $export_limit ) {
					$export_limit_str = '';
				}
				$entry_query .= 'WHERE ' . implode( ' and ', $where ) . $orderby . ' ' . $export_limit_str;
			}
		} else {
			$entry_query .= 'WHERE ' . implode( ' and ', $where ) . $orderby . $limit;
		}

		$entry_result = $wpdb->get_results( $entry_query, ARRAY_A, true );

		$data = [];

		foreach ( $entry_result as $key => $value ) {
			$data[ $value['id'] ]['url']       = $value['url'];
			$data[ $value['id'] ]['captured']  = $value['captured'];
			$data[ $value['id'] ]['fv_status'] = $value['fv_status'];

			if ( $save_user_agent ) {
				$data[ $value['id'] ]['user_agent'] = $value['user_agent'];
			}
		}

		if ( count( array_keys( $data ) ) > 0 ) {
			$entry_meta_query = "SELECT meta_key,meta_value,data_id FROM {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode( ',', array_keys( $data ) ) . ") AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
			if ( false === $save_ip ) {
				$entry_meta_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
			}

			$entry_metas = $wpdb->get_results( $entry_meta_query, ARRAY_A );

			foreach ( $entry_metas as $key => $value ) {
				// prepare fv notes data.
				if ( 'fv-notes' === $value['meta_key'] && $params['export'] !== true ) {
					$notes = (array) json_decode( $value['meta_value'] );

					foreach ( $notes as $note_key => $note_val ) {

						$user_data = get_userdata( $note_val->author_id )->data;

						$username        = $user_data->user_login;
						$current_user_id = get_current_user_id();
						$is_me           = true;
						if ( $current_user_id !== (int) $note_val->author_id ) {
							$is_me = false;
						}
						$notes[ $note_key ]->author_name = $username;
						$notes[ $note_key ]->is_me       = $is_me;
					}

					$value['meta_value'] = $notes;
				}

				$data[ $value['data_id'] ][ $value['meta_key'] ] = $value['meta_value'];
			}

			$entry_count_query = "SELECT COUNT(distinct(e1.data_id)) FROM {$wpdb->prefix}fv_enteries as entry " . implode( ' ', $joins ) . ' WHERE ' . implode( ' and ', $where ) . $orderby;

			$entry_count = $wpdb->get_var( $entry_count_query );

			$distinct_cols_query = "select distinct BINARY(meta_key) from {$wpdb->prefix}fv_entry_meta em join {$wpdb->prefix}fv_enteries e on em.data_id=e.id where form_id='" . $params['form_id'] . "' AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
			if ( false === $save_ip ) {
				$distinct_cols_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
			}

			$columns = $wpdb->get_col( $distinct_cols_query );

			if ( true !== $params['export'] ) {
				if ( $entry_count > 0 ) {
					array_push( $columns, 'captured' );
					array_push( $columns, 'url' );

					if ( $save_user_agent ) {
						array_push( $columns, 'user_agent' );
					}
				}
			}
			$original_columns = $columns;
			$columns          = Utils::prepare_table_columns( $columns, $params['plugin'], $params['form_id'] );

			return [
				'submissions'            => $data,
				'total_submission_count' => $entry_count,
				'columns'                => $columns,
				'original_columns'       => $original_columns,
			];
		} else {
			$entry_count = 0;
			// TODO:: handle no data here.
			$distinct_cols_query = "select distinct BINARY(meta_key) from {$wpdb->prefix}fv_entry_meta em join {$wpdb->prefix}fv_enteries e on em.data_id=e.id where form_id='" . $params['form_id'] . "' AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
			if ( false === $save_ip ) {
				$distinct_cols_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
			}

			$columns = $wpdb->get_col( $distinct_cols_query );

			if ( true !== $params['export'] ) {
				if ( $entry_count > 0 ) {
					array_push( $columns, 'captured' );
					array_push( $columns, 'url' );
					if ( $save_user_agent ) {
						array_push( $columns, 'user_agent' );
					}
				}
			}

			$columns = array_filter(
				$columns,
				function( $column ) {
					return ( $column !== null && $column !== false && $column !== '' );
				}
			);

			return [
				'submissions'            => [],
				'total_submission_count' => 0,
				'columns'                => $columns,
				'original_columns'       => $columns,
			];
		}
	}



	public static function get_tbl_data( $params ) {
		global $wpdb;

		$settings = get_option( 'fvSettings' );

		list($save_ip, $save_user_agent) = self::is_save_ip_user_agent( $settings );

		// Start Entry Query
		$query_cols = [ 'entry.id', 'entry.url', "DATE_FORMAT(captured, '%Y/%m/%d %H:%i:%S') as captured,form_id,form_plugin" ];

		if ( $save_user_agent ) {
			$query_cols[] = 'entry.user_agent';
		}

		$entry_query = 'SELECT distinct ' . implode( ',', $query_cols ) . " FROM {$wpdb->prefix}fv_enteries as entry";

		// Add Joins
		$joins = [ 'INNER JOIN ' . $wpdb->prefix . 'fv_entry_meta as e1 ON (entry.id = e1.data_id )' ];

		$joins = apply_filters( 'formvibes/submissions/query/join', $joins, $params );

		$entry_query = $entry_query . ' ' . implode( ' ', $joins );

		// Where Clauses
		$where   = [];
		$where[] = '1 = 1';

		$where = apply_filters( 'formvibes/submissions/query/where', $where, $params );

		// Form Plugin and Form Id
		if ( '' !== $params['plugin'] && null !== $params['plugin'] ) {
			$where[] = "form_plugin='" . $params['plugin'] . "'";
			if ( '' !== $params['form_id'] && null !== $params['form_id'] ) {
				$where[] = "form_id='" . $params['form_id'] . "'";
			}
		}

		// Date Conditions
		if ( '' !== $params['fromDate'] && null !== $params['fromDate'] ) {
			$where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
		}
		if ( '' !== $params['toDate'] && null !== $params['toDate'] ) {
			if ( '' !== $params['fromDate'] ) {
				$where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
			} else {
				$where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
			}
		}

		// Order By
		$orderby = ' order by captured desc';

		// Limit
		$limit = '';
		if ( false === $params['export'] ) {
			if ( $params['page_num'] > 1 ) {
				$limit = ' limit ' . $params['per_page'] * ( $params['page_num'] - 1 ) . ',' . $params['per_page'];
			} else {
				$limit = ' limit ' . $params['per_page'];
			}
		}

		if ( true === $params['export'] ) {
			$entry_query .= 'WHERE ' . implode( ' and ', $where ) . $orderby;
		} else {
			$entry_query .= 'WHERE ' . implode( ' and ', $where ) . $orderby . $limit;
		}

		$entry_result = $wpdb->get_results( $entry_query, ARRAY_A );
		$data         = [];

		foreach ( $entry_result as $key => $value ) {
			$data[ $value['id'] ]['url']      = $value['url'];
			$data[ $value['id'] ]['captured'] = $value['captured'];
		}
		$entry_meta_query = "SELECT meta_key,meta_value,data_id FROM {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode( ',', array_keys( $data ) ) . ") AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
		if ( false === $save_ip ) {
			$entry_meta_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
		}

		$entry_metas = $wpdb->get_results( $entry_meta_query, ARRAY_A );

		foreach ( $entry_metas as $key => $value ) {
			$data[ $value['data_id'] ][ $value['meta_key'] ] = $value['meta_value'];
		}

		return $data;
	}

	private function add_meta_entries( $post_id, $data ) {
		foreach ( $data['posted_data'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	public function set_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			// check ip from share internet
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// to check ip is pass from proxy
			$temp_ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );

			$ip = $temp_ip[0];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $ip;
	}

	public function insert_enteries( $enteries ) {

		// TODO :: Check exclude form

		$inserted_forms = get_option( 'fv_forms' );

		if ( false === $inserted_forms ) {
			$inserted_forms = [];
		}
		$forms = [];

		if ( array_key_exists( $enteries['plugin_name'], $inserted_forms ) ) {
			$forms = $inserted_forms[ $enteries['plugin_name'] ];

			$forms[ $enteries['id'] ] = [
				'id'   => $enteries['id'],
				'name' => $enteries['title'],
			];
		} else {
			$forms[ $enteries['id'] ] = [
				'id'   => $enteries['id'],
				'name' => $enteries['title'],
			];
		}
		$inserted_forms[ $enteries['plugin_name'] ] = $forms;

		update_option( 'fv_forms', $inserted_forms );

		global $wpdb;
		$entry_data = [
			'form_plugin'  => $enteries['plugin_name'],
			'form_id'      => $enteries['id'],
			'captured'     => $enteries['captured'],
			'captured_gmt' => $enteries['captured_gmt'],
			'url'          => $enteries['url'],
		];

		$settings = get_option( 'fvSettings' );
		$save_ua  = false;

		if ( $settings && array_key_exists( 'save_user_agent', $settings ) ) {
			$save_ua = $settings['save_user_agent'];
		}

		if ( $save_ua ) {
			$entry_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
			$enteries['user_agent']   = $_SERVER['HTTP_USER_AGENT'];
		} else {
			$entry_data['user_agent'] = '';
		}

		$wpdb->insert(
			$wpdb->prefix . 'fv_enteries',
			$entry_data
		);
		$insert_id = $wpdb->insert_id;

		if ( $insert_id !== 0 ) {
			$this->insert_fv_entry_meta( $insert_id, $enteries['posted_data'] );
			return $insert_id;
		}
	}

	public function insert_fv_entry_meta( $insert_id, $enteries ) {
		global $wpdb;

		foreach ( $enteries as $key => $value ) {
			$wpdb->insert(
				$wpdb->prefix . 'fv_entry_meta',
				[
					'data_id'    => $insert_id,
					'meta_key'   => $key,
					'meta_value' => $value,
				]
			);
		}
		$insert_id_meta = $wpdb->insert_id;
		if ( $insert_id_meta < 1 ) {
			write_log( '==============Entry Failed===============' );
		}
	}

	public static function delete_entries( $ids ) {
		global $wpdb;
		$message           = [];
		$delete_row_query1 = "Delete from {$wpdb->prefix}fv_enteries where id IN (" . implode( ',', $ids ) . ')';
		$delete_row_query2 = "Delete from {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode( ',', $ids ) . ')';

		$dl1 = $wpdb->query( $delete_row_query1 );

		$dl2 = $wpdb->query( $delete_row_query2 );

		if ( 0 === $dl1 || 0 === $dl2 ) {
			$message['status']  = 'failed';
			$message['message'] = 'Could not able to delete Entries';
		} else {
			$message['status']  = 'passed';
			$message['message'] = 'Entries Deleted';
		}

		wp_send_json( $message );
	}

	public function save_options( $params ) {
		$forms_data                              = $params['columns'];
		$form_name                               = $params['form'];
		$plugin_name                             = $params['plugin'];
		$key                                     = $params['key'];
		$saved_data                              = get_option( 'fv-keys' );
		$data                                    = $saved_data;
		$data[ $plugin_name . '_' . $form_name ] = $forms_data;
		update_option( $key, $data, false );
		wp_send_json( $this->get_fv_keys() );
	}

	private function get_fv_keys() {
		$temp = get_option( 'fv-keys' );
		if ( '' === $temp || false === $temp ) {
			return [];
		}
		$fv_keys = [];
		foreach ( $temp as $key => $value ) {

			foreach ( $value as $val_key => $val_val ) {
				$fv_keys[ $key ][ $val_val['colKey'] ] = $val_val;
			}
		}
		return $fv_keys;
	}
	public function get_analytics( $params ) {
		$filter_type = $params['filter_type'];
		$plugin_name = $params['plugin'];
		$from_date   = $params['fromDate'];
		$to_date     = $params['toDate'];
		$filter      = '';
		$formid      = $params['formid'];
		$label       = '';
		$query_param = '';
		if ( 'day' === $filter_type ) {
			$default_data = self::get_dates_from_range( $from_date, $to_date );
			$filter       = '%j';
			$label        = "MAKEDATE(DATE_FORMAT(`captured`, '%Y'), DATE_FORMAT(`captured`, '%j'))";
		} elseif ( 'month' === $filter_type ) {
			$default_data = self::get_month_range( $from_date, $to_date );
			$filter       = '%b';
			$label        = "concat(DATE_FORMAT(`captured`, '%b'),'(',DATE_FORMAT(`captured`, '%y'),')')";
		} else {
			$default_data = self::get_date_range_ror_all_weeks( $from_date, $to_date );

			$start_week = get_option( 'start_of_week' );

			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( 0 == $start_week ) {
				$filter      = '%U';
				$day_start   = 'Sunday';
				$week_number = '';
			} else {
				$filter      = '%u';
				$day_start   = 'Monday';
				$week_number = '';
			}
			$label = "STR_TO_DATE(CONCAT(DATE_FORMAT(`captured`, '%Y'),' ', DATE_FORMAT(`captured`, '" . $filter . "')" . $week_number . ",' ', '" . $day_start . "'), '%X %V %W')";
		}
		if ( '%b' === $filter ) {
			$orderby = '%m';
		} else {
			$orderby = $filter;
		}
		global $wpdb;
		$param_where   = [];
		$param_where[] = "DATE_FORMAT(`captured`,GET_FORMAT(DATE,'JIS')) >= '" . $from_date . "'";

		$param_where[] = "DATE_FORMAT(`captured`,GET_FORMAT(DATE,'JIS')) <= '" . $to_date . "'";
		$param_where[] = "form_plugin='" . $plugin_name . "'";
		$param_where[] = "form_id='" . $formid . "'";
		$query_param   = ' Where ' . implode( ' and ', $param_where );
		$data_query    = 'SELECT ' . $label . " as Label,CONCAT(DATE_FORMAT(`captured`, '" . $filter . "'),'(',DATE_FORMAT(`captured`, '%y'),')') as week, count(*) as count,CONCAT(DATE_FORMAT(`captured`, '%y'),'-',DATE_FORMAT(`captured`, '" . $orderby . "')) as ordering from {$wpdb->prefix}fv_enteries " . $query_param . " GROUP BY DATE_FORMAT(`captured`, '" . $orderby . "'),ordering ORDER BY ordering";
		$res           = [];

		$res['data'] = $wpdb->get_results( $data_query, OBJECT_K );

		if ( count( (array) $res['data'] ) > 0 ) {
			$key = array_keys( $res['data'] )[0];
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( null == $res['data'][ $key ]->Label || '' == $res['data'][ $key ]->Label ) {
				$abc                                   = [];
				$abc[ array_keys( $default_data )[0] ] = (object) $res['data'][''];
				$res['data']                           = $abc + $res['data'];
				$res['data'][ array_keys( $default_data )[0] ]->Label = array_keys( $default_data )[0];
				unset( $res['data'][''] );
			}
		}

		$data = array_replace( $default_data, $res['data'] );

		if ( array_key_exists( 'dashboard_data', $params ) && $params['dashboard_data'] ) {
			$dashboard_data         = $this->prepare_data_for_dashboard_widget( $params, $res );
			$data['dashboard_data'] = $dashboard_data;
		}
		return $data;
	}

	private function prepare_data_for_dashboard_widget( $params, $res ) {
		$all_forms      = [];
		$dashboard_data = [];
		$count          = count( $params['allForms'] );

		for ( $i = 0; $i < $count; ++$i ) {
			$plugin       = $params['allForms'][ $i ]['label'];
			$option_count = count( $params['allForms'][ $i ]['options'] );
			for ( $j = 0; $j < $option_count; ++$j ) {
				$id               = $params['allForms'][ $i ]['options'][ $j ]['value'];
				$form_name        = $params['allForms'][ $i ]['options'][ $j ]['label'];
				$all_forms[ $id ] = [
					'id'       => $id,
					'plugin'   => $plugin,
					'formName' => $form_name,
				];
			}
		}
		if ( 'Last_7_Days' === $params['query_type'] || 'This_Week' === $params['query_type'] ) {
			$pre_from_date = date( 'Y-m-d', strtotime( $params['fromDate'] . '-7 days' ) );
			$pre_to_date   = date( 'Y-m-d', strtotime( $params['fromDate'] . '-1 days' ) );
		} elseif ( 'Last_30_Days' === $params['query_type'] ) {
			$pre_from_date = date( 'Y-m-d', strtotime( $params['fromDate'] . '-30 days' ) );
			$pre_to_date   = date( 'Y-m-d', strtotime( $params['fromDate'] . '-1 days' ) );
		} else {
			$pre_from_date = date( 'Y-m-01', strtotime( 'first day of last month' ) );
			$pre_to_date   = date( 'Y-m-t', strtotime( 'last day of last month' ) );
		}
		global $wpdb;
		$pre_param  = " where form_id='" . $params['formid'] . "' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $pre_from_date . "'";
		$pre_param .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $pre_to_date . "'";
		$qry        = "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries " . $pre_param;

		$pre_data_count = $wpdb->get_var( $qry );
		foreach ( $all_forms as $form_key => $form_value ) {
			if ( 'Caldera' === $form_value['plugin'] || 'caldera' === $form_value['plugin'] ) {
				$param  = " where form_id='" . $form_key . "' and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
				$param .= " and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
				$qry    = "SELECT COUNT(*) FROM {$wpdb->prefix}cf_form_entries " . $param;

				$data_count = $wpdb->get_var( $qry );

				$dashboard_data['allFormsDataCount'][ $form_key ] = [
					'plugin'   => $form_value['plugin'],
					'count'    => $data_count,
					'formName' => $form_value['formName'],
				];
			} else {
				$param  = " where form_id='" . $form_key . "' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
				$param .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
				$qry    = "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries " . $param;

				$data_count = $wpdb->get_var( $qry );

				$dashboard_data['allFormsDataCount'][ $form_key ] = [
					'plugin'   => $form_value['plugin'],
					'count'    => $data_count,
					'formName' => $form_value['formName'],
				];
			}
		}
		$total_entries = 0;

		foreach ( $res['data'] as $key => $val ) {
			$total_entries += $val->count;
		}
		$dashboard_widget_setting               = [];
		$dashboard_widget_setting['query_type'] = $params['query_type'];
		$dashboard_widget_setting['plugin']     = $params['plugin'];
		$dashboard_widget_setting['formid']     = $params['formid'];
		update_option( 'fv_dashboard_widget_settings', $dashboard_widget_setting );
		$dashboard_data['totalEntries']               = $total_entries;
		$dashboard_data['previousDateRangeDataCount'] = (int) $pre_data_count;
		return $dashboard_data;
	}

	public static function get_dates_from_range( $start, $end, $format = 'Y-m-d' ) {

		$date_1 = $start;
		$date_2 = $end;
		$array  = [];

		// Use strtotime function
		$variable_1 = strtotime( $date_1 );
		$variable_2 = strtotime( $date_2 );

		// Use for loop to store dates into array
		// 86400 sec = 24 hrs = 60*60*24 = 1 day
		for (
			$current_date = $variable_1;
			$current_date <= $variable_2;
			$current_date += ( 86400 )
		) {

			$store = date( 'Y-m-d', $current_date );

			$array[ $store ] = (object) [
				'Label'    => $store,
				'week'     => ( date( 'z', $current_date ) + 1 ) . '(' . date( 'y', $current_date ) . ')',
				'count'    => 0,
				'ordering' => date( 'y', $current_date ) . '-' . ( date( 'z', $current_date ) + 1 ),
			];
		}
		$array[] = new \stdClass();
		unset( $array[0] );
		return $array;
	}
	public static function get_month_range( $start_date, $end_date ) {
		$start = new \DateTime( $start_date );
		$start->modify( 'first day of this month' );
		$end = new \DateTime( $end_date );
		$end->modify( 'first day of next month' );
		$interval = \DateInterval::createFromDateString( '1 month' );
		$period   = new \DatePeriod( $start, $interval, $end );

		$months = [];
		foreach ( $period as $dt ) {
			$months[ $dt->format( 'M' ) . '(' . $dt->format( 'y' ) . ')' ] = (object) [
				'Label'    => $dt->format( 'M' ) . '(' . $dt->format( 'y' ) . ')',
				'week'     => '',
				'count'    => 0,
				'ordering' => '',
			];
		}

		return $months;
	}
	public static function get_date_range_ror_all_weeks( $start, $end ) {
		$fweek = self::get_date_range_for_week( $start );
		$lweek = self::get_date_range_for_week( $end );

		$week_dates = [];

		$start_week = get_option( 'start_of_week' );
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( 0 == $start_week ) {
			while ( $fweek['saturday'] < $lweek['saturday'] ) {
				$week_dates[ $fweek['sunday'] ] = (object) [
					'Label'    => $fweek['sunday'],
					'week'     => '',
					'count'    => 0,
					'ordering' => '',
				];

				$date = new \DateTime( $fweek['saturday'] );
				$date->modify( 'next day' );

				$fweek = self::get_date_range_for_week( $date->format( 'Y-m-d' ) );
			}
			$week_dates[ $lweek['sunday'] ] = (object) [
				'Label'    => $lweek['sunday'],
				'week'     => '',
				'count'    => 0,
				'ordering' => '',
			];
		} else {
			while ( $fweek['sunday'] < $lweek['sunday'] ) {
				$week_dates[ $fweek['monday'] ] = (object) [
					'Label'    => $fweek['monday'],
					'week'     => '',
					'count'    => 0,
					'ordering' => '',
				];

				$date = new \DateTime( $fweek['sunday'] );
				$date->modify( 'next day' );

				$fweek = self::get_date_range_for_week( $date->format( 'Y-m-d' ) );
			}
			$week_dates[ $lweek['monday'] ] = (object) [
				'Label'    => $lweek['monday'],
				'week'     => '',
				'count'    => 0,
				'ordering' => '',
			];
		}

		return $week_dates;
	}
	public static function get_date_range_for_week( $date ) {
		$date_time = new \DateTime( $date );

		$start_week = get_option( 'start_of_week' );

		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( 0 == $start_week ) {
			if ( 'Sunday' === $date_time->format( 'l' ) ) {
				$sunday = date( 'Y-m-d', strtotime( $date ) );
			} else {
				$sunday = date( 'Y-m-d', strtotime( 'last sunday', strtotime( $date ) ) );
			}

			$saturday = 'Saturday' === $date_time->format( 'l' ) ? date( 'Y-m-d', strtotime( $date ) ) : date( 'Y-m-d', strtotime( 'next saturday', strtotime( $date ) ) );

			return [
				'sunday'   => $sunday,
				'saturday' => $saturday,
			];
		} else {
			if ( 'Monday' === $date_time->format( 'l' ) ) {
				$monday = date( 'Y-m-d', strtotime( $date ) );
			} else {
				$monday = date( 'Y-m-d', strtotime( 'last monday', strtotime( $date ) ) );
			}

			$sunday = 'Sunday' === $date_time->format( 'l' ) ? date( 'Y-m-d', strtotime( $date ) ) : date( 'Y-m-d', strtotime( 'next sunday', strtotime( $date ) ) );

			return [
				'monday' => $monday,
				'sunday' => $sunday,
			];
		}
	}

	public static function is_save_ip_user_agent( $settings ) {
		$settings = get_option( 'fvSettings' );

		$save_ip = false;
		$save_ua = false;

		if ( $settings && array_key_exists( 'save_ip_address', $settings ) ) {
			$save_ip = $settings['save_ip_address'];
		}
		if ( $settings && array_key_exists( 'save_user_agent', $settings ) ) {
			$save_ua = $settings['save_user_agent'];
		}

		return [
			$save_ip,
			$save_ua,
		];
	}
}
