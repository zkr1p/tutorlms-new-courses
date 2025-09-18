<?php
/**
 * Reemplaza el botón "Añadir al carrito" por "Ir a Mis Descargas" solo si quedan descargas disponibles.
 * Versión final, optimizada y segura.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evitar acceso directo
}

// --- FUNCIÓN OPTIMIZADA PARA EL CATÁLOGO / PÁGINAS DE TIENDA ---
add_filter('woocommerce_loop_add_to_cart_link', function($button_html, $product) {
    if ( ! $product->is_downloadable() ) {
        return $button_html;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return $button_html;
    }

    // Usamos una variable estática para que la consulta a la base de datos se haga UNA SOLA VEZ.
    static $user_downloads = null;
    if ( $user_downloads === null ) {
        $user_downloads = wc_get_customer_available_downloads($user_id);
    }

    $has_downloads_left = false;
    // Buscamos si el producto actual está en la lista de descargas del usuario.
    foreach ($user_downloads as $download_data) {
        if ($download_data['product_id'] == $product->get_id()) {
            // Verificamos si las descargas son ilimitadas ('') o si quedan disponibles (> 0).
            if ($download_data['downloads_remaining'] === '' || (int) $download_data['downloads_remaining'] > 0) {
                $has_downloads_left = true;
            }
            break; // Encontramos el producto, no es necesario seguir buscando.
        }
    }

    // Si le quedan descargas, mostramos el botón. Si no, se muestra el botón de compra original.
    if ($has_downloads_left) {
        $downloads_url = wc_get_account_endpoint_url('downloads');
        return '<a href="' . esc_url($downloads_url) . '" class="button alt">' . __('Ir a Mis Descargas', 'woocommerce') . '</a>';
    }

    return $button_html;
}, 100, 2);


// --- FUNCIÓN PARA LA PÁGINA DE PRODUCTO INDIVIDUAL ---
// Mantenemos el hook de Elementor aquí, ya que es específico para la ficha de producto.
add_action('elementor/widget/render_content', function($content, $widget) {
    if ($widget->get_name() !== 'woocommerce-product-add-to-cart' || !is_product()) {
        return $content;
    }

    global $product;
    $user_id = get_current_user_id();
    
    if (!$product || !$user_id || !$product->is_downloadable()) {
        return $content;
    }

    // Verificamos si el cliente ha comprado el producto.
    if ( !wc_customer_bought_product('', $user_id, $product->get_id()) ) {
        return $content;
    }

    $has_downloads_left = false;
    $available_downloads = wc_get_customer_available_downloads($user_id);

    foreach ($available_downloads as $download_data) {
        if ($download_data['product_id'] == $product->get_id()) {
            if ($download_data['downloads_remaining'] === '' || (int) $download_data['downloads_remaining'] > 0) {
                $has_downloads_left = true;
            }
            break;
        }
    }

    // Si le quedan descargas, mostramos nuestro botón.
    if ($has_downloads_left) {
        $downloads_url = wc_get_account_endpoint_url('downloads');
        // Devolvemos solo el botón, Elementor ya pone el '<div>' que lo envuelve.
        return '<a href="' . esc_url($downloads_url) . '" class="buttonalt">' . __('Ir a Mis Descargas', 'woocommerce') . '</a>';
    }
    
    // Si no le quedan descargas, devolvemos el contenido original, que es el botón "Añadir al carrito".
    return $content;
}, 10, 2);