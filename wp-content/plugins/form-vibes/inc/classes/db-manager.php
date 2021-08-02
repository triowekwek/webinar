<?php

namespace FormVibes\Classes;

/**
 * Class DbManager
 *
 * @package WPV_FV\Classes
 */
abstract class DbManager {


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
		$post_id = wp_insert_post( $args );
		$post_id = wp_insert_post( $args );

		// Add Meta Data
		$this->add_meta_entries( $post_id, $data );
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

		$exclude_forms = get_option( 'fv_exclude_forms' );

		if ( false !== $exclude_forms && '' !== $exclude_forms ) {
			if ( array_key_exists( $enteries['plugin_name'], $exclude_forms ) ) {
				if ( array_key_exists( $enteries['id'], $exclude_forms[ $enteries['plugin_name'] ] ) ) {
					return;
				}
			}
		}

		if ( false !== $exclude_forms ) {
			$exclude_forms = [];
		}

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

		if ( ! function_exists( 'write_log' ) ) {
			function write_log( $log ) {
				if ( is_array( $log ) || is_object( $log ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
					error_log( print_r( $log, true ) );
				} else {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( $log );
				}
			}
		}

		global $wpdb;
		$entry_data = [
			'form_plugin'  => $enteries['plugin_name'],
			'form_id'      => $enteries['id'],
			'captured'     => $enteries['captured'],
			'captured_gmt' => $enteries['captured_gmt'],
			'url'          => $enteries['url'],
		];
		if ( get_option( 'fvSettings' ) !== false ) {
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
		}

		write_log( '=============Form Vibes Log===================' );

		write_log( '=============Captured DATA===================' );
		write_log( $enteries );
		write_log( '-----Result----------' );
		write_log( '-----Inserting Data In Entry Table----------' );
		$wpdb->insert(
			$wpdb->prefix . 'fv_enteries',
			$entry_data
		);
		$insert_id = $wpdb->insert_id;
		if ( $insert_id !== 0 ) {
			$this->insert_fv_entry_meta( $insert_id, $enteries['posted_data'] );
		}
	}

	public function insert_fv_entry_meta( $insert_id, $enteries ) {
		global $wpdb;
		write_log( '-----Entry Id ' . $insert_id . '----------' );
		write_log( '-----Inserting Data In Entry Meta Table----------' );

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
		if ( $insert_id_meta > 1 ) {
			write_log( '==============Entry Saved Successfully===============' );
		} else {
			write_log( '==============Entry Failed===============' );
		}
	}

}
