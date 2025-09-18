<?php

use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\Router;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\FrontController;

/*
	Plugin Name: Tutor LMS New Courses
	Description: Tutor LMS Courses & Subscriptions Automation
	Version: 1.6.59
	Author: Pablo Bozzolo
	Domain Path:  /languages
	Text Domain: tutorlms-new-courses
*/


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/app.php';

if (!in_array(Config::get('is_enabled'), [true, '1', 'on'])){
	return;
}

register_activation_hook( __FILE__, function(){
	$log_dir = __DIR__ . '/logs';
	
	if (is_dir($log_dir)){
		Files::globDelete($log_dir);
	} else {
		Files::mkdir($log_dir);
	}

	include_once __DIR__ . '/on_activation.php';
});

if (!defined('DB_NAME')){
	Logger::log('Warning:  Use of undefined constant DB_NAME');
}

db_errors(false);

// Mostrar errores
if ((php_sapi_name() === 'cli') || (isset($_GET['show_errors']) && $_GET['show_errors'] == 1)){
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
} else {
	if (Config::get('debug') == false){
		error_reporting(E_ALL & ~E_WARNING);
		error_reporting(0);
	}	
}

require_once __DIR__ . '/main.php';





