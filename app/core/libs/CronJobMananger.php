<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\System;

class CronJobMananger
{
    static protected Array $filenames;
    static protected Array $classes;
    static protected Array $freq;

    /*
        Para debugging

        1.- Asegurese que el timezone sea el correcto
        2.- php com async loop {nombre-del-cron}.php

        Tambien puede reemplazarse el System::runInBackground() por System::exec() para ver errores en la terminal
    */
    static function start() {
        static::stop();
        sleep(1);
        static::scan();

        DB::getDefaultConnection();

        table('background_process')
        ->truncate();

        foreach (static::$classes as $ix => $class){          
            $file = static::$filenames[$ix];
            $pid  = System::runInBackground("php com async loop $file", 'logs/output.txt');
            
            // lo ideal es poder elegir el "driver" ya sea en base de datos o en memoria tipo REDIS para los PIDs
            
            table('background_process')
            ->insert([
                'filename' => $file,
                'pid' => $pid
            ]);

            dd($pid, $file);
        }
    }

    /*
        Verifica si el proceso de un CronJob esta ejecutandose
        
        Sino lo esta, elimina la referencia al PID de la tabla

        Esto tiene sentido porque solo puede haber un cronjob
        bajo el mismo fichero
    */
    static function isRunning(string $job) : bool {
        DB::getDefaultConnection();

        $pid = table('background_process')
        ->where(['filename' => $job])
        ->value('pid');

        if (empty($pid)){
            $ret = false;
        } else {
            $ret = System::isProcessAlive($pid);

            // dd($ret);
        }

        if ($ret == false){    
            table('background_process')
            ->where(['pid' => $pid])
            ->delete();
        }

        // dd($ret);

        return $ret;
    }

    static function stop(){
        DB::getDefaultConnection();
    
        $pids = table('background_process')
        ->pluck('pid');
    
        if (empty($pids)){
            return;
        }
    
        foreach ($pids as $pid){
            $exit_code = System::kill($pid);
    
            if ($exit_code == 0){
                table('background_process')
                ->where(['pid' => $pid])
                ->delete();
            }
        }
    }    

    static protected function scan(){
        foreach (new \FilesystemIterator(CRONOS_PATH) as $fileInfo) {
            if($fileInfo->isDir() || $fileInfo->getExtension() != 'php') continue;

            $filename = CRONOS_PATH . $fileInfo->getFilename();
            $class    = PHPLexicalAnalyzer::getClassNameByFileName($filename);

            require_once $filename;

            if (!class_exists($class)){
                throw new \Exception ("Class '$class' doesn't exist in $filename");
            } 

            if (!$class::isActive()){
                continue;
            }

            static::$classes[]   = $class;
            static::$filenames[] = basename($filename);
        }   
    }

}

