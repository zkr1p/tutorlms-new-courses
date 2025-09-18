<?php

use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Orders;
use boctulus\TutorNewCourses\core\libs\Metabox;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\core\libs\WCSubscriptions;

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

$uid    = 6;   

$subs = new WCSubscriptions();

dd($subs->getRenovationFrequency($uid));
dd($subs->hasActive($uid));


// dd(
// 	(new WCSubscriptions())->get(2)
// );

// dd(
// 	Products::getMeta(104, '_subscription_period')
// );

// $pid = 15;
// $p = Products::getProductById($pid);

// dd(Products::getTitle($pid));

// dd(
// 	Products::getTagsByPostID($pid), 'TAGS'
// );

// dd(
// 	Products::getMeta($pid, 'for_subscription'), 'METADATO'
// );