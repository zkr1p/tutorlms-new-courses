<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Model;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Schema;
use boctulus\TutorNewCourses\core\libs\StdOut;
use boctulus\TutorNewCourses\core\libs\System;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\JobQueue;
use boctulus\TutorNewCourses\core\libs\Paginator;
use boctulus\TutorNewCourses\background\tasks\UnaTask;
use boctulus\TutorNewCourses\background\tasks\OtraTask;
use boctulus\TutorNewCourses\core\libs\CronJobMananger;
use boctulus\TutorNewCourses\background\tasks\ProcesarCategoria;
use boctulus\TutorNewCourses\core\controllers\MakeControllerBase;
use boctulus\TutorNewCourses\shortcodes\star_rating\StarRatingShortcode;

class DumbController
{
    /*
        Test de shortcode
    */
    function rating_slider(){        
        set_template('templates/tpl_basic.php');  

        $sc = new StarRatingShortcode();

        render($sc->rating_slider());
    }

    /*
        Test de shortcode
    */
    function rating_table()
    {
        set_template('templates/tpl_basic.php');  

        $sc = new StarRatingShortcode();

        render($sc->rating_table());
    }
    
    function is_alive(){
        $pid = 4804;

        dd(System::isProcessAlive($pid), 'Running?');
    }

    /*
        CronJobs
    */

    function cronjob_manager_start()
    {
        CronJobMananger::start();
    }

    function cronjob_manager_stop()
    {
        CronJobMananger::stop();
    }

    function is_cron_running()
    {
        dd(CronJobMananger::isRunning('other.php'));
    }

    /*
        Background process
    */
    
    function dumb_fn(){
        $i = 0;
        while (true){
            Logger::log($i);
            sleep(1);
            $i++;
        }
    }

    function dumb_process(){
        dd(
            System::runInBackground("php com dumb dumb_fn")
        );
    }

    /*  
        Tambien lo ejecuta pero devuelve el PID incorrecto (en Windows)
    */
    function dumb_process_l2(){
        dd(
            System::runInBackground("php com dumb dumb_process")
        );
    }

    /*
        Jobs
    */


    function test_dispatch_q1()
    {
        $queue = new JobQueue("q1");
        // $queue->dispatch(UnaTask::class);
        // $queue->dispatch(UnaTask::class);
        // $queue->dispatch(OtraTask::class);
        // $queue->dispatch(OtraTask::class);
    }   

    function test_dispatch_procesar_cat()
    {
        $queue = new JobQueue("procesar_cat");

        // enviar serializado de categoria:

        // $queue->dispatch(ProcesarCategoria::class, '1 - Juan', 39);
        // $queue->dispatch(ProcesarCategoria::class, '2 - Maria', 21);
        // $queue->dispatch(ProcesarCategoria::class, '3 - Felipito', 10);
    }

    function worker_factory_procesar_cat()
    {
        Logger::truncate();

        $queue = new JobQueue("procesar_cat");
        $queue->addWorkers(1);
    }

    function test_worker_factory2()
    {
        $queue = new JobQueue();
        $queue->addWorkers(3);
    }

    function worker_stop()
    {
        JobQueue::stop();
    }

    function worker_stop_procesar_cat()
    {
        JobQueue::stop('procesar_cat');
    }
    
    /*
        Ticks
    */


    // Función para generar el log
    function generarLog() {
        $ix = 0;

        // Obtener la información actual
        $archivo = debug_backtrace()[$ix]['file'];
        $funcion = debug_backtrace()[$ix]['function'];
        $linea    = debug_backtrace()[$ix]['line'];
        $timestamp = time();
        $fecha = date('Y-m-d H:i:s', $timestamp);

        // Construir el mensaje del log
        $mensaje = "[$fecha] Archivo: $archivo, Función: $funcion, Línea: $linea\n";

        // Registrar el mensaje en el archivo de log
        dd($mensaje);
    }

    function x100(){ // line 142
        $r= 1;
        here();
        return;
    }

    function do_profile() {
        $dt = debug_backtrace();

        $archivo = $dt[0]['file'];
        $funcion = $dt[0]['function'];
        $line    = $dt[0]['line'];

        dd("$archivo::$funcion::$line");
    }

    function test_ticks()
    {   
        declare(ticks=1);
        register_tick_function([$this, 'do_profile']);

        $x = 2000000;

        // Bucle infinito para mantener el script en ejecución
        while (true) {
            // Tu código aquí
            $x = $x - rand(1,7);
            
            dd($x);
            if ($x % 2 === 0){
                $this->x100();
            }

            $x++;
        }
    }

}