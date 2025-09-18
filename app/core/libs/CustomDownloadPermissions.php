<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Strings;

/**
 * VERSIÓN CORREGIDA Y SEGURA
 * Gestiona los permisos de descarga en una tabla personalizada.
 * Todas las consultas a la base de datos han sido "preparadas" para prevenir inyecciones SQL.
 */
class CustomDownloadPermissions
{
    /**
     * Inserta un nuevo permiso de descarga de forma segura.
     */
    static function insert($file_url, $product_id, $user_id, $download_count){
        global $wpdb;
        $table = $wpdb->prefix . 'downloadable_product_permissions_new';
        $download_id = base64_encode($file_url);

        $wpdb->insert(
            $table,
            [
                'download_id'    => $download_id,
                'product_id'     => $product_id,
                'user_id'        => $user_id,
                'download_count' => $download_count,
                'access_granted' => current_time('mysql'),
                'created_at'     => current_time('mysql'),
                'updated_at'     => current_time('mysql')
            ],
            ['%s', '%d', '%d', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Decrementa el contador de descargas de forma segura.
     */
    static function decreaseCount($file_url, $product_id, $user_id){
        global $wpdb;
        $table = $wpdb->prefix . 'downloadable_product_permissions_new';
        $download_id = base64_encode($file_url);

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET download_count = download_count - 1, updated_at = %s
             WHERE download_id = %s AND product_id = %d AND user_id = %d AND download_count > 0",
            current_time('mysql'),
            $download_id,
            $product_id,
            $user_id
        ));
    }

    /**
     * Obtiene el contador de descargas de forma segura.
     */
    static function getCount($file_url, $product_id, $user_id){
        global $wpdb;
        $table = $wpdb->prefix . 'downloadable_product_permissions_new';
        $download_id = base64_encode($file_url);

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT download_count FROM {$table} WHERE download_id = %s AND product_id = %d AND user_id = %d LIMIT 1",
            $download_id,
            $product_id,
            $user_id
        ));

        return ($result === null) ? false : (int) $result;
    }
    
    /**
     * Comprueba si un permiso existe de forma segura.
     */
    static function exists(int $product_id, int $user_id) : bool {
        global $wpdb;
        $table = $wpdb->prefix . 'downloadable_product_permissions_new';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE product_id = %d AND user_id = %d LIMIT 1",
            $product_id,
            $user_id
        ));

        return (bool) $result;
    }

    /**
     * Resetea el contador de descargas de forma segura.
     */
    static function resetCount(int $product_id, int $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'downloadable_product_permissions_new';
        $logger = new Logger();
        $logger->log("Reseteando contador (v3) para Producto ID: $product_id y User ID: $user_id.");

        $product = wc_get_product($product_id);
        if (!$product) {
            $logger->log("Error: No se encontró el producto con ID: $product_id.");
            return;
        }

        $new_limit = $product->get_download_limit();
        if ($new_limit < 1) $new_limit = 999;

        $wpdb->update(
            $table,
            ['download_count' => $new_limit, 'updated_at' => current_time('mysql')],
            ['product_id' => $product_id, 'user_id' => $user_id],
            ['%d', '%s'],
            ['%d', '%d']
        );
    }
}