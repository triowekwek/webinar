<?php
// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
namespace FormVibes\Classes;

use Carbon\Carbon;
use Stripe\Util\Util;

class Utils {


	public static function dashes_to_camel_case( $string, $capitalize_first_character = true ) {
		$str = str_replace( '-', '', ucwords( $string, '-' ) );
		if ( ! $capitalize_first_character ) {
			$str = lcfirst( $str );
		}
		return $str;
	}

	public static function prepare_forms_data() {
		global $wpdb;
		$forms                = [];
		$data                 = [];
		$data['forms_plugin'] = apply_filters( 'fv_forms', $forms );

		$settings   = get_option( 'fvSettings' );
		$debug_mode = false;

		if ( $settings && array_key_exists( 'debug_mode', $settings ) ) {
			$debug_mode = $settings['debug_mode'];
		}

		$form_res = $wpdb->get_results( "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e", OBJECT_K );

		$inserted_forms = get_option( 'fv_forms' );

		$plugin_forms = [];

		foreach ( $data['forms_plugin'] as $key => $value ) {
			$res = [];

			if ( 'caldera' === $key || 'ninja' === $key ) {
				$class = '\FormVibes\Integrations\\' . ucfirst( $key );

				$res = $class::get_forms( $key );
			} else {
				foreach ( $form_res as $form_key => $form_value ) {

					if ( array_key_exists( $key, $inserted_forms ) && array_key_exists( $form_key, $inserted_forms[ $key ] ) ) {
						$name = $inserted_forms[ $key ][ $form_key ]['name'];
					} else {
						$name = $form_key;
					}
					if ( $form_res[ $form_key ]->form_plugin === $key ) {
						$res[ $form_key ] = [
							'id'   => $form_key,
							'name' => $name,
						];
					}
				}
			}

			if ( null !== $res ) {
				$plugin_forms[ $key ] = $res;
			}
		}

		$all_forms = [];

		foreach ( $data['forms_plugin'] as $key => $value ) {
			if ( $plugin_forms[ $key ] ) {
				array_push(
					$all_forms,
					[
						'label'   => $value,
						'options' => [],
					]
				);
			}
		}

		$all_forms_count = count( $all_forms );

		for ( $i = 0; $i < $all_forms_count; ++$i ) {
			foreach ( $data['forms_plugin'] as $key => $value ) {

				foreach ( $plugin_forms[ $key ] as $key1 => $value1 ) {
					$options = [];
					if ( true === $debug_mode ) {
						array_push(
							$options,
							[
								'label'      => $value1['name'] . '(' . $value1['id'] . ')',
								'value'      => $value1['id'],
								'pluginName' => $value,
								'formName'   => $value1['name'],
							]
						);
					} else {
						array_push(
							$options,
							[
								'label'      => $value1['name'],
								'value'      => $value1['id'],
								'pluginName' => $value,
								'formName'   => $value1['name'],
							]
						);
					}

					if ( $all_forms[ $i ]['label'] === $value ) {
						array_push( $all_forms[ $i ]['options'], $options[0] );
					}
				}
			}
		}

		for ( $i = 0; $i < $all_forms_count; ++$i ) {
			if ( count( $all_forms[ $i ]['options'] ) === 0 ) {
				unset( $all_forms[ $i ] );
			}
		}

		$all_forms = array_values( $all_forms );

		$data['allForms'] = $all_forms;

		return $data;
	}

