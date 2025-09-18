<?php

namespace boctulus\TutorNewCourses\libs;

use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\TutorLMS;
use boctulus\TutorNewCourses\core\libs\WCSubscriptions;

/**
 * VERSIÓN FINAL Y OPTIMIZADA
 * Gestiona las inscripciones y cancelaciones de cursos basándose en el estado de las suscripciones.
 * Llama a las funciones optimizadas de TutorLMSWooSubsAutomation para una ejecución instantánea y eficiente.
 * 
 */
class SubsReactor
{
    /**
     * Registra los hooks de WooCommerce necesarios para la automatización.
     */
    public function __construct() {
        // Se dispara cuando una suscripción se marca como ACTIVA.
        add_action('woocommerce_subscription_status_active', [$this, 'on_subscription_activated'], 10, 1);

        // Se dispara cuando una suscripción se CANCELA.
        add_action('woocommerce_subscription_status_cancelled', [$this, 'on_subscription_deactivated'], 10, 1);
        
        // Se dispara cuando una suscripción EXPIRA.
        add_action('woocommerce_subscription_status_expired', [$this, 'on_subscription_deactivated'], 10, 1);
        
        // Se dispara cuando una suscripción se pone EN ESPERA (on-hold).
        add_action('woocommerce_subscription_status_on-hold', [$this, 'on_subscription_deactivated'], 10, 1);
    }

    /**
     * Se ejecuta cuando una suscripción se activa.
     * Llama a la lógica de inscripción optimizada.
     *
     * @param \WC_Subscription $subscription Objeto de la suscripción.
     */
    public function on_subscription_activated($subscription) {
        if (!$subscription) {
            return;
        }

        $user_id = $subscription->get_user_id();

        if (empty($user_id)) {
            Logger::log("Error: No se encontró user_id en la suscripción activada.");
            return;
        }

        Logger::log("Suscripción ACTIVADA para user_id=$user_id. Llamando a la automatización de inscripción...");

        // Llama a la nueva función específica y optimizada para activar
        if (class_exists('\boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation')) {
            try {
                \boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation::enroll_on_activation($user_id);
            } catch (\Exception $e) {
                Logger::log("Error al ejecutar la automatización de inscripción para el usuario $user_id: " . $e->getMessage());
            }
        }
    }

    /**
     * Se ejecuta cuando una suscripción se desactiva (cancelada, expirada, en espera).
     * Llama a la lógica de cancelación de inscripción optimizada.
     *
     * @param \WC_Subscription $subscription Objeto de la suscripción.
     */
    public function on_subscription_deactivated($subscription) {
        if (!$subscription) {
            return;
        }

        $user_id = $subscription->get_user_id();

        if (empty($user_id)) {
            Logger::log("Error: No se encontró user_id en la suscripción desactivada.");
            return;
        }

        Logger::log("Suscripción DESACTIVADA para user_id=$user_id. Llamando a la automatización de cancelación de inscripciones...");

        // Llama a la nueva función específica y optimizada para desactivar
        if (class_exists('\boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation')) {
            try {
                \boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation::unenroll_on_deactivation($user_id);
            } catch (\Exception $e) {
                Logger::log("Error al ejecutar la automatización de cancelación para el usuario $user_id: " . $e->getMessage());
            }
        }
    }
}