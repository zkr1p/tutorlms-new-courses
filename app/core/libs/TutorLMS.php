<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
    /c/249764e5-9720-4f8d-8fa4-d88f56ef149c
*/

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Posts;

/**
 * VERSIÓN FINAL Y COMPLETA
 * Librería de ayuda para interactuar con la base de datos de Tutor LMS.
 * Incluye las funciones que faltaban para la compatibilidad con el resto del plugin.
 */
class TutorLMS 
{
    const EVERY_COURSE = -1;

    /**
     * Obtiene una lista de todos los cursos de Tutor LMS.
     *
     * @param string $post_status Estado de los cursos a obtener (ej. 'publish').
     * @param array $fields Campos a seleccionar de la base de datos.
     * @return array Lista de cursos.
     */
    static function getCourses(string $post_status = 'publish', $fields = []){
        if (empty($fields)){
            $fields = ['ID', 'post_title','post_date'];
        }

        return table('posts')
        ->where([
            'post_type'   => 'courses'
        ])
        ->when(!empty($post_status), function($o) use ($post_status) {
            $o->where([
                'post_status' => $post_status
            ]);
        })
        ->when(!empty($fields), function($o) use ($fields){
            $o->select($fields);
        }) 
        ->get();
    }

    /**
     * FUNCIÓN AÑADIDA
     * Obtiene solo los IDs de todos los cursos de forma eficiente.
     *
     * @param string $post_status
     * @return array
     */
    static function getCourseIds(string $post_status = 'publish'): array {
        $courses = static::getCourses($post_status, ['ID']);
        return wp_list_pluck($courses, 'ID');
    }

    /**
     * Comprueba si un curso existe.
     *
     * @param int $course_id
     * @return bool
     */
    static function courseExists($course_id){
        return (get_post_type($course_id) === 'courses');
    }

    /**
     * Obtiene las inscripciones de un usuario a un curso.
     *
     * @param int|null $user_id
     * @param int|null $course_id
     * @return array
     */
    static function getEnrollments($user_id = null, $course_id = null){
        $data = [
            'post_type'   => 'tutor_enrolled',
            'post_status' => 'completed' // Solo inscripciones completadas y activas
        ];

        if (!empty($user_id)){
            $data['post_author'] = $user_id;
        }

        if (!empty($course_id)){
            $data['post_parent'] = $course_id;
        }

        return table('posts')
        ->where($data)
        ->select([
            'ID', 'post_title', 'post_parent as course_id', 'post_author as student_id', 'post_date'
        ])
        ->get();
    }
    
    /**
     * FUNCIÓN AÑADIDA
     * Obtiene solo los IDs de los cursos en los que un usuario está inscrito.
     *
     * @param int $user_id
     * @return array
     */
    static function getEnrolledCourseIds(int $user_id): array {
        $enrollments = static::getEnrollments($user_id);
        return wp_list_pluck($enrollments, 'course_id');
    }


    /**
     * Comprueba si un usuario está inscrito en un curso específico.
     *
     * @param int $user_id
     * @param int $course_id
     * @return bool
     */
    static function isUserEnrolled($user_id, $course_id) {
        return table('posts')->where([
            'post_type'   => 'tutor_enrolled',
            'post_author' => $user_id,
            'post_parent' => $course_id,
            'post_status' => 'completed'
        ])->exists();
    }

    /**
     * Comprueba si una inscripción fue cancelada.
     *
     * @param int $user_id
     * @param int $course_id
     * @return bool
     */
    static function isCancelled($user_id, $course_id) {
        return table('posts')->where([
            'post_type'   => 'tutor_enrolled',
            'post_author' => $user_id,
            'post_parent' => $course_id,
            'post_status' => 'cancel'
        ])->exists();
    }

    /**
     * Inscribe a un usuario en un curso.
     */
    public static function enrollUser($user_id, $course_id, $product_id = null, $order_id = null) 
    {
        if (!get_user_by('id', $user_id)) {
            throw new \Exception("El usuario con ID = '$user_id' no existe. No se lo puede enrollar.");
        }

        if (!static::courseExists($course_id)) {
            throw new \Exception("El curso con ID = '$course_id' no existe. No se lo puede enrollar a user_id = '$user_id'");
        }

        if (self::isUserEnrolled($user_id, $course_id)) {
            Logger::log("User ID = '$user_id' ya habia sido enrollado para Course ID = '$course_id");
            return true;
        }
        
        $title = __('Course Enrolled', 'tutor') . " &ndash; " . date_i18n(get_option('date_format')) . ' @ ' . date_i18n(get_option('time_format'));

        $data = array(
            'post_type'    => 'tutor_enrolled',
            'post_title'   => $title,
            'post_author'  => $user_id,
            'post_parent'  => $course_id,
            'post_status'  => 'completed'
        );

        $enroll_data = apply_filters('tutor_enroll_data', $data);
    
        $isEnrolled = wp_insert_post($enroll_data);
        update_user_meta($user_id, '_is_tutor_student', time());

        if ($order_id !== null && $product_id !== null){
            update_post_meta( $isEnrolled, '_tutor_enrolled_by_order_id', $order_id );
            update_post_meta( $isEnrolled, '_tutor_enrolled_by_product_id', $product_id );
            update_post_meta( $order_id, '_is_tutor_order_for_course', time() );
            update_post_meta( $order_id, '_tutor_order_for_course_id_'.$course_id, $order_id );
        }
        
        return true;
    }

    /**
     * Cancela la inscripción de un usuario a un curso (o a todos).
     */
    public static function cancelEnrollment($user_id, $course_id, bool $permanent = false) 
    {
        $data = [
            'post_type'   => 'tutor_enrolled',
            'post_author' => $user_id,
            'post_status' => 'completed'
        ];

        if ($course_id != static::EVERY_COURSE){
            $data['post_parent'] = $course_id;
        }

        $pids = table('posts')
        ->where($data)
        ->pluck('ID');

        if (empty($pids)){
            return;
        }

        foreach ($pids as $pid){
            if ($permanent){
                Posts::deleteByID($pid, true);
            } else {
                table('posts')
                ->where(['ID' => $pid])
                ->update(['post_status' => 'cancel']);
            }
        }
    }
    
    public static function getQuiz($id){
        $p = Posts::getPost($id);

        if ($p['post_type'] != 'tutor_quiz'){
            throw new \InvalidArgumentException("Quiz not found");
        }

        return $p;
    }

    /**
     * FUNCIÓN AÑADIDA
     * Verifica si una orden de WooCommerce está asociada a un curso de Tutor LMS.
     *
     * @param int $order_id
     * @return bool
     */
    public static function is_tutor_order(int $order_id) : bool
    {
        // Tutor LMS guarda un metadato en la orden cuando se completa una compra de un curso.
        $is_tutor = get_post_meta($order_id, '_is_tutor_order_for_course', true);
        return !empty($is_tutor);
    }
}