	public static function get_fv_keys() {
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

	public static function get_plugin_key_by_name( $name ) {
		if ( 'Contact Form 7' === $name ) {
			return 'cf7';
		} elseif ( 'Elementor Forms' === $name ) {
			return 'elementor';
		} elseif ( 'Beaver Builder' === $name ) {
			return 'beaverBuilder';
		} elseif ( 'WP Forms' === $name ) {
			return 'wpforms';
		} elseif ( 'Caldera' === $name ) {
			return 'caldera';
		} elseif ( 'Ninja Forms' === $name ) {
			return 'ninja';
		}
		return $name;
	}

	public static function get_query_dates( $query_type, $param ) {
		$gmt_offset = get_option( 'gmt_offset' );
		$hours      = (int) $gmt_offset;
		$minutes    = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

		if ( $hours >= 0 ) {
			$time_zone = '+' . $hours . ':' . $minutes;
		} else {
			$time_zone = $hours . ':' . $minutes;
		}

		if ( 'Custom' !== $query_type ) {
			$dates = self::get_date_interval( $query_type, $time_zone );

			$from_date = $dates['fromDate'];
			$to_date   = $dates['endDate'];
		} else {
			$tz        = new \DateTimeZone( $time_zone );
			$from_date = new \DateTime( $param['fromDate'] );
			$from_date->setTimezone( $tz );
			$to_date = new \DateTime( $param['toDate'] );
			$to_date->setTimezone( $tz );
		}

		return [ $from_date, $to_date ];
	}

	public static function get_date_interval( $query_type, $time_zone ) {
		$dates = [];
		switch ( $query_type ) {
			case 'Today':
				$dates['fromDate'] = Carbon::now( $time_zone );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'Yesterday':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDay();
				$dates['endDate']  = Carbon::now( $time_zone )->subDay();

				return $dates;

			case 'Last_7_Days':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDays( 6 );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'This_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::MONDAY );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SUNDAY );
				} else {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::SUNDAY );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SATURDAY );
				}
				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;
				return $dates;

