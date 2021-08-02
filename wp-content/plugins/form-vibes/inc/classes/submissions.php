<?php
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
namespace FormVibes\Classes;

class Submissions {


	// $source => Plugin class name
	protected $source;

	public function __construct( $source ) {
		if ( '' !== $source && 'noformsplugin' !== $source ) {
			$source_class = '\\FormVibes\\Integrations\\' . Utils::dashes_to_camel_case( $source );
			$this->source = $source_class::instance();
			add_filter( 'formvibes/submissions/query/join', [ $this, 'fv_prepare_query_joins' ], 10, 2 );
			add_filter( 'formvibes/submissions/query/where', [ $this, 'fv_prepare_query_where' ], 10, 2 );
		}
	}


	public function fv_prepare_query_where( $where, $params ) {

		$entry_fields   = Utils::get_entry_table_fields();
		$query_filters  = $params['query_filters'];
		$query_relation = $params['filter_relation'];
		$filter_query   = '(';
		$condition_key  = 'meta_key';
		$condition_val  = 'meta_value';
		if ( '' === $query_filters ) {
			return $where;
		}
		if ( count( $query_filters ) === 0 ) {
			return $where;
		}
		if ( 'caldera' === $params['plugin'] || 'Caldera' === $params['plugin'] ) {
			$condition_key = 'slug';
			$condition_val = 'value';
		}
		if ( '' === $query_filters[0]['value'] ) {
			$filter_query .= '(e1.' . $condition_key . " LIKE '%%' AND e1." . $condition_val . " LIKE '%%')";
			$filter_query .= ')';
			$where[]       = $filter_query;
			return $where;
		}
		foreach ( $query_filters as $key => $value ) {
			$filter_key   = $value['filter'];
			$filter_value = trim( $value['value'] );
			$operator     = $value['operator'];
			$relation     = $query_relation;
			$table_alias  = 'e';
			if ( count( $query_filters ) === $key + 1 ) {
				$relation = '';
			}
			if ( 'OR' === $query_relation ) {
				$key = 0;
			}

			if ( in_array( $value['filter'], $entry_fields, true ) ) {
				$table_alias = 'entry';
			}

			switch ( $operator ) {
				case 'equal':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " = '$filter_value') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " = '$filter_value') $relation";
					}
					break;
				case 'not_equal':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " != '$filter_value') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " != '$filter_value') $relation";
					}
					break;
				case 'contain':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " LIKE '%$filter_value%') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " LIKE '%$filter_value%') $relation";
					}
					break;
				case 'not_contain':
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '$filter_key' AND e" . ( $key + 1 ) . '.' . $condition_val . " NOT LIKE '%$filter_value%') $relation ";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " NOT LIKE '%$filter_value%') $relation";
					}
					break;
				default:
					if ( 'e' === $table_alias ) {
						$filter_query .= '(e' . ( $key + 1 ) . '.' . $condition_key . " = '%%' AND e" . ( $key + 1 ) . '.' . $condition_val . " = '%%')";
					} else {
						$filter_query .= '(entry.' . $value['filter'] . " = '%%')";
					}
					break;
			}
		}
		$filter_query .= ')';
		$where[]       = $filter_query;

		return $where;
	}

	public function fv_prepare_query_joins( $joins, $params ) {
		global $wpdb;
		$query_filters  = $params['query_filters'];
		$query_relation = $params['filter_relation'];

		if ( '' === $query_filters ) {
			return $joins;
		}
		if ( count( $query_filters ) === 0 ) {
			return $joins;
		}
		$condition  = 'data_id';
		$table_name = $wpdb->prefix . 'fv_entry_meta';
		if ( 'caldera' === $params['plugin'] || 'Caldera' === $params['plugin'] ) {
			$condition  = 'entry_id';
			$table_name = $wpdb->prefix . 'cf_form_entry_values';
		}
		foreach ( $query_filters as $key => $value ) {

			$joins[ $key ] =
				'INNER JOIN ' . $table_name . ' as e' . ( $key + 1 ) . ' ON (entry.id = e' . ( $key + 1 ) . '.' . $condition . ' ) ';
		}

		if ( 'OR' === $query_relation ) {
			$temp   = [];
			$temp[] = $joins[0];
			return $temp;
		}
		return $joins;
	}

	public function get_submissions( $params ) {
		if ( 'noformsplugin' === $params['plugin'] && 'noformsfound' === $params['formid'] ) {
			return [
				'submissions'            => [],
				'total_submission_count' => 0,
			];
		}
		$dates              = Utils::get_query_dates( $params['query_type'], $params );
		$params['fromDate'] = $dates[0]->format( 'Y-m-d' );
		$params['toDate']   = $dates[1]->format( 'Y-m-d' );
		$submissions        = $this->source->get_submissions( $params );
		return $submissions;
	}

	public function get_analytics( $params ) {
		$dates              = Utils::get_query_dates( $params['query_type'], $params );
		$params['fromDate'] = $dates[0]->format( 'Y-m-d' );
		$params['toDate']   = $dates[1]->format( 'Y-m-d' );
		$analytics          = $this->source->get_analytics( $params );
		return $analytics;
	}

	public function fv_get_logs_data( $params ) {
		$gmt_offset = get_option( 'gmt_offset' );
		$hours      = (int) $gmt_offset;
		$minutes    = ( $gmt_offset - floor( $gmt_offset ) ) * 60;
		if ( $hours >= 0 ) {
			$time_zone = $hours . ':' . $minutes;
		} else {
			$time_zone = $hours . ':' . $minutes;
		}
		$limit = '';
		if ( $params['page'] > 1 ) {
			$limit = ' limit ' . $params['pageSize'] * ( $params['page'] - 1 ) . ',' . $params['pageSize'];
		} else {
			$limit = ' limit ' . $params['pageSize'];
		}
		global $wpdb;

		$entry_query = "select @a:=@a+1 serial_number, l.id,l.user_id,u.user_login,event,description,DATE_FORMAT(ADDTIME(export_time_gmt,'" . $time_zone . "' ), '%Y/%m/%d %H:%i:%S') as export_time_gmt from {$wpdb->prefix}fv_logs l LEFT JOIN {$wpdb->prefix}users u on l.user_id=u.ID, (SELECT @a:= 0) AS a ORDER BY id desc" . $limit;

		$entry_result      = $wpdb->get_results( $entry_query, ARRAY_A );
		$entry_count_query = "select count(id) from {$wpdb->prefix}fv_logs l ORDER BY id desc";

		$entry_count_result = $wpdb->get_var( $wpdb->prepare( $entry_count_query ) );
		foreach ( $entry_result as $key => $value ) {
			$user_meta                    = get_user_meta( $value['user_id'] );
			$entry_result[ $key ]['user'] = $user_meta['first_name'][0] . ' ' . $user_meta['last_name'][0];
		}
		$results = [
			'count' => $entry_count_result,
			'data'  => $entry_result,
		];
		return $results;
	}

	public function save_options( $params ) {

		$options = $this->source->save_options( $params );
	}
}
