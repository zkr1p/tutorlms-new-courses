<?php

namespace boctulus\TutorNewCourses\core\interfaces;

use boctulus\TutorNewCourses\controllers\Controller;

interface ITransformer {
    function transform(object $user, Controller $controller = NULL);
}