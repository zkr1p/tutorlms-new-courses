<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

namespace boctulus\TutorNewCourses\core\libs;

class Debug
{
	static function is_postman(){
		return (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'PostmanRuntime') !== false);	
	}

	protected static function pre(callable $fn, ...$args){
		echo '<pre>';
		$fn($args);
		echo '</pre>';
	}

	protected static function export($v, $msg = null) 
	{			
		$postman = self::is_postman();
		
		$cli  = (php_sapi_name() == 'cli');
		$br   = ($cli || $postman) ? PHP_EOL : '<br/>';
		$p    = ($cli || $postman) ? PHP_EOL . PHP_EOL : '<p/>';

		$type = gettype($v);
		
		$fn = function($x) use ($type){
			if ($type == 'boolean'){
				echo $x;
			} else {
				echo var_export($x);
			}	
		};

		
		if ($type == 'boolean'){
			$v = $v ? 'true' : 'false';
		}	

		if (!empty($msg)){
			echo "--[ $msg ]-- ". $br;
		}
			
		$fn($v);	
		
		if ($type != "array"){
			echo $p;
		}		

		if ($cli || $postman){
			echo $p;
		}
	}	

	static public function dd($val, $msg = null, callable $precondition_fn = null){
		if ($precondition_fn != NULL){
            if (!call_user_func($precondition_fn, $val)){
				return;
			}
		}

		$cli = (php_sapi_name() == 'cli');
		
		$pre = !$cli;
		
		if (self::is_postman()){
			$pre = false;
		}

		if ($pre) {
			self::pre(function() use ($val, $msg){ 
				self::export($val, $msg); 
			});
		} else {
			self::export($val, $msg);
		}
	}

	static public function d($val, $msg = null, callable $precondition_fn = null){
		return static::dd($val, $msg, $precondition_fn);
	}
}




