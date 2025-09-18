<?php

namespace boctulus\TutorNewCourses\libs;

use boctulus\TutorNewCourses\core\libs\Posts;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\TutorLMS;
use boctulus\TutorNewCourses\core\libs\WCSubscriptions;

/**
 * VERSIÓN FINAL Y CORREGIDA
 * Gestiona la lógica de inscripción y cancelación de cursos de forma eficiente.
 * Utiliza los nombres de función correctos de la librería TutorLMS.php.
 */
class TutorLMSWooSubsAutomation
{
    /**
     * Comprueba el estado de la suscripción y llama a la acción correcta.
     */
    public static function run(int $user_id)
    {
        Logger::log("Ejecutando sincronización (run) para user_id=$user_id");
        $subs = new WCSubscriptions();

        if ($subs->hasActive($user_id)) {
            static::enroll_on_activation($user_id);
        } else {
            static::unenroll_on_deactivation($user_id);
        }
    }

    /**
     * Inscribe a un usuario en todos los cursos si aún no está inscrito.
     */
    public static function enroll_on_activation(int $user_id)
    {
        Logger::log("Ejecutando enroll_on_activation para user_id=$user_id");

        $courses = TutorLMS::getCourses();
        if (empty($courses)) return;

        $all_course_ids = array_column($courses, 'ID');
        
        // Obtiene los IDs de los cursos en los que el usuario ya está inscrito.
        $enrollments = TutorLMS::getEnrollments($user_id);
        $enrolled_course_ids = array_column($enrollments, 'course_id');

        $courses_to_enroll = array_diff($all_course_ids, $enrolled_course_ids);

        if (empty($courses_to_enroll)) return;

        foreach ($courses_to_enroll as $course_id) {
            Logger::log("Inscribiendo a user_id=$user_id en course_id=$course_id");
            TutorLMS::enrollUser($user_id, $course_id);
        }
    }

    /**
     * Cancela la inscripción de un usuario de los cursos a los que ya no debería tener acceso.
     */
    public static function unenroll_on_deactivation(int $user_id)
    {
        Logger::log("Ejecutando unenroll_on_deactivation para user_id=$user_id");

        // CORRECCIÓN: Llama a la función correcta getEnrollments() en lugar de getEnrolledCourses().
        $enrollments = TutorLMS::getEnrollments($user_id);
        if (empty($enrollments)) {
            return;
        }
        
        $user_email = get_userdata($user_id)->user_email;

        foreach ($enrollments as $enrollment) {
            $course_id = $enrollment['course_id'];

            if (static::user_bought_course_fast($user_id, $course_id) || static::hasVipAccess($course_id, $user_email)) {
                continue; 
            }

            Logger::log("Cancelando inscripción de user_id=$user_id del course_id=$course_id");
            TutorLMS::cancelEnrollment($user_id, $course_id);
        }
    }
    
    /**
     * VERSIÓN OPTIMIZADA que utiliza la función nativa de WooCommerce.
     */
    public static function user_bought_course_fast(int $user_id, int $course_id): bool
    {
        $product_id = get_post_meta($course_id, '_tutor_course_product_id', true);
        if (empty($product_id)) {
            return false;
        }
        return wc_customer_bought_product('', $user_id, $product_id);
    }

    /**
     * Verifica si un usuario tiene acceso VIP a un curso.
     */
    public static function hasVipAccess(int $course_id, string $user_email = null): bool
    {
        $str_emails = Posts::getMeta($course_id, 'students_allowed_to_enroll');
        if (empty($str_emails)) return false;
        
        $emails = Strings::getEmails($str_emails);
        if (empty($user_email) && is_user_logged_in()) {
            $user_email = wp_get_current_user()->user_email;
        }

        return !empty($user_email) && in_array($user_email, $emails);
    }
}