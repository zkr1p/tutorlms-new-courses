<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\TutorLMS;
use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation;

/*
    Este controlador es para demostrar capacidades de una libreria

    Se limita su uso a usuarios con rol de Adminr por seguridad
*/
class TutorController
{
    function __construct()
    {   
        // Users::restrictAccess();
    }

    function courses(){
        response()->send([
            'courses' => TutorLMS::getCourses()
        ]);
    }

    // Puede evitarse usando el router y apuntando a cada uno de los metodos
    function enrollment()
    {
        switch ($_SERVER['REQUEST_METHOD']){
            case 'GET':
                $this->get_enrollment();
            break;

            case 'POST':
                $this->new_enrollment();
            break;

            case 'DELETE':
                $this->cancel_enrollment();
            break;

            default:
                return 'Not implemented';
        }
    }

    // Get Enrollment status  --ok verificado 20/dic/204
    function get_enrollment()
    { 
        try {
            $student_id = $_GET['student_id'] ?? $_GET['user_id'] ?? null;
            $course_id  = $_GET['course_id']  ?? null;
            
            $posts = TutorLMS::getEnrollments($student_id, $course_id);

            response()->send([
                'enrollments' => $posts
            ]);
        } catch (\Exception $ex){
            response()->error($ex->getMessage(), $ex->getCode());
            Logger::logError($ex->getMessage());
        }  
    }

    // New Enroll --ok verificado 20/dic/204
    function new_enrollment()
    {
        try {
            $student_id = request()->getBodyParam('student_id') ?? $_GET['student_id'] ?? $_GET['user_id'] ?? null;
            $course_id  = request()->getBodyParam('course_id') ?? $_GET['course_id']  ?? null;

            if (empty($course_id)){
                response()->error([
                    "'course_id' is required"
                ]);
            }

            if (empty($student_id)){
                response()->error([
                    "'student_id' is required"
                ]);
            }

            TutorLMS::enrollUser($student_id, $course_id);

            response()->send([
                'message' => 'User enrolled'
            ]);
        } catch (\Exception $ex){
            response()->error($ex->getMessage(), $ex->getCode());
            Logger::logError($ex->getMessage());
        }            
    }

    // Delete Enrollment
    function cancel_enrollment()
    {
        $student_id = $_GET['student_id'] ?? $_GET['user_id'] ?? null;
        $course_id  = $_GET['course_id']  ?? null;

        if (empty($course_id)){
            error(
                "'course_id' is required"
            );
        }

        if (empty($student_id)){
            error(
                "'student_id' is required"
            );
        }

        TutorLMS::cancelEnrollment($student_id, $course_id);

        response()->send([
            'message' => 'Some enrollments were cancelled'
        ]);
    }

    // Action restaurado el 20/dic/2024
    function cronjob(){
        // --- INICIO DE LA MODIFICACIÓN OPTIMIZADA ---
        $batch_size = 50; 
        $transient_name = 'tutor_controller_user_offset'; 
    
        $offset = get_transient($transient_name);
        if (false === $offset) {
            $offset = 0;
        }
    
        $user_ids = get_users([
            'fields' => 'ID',
            'number' => $batch_size,
            'offset' => $offset
        ]);
    
        if (empty($user_ids)) {
            delete_transient($transient_name);
            dd("Proceso finalizado para todos los usuarios.");
            return;
        }
    
        foreach ($user_ids as $uid){
            dd("Automatizando por user_id=$uid (Lote iniciando en $offset)");
            if (class_exists('\boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation')) {
                \boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation::run($uid);
            }
        }
    
        $new_offset = $offset + count($user_ids);
        set_transient($transient_name, $new_offset, DAY_IN_SECONDS);
        dd("Lote de " . count($user_ids) . " usuarios procesado.");
        // --- FIN DE LA MODIFICACIÓN ---
    }
}

