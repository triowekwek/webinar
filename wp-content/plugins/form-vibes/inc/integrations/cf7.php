<?php
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
namespace FormVibes\Integrations;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\ApiEndpoint;
use FormVibes\Classes\Utils;
use FormVibes\Integrations\Base;
use FormVibes\Classes\Settings;

class Cf7 extends Base {


	private static $instance = null;

	// array for skipping fields or unwanted data from the form data.
	protected $skip_fields       = [];
	public static $submission_id = '';

	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Cf7 constructor.
	 */
	public function __construct() {
		$this->plugin_name = 'cf7';

		$this->set_skip_fields();

		add_action( 'wpcf7_before_send_mail', [ $this, 'before_send_mail' ] );

		add_filter( 'fv_forms', [ $this, 'register_form' ] );

		add_filter( 'wpcf7_mail_components', [ $this, 'update_mail_content' ], 10, 3 );
	}

	public function register_form( $forms ) {
		$forms[ $this->plugin_name ] = 'Contact Form 7';
		return $forms;
	}

	protected function set_skip_fields() {
		// name of all fields which should not be stored in our database.
		$this->skip_fields = [ 'g-recaptcha-response', '_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post' ];
	}

	public function update_mail_content( $components, $current_form, $mail ) {

		$components['body']    = str_replace( '[fv-entry-id]', self::$submission_id, $components['body'] );
		$components['subject'] = str_replace( '[fv-entry-id]', self::$submission_id, $components['subject'] );

		return $components;
	}
	public function before_send_mail( $contact_form ) {
		$data = [];

		$submission = \WPCF7_Submission::get_instance();
		// getting all the fields or data from the form.
		$posted_data = $submission->get_posted_data();

		// File Upload

		$files = $submission->uploaded_files();

		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'form-vibes/cf7';
		if ( ! file_exists( $uploads_dir ) ) {
			wp_mkdir_p( $uploads_dir );
		}

		$cf7upload  = wp_upload_dir();
		$fv_dirname = $cf7upload['baseurl'] . '/form-vibes/cf7';

		foreach ( $files as $file_key => $file ) {
			$filetype = strrpos( $file[0], '.' );
			$filetype = substr( $file[0], $filetype );
			$filename = wp_rand( 1111111111, 9999999999 );
			$time_now = time();

			$posted_data[ $file_key ] = $fv_dirname . '/' . $time_now . '-' . $filename . $filetype;

			array_push( $uploaded_files, $time_now . '-' . $filename . $filetype );
			copy( $file[0], $uploads_dir . '/' . $time_now . '-' . $filename . $filetype );
		}

		// End File Upload Code

		// loop for skipping fields from the posted_data.
		foreach ( $posted_data as $key => $value ) {
			if ( in_array( $key, $this->skip_fields, true ) ) {
				// unset will destroy the skip's fields.
				unset( $posted_data[ $key ] );
			} elseif ( gettype( $value ) === 'array' ) {

				$posted_data[ $key ] = implode( ', ', $value );
			}
		}

		if ( $submission ) {

			$data['plugin_name']  = $this->plugin_name;
			$data['id']           = $contact_form->id();
			$data['captured']     = current_time( 'mysql', 0 );
			$data['captured_gmt'] = current_time( 'mysql', 1 );

			$data['title'] = $contact_form->title();
			$data['url']   = $submission->get_meta( 'url' );

			$posted_data['fv_plugin']  = $this->plugin_name;
			$posted_data['fv_form_id'] = $contact_form->id();

			$settings = get_option( 'fvSettings' );

			if ( array_key_exists( 'save_ip_address', $settings ) && true === $settings['save_ip_address'] ) {
				$posted_data['IP'] = $this->set_user_ip();
			}

			$data['posted_data'] = $posted_data;
		}
		self::$submission_id = $this->insert_enteries( $data );
	}

	public static function get_forms( $param ) {
		global $wpdb;

		$post_type = $param;

		$form_query = "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e WHERE form_plugin='cf7'";
		$form_res   = $wpdb->get_results( $wpdb->prepare( $form_query ), OBJECT_K );

		$inserted_forms = get_option( 'fv_forms' );

		$key   = 'cf7';
		$forms = [];
		foreach ( $form_res as $form_key => $form_value ) {
			if ( $form_res[ $form_key ]->form_plugin === $key ) {
				$forms[ $form_key ] = [
					'id'   => $form_key,
					'name' => null !== $inserted_forms[ $key ][ $form_key ]['name'] ? $inserted_forms[ $key ][ $form_key ]['name'] : $form_key,
				];
			}
		}
		return $forms;
	}

