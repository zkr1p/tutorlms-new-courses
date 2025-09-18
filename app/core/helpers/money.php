<?php

use boctulus\TutorNewCourses\core\libs\Config;

function currency_symbol(){
    $cfg = Config::get();

    if (isset($cfg['currency_symbol']) && $cfg['currency_symbol'] !== null){
        return $cfg['currency_symbol'];
    }

    if (function_exists('get_woocommerce_currency_symbol')){
        return get_woocommerce_currency_symbol();
    }

    return '$';
}
