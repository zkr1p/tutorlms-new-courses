<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\traits\ExceptionHandler;

class Errors
{	
	function __construct()
	{
		set_exception_handler(function(\Throwable $exception) {
			echo "ERROR: " , $exception->getMessage(), "\n";
			exit;
		});
	}
}