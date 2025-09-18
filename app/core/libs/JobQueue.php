<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\System;


class JobQueue
{
    // El $name coincide con el campo "queue" en la tabla "jobs"
    protected $name;

    function __construct(string $name = 'default') {
        $this->name = $name;
    }

    public function dispatch(string $job_class, ...$params){
        if (!class_exists($job_class)){
            throw new \Exception ("Class '$job_class' doesn't exist");
        } 

        if (!$job_class::isActive()){
            throw new \Exception("Job is disabled");
        }

        $job = new $job_class();

        if (! $job instanceof Task){
            throw new \Exception ("Class '$job_class' should be instance of Task");
        }

        DB::getDefaultConnection();

         // enqueue
         $id = table('jobs')
         ->insert([
             'queue'      => $this->name,
             'class'      => $job_class,
             'object'     => serialize($job),
             'params'     => serialize($params),
             'created_at' => at(),
         ]);

        Logger::log("Job id: [$id] --dispatched");
    }

    /*
        Agrega worker, le asigna una tarea y lo deja corriendo en segundo plano 
    */
    public function addWorkers(int $workers = 1, $tasks_per_worker = 1){  
        for ($i=0; $i<$workers; $i++){
            // Ejecuta workerController::listen($this->name)

            $php = System::getPHP();
            $pid = System::runInBackground("$php com worker listen name={$this->name} max=$tasks_per_worker");

            DB::getDefaultConnection();
            
            $id = table('job_workers')
            ->insert([
                'queue' => $this->name,
                'pid'   => $pid
            ]);

            Logger::log("Worker id: [$id] --dispatched");
        }
    }

    /*
        Mata de forma violenta a los workers y por ende a los jobs que está ejecutando. Sería como un "--force"

        Una manera más gentil de frenar (pausar) sería mantener una tabla `queues` donde cada cola pueda
        tener un estado (is_active) y que los workers antes de tomar un nuevo job verifiquen que la cola
        sigue activa y sino que terminen.
    */
    static function stop(?string $queue = null){
        DB::getDefaultConnection();

        $pids = table('job_workers')
        ->when(!is_null($queue), function($q) use ($queue){
            $q->where(['queue' => $queue]);
        }, 
        function($q){
            $q->whereRaw('1=1');
        })
        ->pluck('pid');

        if (empty($pids)){
            return;
        }

        foreach ($pids as $pid){
            $exit_code = System::kill($pid);

            if ($exit_code == 0){
                $ok = table('job_workers')
                ->where(['pid' => $pid])
                ->delete();
            }
        }

        table('job_workers')
        ->when(!is_null($queue), function($q) use ($queue){
            $q->where(['queue' => $queue]);
        }, function($q){
            $q->whereRaw('1=1');
        })
        ->delete();

        

        $pids = table('job_process')
        ->when(!is_null($queue), function($q) use ($queue){
            $q->where(['queue' => $queue]);
        }, 
        function($q){
            $q->whereRaw('1=1');
        })
        ->pluck('pid');

        if (empty($pids)){
            return;
        }

        foreach ($pids as $pid){
            $exit_code = System::kill($pid);

            if ($exit_code == 0){
                $ok = table('job_process')
                ->where(['pid' => $pid])
                ->delete();
            }
        }

        table('job_process')
        ->when(!is_null($queue), function($q) use ($queue){
            $q->where(['queue' => $queue]);
        }, function($q){
            $q->whereRaw('1=1');
        })
        ->delete();
    }

}

