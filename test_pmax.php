<?php

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Orders;
use boctulus\TutorNewCourses\core\libs\Metabox;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\exceptions\SqlException;
use boctulus\TutorNewCourses\libs\WCSubscriptionsExtended;
use boctulus\TutorNewCourses\core\libs\CustomDownloadPermissions;



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

// dd(
// 	Products::getMeta(91, '_download_limit')
// );

$user_id = 11;

dd(
	Users::getUsernameByID($user_id)
);

$sub = new WCSubscriptionsExtended();
$cnt = $sub->downloableBySubscriptionPurchasableQuantity($user_id);

dd($cnt, "Cantidad (max) de productos que pueden adquirise por subs para user_id=$user_id");	

dd('DONE.');