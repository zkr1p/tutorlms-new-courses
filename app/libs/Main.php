<?php

namespace boctulus\TutorNewCourses\libs;

use boctulus\TutorNewCourses\core\Router;
use boctulus\TutorNewCourses\core\FrontController;
use boctulus\TutorNewCourses\core\libs\Logger;

/*
    @author Pablo Bozzolo < boctulus@gmail.com >
*/

class Main
{ 
    function __construct()
    { 
        add_action('init', [$this, 'init']);
        add_action('woocommerce_init', [$this, 'WooInit']);
        // add_action('woocommerce_product_query', [$this, 'hide_products_by_criteria']); 
    }

    function init()
    {   
        Router::resolve();  
        FrontController::resolve();
    }

    function wooInit(){
       // ..
       
    }

    
}