	public static function get_submission_data( $param ) {

		$meta_key = [];
		$cols     = [];

		$gmt_offset = get_option( 'gmt_offset' );
		$hours      = (int) $gmt_offset;
		$minutes    = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

		if ( $hours >= 0 ) {
			$time_zone = '+' . $hours . ':' . $minutes;
		} else {
			$time_zone = $hours . ':' . $minutes;
		}

		if ( 'Custom' !== $param['queryType'] ) {
			$dates = Utils::get_dates( $param['queryType'] );

			$tz = new \DateTimeZone( $time_zone );

			$from_date = new \DateTime( $dates['fromDate'] );
			$from_date->setTimezone( $tz );
			$to_date = new \DateTime( $dates['endDate'] );
			$to_date->setTimezone( $tz );

			$from_date = $from_date->format( 'Y-m-d' );
			$to_date   = $to_date->format( 'Y-m-d' );
		} else {
			$tz = new \DateTimeZone( $time_zone );

			$from_date = new \DateTime( $param['fromDate'] );
			$from_date->setTimezone( $tz );
			$to_date = new \DateTime( $param['toDate'] );
			$to_date->setTimezone( $tz );

			$from_date = $from_date->format( 'Y-m-d' );
			$to_date   = $to_date->format( 'Y-m-d' );
		}

		$param_where      = [];
		$paramcount_where = [];
		if ( '' !== $from_date && null !== $from_date ) {
			$param_where[]      = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $from_date . "'";
			$paramcount_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $from_date . "'";
		}
		if ( '' !== $to_date && null !== $to_date ) {
			if ( '' !== $from_date ) {
				$param_where[]      = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $to_date . "'";
				$paramcount_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $to_date . "'";
			} else {
				$param_where[]      = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $to_date . "'";
				$paramcount_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $to_date . "'";
			}
		}

		$filter_param = [];

		if ( '' === $param['selectedFilter'] || 'undefined' === $param['filterValue'] || '' === $param['filterValue'] ) {
			$filter_param[] = "meta_key like'%%'";
		} else {
			$filter_param[] = "meta_key='" . $param['selectedFilter'] . "'";
		}
		if ( 'undefined' === $param['filterValue'] || '' === $param['filterValue'] ) {
			$filter_param[] = "meta_value like'%%'";
		} else {
			if ( 'equal' === $param['filterOperator'] ) {
				$filter_param[] = "meta_value='" . $param['filterValue'] . "'";
			} elseif ( 'not_equal' === $param['filterOperator'] ) {
				$filter_param[] = "meta_value != '" . $param['filterValue'] . "'";
			} elseif ( 'contain' === $param['filterOperator'] ) {
				$filter_param[] = "meta_value LIKE '%" . $param['filterValue'] . "%'";
			} elseif ( 'not_contain' === $param['filterOperator'] ) {
				$filter_param[] = "meta_value NOT LIKE '%" . $param['filterValue'] . "%'";
			}
		}

		foreach ( $param['columns'] as $key => $val ) {
			if ( 0 === $val->visible || '' === $val->visible ) {

				$meta_key[] = $val->colKey;
			}

			$cols[] = $val->colKey;
		}

		global $wpdb;
		$settings = get_option( 'fvSettings' );

		$filter_col_id     = "select data_id FROM {$wpdb->prefix}fv_enteries e left join {$wpdb->prefix}fv_entry_meta em on e.id=em.data_id where
        " . implode( ' and ', $filter_param ) . " and form_id = '" . $param['form'] . "'";
		$filter_col_id_res = $wpdb->get_results( $wpdb->prepare( $filter_col_id ), ARRAY_A );
		$entry_id          = [];
		foreach ( $filter_col_id_res as $entry_id ) {
			$entry_id[] = $entry_id['data_id'];
		}

		$entry_query = "select * from {$wpdb->prefix}fv_enteries e
        left JOIN {$wpdb->prefix}fv_entry_meta ev ON e.id=ev.data_id
        where " . implode( ' and ', $param_where ) . " and ev.meta_key NOT IN ('" . implode( "','", $meta_key ) . "') and data_id IN ('" . implode( "','", $entry_id ) . "') and form_id = '" . $param['form'] . "' order by captured desc";

		$entry_res = $wpdb->get_results( $wpdb->prepare( $entry_query ), ARRAY_A );

		$meta_data  = [];
		$ip_checker = '';
		$settings   = get_option( 'fvSettings' );

		if ( array_key_exists( 'save_ip_address', $settings ) && true === $settings['save_ip_address'] ) {
			$ip_checker = '';
		} else {
			$ip_checker = 'IP';
		}

		foreach ( $entry_res as $entry_meta ) {
			if ( 'fv_plugin' === $entry_meta['meta_key'] || 'fv_form_id' === $entry_meta['meta_key'] || $ip_checker === $entry_meta['meta_key'] ) {
				continue;
			}
			$meta_data[ $entry_meta['data_id'] ][ $entry_meta['meta_key'] ] = stripslashes( $entry_meta['meta_value'] );
		}

		if ( ! in_array( 'captured', $meta_key, true ) ) {
			foreach ( $entry_res as $entry_meta ) {
				$meta_data[ $entry_meta['data_id'] ]['captured'] = stripslashes( $entry_meta['captured'] );
			}
		}

		if ( $settings['save_user_agent'] ) {
			if ( ! in_array( 'user_agent', $meta_key, true ) ) {
				foreach ( $entry_res as $entry_meta ) {
					$meta_data[ $entry_meta['data_id'] ]['user_agent'] = stripslashes( $entry_meta['user_agent'] );
				}
			}
		}

		$res = [];
		foreach ( $meta_data as $key => $val ) {
			$res[] = $val;
		}

		$final_array = [];
		$final_cols  = array_flip( array_diff( $cols, $meta_key ) );
		foreach ( $final_cols as $key => $value ) {
			$final_cols[ $key ] = '';
		}

		$count = count( $res );

		for ( $i = 0; $i < $count; $i++ ) {
			$final_array[] = array_merge( $final_cols, $res[ $i ] );
		}

		return $final_array;
	}
}
