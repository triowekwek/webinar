<?php
/**
 * Get Caldera Form List
 * @return array
 */
function eael_select_caldera_forms_stand_alone() {
    global $wpdb;
    $eael_cf_table_name = $wpdb->prefix.'cf_forms';
    $forms = $wpdb->get_results( "SELECT * FROM $eael_cf_table_name" );
    foreach( $forms as $form ) {
        $unserialize = unserialize( $form->config );
        $form_title = $unserialize['name'];
        $options[$form->form_id] = $form_title;
    }
    return $options;
}

