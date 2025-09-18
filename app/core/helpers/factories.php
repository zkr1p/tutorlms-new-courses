<?php


use boctulus\TutorNewCourses\core\libs\Factory;
use boctulus\TutorNewCourses\core\libs\Request;
use boctulus\TutorNewCourses\libs\HtmlBuilder\Tag;

function tag(string $name) : Tag {
    return new Tag($name);
}

function request() : Request {
    return Factory::request();
}

function response($data = null, ?int $http_code = 200){
    return Factory::response($data, $http_code);
}

function error($error = null, ?int $http_code = null, $detail = null){
    if (is_cli()){
        if (!empty($detail)){
            dd($detail, $error);
        } else {
            dd($error);
        }
       
        exit;
    }
    return Factory::response()->error($error, $http_code, $detail);
}
