<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

namespace boctulus\TutorNewCourses\core\libs;

class Taxes {
    static function VATapplied(){
        return wc_prices_include_tax();
    }

    /*
        Recibe el % de tax (E: 0, 10.5 o 21)
        y establece la clase de impuestos correspondientes para ese producto

        Falta generalizar. Se utilizo para un pais en particular

        Asume que existe no hay dos impuestos para el mismo pais con el mismo %

        Ver 
        https://woodemia.com/configurar-impuestos-woocommerce/ 
    */
    static function setTax($pid, float $percentage_tax){
        if (get_bloginfo("language") != 'es'){
            throw new \Exception("Clase de impuestos configurada solo para idioma castellano");
        }

        /*
            Deberia obtener todas clases de impuestos y sus %
        */
        
        switch ($percentage_tax){
            case 0:
                $vals = [
                    '_tax_status' => 'none',
                    '_tax_class' => ''
                ];
                break;
            case 10.5:
                $vals = [
                    '_tax_status' => 'taxable',
                    '_tax_class' => 'tasa-reducida'
                ];
                break;
            case 21:
                $vals = [
                    '_tax_status' => 'taxable',
                    '_tax_class' => ''
                ];
                break;
            default:
                throw new \InvalidArgumentException("Tax no admitido.");    
        }

        update_post_meta( $pid, '_tax_status', $vals['_tax_status']); // taxable, none
        update_post_meta( $pid, '_tax_class',  $vals['_tax_class']);
}

}


