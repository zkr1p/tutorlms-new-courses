<?php

use boctulus\TutorNewCourses\core\libs\Config;

/*
    Seteo opciones la primera vez que se activa el plugin (al instalarse)
*/

if (!get_transient(Config::get('namespace') . '__init')){  
    update_option('lms_new_courses_enroll_subs', 1);
    update_option('lms_new_courses_enroll_specific_users', 1);

    set_transient(Config::get('namespace') . '__init', 1);
}




//require_once __DIR__ . '/db/01_link2product_metadata.php';
//require_once __DIR__ . '/db/05_posts_to_lik2prd.php';

// Creacion de otras tablas
// ...

