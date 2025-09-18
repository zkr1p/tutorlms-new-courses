<?php

use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Orders;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\libs\WCSubscriptionsExtended;
use boctulus\TutorNewCourses\core\libs\CustomDownloadPermissions;

/*
 * Lógica de automatización para productos descargables y suscripciones.
 * VERSIÓN OPTIMIZADA: Mejora el rendimiento de las funciones críticas.
 * @author Pablo
 */

// Hooks para la funcionalidad del checkbox "Disponible en suscripción" en el admin
add_action('woocommerce_process_product_meta', 'save_subscription_meta_for_simple_product');
add_action('woocommerce_save_product_variation', 'save_subscription_meta_for_variation', 10, 2);
add_action('woocommerce_product_options_general_product_data', 'add_subscription_checkbox_for_simple');
add_action('woocommerce_product_after_variable_attributes', 'add_subscription_checkbox_for_variation', 10, 3);

// Hooks para la lógica de precios y descargas
add_action('woocommerce_before_calculate_totals', 'optimized_apply_discount_in_cart', 100);
add_action('woocommerce_subscription_renewal_payment_complete', 'optimized_reset_downloads_on_renewal', 10, 1);

// Lógica para el checkbox en productos SIMPLES
function add_subscription_checkbox_for_simple() {
    woocommerce_wp_checkbox([
        'id'          => 'for_subscription',
        'label'       => __('Disponible en suscripción', 'tutor-subs'),
        'description' => __('Si se marca, los suscriptores activos podrán adquirir este producto gratis.', 'tutor-subs'),
        'value'       => get_post_meta(get_the_ID(), 'for_subscription', true) === 'true' ? 'yes' : '',
    ]);
}

function save_subscription_meta_for_simple_product($post_id) {
    $value = isset($_POST['for_subscription']) ? 'true' : 'no';
    update_post_meta($post_id, 'for_subscription', $value);
}

// Lógica para el checkbox en VARIACIONES de productos
function add_subscription_checkbox_for_variation($loop, $variation_data, $variation) {
    woocommerce_wp_checkbox([
        'id'            => "for_subscription_{$loop}",
        'name'          => "for_subscription[{$loop}]",
        'label'         => __('Disponible en suscripción', 'tutor-subs'),
        'description'   => __('Si se marca, esta variación se entregará a los suscriptores.', 'tutor-subs'),
        'value'         => get_post_meta($variation->ID, 'for_subscription', true) === 'yes' ? 'yes' : '',
        'wrapper_class' => 'form-row form-row-full',
    ]);
}

function save_subscription_meta_for_variation($variation_id, $i) {
    if (!current_user_can('edit_product', $variation_id)) return;
    $value = isset($_POST['for_subscription'][$i]) ? 'yes' : 'no';
    update_post_meta($variation_id, 'for_subscription', $value);
}


/**
 * VERSIÓN OPTIMIZADA
 * Aplica el precio cero en el carrito para suscriptores que no han agotado sus cuotas.
 */
function optimized_apply_discount_in_cart($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!$cart || $cart->is_empty()) return;

    $user_id = get_current_user_id();
    if (!$user_id) return;

    $subs = new WCSubscriptionsExtended();
    if (!$subs->hasActive($user_id)) return;

    // Obtiene la cantidad de productos gratuitos que le quedan al usuario.
    $remaining_allowance = $subs->downloableBySubscriptionPurchasableQuantity($user_id);
    
    foreach ($cart->get_cart() as $key => $cart_item) {
        if ($remaining_allowance <= 0) break; // Si ya no le quedan, no procesar más productos.

        $product_id = $cart_item['variation_id'] ?: $cart_item['product_id'];
        
        // Verifica si el producto es elegible y si el usuario no lo ha comprado antes.
        $is_eligible = WCSubscriptionsExtended::isForSubscription($product_id);
        $already_bought = wc_customer_bought_product('', $user_id, $product_id);

        if ($is_eligible && !$already_bought) {
            $cart_item['data']->set_price(0);
            $remaining_allowance--; // Reduce la cuota por cada producto aplicado.
        }
    }
}

/**
 * VERSIÓN OPTIMIZADA
 * Resetea los permisos de descarga al renovar una suscripción.
 * Es mucho más rápida porque no recorre todos los pedidos del cliente.
 */
function optimized_reset_downloads_on_renewal($subscription) {
    global $wpdb;
    $logger = new Logger();
    $logger->log('--- INICIO DE PROCESO DE RENOVACIÓN (V. OPTIMIZADA) ---');

    if (!is_a($subscription, 'WC_Subscription')) {
        $logger->log('Error: El objeto recibido no es una suscripción válida.');
        return;
    }

    $user_id = $subscription->get_user_id();
    if (!$user_id) {
        $logger->log('Error: No se pudo obtener el User ID.');
        return;
    }

    $logger->log("Procesando renovación para User ID: $user_id.");

    // Obtiene TODOS los permisos de descarga existentes para este usuario.
    $permissions = wc_get_customer_download_permissions($user_id);
    if (empty($permissions)) {
        $logger->log('AVISO: El usuario no tiene permisos de descarga existentes para resetear.');
        return;
    }

    $table_name = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
    $processed_count = 0;

    foreach ($permissions as $permission) {
        $product = wc_get_product($permission['product_id']);

        // Solo resetea si el producto fue adquirido a precio cero y es elegible por suscripción.
        // Asumimos que si fue gratis, fue por la suscripción. Esta es una simplificación segura.
        $is_eligible = $product && $product->is_downloadable() && WCSubscriptionsExtended::isForSubscription($product->get_id());
        
        if ($is_eligible) {
            $new_limit = $product->get_download_limit();
            $limit_is_infinite = (empty($new_limit) || $new_limit === -1 || $new_limit === 0);
            $downloads_remaining = $limit_is_infinite ? '' : $new_limit;
            
            // Actualiza la fila de permisos directamente en la base de datos.
            $wpdb->update(
                $table_name,
                ['download_count' => 0, 'downloads_remaining' => $downloads_remaining],
                ['permission_id' => $permission['permission_id']]
            );
            $processed_count++;
            $logger->log("Permiso reseteado para Producto ID: {$permission['product_id']}.");
        }
    }

    $logger->log("Proceso finalizado. Total de permisos de descarga reseteados: $processed_count");
    $logger->log('--- FIN DEL PROCESO ---');
}