			case 'Last_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::MONDAY )->subDays( 7 );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SUNDAY )->subDays( 7 );
				} else {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::SUNDAY )->subDays( 7 );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SATURDAY )->subDays( 7 );
				}

				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;

				return $dates;

			case 'Last_30_Days':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDays( 29 );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'This_Month':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfMonth();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfMonth();

				return $dates;

			case 'Last_Month':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonth()->startOfMonth();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonth()->endOfMonth();

				return $dates;

			case 'This_Quarter':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfQuarter();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfQuarter();

				return $dates;

			case 'Last_Quarter':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonths( 3 )->startOfQuarter();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonths( 3 )->endOfQuarter();

				return $dates;

			case 'This_Year':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfYear();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfYear();

				return $dates;

			case 'Last_Year':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonths( 12 )->startOfYear();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonths( 12 )->endOfYear();

				return $dates;
		}
	}
	public static function get_dates( $query_type ) {
		$dates = [];
		switch ( $query_type ) {
			case 'Today':
				$dates['fromDate'] = date( 'Y-m-d H:i:s' );
				$dates['endDate']  = date( 'Y-m-d H:i:s' );

				return $dates;

			case 'Yesterday':
				$dates['fromDate'] = date( 'Y-m-d H:i:s', strtotime( '-1 days' ) );
				$dates['endDate']  = date( 'Y-m-d H:i:s', strtotime( '-1 days' ) );

				return $dates;

			case 'Last_7_Days':
				$dates['fromDate'] = date( 'Y-m-d H:i:s', strtotime( '-6 days' ) );
				$dates['endDate']  = date( 'Y-m-d H:i:s' );

				return $dates;

			case 'This_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					if ( 'Mon' !== date( 'D' ) ) {
						$staticstart = date( 'Y-m-d', strtotime( 'last Monday' ) );
					} else {
						$staticstart = date( 'Y-m-d' );
					}

					if ( 'Sat' !== date( 'D' ) ) {
						$staticfinish = date( 'Y-m-d', strtotime( 'next Sunday' ) );
					} else {

						$staticfinish = date( 'Y-m-d' );
					}
				} else {
					if ( 'Sun' !== date( 'D' ) ) {
						$staticstart = date( 'Y-m-d', strtotime( 'last Sunday' ) );
					} else {
						$staticstart = date( 'Y-m-d' );
					}

					if ( 'Sat' !== date( 'D' ) ) {
						$staticfinish = date( 'Y-m-d', strtotime( 'next Saturday' ) );
					} else {

						$staticfinish = date( 'Y-m-d' );
					}
				}
				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;
				return $dates;

			case 'Last_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$previous_week = strtotime( '-1 week +1 day' );
					$start_week    = strtotime( 'last monday midnight', $previous_week );
					$end_week      = strtotime( 'next sunday', $start_week );
				} else {
					$previous_week = strtotime( '-1 week +1 day' );
					$start_week    = strtotime( 'last sunday midnight', $previous_week );
					$end_week      = strtotime( 'next saturday', $start_week );
				}
				$start_week = date( 'Y-m-d', $start_week );
				$end_week   = date( 'Y-m-d', $end_week );

				$dates['fromDate'] = $start_week;
				$dates['endDate']  = $end_week;

				return $dates;

			case 'Last_30_Days':
				$dates['fromDate'] = date( 'Y-m-d h:m:s', strtotime( '-29 days' ) );
				$dates['endDate']  = date( 'Y-m-d h:m:s' );

				return $dates;

			case 'This_Month':
				$dates['fromDate'] = date( 'Y-m-01' );
				$dates['endDate']  = date( 'Y-m-t' );

				return $dates;

			case 'Last_Month':
				$dates['fromDate'] = date( 'Y-m-01', strtotime( 'first day of last month' ) );
				$dates['endDate']  = date( 'Y-m-t', strtotime( 'last day of last month' ) );

				return $dates;

			case 'This_Quarter':
				$current_month = date( 'm' );
				$current_year  = date( 'Y' );
				if ( $current_month >= 1 && $current_month <= 3 ) {
					$start_date = strtotime( '1-January-' . $current_year );  // timestamp or 1-Januray 12:00:00 AM
					$end_date   = strtotime( '31-March-' . $current_year );  // timestamp or 1-April 12:00:00 AM means end of 31 March
				} elseif ( $current_month >= 4 && $current_month <= 6 ) {
					$start_date = strtotime( '1-April-' . $current_year );  // timestamp or 1-April 12:00:00 AM
					$end_date   = strtotime( '30-June-' . $current_year );  // timestamp or 1-July 12:00:00 AM means end of 30 June
				} elseif ( $current_month >= 7 && $current_month <= 9 ) {
					$start_date = strtotime( '1-July-' . $current_year );  // timestamp or 1-July 12:00:00 AM
					$end_date   = strtotime( '30-September-' . $current_year );  // timestamp or 1-October 12:00:00 AM means end of 30 September
				} elseif ( $current_month >= 10 && $current_month <= 12 ) {
					$start_date = strtotime( '1-October-' . $current_year );  // timestamp or 1-October 12:00:00 AM
					$end_date   = strtotime( '31-December-' . ( $current_year ) );  // timestamp or 1-January Next year 12:00:00 AM means end of 31 December this year
				}

				$dates['fromDate'] = date( 'Y-m-d', $start_date );
				$dates['endDate']  = date( 'Y-m-d', $end_date );
				return $dates;

			case 'Last_Quarter':
				$current_month = date( 'm' );
				$current_year  = date( 'Y' );

				if ( $current_month >= 1 && $current_month <= 3 ) {
					$start_date = strtotime( '1-October-' . ( $current_year - 1 ) );  // timestamp or 1-October Last Year 12:00:00 AM
					$end_date   = strtotime( '31-December-' . ( $current_year - 1 ) );  // // timestamp or 1-January  12:00:00 AM means end of 31 December Last year
				} elseif ( $current_month >= 4 && $current_month <= 6 ) {
					$start_date = strtotime( '1-January-' . $current_year );  // timestamp or 1-Januray 12:00:00 AM
					$end_date   = strtotime( '31-March-' . $current_year );  // timestamp or 1-April 12:00:00 AM means end of 31 March
				} elseif ( $current_month >= 7 && $current_month <= 9 ) {
					$start_date = strtotime( '1-April-' . $current_year );  // timestamp or 1-April 12:00:00 AM
					$end_date   = strtotime( '30-June-' . $current_year );  // timestamp or 1-July 12:00:00 AM means end of 30 June
				} elseif ( $current_month >= 10 && $current_month <= 12 ) {
					$start_date = strtotime( '1-July-' . $current_year );  // timestamp or 1-July 12:00:00 AM
					$end_date   = strtotime( '30-September-' . $current_year );  // timestamp or 1-October 12:00:00 AM means end of 30 September
				}
				$dates['fromDate'] = date( 'Y-m-d', $start_date );
				$dates['endDate']  = date( 'Y-m-d', $end_date );
				return $dates;

			case 'This_Year':
				$dates['fromDate'] = date( 'Y-01-01' );
				$dates['endDate']  = date( 'Y-12-t' );

				return $dates;

			case 'Last_Year':
				$dates['fromDate'] = date( 'Y-01-01', strtotime( '-1 year' ) );
				$dates['endDate']  = date( 'Y-12-t', strtotime( '-1 year' ) );

				return $dates;
		}
	}

	public static function get_first_plugin_form() {
		$forms   = [];
		$plugins = apply_filters( 'fv_forms', $forms );

		$class = '\FormVibes\Integrations\\' . ucfirst( array_keys( $plugins )[0] );

		$plugin_forms = $class::get_forms( array_keys( $plugins )[0] );
		$plugin       = array_keys( $plugins )[0];

		$data = [
			'formName'       => $plugin_forms,
			'selectedPlugin' => $plugin,
			'selectedForm'   => array_keys( $plugin_forms )[0],
		];

		return $data;
	}

	public static function get_entry_table_fields() {
		$entry_table_fields = [
			'url',
			'user_agent',
			'fv_status',
			'captured',
		];

		$entry_table_fields = apply_filters( 'formvibes/entry_table_fields', $entry_table_fields );

		return $entry_table_fields;
	}

	public static function get_fv_status() {
		$status = [
			[
				'key'   => 'read',
				'label' => 'Read',
				'color' => '#28a745',
			],
			[
				'key'   => 'unread',
				'label' => 'Unread',
				'color' => '#007bff',
			],
			[
				'key'   => 'spam',
				'label' => 'Spam',
				'color' => '#dc3545',
			],
		];

		$status = apply_filters( 'formvibes/submission/status', $status );
		return $status;
	}

	public static function prepare_table_columns( $columns, $plugin_name, $form_id, $type = 'submission' ) {

		$columns = array_filter(
			$columns,
			function( $column ) {
				return ( $column !== null && $column !== false && $column !== '' );
			}
		);

		$key = array_search( 'fv-notes', $columns, true );
		if ( ( $key ) !== false ) {
			unset( $columns[ $key ] );
		}

		$saved_columns = get_option( 'fv-keys' );

		$col_label = 'Header';
		$col_key   = 'accessor';

		if ( $type === 'columns' ) {
			$col_label = 'alias';
			$col_key   = 'colKey';
		}

		if ( $saved_columns ) {
			if ( ! array_key_exists( $plugin_name . '_' . $form_id, $saved_columns ) ) {
				$cols = [];
				foreach ( $columns   as $column ) {
					$label = $column;
					if ( 'captured' === $column || 'datestamp' === $column ) {
						$label = 'Submission Date';
					}
					if ( $column === 'fv_status' ) {
						$label = 'Status';
					}
					$cols[] = (object) [
						$col_label => $label,
						$col_key   => $column,
						'visible'  => true,
					];
				}
				return $cols;
			}
		}

		$current_form_saved_columns = $saved_columns[ $plugin_name . '_' . $form_id ];

		if ( empty( $current_form_saved_columns ) ) {
			$cols = [];
			foreach ( $columns as $column ) {
				$label = $column;
				if ( 'captured' === $column || 'datestamp' === $column ) {
					$label = 'Submission Date';
				}
				if ( $column === 'fv_status' ) {
					$label = 'Status';
				}
				$cols[] = (object) [
					$col_label => $label,
					$col_key   => $column,
					'visible'  => true,
				];
			}

			return $cols;
		}

		$cols = [];
		foreach ( $current_form_saved_columns as $column ) {
			if ( in_array( $column['colKey'], $columns, true ) ) {
				$alias = $column['alias'];
				if ( $alias === 'fv_status' ) {
					$alias = 'Status';
				}
				$cols[] = (object) [
					$col_label => $alias,
					$col_key   => $column['colKey'],
					'visible'  => $column['visible'],
				];
			}
		}

		foreach ( $columns as $column ) {
			$key   = array_search( $column, array_column( $cols, $col_key ), true );
			$alias = $column;
			if ( $alias === 'fv_status' ) {
				$alias = 'Status';
			}
			if ( false === $key ) {
				$cols[] = (object) [
					$col_label => $alias,
					$col_key   => $column,
					'visible'  => true,
				];
			}
		}

		return $cols;
	}
}
