<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\Arrays;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\controllers\MyController;

/*
    API

    Es compatible con el resultado de Products:dump() 
    
*/
class ProductsController
{
    function index()
    {
        dd("Index of ". __CLASS__);                   
    }

    /*
        Actualiza uno o mas productos

        $url   = 'http://{domain}/products/update';
        $res   = consume_api($url, 'POST', $prods);
    */
    function update()
    {
        try {
            $data = request()->getBody();

            // Determino la cantidad de productos recibidos  
            // y ajusto $data para que sea array de productos siempre 
            
            $count = count($data);
            if ($count >1){
                if (Arrays::is_assoc($data)){
                    $count = 1;
                    $data  = [ $data ];
                }
            }

            $cnt = 0;
            foreach ($data as $p_arr){
                $pid = Products::updateProductBySKU($p_arr, 'INTEGER');

                if (!empty($pid)){
                    $cnt++;
                }
            }    
            
            $ret = [
                'processed' => $count,
                'updated'  => $cnt
            ];

            if ($count == 1){
                $ret['pid'] = $pid;
            }

            response()->send($ret);

        } catch (\Exception $e){
            error($e->getMessage());
        } 
    }

    /*
        Inserta uno o mas productos

        $url   = 'http://{domain}/products/create';
        $res   = consume_api($url, 'POST', $prods);
    */
    function create()
    {
        try {
            $data = request()->getBody();

            // Determino la cantidad de productos recibidos  
            // y ajusto $data para que sea array de productos siempre 
            
            $count = count($data);
            if ($count >1){
                if (Arrays::is_assoc($data)){
                    $count = 1;
                    $data  = [ $data ];
                }
            }

            $cnt = 0;
            foreach ($data as $p){
                $pid = Products::createProduct($p, false, 'INTEGER');

                if (!empty($pid)){
                    $cnt++;
                }
            }    
            
            $ret = [
                'processed' => $count,
                'inserted'  => $cnt
            ];

            if ($count == 1){
                $ret['pid'] = $pid;
            }

            response()->send($ret);

        } catch (\Exception $e){
            error($e->getMessage());
        } 
    }

    /*
        Devuelve un producto 
        o un listado
    */
    function get($sku = null)
    {
        if (empty($sku)){
            $limit  = $_GET['limit']  ?? 10;
            $offset = $_GET['offset'] ?? null;
            $order  = $_GET['order']  ?? ['id' => 'DESC'];

            $pids = Products::getProductIds('publish', $limit, $offset, null, $order);

            $data = [];
            foreach ($pids as $pid){
                $data['products'][] = Products::dumpProduct($pid);
            }
        } else {
            $pid  = Products::getIdBySKU($sku);
            $data = Products::dumpProduct($pid);
        }   

        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        $data = str_replace("\r\n", '', $data);

        header('Content-Type: application/json; charset=utf-8');

        return $data;
    }

    /*
        Borra un producto
    */
    function delete($sku = null, $permanent = false)
    {
        if (empty($sku)){
           error("SKU is required");
        }

        try {
            $permanent = ($permanent === true || $permanent == '1' || $permanent == true);

            Products::deleteProductBySKU($sku, $permanent);

            $data = 'ok';

            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
            $data = str_replace("\r\n", '', $data);

            header('Content-Type: application/json; charset=utf-8');

            return $data;
        } catch (\Exception $e){
            error($e->getMessage());
        } 
    }
}

