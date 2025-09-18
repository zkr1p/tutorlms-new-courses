<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Products;
use boctulus\TutorNewCourses\core\libs\Request;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\System;
use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\XML;
use boctulus\TutorNewCourses\libs\Import;

class AdminTasksController
{
    function __construct()
    {   
        // Restringe acceso a admin
        Users::restrictAccess();
    }

    function index(){
        $php = System::getPHP();
        dd($php, 'PHP PATH');

        dd("Bienvenido!");
    }

    function migrate()
    {   
        dd("Migrating ...");

        $mgr = new MigrationsController();
        $mgr->migrate(); // "--dir=$folder", "--to=$tenant"
    }

    // Borra productos y sus categorias
    function wipe(){
        dd("Wiping products & categories ...");

        Products::deleteAllProducts();
        Products::deleteAllCategories(false, false);
    }

    /*
        --| max_execution_time
        300

        --| PHP version
        8.1.26
    */
    function show_system_vars(){
        dd(
            ini_get('max_execution_time'), 'max_execution_time'
        );

        dd(phpversion(), 'PHP version');
    }

    /*
        Devuelve algo como

        D:\www\woo6\wp-content\plugins\wp_runa\
    */
    function plugin_dir(){
        return realpath(__DIR__);
    }

    function get_smtp(){
        $smtp_host = ini_get('SMTP');
        $smtp_port = ini_get('smtp_port');
        $smtp_user = ini_get('smtp_user');
        $smtp_pass = ini_get('smtp_pass');

        // Muestra la información
        dd( "SMTP Host: $smtp_host");
        dd( "SMTP Port: $smtp_port");
        dd( "SMTP User: $smtp_user");
        dd( "SMTP Password: $smtp_pass");

        $to      = 'boctulus@gmail.com';    
        $subject = "Test";
        $message = "Probando 1,2,3";

        $sent = wp_mail($to, $subject, $message);        
        dd($sent, 'Sent?');
    }

    function log_me(){
        Logger::log(__FUNCTION__);
    }

    private function __debug_log($path, $kill = false)
    {
        if ($kill){
            Files::delete($path);
            die("Log truncated");
        }

        if (!is_cli()) echo '<pre>';
        echo Files::read($path) ?? '--x--';
        if (!is_cli()) echo '</pre>';
    }

    function log($kill = false){
        $path = Constants::LOGS_PATH . 'log.txt';
        $this->__debug_log($path, $kill);
    }

    function error_log($kill = false){
        $path = Constants::LOGS_PATH . 'errors.txt';
        $this->__debug_log($path, $kill);
    }

    function debug_log($kill = false){
        $path = Constants::WP_CONTENT_PATH . 'debug.log';
        $this->__debug_log($path, $kill);
    }

    function wc_logs($file = null){
        $path = Constants::WP_CONTENT_PATH . '/uploads/wc-logs/';

        if ($file != null){
            if (!Strings::contains('/uploads/wc-logs/', $file)){
                $file = $path . $file;
            }

            dd(Files::getContentOrFail($file));
            exit;
        }

        dd(
            Files::glob($path, '*.log' )
        );
    }

    function logs($kill = false){    
        dd("Plugin log:");  
        $this->log($kill);
                
        dd("Plugin error_log:");
        $this->error_log($kill);

        dd("WordPress log:");
        $this->debug_log($kill);

        dd("WooCommece log files:");
        $this->wc_logs($kill);
    }
    function req($kill = false){
        $path = Constants::LOGS_PATH . 'req.txt';
        $this->__debug_log($path, $kill);
    }

    function res($kill = false){
        $path = Constants::LOGS_PATH . 'res.txt';
        $this->__debug_log($path, $kill);
    }

    function kill_logs(){
        if(file_exists(Constants::LOGS_PATH . 'errors.txt')){
            unlink(Constants::LOGS_PATH . 'errors.txt');
            dd("File 'errors.txt' was deleted");
        }

        if(file_exists(Constants::LOGS_PATH . 'log.txt')){
            unlink(Constants::LOGS_PATH . 'log.txt');
            dd("File 'log.txt' was deleted");
        }

        if(file_exists(Constants::WP_CONTENT_PATH . 'debug.log')){
            unlink(Constants::WP_CONTENT_PATH . 'debug.log');
            dd("File 'debug.log' was deleted");
        }
    }

    function log_queries()
    {
        $logFilePath = Constants::LOGS_PATH . 'mysql.txt';

        try {
            $conn = DB::getConnection();
            
            // Habilitar el registro general de consultas
            $conn->exec("SET GLOBAL general_log = 1");
            
            // Establecer la ubicación del archivo de registro general de consultas
            $conn->exec("SET GLOBAL general_log_file = '$logFilePath'");
            
            dd("General query log enabled successfully.");
        } catch (\PDOException $e) {
            dd("Error: " . $e->getMessage());
        }
    }
    
    function adminer(){
        require_once __DIR__ . '/../scripts/adminer.php';
    }

    function update_db(){
        require __DIR__ . '/../scripts/installer.php';
        dd('done table creation');

        $this->insert();
        dd('done insert table');
    }

    function insert(){
        global $wpdb;
        
       // ...
    }
}
