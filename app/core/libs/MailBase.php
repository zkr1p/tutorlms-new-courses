<?php 

/*
	@author boctulus
*/

namespace boctulus\TutorNewCourses\core\libs;

abstract class MailBase
{
    protected static $errors      = null; 
    protected static $status      = null; 
    protected static $silent      = false;
    protected static $debug_level = null;

    static function errors(){
        return static::$errors;
    }

    static function status(){
        return (empty(static::$errors)) ? 'OK' : 'error';
    }

    static function silentDebug($level = null){    
        static::$silent = !empty($level);
    }
}

