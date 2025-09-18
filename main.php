<?php

use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Plugins;
use boctulus\TutorNewCourses\libs\Main;
use boctulus\TutorNewCourses\libs\SubsReactor;
use boctulus\TutorNewCourses\libs\OrderObserver;

/*
 * Plugin Name: Tutor LMS New Courses Customizations
 * Description: Plugin personalizado para extender Tutor LMS y WooCommerce.
 * Version: 1.2.1 (Stable)
 * Author: Pablo Bozzolo <boctulus@gmail.com>
 */

// Prevenir acceso directo al archivo.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función principal de inicialización del plugin.
 */
function initialize_tutor_new_courses_plugin() {
    // Carga los archivos necesarios
    require_once __DIR__ . '/app.php';

    if (!is_cli()){
        require_once __DIR__ . '/downloable_subs_automation.php';
        require_once __DIR__ . '/tutorlms_enrollment_script.php';
    }

    // Activa el observador de pedidos corregido para manejar las inscripciones.
    if (class_exists('\boctulus\TutorNewCourses\libs\OrderObserver')) {
        \boctulus\TutorNewCourses\libs\OrderObserver::init();
    }
    
    // Inicializa el controlador principal del plugin.
    new Main();

    // El antiguo SubsReactor ya no es necesario con el nuevo OrderObserver,
    // pero se mantiene la lógica por si se usan versiones antiguas de WC Subscriptions.
    if (class_exists('WC_Subscriptions') && Plugins::getVersion('woocommerce-subscriptions') < '5.2.0'){
        new SubsReactor('shop_subscription');
    }
}

// Enganchamos nuestra función principal al hook 'plugins_loaded'.
// Esto asegura que todo se ejecute en el momento correcto.
add_action('plugins_loaded', 'initialize_tutor_new_courses_plugin');