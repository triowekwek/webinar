<?php
namespace Elementor;

function eael_caldera_forms_init(){
    Plugin::instance()->elements_manager->add_category(
        'elementor-caldera-forms',
        [
            'title'  => 'Elementor Caldera Forms',
            'icon' => 'font'
        ],
        1
    );
}
add_action( 'elementor/init','Elementor\eael_caldera_forms_init' );



