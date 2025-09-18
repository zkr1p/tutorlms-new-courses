<?php

namespace boctulus\TutorNewCourses\core\interfaces;

interface IUpdateBatch {

    /**
     * Run migration
     *
     * @return void
     */
    function run() : ?bool;
}