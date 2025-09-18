<?php

use boctulus\TutorNewCourses\core\libs\Strings;

function plugin_url(){
    return base_url() . '/wp-content/plugins/' . plugin_name();
}

function plugin_assets_url($file = null){
    $url = base_url() . '/wp-content/plugins/' . plugin_name() . '/assets';

    return $url;
}

/*
    Ej:

    wp_enqueue_script( 'main-js', asset_url('/js/main.js') , array( 'jquery' ), '1.0', true );
*/
function asset_url($file){
    $url = base_url() . '/wp-content/plugins/' . plugin_name() . '/assets';

    if (!empty($file)){
        $file = Strings::removeFirstSlash($file);
        $url .= '/' . $file;
    }

    return $url;
}
