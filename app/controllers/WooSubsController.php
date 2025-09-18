<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\WCSubscriptions;

/*
    Este controlador es para demostrar capacidades de una libreria

    Se limita su uso a usuarios con rol de Adminr por seguridad
*/
class WooSubsController
{
    function __construct()
    {   
        // Users::restrictAccess();
    }
    
    function list()
    {
        $user_id = $_GET['user_id'] ?? null;
        $status  = $_GET['status']  ?? null;
        
        $s = new WCSubscriptions();

        response()->send([
            'subs' => $s->get($user_id, $status)
        ]);                 
    }
}

