<?php

use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Logger;

/*
    Requiere que este habilitado el modo debug
*/
function logger($data, ?string $path = null, $append = true){
    if (!Config::get('debug')){
        return;
    }

    return Logger::log($data, $path, $append);
}

/*
    Requiere que este habilitado el modo debug
*/
function dump($object, ?string $path = null, $append = false){
    if (!Config::get('debug')){
        return;
    }

    return Files::dump($object, $path, $append);
}

/*
    Requiere que este habilitado el modo debug
*/
function log_error($error){
    if (!Config::get('debug')){
        return;
    }

    return Logger::logError($error);
}

/*
    Requiere que este habilitado el modo debug y log_sql
*/
function log_sql(string $sql_str){
    $cfg = Config::get();

    if (!$cfg['debug'] || !$cfg['log_sql']){
        return;
    }

    return Logger::logSQL($sql_str);
}