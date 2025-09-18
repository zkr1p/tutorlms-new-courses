<?php

namespace boctulus\TutorNewCourses\core\controllers;

class ConsoleController extends Controller
{
    function __construct()
    {
        if (!is_cli()){
            throw new \Exception("Only cli is allowed");
        }

        parent::__construct();        
    }
}

