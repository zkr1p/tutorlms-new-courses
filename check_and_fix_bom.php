<?php

/**
 * Busca el BOM en un archivo, muestra la línea donde se encuentra, y opcionalmente lo elimina.
 *
 * @param string $filePath Ruta del archivo a verificar.
 * @param bool $fix Indica si debe eliminar el BOM.
 * @return void
 */
function checkAndFixBOM($filePath, $fix = false)
{
    $bom = "\xEF\xBB\xBF"; // Secuencia de bytes BOM para UTF-8

    // Leer el contenido completo del archivo
    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "Error: No se pudo leer el archivo: $filePath\n";
        return;
    }

    // Detectar si contiene BOM
    if (substr($content, 0, 3) === $bom) {
        echo "BOM encontrado en: $filePath, Línea: 1\n";

        // Si se solicita, eliminar el BOM
        if ($fix) {
            $contentWithoutBOM = substr($content, 3);
            $result = file_put_contents($filePath, $contentWithoutBOM);
            if ($result !== false) {
                echo "BOM eliminado de: $filePath\n";
            } else {
                echo "Error: No se pudo escribir el archivo sin BOM: $filePath\n";
            }
        }
    }
}

/**
 * Escanea un directorio en busca de archivos con BOM, muestra líneas y opcionalmente elimina BOM.
 *
 * @param string $directoryPath Ruta del directorio a escanear.
 * @param bool $fix Indica si debe eliminar el BOM.
 */
function scanDirectoryForBOM($directoryPath, $fix = false)
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directoryPath));

    foreach ($files as $file) {
        if ($file->isFile()) { // Solo procesar archivos
            $filePath = $file->getPathname();
            checkAndFixBOM($filePath, $fix);
        }
    }
}

// Manejo de argumentos de línea de comandos
$options = getopt('', ['fix']);
$fix = isset($options['fix']);

// Ruta del archivo o directorio a verificar
$path = isset($argv[1]) ? $argv[1] : null;

if (!$path || !file_exists($path)) {
    echo "Error: Debes especificar una ruta válida.\n";
    exit(1);
}

// Si es un archivo, verificamos directamente.
if (is_file($path)) {
    checkAndFixBOM($path, $fix);
} elseif (is_dir($path)) {
    // Si es un directorio, escaneamos todos los archivos en su interior.
    scanDirectoryForBOM($path, $fix);
} else {
    echo "Error: La ruta especificada no es un archivo ni un directorio válido.\n";
}
