<?php

use boctulus\TutorNewCourses\core\libs\Config;

function set_upload_limits($upload_max_filesize = '1024M', $post_max_size = '1024M', $memory_limit = '768M', $max_exec_time = '600'){
    $config = Config::get();

    @ini_set("upload_max_filesize",   $upload_max_filesize ?? $config["upload_max_filesize"] ?? "1024M");
    @ini_set("post_max_size",  $post_max_size ?? $config["post_max_size"] ?? $upload_max_filesize);
    @ini_set("memory_limit", $memory_limit ?? $config["memory_limit"] ?? "768M");
    @ini_set("max_execution_time", $max_exec_time ?? $config["max_execution_time"] ?? "600");
}

function get_upload_limits(){
    return [
        "upload_max_filesize"   => ini_get("upload_max_filesize"),
        "post_max_size"         => ini_get("post_max_size"),
        "memory_limit"          => ini_get("memory_limit"),
        "max_execution_time"    => ini_get("max_execution_time"),
    ];
}