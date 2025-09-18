<?php

namespace boctulus\TutorNewCourses\libs;

use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\TutorLMS;

/**
 * VERSIÓN FINAL Y FUNCIONAL
 * Observa los pedidos de WooCommerce y dispara la automatización de inscripción
 * tanto para cursos individuales como para suscripciones.
 */
class OrderObserver
{
    /**
     * @var array Previene que una orden se procese múltiples veces.
     */
    static protected $consumed = [];

    /**
     * Engancha las funciones de esta clase a los eventos de WordPress.
     */
    public static function init() {
        add_action('woocommerce_order_status_completed', [__CLASS__, 'callback'], 10, 1);
    }

    /**
     * Función que se ejecuta cuando una orden se completa.
     *
     * @param int $order_id
     */
    public static function callback($order_id){
        if (in_array($order_id, static::$consumed)){
            return;
        }

        Logger::log("OrderObserver activado para order_id=$order_id");

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // --- INICIO DE LA LÓGICA CORREGIDA ---

        // Comprobación #1: ¿La orden contiene un producto de suscripción?
        $is_subscription_order = false;
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            $is_subscription_order = true;
            Logger::log("Es una orden de suscripción.");
        }

        // Comprobación #2: ¿Es una orden de un curso individual de Tutor?
        $is_tutor_order = TutorLMS::is_tutor_order($order_id);
        if ($is_tutor_order) {
            Logger::log("Es una orden de un curso de TutorLMS.");
        }

        // Si no es ninguno de los dos, detenemos el proceso.
        if (!$is_subscription_order && !$is_tutor_order) {
            Logger::log("No es una orden de Tutor ni de suscripción. Proceso detenido.");
            return;
        }
        
        // --- FIN DE LA LÓGICA CORREGIDA ---

        $user_id = $order->get_user_id();

        if (empty($user_id)){
            Logger::log("ERROR: user_id no encontrado para order_id=$order_id");
            return;
        }
        
        Logger::log("Llamando a la automatización para user_id=$user_id");

        if (class_exists('\boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation')) {
            try {
                \boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation::enroll_on_activation($user_id);
            } catch (\Exception $e) {
                Logger::log("Error al ejecutar la automatización desde OrderObserver para el usuario $user_id: " . $e->getMessage());
            }
        }
        
        static::$consumed[] = $order_id;
    }
}