<?php

use boctulus\TutorNewCourses\core\libs\CronJob;

class __NAME__ extends CronJob
{
	static protected $month;
    static protected $monthday;
	static protected $weekday;
	static protected $hour; 
	static protected $minute;
	static protected $second;
	static protected $is_active = true;

	/*
		Number of retries in 24 Hs.
	*/
	static protected $retries = 3;
    static protected $retry_timeframe = 3600 * 24;


	function run(){
		// your logic here
	}
	
    /*
        @paran $error Exception object
        @param $times int number of fails
    */
    function onFail(\Exception $error, int $times){
    }

    function onSuccess(){

    }

}
