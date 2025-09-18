<?php

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Config;

/*
    @author Pablo Bozzolo < boctulus@gmail.com >

    Version: -- 
*/

// Mostrar errores


if ((php_sapi_name() === 'cli') || (isset($_GET['show_errors']) && $_GET['show_errors'] == 1)){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

require_once __DIR__   . '/app/core/Constants.php';

require_once __DIR__   . '/app/core/libs/Env.php';

require_once __DIR__   . '/app/core/helpers/debug.php';
require_once __DIR__   . '/app/core/helpers/autoloader.php';


if ((php_sapi_name() === 'cli')){
    /*
        Parse command line arguments into the $_GET variable <sep16@psu.edu>
    */

    if (isset($argv)){
        parse_str(implode('&', array_slice($argv, 1)), $_GET);
    }
}

// /* Helpers */

$includes = [
    __DIR__ . '/app/core/helpers', 
    __DIR__ . '/app/helpers',
    __DIR__ . '/boot'
];

$excluded    = [
    'cli.php'
];

foreach ($includes as $dir){
    if (!file_exists($dir) || !is_dir($dir)){
        Files::mkdir($dir);
    }

    foreach (new \DirectoryIterator($dir) as $fileInfo) {
        if($fileInfo->isDot()) continue;
        
        $path     = $fileInfo->getPathName();
        $filename = $fileInfo->getFilename();

        // No incluyo archivos que comiencen con "_"
        if (substr($filename, 0, 1) == '_'){
            
            continue;
        }

        if (in_array($filename, $excluded)){
            continue;
        }

        if(pathinfo($path, PATHINFO_EXTENSION) == 'php'){
            require_once $path;
        }
    }    
}

DB::setPrimaryKeyName('ID');
    
require_once __DIR__ . '/app/core/scripts/admin.php';

/*
	Habilitar uploads
*/

$config = Config::get();

ini_set("memory_limit", $config["memory_limit"] ?? "728M");
ini_set("max_execution_time", $config["max_execution_time"] ?? 1800);
ini_set("upload_max_filesize",  $config["upload_max_filesize"] ?? "50M");
ini_set("post_max_size",  $config["post_max_size"] ?? "50M");


if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY){
	error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
}

credits_to_author();

