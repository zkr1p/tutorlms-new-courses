<?php

use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Metabox;
use boctulus\TutorNewCourses\core\libs\Orders;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\Users;
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

// Orders::createRandom(1, [22]);
// Orders::setLastOrderStatus(Orders::STATUS_COMPLETED);
// Orders::setLastOrderStatus(Orders::STATUS_ON_HOLD);

$uid    = 1;
$orders = Orders::getOrdersByUserId($uid, ['completed']);



$downloads = [];
foreach ($orders as $order){
	$items   = $order->get_downloadable_items();
	
	foreach ($items as $item){
		$downloads[] = $item;
	}
}

foreach ($downloads as $download){
	if ($download['product_id'] == 21){
		$download_url = $download['download_url'];
		break;
	}
}

dd($download_url, '$download_url');


// $order_id = 89;

// /*
// 	Si el total es 0 y total_discount tambien 
// 	entonces se adquirio por "subscripcion" seguramente
// */

// $order   = Orders::getOrderById($order_id);
// $uid     = $order->get_user_id();
// // $total   = $order->get_total();          
// // $desc    = $order->get_total_discount(); 
// $items   = $order->get_downloadable_items();
// $status  = $order->get_status();

// dd($status, 'Order status');
// dd($total, 'Total');
// dd($desc, 'Descuentos');
// dd($items, 'Downloable Items');

DB::statement("
    INSERT INTO `{$prefix}downloadable_product_permissions_new` 
    (`download_id`, `product_id`, `user_id`, `download_count`, `access_granted`, `created_at`, `updated_at`)
    VALUES 
    ('some_download_id', 123, 456, 0, NOW(), NOW(), NOW())
");
