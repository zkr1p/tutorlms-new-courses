<?php

namespace boctulus\TutorNewCourses\libs;

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\VarDump;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\core\interfaces\IProcessable;

class Sync implements IProcessable
{
    static $path; 

    static function count() : int {
        return Files::countLines(static::$path);
    }

    static function run($query_sku = null, $offset = null, $limit = null)
    {    
        if (empty(static::$path)){
            throw new \Exception("Especifique el filename");
        }

        debug("Running ...");

        if (empty(trim($query_sku))){
            $query_sku = [];
        } else {
            if (!is_array($query_sku)){
                $query_sku = explode(',', trim($query_sku));
            }
        }

        global $total, $processed, $updated; 

        $total     = 0;
        $processed = 0;
        $updated   = 0;

        $sep = config()['field_separator'];

        Files::processCSV(static::$path, $sep, false, function($p) use ($query_sku) { 
            global $total, $processed, $updated; 

            $total++;

            // dd($p, 'P (por procesar)');
        
            $p['sku'] = !isset($p['sku']) ? null : trim($p['sku']);

            debug("Analizando SKU={$p['sku']} ...");

            // Filtro para pruebas
            if (!empty($query_sku) && !in_array($p['sku'], $query_sku)){
                return;
            }
        
            if (empty($p['sku'])){
                debug("Producto sin SKU: ". var_export($p, true));
                return;
            }
        
            if (!Products::productExists($p['sku'])){
                debug("Producto con SKU = '{$p['sku']}' no encontrado");
                return;
            }
        
            // dd($p, 'P');
        
            $pid = Products::updateProductBySKU($p, 'INTEGER');
        
            if (empty($p)){
                debug("Producto no pudo ser actualizado: ". var_export($p, true));
            } else {
                $updated++;
            }

            $processed++;
        
            dd($p, "ACTUALIZADO para SKU=`{$p['sku']}` y PID=`$pid` <------------------------ *");
        }, [
            'sku',
            'stock',
            'price',
            'sale_price'
        ], $offset, $limit);  

        debug([
            'total'     => $total, 
            'processed' => $processed, 
            'updated'   => $updated
        ], 'RESULTADO DEL LOTE |-----------------------');
    }

}