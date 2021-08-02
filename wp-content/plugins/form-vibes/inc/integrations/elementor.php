<?php
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
namespace FormVibes\Integrations;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\Settings;
use Exception;

class Elementor extends Base {


	private static $instance     = null;
	public static $forms         = [];
	public static $submission_id = '';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->plugin_name = 'elementor';

		add_action( 'elementor_pro/forms/process', [ $this, 'form_new_record' ], 10, 2 );

		add_filter( 'fv_forms', [ $this, 'register_form' ] );

		if ( WPV_FV_PLAN === 'PRO' ) {
			add_action( 'elementor/widgets/widgets_registered', [ $this, 'elementor_widget_registered' ] );
		}
		add_action( 'wp_ajax_fv_upgrade_database', [ $this, 'fv_upgrade_database' ] );
		add_action( 'wp_ajax_nopriv_fv_upgrade_database', [ $this, 'fv_upgrade_database' ] );

		add_filter( 'elementor_pro/forms/wp_mail_message', [ $this, 'add_content_to_mail' ] );

		add_action( 'wp_ajax_elementor_data_import', [ $this, 'elementor_data_import' ] );
	}

	public function elementor_data_import() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		global $wpdb;
		$elementor_entry_table_name      = $wpdb->prefix . 'e_submissions';
		$elementor_entry_meta_table_name = $wpdb->prefix . 'e_submissions_values';
		$entry_table_name                = $wpdb->prefix . 'fv_enteries';
		$entry_meta_table_name           = $wpdb->prefix . 'fv_entry_meta';
		try {
			$wpdb->query( 'START TRANSACTION' );

			// getting entry id from fv meta table
			$elementor_data_id_query = "SELECT meta_value from ${entry_meta_table_name} WHERE meta_key = 'elementor_id'";

			// get all elementor entry table data
			// TODO:: add batch(offset) size to query
			$elementor_entry_table_data = $wpdb->get_results(
				"SELECT * from ${elementor_entry_table_name} WHERE id NOT IN (${elementor_data_id_query})"
			);

			foreach ( $elementor_entry_table_data as $row ) {
				// Making an entry in fv entry table
				$insert_success = $wpdb->insert(
					$entry_table_name,
					[
						'id'           => $row->id,
						'form_plugin'  => 'elementor',
						'form_id'      => $row->element_id,
						'captured'     => $row->created_at,
						'captured_gmt' => $row->created_at_gmt,
						'url'          => $row->referer,
						'user_agent'   => $row->user_agent,
						'fv_status'    => 'undefined',
					]
				);

				if ( ! $insert_success ) {
					throw new Exception( $wpdb->last_error );
				}

				// getting data from elementor meta table
				$elementor_entry_meta_table_data = $wpdb->get_results(
					$wpdb->prepare( "SELECT * from ${elementor_entry_meta_table_name} WHERE submission_id = %s", $row->id )
				);

				foreach ( $elementor_entry_meta_table_data as $meta_data ) {
					// inserting elementor meta to fv meta table
					$wpdb->insert(
						$entry_meta_table_name,
						[
							'data_id'    => $meta_data->submission_id,
							'meta_key'   => $meta_data->key,
							'meta_value' => $meta_data->value,
						]
					);
					// inserting ip to fv meta table
					$wpdb->insert(
						$entry_meta_table_name,
						[
							'data_id'    => $meta_data->submission_id,
							'meta_key'   => 'IP',
							'meta_value' => $row->user_ip,
						]
					);
					// inserting elementor id to fv meta table
					$wpdb->insert(
						$entry_meta_table_name,
						[
							'data_id'    => $meta_data->submission_id,
							'meta_key'   => 'elementor_id',
							'meta_value' => $row->id,
						]
					);
				}
			}

			$wpdb->query( 'COMMIT' );

			// TODO:: implement is complete.
			wp_send_json(
				[
					'is_error'      => false,
					'is_complete'   => true,
					'error_message' => '',
				]
			);
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json(
				[
					'is_error'      => true,
					'is_complete'   => false,
					'error_message' => $e,
				]
			);
		}
	}

	public function register_form( $forms ) {
		$forms[ $this->plugin_name ] = 'Elementor Forms';
		return $forms;
	}


	public function form_new_record( $record, $handler ) {

		$data = [];
		$id   = $record->get_form_settings( 'id' );

		$save_entry = true;

		$save_entry = apply_filters( 'formvibes/elementor/save_record', $save_entry, $record );

		if ( ! $save_entry ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_global = $this->check_form_global( $_POST['post_id'], $id );

		if ( ! empty( $form_global ) ) {
			$id = $form_global['templateID'];
		}

		$data['plugin_name']  = $this->plugin_name;
		$data['id']           = $id;
		$data['captured']     = current_time( 'mysql', 0 );
		$data['captured_gmt'] = current_time( 'mysql', 1 );

		$data['title'] = $record->get_form_settings( 'form_name' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data['url']              = $_POST['referrer'];
		$posted_data              = [];
		$posted_data['fv_plugin'] = $this->plugin_name;
		$posted_data              = $this->field_processor( $record );

		$settings = get_option( 'fvSettings' );

		if ( array_key_exists( 'save_ip_address', $settings ) && true === $settings['save_ip_address'] ) {
			$posted_data['IP'] = $this->set_user_ip();
		}

		$posted_data['fv_form_id'] = $id;
		$data['posted_data']       = $posted_data;

		$this->field_processor( $record );
		self::$submission_id = $this->insert_enteries( $data );
	}
	public function add_content_to_mail( $mail_content ) {
		$mail_content = str_replace( '[fv-entry-id]', self::$submission_id, $mail_content );
		return $mail_content;
	}

	public function check_form_global( $post_id, $form_id ) {

		global $wpdb;
		$meta    = get_post_meta( $post_id, '_elementor_data' );
		$element = json_decode( $meta[0], true );

		$result = $this->find_element_recursive( $element, $form_id );
		if ( $result ) {
			return $result;
		}
	}

	public function fv_upgrade_database() {
		if ( check_ajax_referer( 'fv_ajax_nonce', 'ajax_nonce' ) ) {
			global $wpdb;

			$sql_query = "SELECT distinct form_plugin,form_id FROM {$wpdb->prefix}fv_enteries WHERE form_plugin = 'elementor'";
			$results   = $wpdb->get_results( $wpdb->prepare( $sql_query ), ARRAY_A );

			foreach ( $results as $result ) {
				$query = [
					'post_type'   => 'page',
					'post_status' => 'publish',
					'meta_query'  => [
						[
							'key'     => '_elementor_data',
							'value'   => '"' . $result['form_id'] . '"',
							'compare' => 'LIKE',
						],
					],
				];

				$data = new \WP_Query( $query );
				if ( count( $data->posts ) > 0 ) {
					$post = $data->posts;
					$res  = $this->check_form_global( $post[0]->ID, $result['form_id'] );

					if ( trim( $res ) !== '' ) {
						// update form id to template id - entry table
						$qry = "UPDATE {$wpdb->prefix}fv_enteries SET form_id='" . $res['templateID'] . "' WHERE form_id = '" . $result['form_id'] . "' AND form_plugin='elementor'";
						$wpdb->query( $wpdb->prepare( $qry ) );

						// update meta table form id
						$qry = "UPDATE {$wpdb->prefix}fv_entry_meta SET meta_value='" . $res['templateID'] . "' WHERE meta_value = '" . $result['form_id'] . "' AND meta_key='fv_form_id'";
						$wpdb->query( $wpdb->prepare( $qry ) );
					}
				}
			}
		}
	}

	private function find_element_recursive( $elements, $widget_id ) {

		foreach ( $elements as $element ) {
			if ( 'widget' === $element['elType'] && 'global' === $element['widgetType'] ) {
				if ( $widget_id === $element['id'] ) {
					return $element;
				}
			}

			if ( ! empty( $element['elements'] ) ) {
				$element = $this->find_element_recursive( $element['elements'], $widget_id );

				if ( $element ) {
					return $element;
				}
			}
		}

		return false;
	}

	public function field_processor( $record ) {
		$data  = $record->get( 'fields' );
		$files = $record->get( 'files' );

		$save_data = [];
		foreach ( $data as $key => $value ) {
			if ( '' === $key || null === $key ) {
				continue;
			}

			if ( 'upload' === $value['type'] ) {
				$save_data[ $key ] = implode( ',', $files[ $key ]['url'] );
			} else {
				$save_data[ $key ] = $value['value'];
			}
		}
		return $save_data;
	}

	public static function get_forms( $param ) {
		global $wpdb;

		$form_query = "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e WHERE form_plugin='elementor'";
		$form_res   = $wpdb->get_results( $wpdb->prepare( $form_query ), OBJECT_K );

		$inserted_forms = get_option( 'fv_forms' );

		$key = 'elementor';

		foreach ( $form_res as $form_key => $form_value ) {
			if ( $form_res[ $form_key ]->form_plugin === $key ) {
				self::$forms[ $form_key ] = [
					'id'   => $form_key,
					'name' => null !== $inserted_forms[ $key ][ $form_key ]['name'] ? $inserted_forms[ $key ][ $form_key ]['name'] : $form_key,
				];
			}
		}

		return self::$forms;
	}
	public static function find_form( $element_data, $post_id, $original_data ) {
		if ( ! $element_data['elType'] ) {
			return;
		}

		if ( 'widget' === $element_data['elType'] && ( 'form' === $element_data['widgetType'] || 'global' === $element_data['widgetType'] ) ) {
			$id = self::check_global( $post_id );

			if ( 'form' === $element_data['widgetType'] ) {
				if ( null === $id || 'NULL' === $id ) {
					self::$forms[ $element_data['id'] ] = [
						'id'   => $element_data['id'],
						'name' => $element_data['settings']['form_name'],
					];
				} else {
					self::$forms[ $id ] = [
						'id'   => $id,
						'name' => $element_data['settings']['form_name'],
					];
				}
			}
		}

		if ( ! empty( $element_data['elements'] ) ) {
			foreach ( $element_data['elements'] as $element ) {
				self::find_form( $element, $post_id, $original_data );
			}
		}
	}

	public static function check_global( $post_id ) {
		global $wpdb;
		// check global key exist in meta key
		$sql_query1 = "SELECT *  FROM {$wpdb->prefix}postmeta
		WHERE meta_key LIKE '_elementor_global_widget_included_posts'
		AND post_id={$post_id}";

		$results1 = $wpdb->get_results( $wpdb->prepare( $sql_query1 ) );

		if ( ! count( $results1 ) ) {
			// not global widget
			return;
		}
		return $results1[0]->post_id;
	}

	public static function get_global_widget_id( $element_data, $post_id, $global_id ) {
		if ( ! $element_data['elType'] ) {
			return;
		}

		if ( 'widget' === $element_data['elType'] && 'global' === $element_data['widgetType'] ) {
			if ( $global_id === $element_data['templateID'] ) {
				return $element_data['id'];
			}
		}

		if ( ! empty( $element_data['elements'] ) ) {
			foreach ( $element_data['elements'] as $element ) {
				$a = self::get_global_widget_id( $element, $post_id, $global_id );
				if ( '' !== $a && null !== $a ) {
					return $a;
				}
			}
		}
	}
	public static function get_submission_data( $param ) {
		$class = '\WPV_FV\Integrations\\' . ucfirst( 'cf7' );
		$data  = $class::get_submission_data( $param );

		return $data;
	}

	public function elementor_widget_registered() {
		require_once WPV_FV_PATH . 'inc/pro/fv-data-table.php';
	}
}
