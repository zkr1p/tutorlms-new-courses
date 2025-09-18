<?php

/**
 * Busca el BOM en archivos de texto.
 * 
 * @param string $filePath Ruta del archivo a verificar.
 * @return bool Devuelve true si el archivo contiene BOM, false en caso contrario.
 */
function hasBOM($filePath)
{
    $bom = "\xEF\xBB\xBF"; // Secuencia de bytes BOM para UTF-8
    $handle = fopen($filePath, 'rb');
    
    if ($handle === false) {
        echo "Error: No se pudo abrir el archivo: $filePath\n";
        return false;
    }

    $firstBytes = fread($handle, 3); // Leer los primeros 3 bytes del archivo
    fclose($handle);

    return $firstBytes === $bom;
}

/**
 * Escanea un directorio en busca de archivos con BOM.
 * 
 * @param string $directoryPath Ruta del directorio a escanear.
 */
function scanDirectoryForBOM($directoryPath)
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directoryPath));

    foreach ($files as $file) {
        if ($file->isFile()) { // Solo procesar archivos
            $filePath = $file->getPathname();
            if (hasBOM($filePath)) {
                echo "BOM encontrado en: $filePath\n";
            }
        }
    }
}

// Ruta del archivo o directorio a verificar
$path = '.';

// Si es un archivo, verificamos directamente.
if (is_file($path)) {
    if (hasBOM($path)) {
        echo "BOM encontrado en el archivo: $path\n";
    } else {
        echo "El archivo no contiene BOM: $path\n";
    }
} elseif (is_dir($path)) {
    // Si es un directorio, escaneamos todos los archivos en su interior.
    scanDirectoryForBOM($path);
} else {
    echo "Error: La ruta especificada no es un archivo ni un directorio válido.\n";
}
