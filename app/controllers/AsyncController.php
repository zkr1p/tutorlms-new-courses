<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\controllers\MyController;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\Date;
use boctulus\TutorNewCourses\core\libs\CronJob;

class AsyncController
{
    /*
        Para debugging

        1.- Asegurese que el timezone sea el correcto
        2.- php com async loop {nombre-del-cron}.php
    */
    public function loop(string $job_filename)
    {   
        // dd(at());

        $path = CRONOS_PATH . $job_filename;

        $class_name = Strings::getClassNameByFileName($path);

        require_once $path;

        if (!class_exists($class_name)){
            throw new \Exception ("Class '$class_name' doesn't exist in $job_filename");
        } 

        $job = new $class_name();
        //dd($class_name, 'job name');

        if (! $job instanceof CronJob){
            throw new \Exception ("Class '$class_name' should be instance of CronJob");
        }

        $freq = $job::getFrequency();        

        $mnth = $freq['month']    ?? -1;
        $mndy = $freq['monthday'] ?? -1;
        $wkdy = $freq['weekday']  ?? -1;
        $hour = $freq['hour']     ?? -1;
        $mins = $freq['minute']   ??  0;
        $secs = $freq['second']   ??  0;

        while (true)
        {
            $M = (int) datetime('n');
            $d = (int) datetime('j');
            $w = (int) datetime('w');
            $h = (int) datetime('G');
            $m = (int) datetime('i');
            $s = (int) datetime('s');

            if (($mnth !== -1)){
                if ($mnth != $M){
                    $dm = Date::diffInSeconds(Date::nextNthMonthFirstDay($mnth));
                    //d($dm, 'Diff por $mnth');
                }
            }

            if (($wkdy !== -1)){
                if ($wkdy != $w){
                    $dw = Date::diffInSeconds(Date::nextNthWeekDay($wkdy));
                    //d($dw, 'Diff por $wkdy');
                }
            }

            if (($mndy !== -1)){
                if ($mndy != $d){
                    $dd = Date::diffInSeconds(Date::nextNthMonthDay($mndy));
                    //d($dd, 'Diff por $mndy');
                }
            }

            if (($hour !== -1)){
                if ($hour != $h){
                    if ($hour > $h){
                        $dh = ($hour - $h -1) * 3600 + (3600 -$s -($m * 60));
                    } else {
                        $dh = (24 - $h + $hour) * 3600 + (3600 -$s -($m * 60));
                    }
                    //d($dh, 'Diff por $h');
                }
            }

            if (($secs !== 0) || $mins !== 0){
                $ds = $secs + ($mins *60);
                //d($ds, 'Diff por $secs y $mins');
            }

            $diff = max($dm ?? 0, $dw ?? 0, $dd ?? 0, $dh ?? 0);
            dd($diff, 'Total diff en segundos');

            sleep($diff);

            /*
                Dado que la computadora pudo haber sido puesta en "suspención" en lo que duraba
                el sleep(), es necesario volver a comprobar que cumpla las condiciones antes de dar start. 
            */
            if (
                ($mnth !== -1 && $mnth != $M) ||
                ($wkdy !== -1 && $wkdy != $w) ||
                ($mndy !== -1 && $mndy != $d) ||
                ($hour !== -1 && $hour != $h)
            ){
                continue;
            }

            $job->start();

            /*
                Si $mins == 0  &&  $secs == 0
                => ejecutar solo una vez
                
                Para esto, luego de ejecutarse, esperar 86400 segundos con lo cual ya no se cumplirá la condición 
                en ese día.
            */

            if ($mins == 0  &&  $secs == 0){
                sleep(86400);
            } else {
                sleep($ds);
            }


        } // end while
    }
}

