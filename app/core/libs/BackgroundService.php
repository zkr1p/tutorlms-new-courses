<?php

namespace boctulus\TutorNewCourses\core\libs;

abstract class BackgroundService
{
    static protected $is_active = true;

    /*
		Number of retries in 24 Hs.
	*/
	static protected $retries;
    static protected $retry_timeframe = 3600 * 24;

    protected $fails = [];

    function __construct() {  
    }

    static function isActive() : bool {
        return static::$is_active;
    }

    function start()
    {
        try {
            $this->run();
            $this->onSuccess();
        } catch (\Exception $e){
            if (static::$retries !== null){
                /*
                    Number of retries are in some timeframe 
                    =>
                    oldest retries which are not in the timeframe are retired 
                */
                foreach ($this->fails as $ix => $t){
                    if ($t + static::$retry_timeframe < time() ){
                        unset($this->fails[$ix]);
                    }
                }

                if (static::$retries === count($this->fails ?? [])){
                    exit;
                }
            }

            $this->fails[] = time();
            $this->onFail($e, count($this->fails));
        }
    }

	function run(){
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

