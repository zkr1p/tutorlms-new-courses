<?php

namespace boctulus\TutorNewCourses\libs;

use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\core\libs\WCSubscriptions;

/**
 * VERSIÓN OPTIMIZADA
 * Gestiona la lógica de los límites de productos por suscripción de forma eficiente.
 */
class WCSubscriptionsExtended extends WCSubscriptions
{
    /**
     * Verifica si un producto es elegible para ser adquirido vía suscripción.
     * (Sin cambios, esta función ya era eficiente).
     *
     * @param int $product_id El ID del producto.
     * @return bool
     */
    static function isForSubscription(int $product_id) : bool
    {
        $meta_value = Products::getMeta($product_id, 'for_subscription');
        return ($meta_value === 'yes' || $meta_value === 'true');
    }

    /**
     * Comprueba si un usuario cumple las condiciones para obtener un producto con descuento.
     * (Sin cambios, depende de la función optimizada de abajo).
     *
     * @param int|null $user_id
     * @return bool
     */
    function meetConditionsForDiscountUsingSubscription($user_id = null): bool 
    {
        if ($user_id === null){
            if (is_cli()){
                die("Especifique el user_id");
            }
            $user_id  = get_current_user_id();
        }

        if (!$this->hasActive($user_id)) {
            return false;
        }

        $p_limits = Config::get('max_discounted_products_using_subscription');
        if (empty($p_limits)){
            return true;
        }

        $cnt = $this->downloableBySubscriptionPurchasableQuantity($user_id);
        return ($cnt > 0);
    }

    /**
     * VERSIÓN OPTIMIZADA
     * Calcula cuántos productos gratuitos le quedan a un usuario en su ciclo de suscripción actual.
     * Utiliza una consulta directa y eficiente a la base de datos en lugar de recorrer todos los pedidos.
     *
     * @param int|null $user_id
     * @return int
     */
    function downloableBySubscriptionPurchasableQuantity($user_id = null): int 
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (empty($user_id) || !$this->hasActive($user_id)) {
            return 0;
        }

        $p_limits = Config::get('max_discounted_products_using_subscription');
        if (empty($p_limits)) {
            return 99999; // Si no hay límites definidos, se asume que es ilimitado.
        }

        // Determina el límite máximo basado en la frecuencia de la suscripción del usuario.
        $freq = $this->getRenovationFrequency($user_id);
        $max_downloads = 0;
        foreach ($p_limits as $p_limit) {
            if ($p_limit['interval'] === $freq) {
                $max_downloads = (int) $p_limit['value'];
                break;
            }
        }

        // Si no se encontró un límite para la frecuencia del usuario, no se le permite descargar.
        if ($max_downloads === 0) {
            return 0;
        }
        
        // Determina el período de tiempo a revisar (últimos 30 días para mensual, último año para anual).
        $date_query_after = '';
        if ($freq === 'month') {
            $date_query_after = date('Y-m-d H:i:s', strtotime('-30 days'));
        } elseif ($freq === 'year') {
            $date_query_after = date('Y-m-d H:i:s', strtotime('-1 year'));
        }

        global $wpdb;
        
        // Consulta SQL optimizada para contar los productos descargados con la suscripción.
        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT itemmeta_product.meta_value)
            FROM {$wpdb->prefix}woocommerce_order_items AS order_items
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta_product ON order_items.order_item_id = itemmeta_product.order_item_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta_total ON order_items.order_item_id = itemmeta_total.order_item_id
            LEFT JOIN {$wpdb->posts} AS orders ON order_items.order_id = orders.ID
            LEFT JOIN {$wpdb->postmeta} AS ordermeta_customer ON orders.ID = ordermeta_customer.post_id
            WHERE orders.post_type = 'shop_order'
            AND orders.post_status IN ('wc-completed', 'wc-processing')
            AND ordermeta_customer.meta_key = '_customer_user'
            AND ordermeta_customer.meta_value = %d
            AND itemmeta_product.meta_key = '_product_id'
            AND itemmeta_total.meta_key = '_line_total'
            AND itemmeta_total.meta_value = '0'
            AND orders.post_date > %s
        ", $user_id, $date_query_after);
        
        $downloads_count = (int) $wpdb->get_var($query);

        return max(0, $max_downloads - $downloads_count);
    }
}