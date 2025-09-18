<?php 

namespace boctulus\TutorNewCourses\core\libs;

class EmailTemplate
{
    static function formatContentWithHeader($content) {
        // Separar las líneas del contenido
        $lines = explode("\n", $content);
    
        // Tomar la primera línea y convertirla en un <h2>
        $header = '<h2 style="color: #333;">' . array_shift($lines) . '</h2>';
    
        // Aplicar estilos al resto de las líneas
        $formattedLines = array_map(function ($line) {
            return empty(trim($line)) ? '' : '<p style="color: #555;">' . $line . '</p>';
        }, $lines);
    
        // Combinar la línea del encabezado y las líneas formateadas
        $formattedContent = $header . implode("\n", $formattedLines);
    
        return $formattedContent;
    }
}