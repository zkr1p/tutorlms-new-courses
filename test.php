<?php

use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Metabox;

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

$pid = 15;
$p = Products::getProductById($pid);

dd(Products::getTitle($pid));

dd(
	Products::getTagsByPostID($pid), 'TAGS'
);

dd(
	Products::getMeta($pid, 'for_subscription'), 'METADATO'
);