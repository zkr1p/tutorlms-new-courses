<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
    JSON-LD
*/
class JsonLd
{
    static function extract(string $html) {
        // Patrón de expresión regular para encontrar bloques JSON-LD
        $pattern = '/<script type="application\/ld\+json">(.*?)<\/script>/s';

        // Encuentra todos los bloques JSON-LD en la cadena de HTML
        $ok = preg_match_all($pattern, $html, $matches);

        if ($ok === false) {
            return [];
        }

        // $matches[1] contendrá todos los bloques JSON-LD encontrados
        $jsonLdBlocks = $matches[1];
        
        // Decodifica cada bloque JSON-LD y almacénalos en un array
        $dataArray = [];
        foreach ($jsonLdBlocks as $jsonLdBlock) {
            $decodedData = json_decode($jsonLdBlock, true);
            if ($decodedData) {
                $dataArray[] = $decodedData;
            }
        }

        return $dataArray;          
    }


}

