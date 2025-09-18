<?php

/*
    Routes for Router

    Nota: la ruta mas general debe colocarse al final
*/

return [
    // rutas 

    'GET:/tutor/courses' => 'boctulus\TutorNewCourses\controllers\TutorController@courses',
    '/tutor/enrollment'  => 'boctulus\TutorNewCourses\controllers\TutorController@enrollment',

    '/tutor/cronjob'     => 'boctulus\TutorNewCourses\controllers\CronjobController'
];
