<?php

namespace FormVibes\Classes;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\Utils;
use Exception;

class Settings {


	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_filter( 'formvibes/global/settings', [ $this, 'set_default_settings' ] );
		add_filter( 'formvibes/global/settings', [ $this, 'set_settings' ] );
		add_action( 'wp_ajax_save_settings', [ $this, 'save_settings' ] );
		$this->set_initial_settings();
	}

	private function set_initial_settings() {
		$settings         = get_option( 'fvSettings' );
		$settings_default = $this->get_default_settings();
		if ( ! $settings ) {
			$save_settings = [];
			foreach ( $settings_default as $key => $values ) {
				$save_settings[ $key ] = $values['default'];
			}
			update_option( 'fvSettings', $save_settings, false );
		}
	}

	public function set_settings( $args ) {

		$settings               = get_option( 'fvSettings' );
		$settings               = apply_filters( 'formvibes/settings/saved', $settings );
		$args['settings_saved'] = $settings;
		return $args;
	}

	public function set_default_settings( $args ) {

		$settings                 = $this->get_default_settings();
		$args['settings_default'] = $settings;

		return $args;
	}

	public function save_settings() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}
		$settings = (array) json_decode( stripslashes( sanitize_text_field( $_POST['params'] ) ) );

		try {
			// save settings to db.
			$is_saved = update_option( 'fvSettings', $settings, false );

			if ( $is_saved ) {
				wp_send_json(
					[
						'is_error' => false,
						'message'  => 'Settings have been saved successfully.',
					]
				);
			}

			throw new Exception( 'error' );

		} catch ( Exception $e ) {
			wp_send_json(
				[
					'is_error' => true,
					'message'  => 'Failed to save settings in database.',
				]
			);
		}
	}

	public function get_default_settings() {
		$settings = [
			'dashboard_widget'       => [
				'label'   => __( 'Dashboard Widgets', 'wpv-fv' ),
				'type'    => 'select',
				'options' => [
					'enable'  => [
						'label' => __( 'Enable', 'wpv-fv' ),
					],
					'disable' => [
						'label' => __( 'Disable', 'wpv-fv' ),
					],
				],
				'default' => 'enable',
			],
			'save_ip_address'        => [
				'label'   => __( 'Save IP Address', 'wpv-fv' ),
				'type'    => 'toggle',
				'default' => true,
			],
			'save_user_agent'        => [
				'label'   => __( 'Save User Agent', 'wpv-fv' ),
				'type'    => 'toggle',
				'default' => true,
			],
			'debug_mode'             => [
				'label'   => __( 'Debug Mode', 'wpv-fv' ),
				'type'    => 'toggle',
				'default' => false,
			],
			'csv_export_reason'      => [
				'label'   => __( 'CSV Export Reason', 'wpv-fv' ),
				'type'    => 'toggle',
				'default' => false,
			],
			'auto_refresh'           => [
				'label'   => __( 'Auto Refresh', 'wpv-fv' ),
				'type'    => 'toggle',
				'default' => false,
			],
			'auto_refresh_frequency' => [
				'label'      => __( 'Auto Refresh Frequency', 'wpv-fv' ),
				'type'       => 'input',
				'input_type' => 'number',
				'default'    => 30,
			],
			'persist_filter' => [
				'label'   => __( 'Persist Filter', 'wpv-fv' ),
				'type'    => 'toggle',
				'default' => true,
			],
		];

		$settings = apply_filters( 'formvibes/settings/default', $settings );
		return $settings;
	}

}
