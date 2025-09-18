<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\controllers\MyController;
use boctulus\TutorNewCourses\libs\TutorLMSWooSubsAutomation;

class CronjobController
{
    /**
     * VERSIÓN OPTIMIZADA
     * Procesa a los usuarios en lotes para evitar agotar la memoria del servidor.
     */
    function index()
    {
        // --- INICIO DE LA MODIFICACIÓN OPTIMIZATED ---

        // Procesar usuarios en lotes de 50 para no agotar la memoria.
        // Puedes ajustar este número si es necesario.
        $batch_size = 50; 
        $transient_name = 'automation_user_offset'; // Clave para guardar el progreso

        // Obtiene el último usuario procesado, o empieza desde cero.
        $offset = get_transient($transient_name);
        if (false === $offset) {
            $offset = 0;
        }

        // Obtiene solo el siguiente lote de IDs de usuario de forma nativa y eficiente.
        $user_ids = get_users([
            'fields' => 'ID',
            'number' => $batch_size,
            'offset' => $offset
        ]);

        if (empty($user_ids)) {
            // Si no hay más usuarios, hemos terminado. Reiniciamos el contador para la próxima vez.
            delete_transient($transient_name);
            
            if (function_exists('dd')){
                dd("Automatización completada para todos los usuarios. El proceso ha finalizado.");
            }
            
            return; // Termina la ejecución
        }

        // Procesa solo el lote actual de usuarios.
        foreach ($user_ids as $uid){
            if (function_exists('dd')){
                dd("Automatizando por user_id=$uid (Lote iniciando en $offset)");
            }
            TutorLMSWooSubsAutomation::run($uid);
        }

        // Guarda el progreso para el siguiente lote.
        $new_offset = $offset + count($user_ids);
        set_transient($transient_name, $new_offset, DAY_IN_SECONDS); // Guarda el progreso por 24 horas

        if (function_exists('dd')){
            dd("Lote de " . count($user_ids) . " usuarios procesado. El siguiente lote comenzará en el usuario $new_offset.");
        }

        // --- FIN DE LA MODIFICACIÓN ---
    }
}