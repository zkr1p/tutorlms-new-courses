<?php

use boctulus\TutorNewCourses\core\Constants;


return [
    /*
        Si es true, se habilita una tabla que reemplaza a la de /my-account/downloads/
        Esta tabla HTML da acceso a todos los productos descargables cuando el producto
        es downloable, de susbcripcion (checkbox marcado) 
        y el usuario tiene subscripcion activa

        Valores admitidos: 0/false/1/true
    */
    'custom_table_for_downloads'  => 1,

    /*
        Si hay productos marcados como para subscripcion   --ok
        y el cliente tiene una subscripcion, ...

        Y si este flag esta encendido, se muestran no solo los productos
        adquiridos (ya sea a precio 0.00 o no) sino tambien *todos* los 
        productos "marcados como para subscripcion"

        Entonces da un acceso mayor que si estuviera desactivado.

        Valores admitidos: 0/false/1/true
    */
    'custom_table_shows_links_for_every_product_for_subscription' => 0,

    /*
        Si los productos estan marcados como para subscripcion 
        y el cliente tiene una subscripcion, ...
    
        El precio del producto para el cliente con este flag en true quedaria en 0.00

        Valores admitidos: 0/false/1/true
    */
    'apply_discount_for_subscribers' => true,

    // Se puede dejar en true para que no haya reclamos de que se esta cobrando por algo gratis
    'apply_discount_for_subscribers_in_cart' => true,

    // Implementar !!! <<----------------------------------------- *
    'max_discounted_products_using_subscription' => [
        [
            'interval' => 'month',
            'value'    => 5            // el valor del requerimiento es: 5
        ],
        [
            'interval' => 'year',   // <-- AÑADIDO: La regla que faltaba para anuales
            'value'    => 99999     // Cupo "ilimitado" para suscriptores anuales
        ]
    ],

    'replace_add-to-cart_with_download' => [
        'value'    => true,
        'tpl_script_file' => Constants::ROOT_PATH . env('TPL_SCRIPT_FILE')
    ],

    'is_enabled' => env('ENABLED', true),
    
	"field_separator" => ";",

	"memory_limit" => "728M",
	"max_execution_time" => 1800,
	"upload_max_filesize" => "50M",
	"post_max_size" => "50M",

    //
    // No editar desde aqui
    //

    'app_name'          => env('APP_NAME'),
    'namespace'         => "boctulus\TutorNewCourses", 
    'use_composer'      => false, // 

    /*
        Intercepta errores
    */
    
    'error_handling'    => true,

    /*
        Puede mostrar detalles como consultas SQL fallidas 

        Ver 'log_sql'
    */

    'debug'             => env('DEBUG'),

    'log_file'          => 'log.txt',
    
    /*
        Loguea cada consulta / statement -al menos las ejecutadas usando Model-

        Solo aplica si 'debug' esta en true
    
    */

    'log_sql'           => true,
    
    /*
        Genera logs por cada error / excepcion
    */

    'log_errors'	    => true,

    /*
        Si se quiere incluir todo el trace del error -suele ser bastante largo-

        Solo aplica con 'log_errors' en true
    */

    'log_stack_trace'  => true,

    'front_controller' => true,
    'router'           => true,
];

