<?php

use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\interfaces\IUpdateBatch;
use boctulus\TutorNewCourses\controllers\MigrationsController;

/*
    Run batches
*/

class __NAME__ implements IUpdateBatch
{
    function run() : ?bool{
        // ...
        
        return true;
    }
}