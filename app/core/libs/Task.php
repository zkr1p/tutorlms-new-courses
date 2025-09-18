<?php

namespace boctulus\TutorNewCourses\core\libs;

class Task
{   
    static protected $priority = 10;
    static protected $exec_time_limit   ;
    static protected $memory_limit;
    static protected $dontOverlap = false;
    static protected $is_active = true;

    function __construct() { }

    static function canOverlap() : bool {
        return static::$dontOverlap;
    }

    static function isActive() : bool {
        return static::$is_active;
    }

}

