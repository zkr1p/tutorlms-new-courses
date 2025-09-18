<?php

use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Orders;
use boctulus\TutorNewCourses\core\libs\Metabox;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\libs\WCSubscriptionsExtended;


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (php_sapi_name() != "cli"){
	return; 
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR);

	require_once ABSPATH . '/wp-config.php';
	require_once ABSPATH .'/wp-load.php';
}

require_once __DIR__ . '/app.php';

////////////////////////////////////////////////


$uid = 2;

/*
	Devuelve un array como:
	
	Array
	(
		[0] => Array
			(
				[ID] => 21
				[type] => simple
				[is_virtual] => 1
				[is_downloadable] => 1
			)
		, ...
	)
*/
$purchased = Products::getPurchasedProducts($uid);

dd(
	$purchased 
);