<?php

use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Solo permitir ejecución desde la línea de comandos (CLI)
if (php_sapi_name() != "cli"){
    die('Acceso no permitido.'); 
}

// Carga de WordPress
require_once __DIR__ . '/app.php';
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR);
    require_once ABSPATH . '/wp-config.php';
    require_once ABSPATH .'/wp-load.php';
}

echo "--- Inicio del Proceso de Automatización por Lotes ---" . PHP_EOL;

// --- INICIO DE LA LÓGICA OPTIMIZADA ---

// Procesar usuarios en lotes de 50 para no agotar la memoria.
$batch_size = 50; 
$transient_name = 'cron_script_user_offset'; // Clave única para este script

// Obtiene el último usuario procesado, o empieza desde cero.
$offset = get_transient($transient_name);
if (false === $offset) {
    $offset = 0;
}

// Obtiene solo el siguiente lote de IDs de usuario.
$user_ids = get_users([
    'fields' => 'ID',
    'number' => $batch_size,
    'offset' => $offset
]);

if (empty($user_ids)) {
    // Si no hay más usuarios, hemos terminado.
    delete_transient($transient_name);
    echo "Automatización completada para todos los usuarios. El proceso ha finalizado." . PHP_EOL;
    exit; // Termina la ejecución
}

echo "Procesando " . count($user_ids) . " usuarios (Lote iniciando en $offset)..." . PHP_EOL;

// Procesa solo el lote actual de usuarios.
foreach ($user_ids as $uid){
    echo "Automatizando por user_id=$uid..." . PHP_EOL;
    // Usamos un bloque try-catch para que un error en un usuario no detenga todo el proceso.
    try {
        TutorLMSWooSubsAutomation::run($uid);
    } catch (\Exception $e) {
        echo "Error procesando al usuario $uid: " . $e->getMessage() . PHP_EOL;
    }
}

// Guarda el progreso para el siguiente lote.
$new_offset = $offset + count($user_ids);
set_transient($transient_name, $new_offset, DAY_IN_SECONDS);

echo "--- Lote finalizado. El siguiente comenzará en el usuario $new_offset. ---" . PHP_EOL;

// --- FIN DE LA LÓGICA OPTIMIZADA ---