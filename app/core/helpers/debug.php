<?php declare(strict_types = 1);

use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\libs\Debug;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\StdOut;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\VarDump;

function show_debug_trace(bool $status = true){
    VarDump::showTrace($status);
}

function hide_debug_trace(){
    VarDump::hideTrace();
}

function show_debug_response(bool $status = true){
    VarDump::showResponse($status);
}

function hide_debug_response(){
    VarDump::hideResponse();
}

function _dd($val = null, $msg = null, bool $additional_carriage_return = true){
    // foo();
	return VarDump::dd($val, $msg, $additional_carriage_return);
}

if (!function_exists('dd')){
	function dd($val = null, $msg = null, bool $additional_carriage_return = true){
		return _dd($val, $msg, $additional_carriage_return);
	}
}

if (!function_exists('foo')){
	function foo(){
		throw new \Exception("FOO");
	}
}

if (!function_exists('here')){
	function here(){
		_dd('HERE !');
	}
}

if (!function_exists('debug')){
	function debug($val, $msg = null) 
	{
		dd($val, $msg);
		
		if (!empty($msg)){
			Logger::log($msg. ': '. var_export($val, true));
		} else {
			Logger::log(var_export($val, true));
		}	
	}
}

function console_log($val, $msg = null, bool $only_admin = false){
	if ($only_admin && !is_admin()){
		return;
	}

	if (!is_cli()){
		?>
		<script>
			console.log('<?= $msg ?>', '<?= $val ?>');		
		</script>
		<?php
	}
}

function get_log(string $file, bool $reverse = true){
	if (!file_exists(Constants::LOGS_PATH . $file)){
		return '--x--';
	}

	$content = file_get_contents(Constants::LOGS_PATH . $file);

	if (empty($content)){
		return '--x--';
	}

	if ($reverse){
		$lines   = Strings::lines($content);
		$lines   = array_reverse($lines);
	
		$content = implode(PHP_EOL, $lines);
	}

	return nl2br($content);
}