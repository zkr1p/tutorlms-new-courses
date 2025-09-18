<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
    @author Pablo Bozzolo < boctulus@gmail.com >

    Version 1.5
*/
class Autoloader
{   
    function __construct()
    {   
        spl_autoload_register([$this, 'wp_namespace_autoload']);
    }
 
    function wp_namespace_autoload( $class ) {
        $config    = include __DIR__ . '/../../../config/config.php';
        $namespace = $config['namespace'];

        if (strpos($class, $namespace) !== 0) {
            return;
        }
    
        $class = str_replace($namespace, '', $class);
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

        $app_path  = __DIR__ . '/../../../app/';  // fixed

        $directory = realpath($app_path);
        $path      = $directory . $class;

        if ( file_exists( $path ) ) {
            include_once( $path );
        } else {
            // throw new \Exception("The file attempting to be loaded at '$path' does not exist." );
        }
    }
}