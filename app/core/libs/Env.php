<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\Constants;


/*
    Customizada para WP
*/
class Env
{
    static $data;

    static function setup(){
        if (!file_exists(Constants::ROOT_PATH . '.env')){
            if (!file_exists(Constants::ROOT_PATH . 'env.example')){
                wp_die("Neither .env nor env.example found");
            }

            copy(Constants::ROOT_PATH . 'env.example', Constants::ROOT_PATH . '.env');
        }
        
        if (!empty($_ENV)){
            static::$data = $_ENV;  
        }

        // Doy prioridad a '.dev-env' sobre '.env'
        $env_file     = file_exists(Constants::ROOT_PATH . '.dev-env') && trim(file_get_contents(Constants::ROOT_PATH . '.dev-env')) !='' ? '.dev-env' : '.env';

        static::$data = parse_ini_file(Constants::ROOT_PATH . $env_file);
    }

    static function get(?string $key = null, $default_value = null){
        if (empty(static::$data)){
            static::setup();
        }

        if (empty($key)){
            return static::$data;
        } 

        return static::$data[$key] ?? $default_value;
    }
}

