<?php

namespace FormVibes\Api;

use calderawp\calderaforms\pro\api\log;
use FormVibes\Classes\Analytics;
use FormVibes\Classes\Export;
use FormVibes\Classes\Settings;
use FormVibes\Classes\Submissions;
use FormVibes\Integrations\Base;
use WP_Error;
use WP_Query;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Controller;

class AdminRest extends WP_REST_Controller {


	protected $namespace = 'formvibes/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/submissions',
			// Get Submissions -> done
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'view_submissions' ],
					'permission_callback' => [ '\\FormVibes\\Classes\\Permissions', 'view_submissions' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/submissions/delete',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'delete_entries' ],
					'permission_callback' => [ '\\FormVibes\\Classes\\Permissions', 'delete_submissions' ],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/saveOption',
			// Save Option -> done
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_options' ],
					'permission_callback' => [ '\\FormVibes\\Classes\\Permissions', 'view_submissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/analytics',
			// Get Analytics done
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'view_analytics' ],
					'permission_callback' => [ '\\FormVibes\\Classes\\Permissions', 'view_submissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings',
			// Save Settings done
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_settings' ],
					'permission_callback' => [ '\\FormVibes\\Classes\\Permissions', 'view_submissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/event_logs',
			// Get Event logs Data done
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'fv_get_logs_data' ],
					'permission_callback' => [ '\\FormVibes\\Classes\\Permissions', 'view_submissions' ],
				],
			]
		);
	}

	private function get_params( $request ) {
		return $request->get_json_params();
	}
	private function make_params( $params ) {
		$temp = [
			'query_type' => '',
			'per_page'   => '',
			'page_num'   => '',
			'fromDate'   => '',
			'toDate'     => '',
			'plugin'     => '',
			'formid'     => '',
		];

		return array_merge( $temp, $params );
	}

	public function view_submissions( WP_REST_Request $request ) {
		$params      = $this->make_params( $this->get_params( $request ) );
		$plugin      = $params['plugin'];
		$submissions = new Submissions( $plugin ); // TODO:: Get elementor from request
		$data        = $submissions->get_submissions( $params );
		wp_send_json( $data );
	}

	public function fv_get_logs_data( WP_REST_Request $request ) {
		$params      = $this->get_params( $request );
		$submissions = new Submissions( '' );
		$logs_data   = $submissions->fv_get_logs_data( $params );
		wp_send_json( $logs_data );
	}

	public function save_settings( WP_REST_Request $request ) {
		$params   = $this->get_params( $request );
		$callback = $params['callback'];
		$settings = new Settings();
		$res      = $settings->$callback( $params );
		wp_send_json( $res );
	}

	public function view_analytics( WP_REST_Request $request ) {
		$params    = $this->make_params( $this->get_params( $request ) );
		$plugin    = $params['plugin'];
		$analytics = new Submissions( $plugin );
		$data      = $analytics->get_analytics( $params );
		wp_send_json( $data );
	}

	public function delete_entries( WP_REST_Request $request ) {
		$ids = $this->get_params( $request );
		Base::delete_entries( $ids );
	}

	public function save_options( WP_REST_Request $request ) {
		$params      = $this->get_params( $request );
		$plugin      = $params['plugin'];
		$submissions = new Submissions( $plugin );
		// TODO:: Get elementor from request
		$submissions->save_options( $params );
	}
}
