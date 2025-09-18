<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\Posts;
use boctulus\TutorNewCourses\core\libs\System;
use boctulus\TutorNewCourses\libs\Sync;
use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Files;

use boctulus\TutorNewCourses\core\libs\Products;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class OrdersController
{
    function __construct()
    {   
        // Users::restrictAccess();
    }

    function get_list($after_id = null)
    {
        $query = new \WC_Order_Query( array(
            // 'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
        ) );

        $oids = $query->get_orders();

        if ($after_id !== null){
            foreach ($oids as $ix => $oid){
                if ($oid <= $after_id){
                    unset($oids[$ix]);
                }                
            }
        }

        response()->send($oids);        
    }

    function export(){
       $orders = Posts::getPosts('ID,post_date', 'shop_order_placehold');
       Logger::varExport($orders, ETC_PATH . 'orders.php');
    }

    function analyze(){
        /*
            Array
            (
                [0] => Array
                    (
                        [ID] => 5665
                        [post_date] => 2023-10-25 16:34:10
                    ),...
            )
        */
        $events = include ETC_PATH . 'orders.php';

        // Initialize an array to count orders by hourly range
        $hourly_ranges = array_fill(0, 24, 0);

        // Count orders in each hourly range
        foreach ($events as $event) {
            $hour = date('G', strtotime($event['post_date']));
            $hourly_ranges[$hour]++;
        }

        /*
            El mejor horario para correr cronjob es 2 AM a 5 AM

            [0] => 15
            [1] => 12
            [2] => 5
            [3] => 8
            [4] => 10
            [5] => 17
            [6] => 27
                ..
        */
        dd($hourly_ranges);
    }
   
}
