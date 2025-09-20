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
// INICIO DE LA ARQUITECTURA FINAL Y OPTIMIZADA (VERSIÓN DE ALTO RENDIMIENTO)
// =========================================================================

/**
 * Función de ayuda para obtener el estado del suscriptor UNA SOLA VEZ por carga de página.
 * Utiliza una variable estática (un tipo de caché interna) para "recordar" el resultado.
 * Esto reduce drásticamente las consultas a la base de datos en páginas con muchos productos.
 *
 * @return array{is_subscriber: bool, user_id: int|null}
 */
function t4p_get_cached_user_status() {
    // La variable 'static' conserva su valor entre llamadas a la función durante una misma carga de página.
    static $status = null;

    // Si ya hemos calculado el estado, lo devolvemos inmediatamente sin hacer nada más.
    if ($status !== null) {
        return $status;
    }

    // Si el usuario no ha iniciado sesión, establecemos el estado y lo devolvemos.
    if (!is_user_logged_in()) {
        $status = ['is_subscriber' => false, 'user_id' => null];
        return $status;
    }

    // Si es la primera vez que se llama a la función para un usuario logueado,
    // hacemos la consulta a la base de datos.
    $user_id = get_current_user_id();
    $subs_checker = new \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended();
    
    // Guardamos el resultado en nuestra variable 'static' para las próximas veces.
    $status = [
        'is_subscriber' => $subs_checker->hasActive($user_id),
        'user_id' => $user_id
    ];
    
    return $status;
}

/**
 * Filtro de precio principal OPTIMIZADO.
 */
add_filter('woocommerce_get_price_html', function($price_html, $product) {
    if (is_admin() || !is_object($product)) {
        return $price_html;
    }

    // Obtenemos el estado del usuario de nuestra función optimizada.
    $status = t4p_get_cached_user_status();

    if ($status['is_subscriber']) {
        if (wc_customer_bought_product('', $status['user_id'], $product->get_id())) {
            return $price_html; 
        }

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
        if ($product->is_type('simple') && \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended::isForSubscription($product->get_id())) {
            return wc_price(0);
        }
    }

    return $price_html;
}, 100, 2);

/**
 * Filtro de precio de variación OPTIMIZADO.
 */
add_filter('woocommerce_available_variation', function($variation_data, $product, $variation) {
    if (empty($variation_data['price_html'])) {
         $variation_data['price_html'] = '<span class="price">' . $variation->get_price_html() . '</span>';
    }

    // Obtenemos el estado del usuario de nuestra función optimizada.
    $status = t4p_get_cached_user_status();

    if ($status['is_subscriber']) {
        if ($variation->is_downloadable() && \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended::isForSubscription($variation->get_id())) {
            $variation_data['price_html'] = wc_format_sale_price(wc_get_price_to_display($variation, ['price' => $variation->get_regular_price()]), wc_get_price_to_display($variation, ['price' => 0]));
        }
    }
    
    return $variation_data;
}, 100, 3);

/**
 * Filtro de botón de la tienda OPTIMIZADO.
 */
add_filter('woocommerce_loop_add_to_cart_link', function($button_html, $product, $args) {
    if (is_admin() || !is_object($product)) {
        return $button_html;
    }
    
    // Obtenemos el estado del usuario de nuestra función optimizada.
    $status = t4p_get_cached_user_status();
    
    if ($status['user_id'] && wc_customer_bought_product('', $status['user_id'], $product->get_id())) {
        $downloads_url = wc_get_account_endpoint_url('downloads');
        $output = '<div class="product-owned-wrapper">';
        $output .= '<span class="disponible-notice" style="display: block; margin-bottom: 10px; font-weight: bold; color: #28a745;">Disponible en tu cuenta</span>';
        $output .= '<a href="' . esc_url($downloads_url) . '" class="button">' . __('Ir a Mis Descargas', 'tutorstarter') . '</a>';
        $output .= '</div>';
        return $output;
    }
    
    if ($status['is_subscriber'] && \boctulus\TutorNewCourses\libs\WCSubscriptionsExtended::isForSubscription($product->get_id()) && $product->is_type('simple')) {
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