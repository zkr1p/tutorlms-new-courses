<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\controllers\MyController;
use boctulus\TutorNewCourses\libs\WCSubscriptionsExtended;
use boctulus\TutorNewCourses\core\libs\CustomDownloadPermissions;

class DownloadController
{
    function index()
    {
        try {
            // Verificar nonce
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'download_file')) {
                wp_die('Invalid request - Nonce failed');
            }

            // Obtener y verificar parámetros
            $file_url   = base64_decode($_GET['download_file']);
            $product_id = intval($_GET['product_id']);
            $user_id    = get_current_user_id();

            // Verificar que el usuario actual sea el mismo que solicita la descarga
            if (!is_user_logged_in() || get_current_user_id() != $user_id) {
                wp_die('Unauthorized access - User verification failed');
            }

            // Verificar si el usuario tiene acceso al producto
            $has_permission = false;

            // Verificar si el usuario ha comprado el producto
            $purchased_products = Products::getPurchasedProducts($user_id);
            foreach ($purchased_products as $product) {
                if ($product['ID'] == $product_id && $product['is_downloadable']) {
                    $has_permission = true;
                    break;
                }
            }

            // Si no lo ha comprado, verificar si tiene acceso por suscripción
            if (!$has_permission) {
                if (!WCSubscriptionsExtended::isForSubscription($product_id)) {
                    wp_die("No valid purchase for product ID='$product_id'");
                }

                $wcSubscriptions = new WCSubscriptionsExtended();

                if (!$wcSubscriptions->hasActive($user_id)) {
                    wp_die('No active subscription');
                } else {
                    $has_permission = true;
                }
            }

            if (!$has_permission){
                wp_die("No valid purchase for product ID='$product_id'");
            }

            // Obtener detalles del producto y sus archivos
            $product_details = Products::getDownloadableProductsWithDetails($product_id);

            // dd($product_details); exit;

            if (empty($product_details)) {
                wp_die('Product not found or not downloadable');
            }

            // Convertir URL a ruta del sistema de archivos
            $uploads_dir = wp_upload_dir();
            $file_path = str_replace(
                $uploads_dir['baseurl'],
                $uploads_dir['basedir'],
                $file_url
            );

            // Verificar que el archivo existe
            if (!file_exists($file_path)) {
                wp_die('File not found. PATH=\'' . $file_path . '\'');
            }

            /* 
                Actualizar el contador de descargas en la base de datos
            */
            
            $count = CustomDownloadPermissions::getCount($file_url , $product_id, $user_id);

            // DEBUGGING
            // dd($count, "Count for product_id={$product['ID']}, file_url='{$file_url}'");  exit;
            // Logger::log($count, "Count for product_id={$product['ID']}, file_url='{$file_url}'");

            // deberia extraerlo del producto
            $limit = Products::getMeta($product_id, '_download_limit');

            if ($count  === false){
                CustomDownloadPermissions::insert($file_url, $product_id, $user_id, $limit);
            } else {
                if ($count == 0){
                    wp_die("Limite de descargas alcanzado");
                }

                CustomDownloadPermissions::decreaseCount($file_url, $product_id, $user_id);
            }       

            Files::forceDownload($file_path);
        } catch (\Exception $e){
            Logger::logError($e->getMessage());
            wp_die($e->getMessage());
        }
    }
}

