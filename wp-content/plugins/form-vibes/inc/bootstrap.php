<?php

namespace FormVibes;

use FormVibes\Classes\Export;
use FormVibes\Classes\Utils;
use FormVibes\Integrations\Cf7;
use FormVibes\Integrations\Elementor;
use FormVibes\Integrations\Caldera;
use FormVibes\Classes\ApiEndpoint;
use FormVibes\Classes\Settings;
use FormVibes\Integrations\BeaverBuilder;
use function GuzzleHttp\Promise\all;
use FormVibes\Classes\Forms;
use FormVibes\Classes\DbTables;

class Plugin {


	private static $instance    = null;
	private $current_tab        = '';
	private static $_forms      = null;
	private $cap_fv_leads       = 'publish_posts';
	private $cap_fv_analytics   = 'publish_posts';
	private $cap_fv_export      = 'publish_posts';
	private $cap_fv_view_logs   = 'publish_posts';
	private $fv_title           = 'Form Vibes';
	private static $show_notice = true;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ], 10, 1 );

		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'plugins_loaded', [ 'FormVibes\Classes\DbTables', 'fv_plugin_activated' ] );

		add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widgets' ] );

		add_filter( 'plugin_action_links_' . plugin_basename( WPV_FV_PATH . 'form-vibes.php' ), [ $this, 'settings_link' ], 10 );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'caldera-forms/caldera-core.php' ) ) {
			new Caldera();
		}
		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			new Cf7();
		}
		if ( is_plugin_active( 'elementor-pro/elementor-pro.php' ) || is_plugin_active( 'pro-elements/pro-elements.php' ) ) {

			new Elementor();
		}
		if ( is_plugin_active( 'bb-plugin/fl-builder.php' ) ) {

			new BeaverBuilder();
		}

		Settings::instance();

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 9 );

		add_action( 'admin_menu', [ $this, 'admin_menu_after_pro' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

		add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ] );
		add_action( 'init', [ $this, 'fv_db_update' ] );

		add_action( 'in_admin_header', [ $this, 'in_admin_header' ] );

		$this->fv_title = apply_filters( 'formvibes/fv_title', 'Form Vibes' );

		self::$_forms = Forms::instance();

		new Export( '' );
	}

	public function init_rest_api() {

		$controllers = [
			new \FormVibes\Api\AdminRest(),
		];

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}

	public function go_pro_link( $links ) {
		$links['go_pro'] = sprintf( '<a href="%1$s" target="_blank" class="fv-pro-link">%2$s</a>', 'https://wpvibes.com', __( 'Go Pro', 'wpv-fv' ) );

		return $links;
	}

	public function admin_footer_text( $footer_text ) {
		$screen = get_current_screen();
		// Todo:: Show on plugin screens
		$fv_screens = [
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
			'edit-fv_data_profile',
			'edit-fv_export_profile',
		];

		if ( in_array( $screen->id, $fv_screens, true ) ) {
			$footer_text = sprintf(
				/* translators: 1: Form Vibes, 2: Link to plugin review */
				__( 'Enjoyed %1$s? Please leave us a %2$s rating. We really appreciate your support!', 'wpv-fv' ),
				'<strong>' . __( 'Form Vibes', 'wpv-fv' ) . '</strong>',
				'<a href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
	}

	public function wpv_fv() {
		global $wpv_fv;
		if ( ! isset( $wpv_fv ) ) {
			// Include Freemius SDK.
			require_once WPV_FV_PATH . '/freemius/start.php';
			$wpv_fv = fs_dynamic_init(
				[
					'id'             => '4666',
					'slug'           => 'form-vibes',
					'type'           => 'plugin',
					'public_key'     => 'pk_321780b7f1d1ee45009cf6da38431',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => false,
					'menu'           => [
						'slug'       => 'fv-leads',
						'first-path' => 'admin.php?page=fv-db-settings',
						'account'    => false,
						'contact'    => false,
						'support'    => false,
					],
				]
			);
		}
		return $wpv_fv;
	}

	public function autoload( $class ) {

		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		if ( ! class_exists( $class ) ) {

			$filename = strtolower(
				preg_replace(
					[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
					[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
					$class
				)
			);

			$filename = WPV_FV_PATH . '/inc/' . $filename . '.php';
			if ( is_readable( $filename ) ) {
				include $filename;
			}
		}
	}



	public function admin_scripts() {
		$screen = get_current_screen();

		wp_enqueue_style( 'fv-style-css', WPV_FV_URL . 'assets/css/style.css', [], WPV_FV_VERSION );
			wp_enqueue_script( 'fv-js', WPV_FV_URL . 'assets/script/index.js', [ 'jquery-ui-datepicker' ], WPV_FV_VERSION, true );

			$user      = wp_get_current_user();
			$user_role = $user->roles;

				$global_settings = [
					'site_url'                     => site_url(),
					'ajax_url'                     => admin_url( 'admin-ajax.php' ),
					'admin_url'                    => admin_url(),
					'rest_url'                     => get_rest_url(),
					'fv_version'                   => WPV_FV_VERSION,
					// current user
					'user'                         => $user_role,
					'nonce'                        => wp_create_nonce( 'wp_rest' ),
					'ajax_nonce'                   => wp_create_nonce( 'fv_ajax_nonce' ),
					// All Forms
					'forms'                        => Utils::prepare_forms_data(),
					'fv_dashboard_widget_settings' => get_option( 'fv_dashboard_widget_settings' ),
					'entry_table_fields'           => Utils::get_entry_table_fields(),
					'saved_columns'                => Utils::get_fv_keys(),
					'user_capability'              => [],
				];

				$global_settings = apply_filters(
					'formvibes/global/settings',
					$global_settings
				);

			wp_localize_script(
				'fv-js',
				'fvGlobalVar',
				$global_settings
			);

			add_action( 'admin_print_scripts', [ $this, 'fv_disable_admin_notices' ] );

			wp_enqueue_style( 'wp-components' );

		if ( 'dashboard' === $screen->id ) {
			$this->load_dashboard_scripts();
		}
		if ( 'form-vibes_page_fv-db-settings' === $screen->id ) {
			$this->load_settings_scripts();
		}
		if ( 'form-vibes_page_fv-analytics' === $screen->id ) {
			$this->load_analytics_scripts();
		}
		if ( 'form-vibes_page_fv-logs' === $screen->id ) {
			$this->load_logs_scripts();
		}
		if ( 'toplevel_page_fv-leads' === $screen->id ) {
			$this->load_submissions_scripts();
		}
	}

	private function load_submissions_scripts() {
		wp_enqueue_script( 'submissions-js', WPV_FV_URL . 'assets/js/submissions.js', [ 'wp-components' ], WPV_FV_VERSION, true );
		wp_enqueue_style( 'fv-submission-css', WPV_FV_URL . 'assets/js/submissions.css', '', WPV_FV_VERSION );
	}
	private function load_logs_scripts() {
		wp_enqueue_script( 'analytics-js', WPV_FV_URL . 'assets/js/eventLogs.js', [ 'wp-components' ], WPV_FV_VERSION, true );
		wp_enqueue_style( 'analytics-css', WPV_FV_URL . 'assets/js/eventLogs.css', '', WPV_FV_VERSION );
	}

	private function load_analytics_scripts() {
		wp_enqueue_script( 'analytics-js', WPV_FV_URL . 'assets/js/analytics.js', [ 'wp-components' ], WPV_FV_VERSION, true );
			wp_enqueue_style( 'analytics-css', WPV_FV_URL . 'assets/js/analytics.css', '', WPV_FV_VERSION );
	}

	private function load_dashboard_scripts() {
			wp_enqueue_script( 'dashboard-select-form-js', WPV_FV_URL . 'assets/script/add-dashboard-widget-gear-icon.js', [], WPV_FV_VERSION, true );

			wp_enqueue_script( 'dashboard-js', WPV_FV_URL . 'assets/js/dashboard.js', [ 'wp-components' ], WPV_FV_VERSION, true );
			wp_enqueue_script( 'script-js', WPV_FV_URL . 'assets/script/index.js', '', WPV_FV_VERSION, true );
			wp_enqueue_style( 'dashboard-css', WPV_FV_URL . 'assets/js/dashboard.css', '', WPV_FV_VERSION );
	}

	private function load_settings_scripts() {
		wp_enqueue_script( 'setting-js', WPV_FV_URL . 'assets/js/settings.js', [ 'wp-components' ], WPV_FV_VERSION, true );
			wp_enqueue_style( 'setting-css', WPV_FV_URL . 'assets/js/settings.css', '', WPV_FV_VERSION );
	}

	public function add_dashboard_widgets() {
		$settings         = get_option( 'fvSettings' );
		$dashboard_widget = 'disable';

		if ( $settings && array_key_exists( 'dashboard_widget', $settings ) ) {
			$dashboard_widget = $settings['dashboard_widget'];
		}

		if ( $dashboard_widget !== 'enable' ) {
			return;
		}

		if ( ! $this->check_capability( $this->cap_fv_analytics ) ) {
			return;
		}

		add_meta_box( 'form_vibes_widget-0', 'Form Vibes Analytics', [ $this, 'dashboard_widget' ], null, 'normal', 'high', 0 );
	}

	public function dashboard_widget( $vars, $i ) {
		echo '<div name="dashboard-widget" id="fv-dashboard-widgets-' . esc_html( $i['args'] ) . '">
				</div>';
	}


	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( WPV_FV__PLUGIN_BASE === $plugin_file ) {
			$row_meta = [
				'docs'    => '<a href="https://wpvibes.link/go/fv-all-docs-pp/" aria-label="' . esc_attr( __( 'View Documentation', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Read Docs', 'wpv-fv' ) . '</a>',
				'support' => '<a href="https://wpvibes.link/go/form-vibes-support/" aria-label="' . esc_attr( __( 'Support', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Need Support', 'wpv-fv' ) . '</a>',
			];

			$plugin_meta = array_merge( $plugin_meta, $row_meta );
		}

		return $plugin_meta;
	}


	public function admin_menu() {

		$this->cap_fv_leads = apply_filters( 'formvibes/cap/view_submissions', 'publish_posts' );

		$this->cap_fv_analytics = apply_filters( 'formvibes/cap/view_fv_analytics', 'publish_posts' );

		$this->cap_fv_export = apply_filters( 'formvibes/cap/export_fv_submissions', 'publish_posts' );

		add_menu_page( 'Form Vibes Leads', 'Form Vibes', $this->cap_fv_leads, 'fv-leads', [ $this, 'display_react_table' ], 'dashicons-analytics', 30 );
		add_submenu_page( 'fv-leads', 'Form Vibes Submissions', 'Submissions', $this->cap_fv_leads, 'fv-leads', [ $this, 'display_react_table' ], 1 );
		add_submenu_page( 'fv-leads', 'Form Vibes Analytics', 'Analytics', $this->cap_fv_analytics, 'fv-analytics', [ $this, 'fv_analytics' ], 2 );
	}



	public function admin_menu_after_pro() {

		$this->cap_fv_view_logs = apply_filters( 'formvibes/cap/view_fv_logs', 'publish_posts' );
		add_submenu_page( 'fv-leads', 'Form Vibes Settings', 'Settings', 'manage_options', 'fv-db-settings', [ $this, 'fv_db_settings' ], 5 );
		add_submenu_page( 'fv-leads', 'Form Vibes Logs', 'Event Logs', $this->cap_fv_view_logs, 'fv-logs', [ $this, 'fv_logs' ], 6 );
	}
	public function fv_disable_admin_notices() {
		global $wp_filter;
		$screen     = get_current_screen();
		$fv_screens = [
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
		];

		if ( in_array( $screen->id, $fv_screens, true ) ) {
			if ( is_user_admin() ) {
				if ( isset( $wp_filter['user_admin_notices'] ) ) {
					unset( $wp_filter['user_admin_notices'] );
				}
			} elseif ( isset( $wp_filter['admin_notices'] ) ) {
				unset( $wp_filter['admin_notices'] );
			}
			if ( isset( $wp_filter['all_admin_notices'] ) ) {
				unset( $wp_filter['all_admin_notices'] );
			}
		}

		$this->fv_review_box();
		$this->fv_pro_purchase();
		$this->pro_notice();
		add_action( 'admin_notices', [ $this, 'fv_table_notice' ] );
	}

	public function pro_notice() {
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );

		if ( defined( 'WPV_FV_VERSION' ) && $is_pro_activated ) {
			if ( version_compare( WPV_FV_MIN_VERSION, WPV_FV_VERSION, '>' ) ) {
				add_action( 'admin_notices', 'fv_update_notice' );
			}
			if ( version_compare( WPV_PRO_FV_VERSION, WPV_FV_PRO_RECOMMENDED_VERSION, '<' ) ) {
				add_action( 'admin_notices', [ $this, 'update_pro_notice' ] );
			}
		}
	}

	public function update_pro_notice() {
		?>
		<div class="fv-plugin-error error">
		<p>
			You are using an older version of <b>Form Vibes Pro.</b>
			Kindly <a href="plugins.php">update</a> to latest version.
		</p>
	</div>
		<?php
	}


	public function fv_review_box() {

		if ( isset( $_GET['fv_nonce'] ) && ! wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if ( isset( $_GET['remind_later'] ) ) {
			add_action( 'admin_notices', [ $this, 'fv_remind_later' ] );

		} elseif ( isset( $_GET['review_done'] ) ) {
			add_action( 'admin_notices', [ $this, 'fv_review_done' ] );
		} else {
			add_action( 'admin_notices', [ $this, 'fv_review' ] );
		}
	}

	public function fv_review() {
		$show_review = get_transient( 'fv_remind_later' );

		$review_status = get_option( 'fv-review' );

		if ( 'done' !== $review_status ) {
			if ( ( '' === $show_review || false === $show_review ) && self::$show_notice ) {
				global $wpdb;

				$rowcount       = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries" );
				$current_screen = get_current_screen();
				$page_id        = $current_screen->id;
				$fv_page_id_arr = [
					'toplevel_page_fv-leads',
					'form-vibes_page_fv-analytics',
					'edit-fv_export_profile',
					'edit-fv_data_profile',
					'form-vibes_page_fv-db-settings',
					'form-vibes_page_fv-logs',
				];
				$hide_logo      = '';
				if ( in_array( $page_id, $fv_page_id_arr, true ) ) {
					$hide_logo = 'fv-hide-logo';
				}
				if ( $rowcount > 9 ) {
					self::$show_notice = false;
					?>
					<div class="fv-review notice notice-success is-dismissible">
						<div class="fv-logo
					<?php

						echo esc_html( $hide_logo );
					?>
							"
						>
							<svg viewBox="0 0 1340 1340" version="1.1">
								<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
									<g id="Artboard" transform="translate(-534.000000, -2416.000000)" fill-rule="nonzero">
										<g id="g2950" transform="translate(533.017848, 2415.845322)">
											<circle id="circle2932" fill="#FF6634" cx="670.8755" cy="670.048026" r="669.893348"></circle>
											<path d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z" id="Combined-Shape" fill="#FFFFFF"></path>
										</g>
									</g>
								</g>
							</svg>
						</div>
						<div class="fv-review-content">
							<p class="fv-review-desc">
						<?php

						echo 'Form Vibes has already captured 10+ form submissions. That’s awesome! Could you please do a BIG favor and give it a 5-star rating on WordPress? <br/> Just to help us spread the word and boost our motivation. <br/><b>~ Anand Upadhyay</b>'
						?>
							</p>
							<span class="fv-notic-link-wrapper">
								<a class="fv-notice-link" target="_blank" href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" class="button button-primary"><span class="dashicons dashicons-heart"></span><?php esc_html_e( 'Ok, you deserve it!', 'wpv-fv' ); ?></a>
								<a class="fv-notice-link" href="<?php echo esc_html( add_query_arg( 'remind_later', 'later' ) ); ?>"><span class="dashicons dashicons-schedule"></span><?php esc_html_e( 'May Be Later', 'wpv-fv' ); ?></a>
								<a class="fv-notice-link" href="
							<?php
							echo esc_html(
								add_query_arg( 'review_done', 'done' ) .
								add_query_arg( 'fv_nonce', wp_create_nonce( 'wp_rest' ) )
							);
							?>
							"><span class="dashicons dashicons-smiley"></span><?php esc_html_e( 'Already Done', 'wpv-fv' ); ?></a>
							</span>
						</div>
					</div>
						<?php
				}
			}
		}
	}
	public function fv_remind_later() {
		set_transient( 'fv_remind_later', 'show again', WEEK_IN_SECONDS );
	}

	public function fv_review_done() {
		update_option( 'fv-review', 'done', false );
	}

	private function check_capability( $cap ) {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( ! $user->has_cap( $cap ) ) {
				return false;
			}
		}
		return true;
	}

	public function display_react_table() {
		if ( ! $this->check_capability( $this->cap_fv_leads ) ) {
			return;
		}
		?>
		<div id="fv-submissions">

		</div>
		<?php
	}

	public function fv_logs() {
		if ( ! $this->check_capability( $this->cap_fv_view_logs ) ) {
			return;
		}

		?>
		<div id="fv-logs" class="fv-logs">
			<div class="fv-wrapper">
				<div class="fv-data-wrapper">
					<div id="fv-event-log-wrapper" class="fv-event-log-wrapper">
					</div>
				<?php $this->sidebar(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function sidebar() {
		echo ''
		?>
		<div class="fv-sidebar">
			<div class="fv-sidebar-wrapper">
				<div class="fv-sidebar-box" style="display: none">
					<div class="fv-sidebar-inner">
						<div class="fv-free-version">
							<h4 class="fv_title">Form Vibes:</h4>
							<span class="fv_version"><?php echo esc_html( WPV_FV_VERSION ); ?></span>
						</div>
					<?php
					$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
					if ( $is_pro_activated ) {
						?>
							<div class="fv-pro-version">
								<h4>Form Vibes Pro:</h4>
								<span><?php echo esc_html( WPV_PRO_FV_VERSION ); ?></span>
							</div>
							<?php
					}
					?>
					</div>
				</div>
				<div class="fv-sidebar-box">
					<h3>Need Help?</h3>
					<div class="fv-sidebar-inner">
						<ul>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-getting-started/">Getting Started</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-view-submitted-data/">View Submitted Data</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-export-form-data-to-csv/">Export to CSV</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-data-analytics/">View Data Analytics</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-add-dashboard-widget/">Add Dashboard Widgets</a></li>
						</ul><a target="_blank" href="https://wpvibes.link/go/fv-all-docs/"><b>View All Documentation <i class="dashicons dashicons-arrow-right"></i></b></a><br><a target="_blank" href="https://wpvibes.link/go/form-vibes-support/"><b>Get Support <i class="dashicons dashicons-arrow-right"></i></b></a>
					</div>
				</div>
			</div>

		</div>
			<?php
	}

	public function fv_analytics() {
		if ( ! $this->check_capability( $this->cap_fv_analytics ) ) {
			return;
		}

		?>
		<div id="fv-analytics" class="fv-analytics"></div>
		<?php
	}
	public function fv_db_settings() {

		if ( isset( $_GET['fv_nonce'] ) && ! wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if ( isset( $_GET['tab'] ) ) {
			$this->current_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
		}

		$setting_pages = [
			'general' => __( 'General', 'wpv-fv' ),
		];

		$setting_pages = apply_filters( 'formvibes/settings/pages', $setting_pages );

		?>

		<div class="fv-settings-wrapper">

			<div class="fv-data-wrapper">
				<div class="fv-settings-content-wrapper">
					<nav aria-label="nav_links" class="fv-nav-tab-wrapper">
					<?php
					foreach ( $setting_pages as $key => $label ) {
						?>
							<a class="fv-nav-tab <?php echo ( ( '' === $this->current_tab && 'general' === $key ) || $key === $this->current_tab ) ? 'fv-tab-active' : ''; ?>" href="admin.php?page=fv-db-settings&tab=<?php echo esc_html( $key ); ?>&fv_nonce=<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ); ?>"><?php echo esc_html( $label ); ?></a>
							<?php
					}
					?>
					</nav>

					<div class="fv-settings-tab-content-wrapper">

					<?php
					if ( '' === $this->current_tab || 'general' === $this->current_tab ) {
						?>
							<div id="fv-settings-general"></div>
						<?php
					}

					do_action( 'formvibes/settings/' . $this->current_tab );
					?>

					</div>

				</div>

					<?php $this->sidebar(); ?>
			</div>

		</div>

			<?php
	}
	public function fv_render_controls() {
		?>
		<div id="fv-render-controls" class="fv-render-controls-wrapper"></div>
		<?php
	}

	public function in_admin_header() {
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );

		$nav_links = $this->get_nav_links();
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( ! in_array( 'administrator', $user->roles, true ) ) {
				// remove setting page link
				unset( $nav_links['form-vibes_page_fv-db-settings'] );
			}
			if ( ! in_array( 'administrator', $user->roles, true ) && $is_pro_activated ) {
				if ( ! $user->has_cap( 'view_fv_submissions' ) ) {
					// remove submissions page link
					unset( $nav_links['toplevel_page_fv-leads'] );
				}
				if ( ! $user->has_cap( 'view_fv_analytics' ) ) {
					// remove analytics page link
					unset( $nav_links['form-vibes_page_fv-analytics'] );
				}
			}
		}
		$current_screen = get_current_screen();

		if ( ! isset( $nav_links[ $current_screen->id ] ) ) {
			return;
		}

		?>

		<div class="fv-admin-topbar">
			<div class="fv-branding">
				<svg viewBox="0 0 1340 1340" version="1.1">
					<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
						<g id="Artboard" transform="translate(-534.000000, -2416.000000)" fill-rule="nonzero">
							<g id="g2950" transform="translate(533.017848, 2415.845322)">
								<circle id="circle2932" fill="#FF6634" cx="670.8755" cy="670.048026" r="669.893348"></circle>
								<path d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z" id="Combined-Shape" fill="#FFFFFF"></path>
							</g>
						</g>
					</g>
				</svg>
				<h1><?php echo esc_html( $this->fv_title ); ?></h1>
				<span class="fv-version"><?php echo esc_html( WPV_FV_VERSION ); ?></span>
			</div>

			<nav class="fv-nav" aria-label="nav_links">
				<ul>
				<?php
				if ( isset( $nav_links ) && count( $nav_links ) ) {
					foreach ( $nav_links as $id => $link ) {

						if ( false === $link['top_nav'] ) {
							continue;
						}

						$active = ( $current_screen->id === $id ) ? 'fv-nav-active' : '';
						$target = 'Get Support' === $link['label'] ? 'target="_blank"' : '';
						?>
							<li class="<?php echo esc_html( $active ); ?>">
								<a <?php echo esc_html( $target ); ?> href="<?php echo esc_html( $link['link'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
							</li>
							<?php
					}
				}
				?>
				</ul>
			</nav>
		</div>

			<?php
			do_action( 'fv_notices' );
	}

	public function get_nav_links() {

		$nav = [
			'toplevel_page_fv-leads'         => [
				'label'   => __( 'Submissions', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-leads' ),
				'top_nav' => true,
			],

			'form-vibes_page_fv-analytics'   => [
				'label'   => __( 'Analytics', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-analytics' ),
				'top_nav' => true,
			],

			'form-vibes_page_fv-db-settings' => [
				'label'   => __( 'Settings', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-db-settings' ),
				'top_nav' => true,
			],

			'get_support'                    => [
				'label'   => __( 'Get Support', 'wpv-fv' ),
				'link'    => 'https://wpvibes.link/go/form-vibes-support/',
				'top_nav' => true,
			],

			'form-vibes_page_fv-logs'        => [
				'label'   => __( 'Event Log', 'wpv-fv' ),
				'link'    => admin_url( 'admin.php?page=fv-logs' ),
				'top_nav' => false,

			],
		];

		$nav = apply_filters( 'formvibes/nav_links', $nav );

		return $nav;
	}

	public function handle_pro() {
		wp_safe_redirect( 'https://go.elementor.com/docs-admin-menu/' );
		die();
	}

	public function fv_pro_purchase() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
		if ( $is_pro_activated ) {
			return;
		}

		if ( isset( $_GET['fv_nonce'] ) && ! wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if ( isset( $_GET['fv_pro_later'] ) ) {
			add_action( 'admin_notices', [ $this, 'fv_pro_later' ] );

		} elseif ( isset( $_GET['fv_pro_done'] ) ) {
			add_action( 'admin_notices', [ $this, 'fv_pro_done' ] );
		} else {
			add_action( 'admin_notices', [ $this, 'fv_pro_purchase' ] );
		}

		$check_review = get_option( 'fv_pro_purchase' );

		if ( ! $check_review ) {
			$review = [
				'installed' => current_time( 'yy/m/d' ),
				'status'    => '',
			];

			update_option( 'fv_pro_purchase', $review );
		}

		$check_review = get_option( 'fv_pro_purchase' );

		$start = $check_review['installed'];
		$end   = current_time( 'yy/m/d' );

		$days = $this->date_diff( $start, $end );

		if ( $days < 6 ) {
			return;
		}

		if ( ( '' === $check_review['status'] || 'remind_later' === $check_review['status'] ) && self::$show_notice ) {

			add_action( 'admin_notices', [ $this, 'fv_pro_purchase_box' ], 10 );
		}
	}

	public function date_diff( $start, $end ) {
		$start_time = strtotime( $start );
		$end_time   = strtotime( $end );
		$date_diff  = $end_time - $start_time;
		return round( $date_diff / 86400 );
	}

	public function fv_pro_purchase_box( $review ) {
		if ( ! self::$show_notice ) {
			return;
		}

		$review = get_option( 'fv_pro_purchase' );

		$remind_later   = get_transient( 'fv_pro_remind_later' );
		$status         = $review['status'];
		$current_screen = get_current_screen();
		$page_id        = $current_screen->id;
		$fv_page_id_arr = [
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'edit-fv_export_profile',
			'edit-fv_data_profile',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
		];

		$hide_logo = '';
		if ( in_array( $page_id, $fv_page_id_arr, true ) ) {
			$hide_logo = 'fv-hide-logo';
		}
		if ( 'done' !== $status ) {
			if ( '' === $status && false === $remind_later ) {
				?>
				<div class="fv-pro-box notice notice-success">
					<div class="fv-logo <?php echo esc_html( $hide_logo ); ?>">
						<svg viewBox="0 0 1340 1340" version="1.1">
							<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
								<g id="Artboard" transform="translate(-534.000000, -2416.000000)" fill-rule="nonzero">
									<g id="g2950" transform="translate(533.017848, 2415.845322)">
										<circle id="circle2932" fill="#FF6634" cx="670.8755" cy="670.048026" r="669.893348"></circle>
										<path d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z" id="Combined-Shape" fill="#FFFFFF"></path>
									</g>
								</g>
							</g>
						</svg>
					</div>
					<div class="fv-pro-content">
						<span>
						<?php printf( 'Enjoying Form Vibes? Explore <b>%1$s</b> for more advanced features.', 'Form Vibes Pro' ); ?>

						</span>
						<span class="fv-go-pro-button">
							<a class="button button-primary " target="_blank" href="https://wpvibes.link/go/form-vibes-pro"><?php esc_html_e( 'Explore Pro!', 'wpv-fv' ); ?></a>

						</span>
						<a class="notice-dismiss" href="<?php echo esc_html( add_query_arg( 'fv_pro_later', 'later' ) . add_query_arg( 'fv_nonce', wp_create_nonce( 'wp_rest' ) ) ); ?>"></a>
					</div>
				</div>
				<?php
			}
		}
	}


	public function fv_pro_later() {
		set_transient( 'fv_pro_remind_later', 'show again', MONTH_IN_SECONDS );
	}

	public function fv_pro_done() {
		$review              = get_option( 'fv_pro_purchase' );
		$review['status']    = 'done';
		$review['purchased'] = current_time( 'yy/m/d' );
		update_option( 'fv_pro_purchase', $review, false );
	}

	public function settings_link( $links ) {
		$url           = admin_url( 'admin.php' ) . '?page=fv-db-settings';
		$settings_link = '<a class="fv-go-pro-menu" href=' . $url . '>Settings</a>';

		array_unshift( $links, $settings_link );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		return $links;
	}

	public function fv_table_notice() {
		$screen = get_current_screen();

		if ( $screen->id === 'form-vibes_page_fv-db-settings' ) {
			global $wpdb;
			$table_exist = true;

			$settings   = get_option( 'fvSettings' );
			$debug_mode = false;
			if ( $settings && array_key_exists( 'debug_mode', $settings ) ) {
				$debug_mode = $settings['debug_mode'];
			}

			if ( ! $debug_mode ) {
				return;
			}

			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_enteries'" ) === null ) {
				$table_exist = false;
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_entry_meta'" ) === null ) {
				$table_exist = false;
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_logs'" ) === null ) {
				$table_exist = false;
			}

			if ( $table_exist ) {
				return;
			} else {
				?>
				<div class="fv-notice notice notice-error">
					<div class="fv-notice-content">
						<span>
						<?php esc_html_e( 'Database update required.', 'wpv-fv' ); ?>
						</span>
						<span class="fv-notice-action">
							<a href="<?php echo esc_html( add_query_arg( 'fv_db_update', 'yes' ) . add_query_arg( 'fv_nonce', wp_create_nonce( 'wp_rest' ) ) ); ?>"><?php esc_html_e( 'Click here!', 'wpv-fv' ); ?></a>
						</span>
					</div>
				</div>
				<?php
			}
		}
	}

	public function fv_db_update() {

		if ( isset( $_GET['fv_nonce'] ) && ! wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if ( isset( $_GET['fv_db_update'] ) ) {

			DbTables::create_db_table();
		}
	}
}

Plugin::instance();
