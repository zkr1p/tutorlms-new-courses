<?php

use boctulus\TutorNewCourses\core\libs\Env;

require_once __DIR__ . '/../../core/libs/Env.php';

if (!function_exists('env')){
    function env(string $key, $default_value = null){
        return Env::get($key, $default_value);
    }
}
