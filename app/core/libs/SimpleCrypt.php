<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

/*
    Mejor que nada..... 

    Ventaja: no utiliza extensiones de PHP que pueden no estar instaladas
*/

class SimpleCrypt 
{
    protected static $settings = [
        'keep_length' => false
    ];

    /*
        Encriptacion simetrica

        crypt(crypt($str)) == $str
    */
    static function crypt($str) {
        $length = strlen($str);
        $encrypted = '';
        
        // Realizar translocaciones reversibles de subcadenas
        for ($i = 0; $i < $length; $i += 2) {
            $substring = substr($str, $i, 2);
            $encrypted .= strrev($substring); // Revertir subcadena
        }
      
        return $encrypted;
    }

    static function encrypt($str) {
        $encrypted = static::crypt($str);
      
        if (!static::$settings['keep_length']){
            $encrypted = Strings::randomHexaString(7) . $encrypted . Strings::randomHexaString(13);
        }

        return $encrypted;
    }

    static function decrypt(string $str) {
        if (!static::$settings['keep_length']){
            $str = substr($str, 7, strlen($str) - 20);
        }

        $decrypted = static::crypt($str);
        
        return $decrypted;
    }
}

