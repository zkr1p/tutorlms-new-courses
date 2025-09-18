<?php

namespace boctulus\TutorNewCourses\middlewares;

use boctulus\TutorNewCourses\core\Middleware;
use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Strings;

class __NAME__ extends Middleware
{   
    function __construct()
    {
        parent::__construct();
    }

    function handle(?callable $next = null){
        $res = $this->res->get();

        // ...

        $this->res->set($res);
    }
}