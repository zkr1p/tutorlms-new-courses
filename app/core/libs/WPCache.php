<?php

namespace boctulus\TutorNewCourses\core\libs;

class WPCache
{       
    // vacia la cache
    static function clear(){
        wp_cache_flush();
    } 

    // acceso a mas funciones del core
}