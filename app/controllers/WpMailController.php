<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Request;
use boctulus\TutorNewCourses\core\libs\Users;

/*
    API para envio de correos remotamente con wp_mail 

        POST /api/wp_mail/send

        {
            "to": "mi-correo@gmail.com",
            "subject": "Prueba test",
            "body": "Contenido de <b>prueba</b>"
        }

    Desde PHP,
    
        $url     = '{url-base}/api/wp_mail/send';

        $email   = 'mi-correo@gmail.com';
        $subject = 'Prueba test';
        $content = 'Contenido de <b>prueba</b>';

        $data = [
            'to' => $email,
            'subject' => $subject,
            'body' => $content
        ];

        $res = consume_api($url, 'POST', $data, [
            'User-Agent' => 'PostmanRuntime/7.34.0'
        ]);


    Notas:

        . Enviar User-Agent !!!
        . Faltaria autenticacion de algun tipo por si revelara la API
*/

class WpMailController
{  
    function __construct(){
        _cors();
    }

    // Endpoint callback
    public function send() {
        try {

            $data        = request()->getBody(false);

            $to          = $data['to'] ?? null;
            $subject     = $data['subject'] ?? $data['title'] ?? null;
            $message     = $data['message'] ?? $data['body'] ?? null;
            $from        = $data['from'] ?? null;
            $reply_to    = $data['reply_to'] ?? null;
            $attachments = $data['attachments'] ?? null;

            if (!empty($subject)){
                $subject  = sanitize_text_field($subject);
            }

            if (!empty($to)){
                $to  =  sanitize_email($to);
            }

            if (!empty($message)){
                $message  = wp_kses_post($message);
            }

            if (!empty($from)){
                $from    = sanitize_email($from);
            }

            if (!empty($reply_to)){
                $reply_to = sanitize_email($reply_to);
            }

            // Agregar validaciones para campos obligatorios
            if ( empty( $to ) || empty( $subject ) || empty( $message )) {
                // Manejo de errores para campos obligatorios no completados
                error('missing_required_fields', 400, 'Todos los campos obligatorios deben completarse.');
            }

            // Agregar manejo de errores
            if (!is_email($to)) {
                // Manejo de errores para direcciones de correo electrónico no válidas
                error('invalid_email', 400, 'Dirección de correo electrónico es requerida');
            }

            $headers = array(
                "MIME-Version: 1.0",
                "Content-type: text/html; charset=utf-8",   
            );

            if  (!empty($from)){
                $headers[] = "From: $from";
            }

            if  (!empty($reply_to)){
                $headers[] = "Reply-To: $reply_to";
            }

            // paths to attachments
            if ( strpos( $attachments, ',' ) !== false ) {
                $attachments = explode( ',', $attachments );
            }

            $ok = wp_mail( $to, $subject, $message, $headers, $attachments );

            if ( ! $ok ) {
                // Manejo de errores para wp_mail
                error( 'mail_error', 500, 'Error al enviar el correo.');
            }

            // Resultado de éxito o devolución de errores
            return response([ 
                'status'  => 'success',
                'message' => 'Correo enviado exitosamente.'
            ]);

        } catch ( \Exception $e ) {
           error('mail_error', 500, $e->getMessage());
        }
    }
}
