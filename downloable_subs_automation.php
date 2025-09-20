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
        'id'              => "for_subscription_{$loop}",
        'name'            => "for_subscription[{$loop}]",
        'label'           => __('Disponible en suscripción', 'tutor-subs'),
        'description'     => __('Si se marca, esta variación se entregará a los suscriptores.', 'tutor-subs'),
        'value'           => get_post_meta($variation->ID, 'for_subscription', true) === 'yes' ? 'yes' : '',
        'wrapper_class'   => 'form-row form-row-full',
    ]);
}

function save_subscription_meta_for_variation($variation_id, $i) {
    if (!current_user_can('edit_product', $variation_id)) return;
    $value = isset($_POST['for_subscription'][$i]) ? 'yes' : 'no';
    update_post_meta($variation_id, 'for_subscription', $value);
}

function optimized_apply_discount_in_cart($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!$cart || $cart->is_empty()) return;
    $user_id = get_current_user_id();
    if (!$user_id) return;
    $subs = new WCSubscriptionsExtended();
    if (!$subs->hasActive($user_id)) return;
    $remaining_allowance = $subs->downloableBySubscriptionPurchasableQuantity($user_id);
    foreach ($cart->get_cart() as $key => $cart_item) {
        if ($remaining_allowance <= 0) break;
        $product_id = $cart_item['variation_id'] ?: $cart_item['product_id'];
        $is_eligible = WCSubscriptionsExtended::isForSubscription($product_id);
        $already_bought = wc_customer_bought_product('', $user_id, $product_id);
        if ($is_eligible && !$already_bought) {
            $cart_item['data']->set_price(0);
            $remaining_allowance--;
        }
    }
}

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
    $permissions = wc_get_customer_download_permissions($user_id);
    if (empty($permissions)) {
        $logger->log('AVISO: El usuario no tiene permisos de descarga existentes para resetear.');
        return;
    }
    $table_name = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
    $processed_count = 0;
    foreach ($permissions as $permission) {
        $product = wc_get_product($permission['product_id']);
        $is_eligible = $product && $product->is_downloadable() && WCSubscriptionsExtended::isForSubscription($product->get_id());
        if ($is_eligible) {
            $new_limit = $product->get_download_limit();
            $limit_is_infinite = (empty($new_limit) || $new_limit === -1 || $new_limit === 0);
            $downloads_remaining = $limit_is_infinite ? '' : $new_limit;
            $wpdb->update($table_name, ['download_count' => 0, 'downloads_remaining' => $downloads_remaining], ['permission_id' => $permission['permission_id']]);
            $processed_count++;
            $logger->log("Permiso reseteado para Producto ID: {$permission['product_id']}.");
        }
    }
    $logger->log("Proceso finalizado. Total de permisos de descarga reseteados: $processed_count");
    $logger->log('--- FIN DEL PROCESO ---');
}


// =========================================================================
// INICIO DE LA ARQUITECTURA FINAL, OPTIMIZADA Y SEGURA (A PRUEBA DE BUCLES)
// =========================================================================

/**
 * Función de ayuda para obtener y cachear el estado del usuario y sus descargas.
 * Se ejecuta UNA SOLA VEZ por carga de página, garantizando un rendimiento óptimo.
 *
 * @return array{is_subscriber: bool, user_id: int|null, downloads: array}
 */
function t4p_get_cached_user_and_downloads_status() {
    static $status = null;
    if ($status !== null) return $status;

    // --- GUARDIA 1: Usuario no logueado (Invitado) ---
    // Si no ha iniciado sesión, establecemos un estado "invitado" y salimos inmediatamente.
    // Coste: 0 consultas a la base de datos.
    if (!is_user_logged_in()) {
        $status = ['is_subscriber' => false, 'user_id' => null, 'downloads' => []];
        return $status;
    }

    // A partir de aquí, sabemos que el usuario ha iniciado sesión.
    $user_id = get_current_user_id();
    $subs_checker = new \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended();
    $is_subscriber = $subs_checker->hasActive($user_id);

    // --- GUARDIA 2: Usuario logueado pero NO suscriptor ---
    // Si no es suscriptor, no necesitamos obtener su lista de descargas para la lógica de precios.
    if (!$is_subscriber) {
        $status = ['is_subscriber' => false, 'user_id' => $user_id, 'downloads' => []]; // Descargas vacío por eficiencia.
        return $status;
    }

    // --- SOLO EL SUSCRIPTOR ACTIVO LLEGA AQUÍ ---
    // Solo si el usuario es un suscriptor activo, hacemos la consulta "pesada" de obtener sus descargas.
    $status = [
        'is_subscriber' => true,
        'user_id'       => $user_id,
        'downloads'     => wc_get_customer_available_downloads($user_id)
    ];
    return $status;
}

/**
 * Función de ayuda para comprobar si a un usuario le quedan descargas para un producto.
 */
