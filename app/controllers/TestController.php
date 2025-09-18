<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\Posts;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\TutorLMS;
use boctulus\TutorNewCourses\core\libs\WCSubscriptions;

class TestController
{
    function __construct()
    {   
        // Restringe acceso a admin
        // Users::restrictAccess();
    }   

    function index(){
        // dd(Posts::getPostTypes(), 'Post Types');

        dd(
            Posts::getMetaValuesAndPostIds('students_allowed_to_enroll', 'courses')
        );
    }

    #21069
    function get_post_type(){
        $pid = $_GET['pid'] ?? 21069;

        dd(
            Posts::getPostType($pid), "Post Type para pid=$pid"
        );        
    }

    function test_tutorlms_and_subs_libs($user_id){
        if (empty($user_id)){
            die("user_id es requerido");
        }

        $s       = new WCSubscriptions();

        $courses = TutorLMS::getCourses();

        dd($courses, 'CURSOS');
        dd($s->hasActive($user_id), "TIENE user_id=$user_id SUB ACTIVA?");

        if ($s->hasActive($user_id)){
            $enrolled_inv_list = [];

            foreach ($courses as $course){
                $course_id = $course['ID'];

                if (!TutorLMS::isUserEnrolled($user_id, $course_id)){
                    $enrolled_inv_list[] = $course_id;
                }
            }

            dd($enrolled_inv_list, 'LISTA DE CURSOS A ENROLAR');

            foreach ($enrolled_inv_list as $course_id){
                dd("Deberia enrrolar para user_id=$user_id y course_id=$course_id ..."); // 
                // TutorLMS::enrollUser($user_id, $course_id);
            }
        }
    }
   
}
