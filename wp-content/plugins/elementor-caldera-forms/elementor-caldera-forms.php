<?php
/**
 * Plugin Name: Caldera Forms styler for Elementor
 * Description: Caldera Forms styler for elementor. Design the form visually with elementor.
 * Plugin URI: https://essential-addons.com/elementor/caldera-forms
 * Author: Essential Addons
 * Version: 1.0.0
 * Author URI: https://essential-addons.com/elementor/
 *
 * Text Domain: elementor-caldera-forms
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'EAEL_CALDERA_FORMS_URL', plugins_url( '/', __FILE__ ) );
define( 'EAEL_CALDERA_FORMS_PATH', plugin_dir_path( __FILE__ ) );


require_once EAEL_CALDERA_FORMS_PATH.'includes/elementor-helper.php';
require_once EAEL_CALDERA_FORMS_PATH.'includes/queries.php';
require_once EAEL_CALDERA_FORMS_PATH.'admin/settings.php';


// Upsell
include_once dirname( __FILE__ ) . '/includes/eael-caldera-forms-upsell.php';
new Eael_Caldera_Forms_Upsell('');
/**
 * Load Elementor Caldera Forms
 */
function add_eael_caldera_forms() {

  if ( class_exists( 'Caldera_Forms' ) ) {
    require_once EAEL_CALDERA_FORMS_PATH.'includes/caldera-forms.php';
  }

}
add_action('elementor/widgets/widgets_registered','add_eael_caldera_forms');

/**
 * Load Eael Caldera Forms CSS
 */
function eael_caldera_forms_enqueue() {

   wp_enqueue_style('essential_addons_elementor-cf7-css',EAEL_CALDERA_FORMS_URL.'assets/css/elementor-caldera-forms.css');

}
add_action( 'wp_enqueue_scripts', 'eael_caldera_forms_enqueue' );

/**
 * Admin Notices
 */
function eael_caldera_forms_admin_notice() {
	if( !class_exists( 'Caldera_Forms' ) ) :
	?>
		<div class="error notice is-dismissible">
			<p><strong>Elementor Caldera Forms Styler</strong> needs <strong>Caldera Forms</strong> plugin to be installed. Please install the plugin now! <button id="eael-install-caldera" class="button button-primary">Install Now!</button></p>
		</div>
	<?php
	endif;
}
add_action( 'admin_notices', 'eael_caldera_forms_admin_notice' );
