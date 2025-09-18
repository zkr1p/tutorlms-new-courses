<?php

namespace boctulus\TutorNewCourses\core\libs;

class CronJob extends BackgroundService
{
    static protected $month;
    static protected $monthday;
	static protected $weekday;
	static protected $hour;
	static protected $minute;
    static protected $second;

    const SUN = 0;
    const MON = 1;
    const TUE = 2;
    const WED = 3;
    const THU = 4;
    const FRI = 5;
    const SAT = 6;

    function __construct() { }

    static function getFrequency(){
        return [
            'month' => static::$month,
            'monthday' => static::$monthday,  
            'weekday' => static::$weekday,
            'hour' => static::$hour,
            'minute' => static::$minute,
            'second' => static::$second
        ];
    }



}

