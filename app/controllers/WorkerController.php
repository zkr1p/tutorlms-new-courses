<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\System;

class WorkerController
{
    /*
        @return void

        Ej:

        php com worker listen name=procesar_cat
        php com worker listen name=procesar_cat max=4
        php com worker listen max=4
    */
    function listen()
    {
        $queue     = $_GET['name'] ?? null;
        $max_tasks = $_GET['max']  ?? 1;

        /*
            Para pruebas
        */
        Logger::truncate(); 

        
        while (true)
        {
            $proc_ids  = table('job_process')
            ->when(!is_null($queue), function($q) use ($queue) {
                $q->where([
                    'queue' => $queue
                ]);
            })
            ->pluck('pid');

            foreach ($proc_ids as $pid){
                dd("Checking for pid = $pid");

                if (!System::isProcessAlive($pid)){
                    table('job_process')
                    ->where(['pid' => $pid])
                    ->delete();
                }
            }

            // Controlo nivel de parelelismo
            $count = table('job_process')
            ->when(!is_null($queue), function($q) use ($queue) {
                $q->where([
                    'queue' => $queue
                ]);
            })
            ->count();

            if ($count >= $max_tasks){
                dd("Nivel de parelismo $max_tasks alcanzado ***");
                sleep(1);
                continue;
            }

            // dequeue           
            $job_id = table('jobs')
            ->when(!is_null($queue), function($q) use ($queue) {
                $q->where([
                    'queue' => $queue
                ]);
            })
            ->where(['taken' => 0])
            ->orderBy(['id' => 'ASC'])
            ->value('id');  

            dd($job_id, 'JOB ID');

            // Para evitar darle palos a la DB
            if (empty($job_id)){
                usleep(800000); 
                continue;
            }

            // System::runInBackground
            $pid = System::runInBackground("php com inflator inflate $job_id");
            dd("Se ha lanzado proceso en background para '$queue' con job_id = $job_id bajo el PID = $pid");

            //
            // Deberia usar esta data chequeando si los PIDs estan activos para determinar si
            // el grado max de paralelismo se ha alanzado
            //
            // El problema con usar simplemente la tabla 'jobs' es ante un re-inicio del sistema
            //
            table('job_process')
            ->insert([
                'queue'  => $queue,
                'job_id' => $job_id,
                'pid'    => $pid,
                'created_at' => at()
            ]);

            $ok =  table('jobs')
            ->where(['id' => $job_id])
            ->update([
                'taken' => 1
            ]);

            // exit;

            usleep(200000);            
        }
    }
}

