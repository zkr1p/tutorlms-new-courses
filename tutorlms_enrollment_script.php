<?php

use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Url;
use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\libs\Page;
use boctulus\TutorNewCourses\core\libs\Posts;
use boctulus\TutorNewCourses\core\libs\Metabox;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation;


/*
    Pseudo-Metabox para TutorLMS Course Builder 
    (reemplaza el uso de MetaBox para el CPT "courses")
*/

// Registrar endpoints
add_action('rest_api_init', function () {
    // Endpoint GET para obtener emails
    register_rest_route('tutorlms-new-courses/v1', '/enrollment-emails/(?P<course_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_enrollment_emails',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);

    // Endpoint POST para guardar emails
    register_rest_route('tutorlms-new-courses/v1', '/enrollment-emails/(?P<course_id>\d+)', [
        'methods' => 'POST',
        'callback' => 'save_enrollment_emails',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);
});

add_action('admin_enqueue_scripts', function() {
    wp_localize_script('wp-api', 'wpApiSettings', array(
        'nonce' => wp_create_nonce('wp_rest')
    ));
});

// Función para obtener emails
function get_enrollment_emails($request) {
    $course_id = $request['course_id'];
    $emails = get_post_meta($course_id, 'students_allowed_to_enroll', true);
    return new WP_REST_Response(['emails' => $emails], 200);
}

/*
    Si al ACTUALIZAR la pagina (via callback) veo que la lista contiene al menos un correo.... 
    ... cambio el status:

    `post_status` = 'private'
*/
function __set_privacy_callback($course_id, $email_str){
    // Devuelve un array luego de parsear un string con correos
    $emails = Strings::getEmails($email_str);

    if (!empty($emails)){
        Posts::setAsPrivate($course_id);
    }
}

// Función para guardar emails
function save_enrollment_emails($request) {
    $course_id = $request['course_id'];
    $emails    = sanitize_textarea_field($request->get_param('emails'));
    
    update_post_meta($course_id, 'students_allowed_to_enroll', $emails);

    // Ejecutar el callback de privacidad
    __set_privacy_callback($course_id, $emails);
    
    return new WP_REST_Response(['success' => true], 200);
}


/*
    Nuevo Widget para correos a inscribir
*/

add_action('init', function() {    
    $course_id = Url::getQueryParam(null, 'course_id');

    if (Page::isPage('create-course') && !empty($course_id)) {
        // Definir la ruta del archivo parcial
        $partial = Constants::VIEWS_PATH . 'enrollment_widget.php';

        // Verificar si el archivo existe antes de incluirlo
        if (file_exists($partial)) {
            include $partial;
        } else {
            // Manejar el caso donde el archivo no existe
            echo '<!-- Partial file not found: ' . esc_html($partial) . ' -->';
        }
    }
});


// Hook into 'posts_results' to check if a private course is being accessed directly.
add_filter('posts_results', 'tcd_allow_private_course_access', 10, 2);

/*  
    Sin esto no seria siquiera posible ver el post via URL si es privado
*/
function tcd_allow_private_course_access($posts, $query)
{
    // Correccion
    if (!is_user_logged_in()){
        return $posts;
    }

    // Check if it's a single post being queried and if the user is not logged in or not an admin.
    if (is_single() && count($posts) == 1) {

        $post = $posts[0];

        $pid = $post->ID;

        if (!TutorLMSWooSubsAutomation::hasVipAccess($pid)){
            return $posts;
        }

        // Check if the post is a Tutor LMS course and if it's private.
        if ($post->post_type == 'courses' && $post->post_status == 'private') {
            // Temporarily change the post status to 'publish' to allow access.
            $posts[0]->post_status = 'publish';
        }
    } 
    
    return $posts;
}