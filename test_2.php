<?php

use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Metabox;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Strings;
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


// // Get 10 Random users
// for ($i=0; $i<10; $i++){
// 	$username = Strings::randomString(15, false);
// 	Users::create($username, null, 'gog#og$O2k!', 'customer');
// }


/*
	Devuelve algo como:
	
	Array
	(
		[0] => Array
			(
				[ID] => 15
				[title] => Archivo 1B
				[files] => Array
					(
						[0] => http://taxes4pros.lan/wp-content/uploads/woocommerce_uploads/2024/11/400_parole_composte-bk62hr.txt
					)

				[downloads_remaining] => -1
				[access_expires] =>
			),
	...
	)
*/
$prods = Products::getDownloadableProductsWithDetails();
dd($prods);