function t4p_user_has_downloads_left_for_product($product_id, $all_downloads) {
    if (empty($all_downloads)) return false;
    foreach ($all_downloads as $download) {
        if ($download['product_id'] == $product_id || $download['variation_id'] == $product_id) {
            return ($download['downloads_remaining'] === '' || (int) $download['downloads_remaining'] > 0);
        }
    }
    return false;
}

/**
 * Filtro de precio principal OPTIMIZADO con "Guard Clauses".
 */
add_filter('woocommerce_get_price_html', function($price_html, $product) {
    if (is_admin() || !is_object($product)) return $price_html;

    $status = t4p_get_cached_user_and_downloads_status();
    
    // --- GUARDIA 1: Si no es suscriptor, no hacemos NADA. ---
    // Esto cubre a invitados y usuarios normales, que salen de aquí instantáneamente.
    if (!$status['is_subscriber']) return $price_html;

    $user_id = $status['user_id'];
    $product_id = $product->get_id();
    
    // Lógica solo para suscriptores
    $has_bought = wc_customer_bought_product('', $user_id, $product_id);
    $has_downloads_left = t4p_user_has_downloads_left_for_product($product_id, $status['downloads']);

    if ($has_bought && !$has_downloads_left) return $product->get_price_html();
    if ($has_bought && $has_downloads_left) return $price_html;

    if ($product->is_type('variable')) {
        $prices = $product->get_variation_prices(true);
        if (empty($prices['price'])) return $price_html;
        $max_price = end($prices['price']);
        foreach ($product->get_children() as $child_id) {
            if (\boctulus\TutorNewCourses\libs\WCSubscriptionsExtended::isForSubscription($child_id)) {
                return wc_price(0) . ' - ' . wc_price($max_price);
            }
        }
    }
    if ($product->is_type('simple') && \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended::isForSubscription($product_id)) {
        return wc_price(0);
    }
    
    return $price_html;
}, 100, 2);

/**
 * Filtro de precio de variación OPTIMIZADO y a prueba de bucles.
 */
add_filter('woocommerce_available_variation', function($variation_data, $product, $variation) {
    $status = t4p_get_cached_user_and_downloads_status();
    
    // --- GUARDIA: Si no es suscriptor, no hacemos NADA. ---
    if (!$status['is_subscriber']) return $variation_data;

    $user_id = $status['user_id'];
    $variation_id = $variation->get_id();
    $has_bought = wc_customer_bought_product('', $user_id, $variation_id);
    $has_downloads_left = t4p_user_has_downloads_left_for_product($variation_id, $status['downloads']);

    if ($has_bought && !$has_downloads_left) {
        $price = wc_get_price_to_display($variation);
        $variation_data['price_html'] = wc_price($price);
    } 
    elseif (!$has_bought && $variation->is_downloadable() && \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended::isForSubscription($variation_id)) {
        $variation_data['price_html'] = wc_format_sale_price(
            wc_get_price_to_display($variation, ['price' => $variation->get_regular_price()]),
            wc_get_price_to_display($variation, ['price' => 0])
        );
    }
    
    return $variation_data;
}, 100, 3);

/**
 * Filtro de botón de la tienda OPTIMIZADO Y ROBUSTO.
 */
add_filter('woocommerce_loop_add_to_cart_link', function($button_html, $product, $args) {
    if (is_admin() || !is_object($product)) return $button_html;
    
    $status = t4p_get_cached_user_and_downloads_status();

    // --- GUARDIA: Si no ha iniciado sesión, no hacemos NADA. ---
    if (!$status['user_id']) return $button_html;

    $user_id = $status['user_id'];
    $product_id = $product->get_id();
    
    // Esta lógica sí se aplica a cualquier usuario logueado, sea suscriptor o no.
    $has_bought = wc_customer_bought_product('', $user_id, $product_id);
    $has_downloads_left = t4p_user_has_downloads_left_for_product($product_id, $status['downloads']);

    if ($has_bought && $has_downloads_left) {
        $downloads_url = wc_get_account_endpoint_url('downloads');
        $output = '<div class="product-owned-wrapper">';
        $output .= '<span class="disponible-notice">Disponible en tu cuenta</span>';
        $output .= '<a href="' . esc_url($downloads_url) . '" class="button">' . __('Ir a Mis Descargas', 'tutorstarter') . '</a>';
        $output .= '</div>';
        return $output;
    }
    
    // Esta lógica solo se aplica a suscriptores.
    if ($status['is_subscriber'] && !$has_bought && \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended::isForSubscription($product_id) && $product->is_type('simple')) {
        return sprintf(
            '<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
            esc_url($product->add_to_cart_url()),
            esc_attr(isset($args['quantity']) ? $args['quantity'] : 1),
            esc_attr(isset($args['class']) ? $args['class'] : 'button'),
            isset($args['attributes']) ? wc_implode_html_attributes($args['attributes']) : '',
            esc_html(__('Obtener gratis', 'tutorstarter'))
        );
    }
    
    return $button_html;
}, 100, 3);