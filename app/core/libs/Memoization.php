<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
    Antes de ...$args podria incluirse un $exp_time en segundos

    De momento utiliza un solo "driver" y no hay persistencia
    entre distintos requests.
*/
class Memoization
{
    protected static $cache = [];

    static function memoize($key, $callback_or_value = null, ...$args)
    {
        if ($callback_or_value != null && is_callable($callback_or_value)){
            $value = call_user_func_array($callback_or_value, $args);
        } else {
            $value = $callback_or_value;
        }
        
        // Si se proporciona $value, asigna ese valor al caché y retorna el valor
        if ($value !== null) {
            static::$cache[$key] = $value;
            return $value;
        }

        // Si no se proporciona $value y la clave existe en el caché, retorna el valor almacenado
        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        // Si no se proporciona $value y la clave no existe en el caché, retorna null
        return null;
    }

}

