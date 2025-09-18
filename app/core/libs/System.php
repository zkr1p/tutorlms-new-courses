<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\Constants;

class System
{
    static $res_code;

    static function getOS(){
        return defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : PHP_OS;
    }

    static function isLinux(){
        $os = static::getOS();

        return ($os == 'Linux');
    }

    static function isWindows(){
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
            return true;
        }

        $os = static::getOS();

        return ($os == 'Windows' || $os == 'WIN32' || $os == 'WINNT');
    }

    static function isUnix(){
        $os = static::getOS();

        return (in_array($os, ['Linux', 'BSD', 'Darwin', ' NetBSD', 'FreeBSD', 'Solaris']));
    }

    /*
        Es el server IIS ?
    */
    function isIIS() {
        $server_software = strtolower( $_SERVER['SERVER_SOFTWARE'] );
        if ( strpos( $server_software, 'microsoft-iis') !== false ) {
            return true;
        }
    
        return false;
    }

    // https://www.php.net/manual/en/function.is-executable.php#123883
    static function isExecutableInPath(string $filename) : bool
    {
        if (is_executable($filename)) {
            return true;
        }

        if ($filename !== basename($filename)) {
            return false;
        }

        $paths = explode(PATH_SEPARATOR, getenv("PATH"));
        
        foreach ($paths as $path) {

            $f = $path . DIRECTORY_SEPARATOR . $filename;

            if (is_executable($f)) {
                return true;
            }
        }

        return false;
    }

    /*
        Ej:

        $git_installed = System::inPATH('git');
    */
    static function inPATH(string $command){
        $w = System::isWindows() ? 'where.exe' : 'where';

        return exec("$w $command", $output, $exit_code) == 0;
    }

    /*
        Returns PHP path
        as it is needed to be used with runInBackground()

        Setear la variable de entorno PHP_BINARY en caso
        de necesitar ejecutar desde el navegador
    */  
    static function getPHP(){
        static $location;

        if ($location !== null){
            return $location;
        }

        $location = env('PHP_BINARY');

        if (!empty($location)){
            return $location;
        }

        $location =  System::isWindows() ? shell_exec("where.exe php.exe") : "php";
        
        return  $location;
    }

    /*
        https://factory.dev/pimcore-knowledge-base/how-to/execute-php-pimcore

        Ver tambi'en
        https://gist.github.com/damienalexandre/1300820
        https://stackoverflow.com/questions/13257571/call-command-vs-start-with-wait-option
    */
    static function runInBackground(string $cmd, $output_path = null, $ignore_user_abort = true, int $execution_time = null, $working_dir = null)
    {
        ignore_user_abort($ignore_user_abort);

        if ($execution_time !== null){
            set_time_limit($execution_time);
        }

        $working_dir = $working_dir ?? ROOT_PATH;

        if ($working_dir){
		    chdir($working_dir);
        }

        switch (PHP_OS_FAMILY) {
            case 'Windows':
                if ($output_path !== null){
                    $cmd .= " >> $output_path";
                }
    
                $WshShell = new \COM("WScript.Shell");
                $oExec = $WshShell->Exec($cmd);
                $pid = (int) $oExec->ProcessID;
                $WshShell = null;
    
                break;
            case 'Linux':
                if ($output_path !== null){
                    $pid = (int) shell_exec("nohup nice -n 19 $cmd > $output_path 2>&1 & echo $!");
                } else {
                    $pid = (int) shell_exec("nohup nice -n 19 $cmd > /dev/null 2>&1 & echo $!");
                }

                break;
            default:
            // unsupported
            return false;
        }

        return $pid ?? null;
    }

    public static function isProcessAlive(int $pid): bool 
    {
        // Comprobar si el sistema operativo es Linux
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'LIN') {
            $output = null;
            // Ejecutar el comando 'ps' para obtener información sobre los procesos
            exec("ps -p $pid", $output);

            // Comprobar si se encontró una línea correspondiente al proceso
            return count($output) > 1;
        }
        // Comprobar si el sistema operativo es Windows
        elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Ejecutar el comando 'tasklist' para obtener información sobre los procesos
            exec("tasklist /FI \"PID eq $pid\"", $output);

            // Comprobar si se encontró una línea correspondiente al proceso
            return count($output) > 3;
        }

        // En caso de que el sistema operativo no sea compatible, retornar false
        return false;
    }

    static function kill($pid){
        $exit_code = null;
    
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $exit_code = shell_exec("taskkill /F /PID $pid 2>nul && echo %errorlevel%");
                break;
            case 'Linux':
                $exit_code = shell_exec("kill $pid 2>/dev/null && echo $?");
                break;
            default:
                // unsupported
                break;
        }

        return $exit_code;
    }


    static function exec(string $command, ...$args){
        $extra = implode(' ', array_values($args));

        exec("$command $extra", $ret, static::$res_code);
        
        return $ret;
    }

    /*
        Ejecuta un comando / script situandose primero en el directorio especificado

        Ej:

        $git_log_repo_1 = System::execAt("git log", $path_repo_1)
    */
    static function execAt(string $command, string $dir, ...$args){
        $extra = implode(' ', array_values($args));

        $current_dir = getcwd();

		chdir($dir);
        exec("$command $extra", $ret, static::$res_code);
        chdir($current_dir);
        
        return $ret;
    }

    /*
        Ejecuta un comando / script situandose primero en el root del proyecto
    */
    static function execAtRoot(string $command, ...$args){
        return static::execAt($command, ROOT_PATH, ...$args);
    }

    static function resultCode(){
        return static::$res_code;
    }

    /*
        Ejecuta un comando "com"
    */
    static function com(string $command, ...$args){
        return static::execAtRoot(static::getPHP() . " com $command", ...$args);
    }


    /*        
       "Memory profilers"
        
        - Xhprof PHP Memory Profiler
        
        XHprof has a simple user interface that will help you discover PHP memory leaks. It can also identify the performance issues that make PHP memory leaks happen.

        - Xdebug PHP Profiler
        
        XDebug is a standard PHP profiler that you can use to discover a variety of performance issues in your scripts. The lightweight profiler doesn’t use much memory, so you can run it alongside your PHP scripts for real-time performance debugging.

        - PHP-memprof
        
        PHP-memprof is a stand-alone PHP memory profiler that can tell you exactly how much memory each of your functions uses. It can even trace an allocated byte back to a function.

        - New Relic
    */

    /**
     * Determines whether a PHP ini value is changeable at runtime.
     *
     * Taken from WordPress core 
     * 
     * Uso. Ej:
     * 
     * System::isINIChangeable('memory_limit') === false
     *
     * @link https://www.php.net/manual/en/function.ini-get-all.php
     *
     * @param string $setting The name of the ini setting to check.
     * @return bool True if the value is changeable at runtime. False otherwise.
     */
    static function isINIChangeable(string $setting) {
        static $ini_all;

        if ( ! isset( $ini_all ) ) {
            $ini_all = false;
            // Sometimes `ini_get_all()` is disabled via the `disable_functions` option for "security purposes".
            if ( function_exists( 'ini_get_all' ) ) {
                $ini_all = ini_get_all();
            }
        }

        // If we were unable to retrieve the details, fail gracefully to assume it's changeable.
        if ( ! is_array( $ini_all ) ) {
            return true;
        }

        return false;
    }


    /*
        dd(System::getMemoryLimit(), 'Memory limit');
    */
    static function getMemoryLimit()
    {
        return ini_get('memory_limit');
    }

    /*
        Ej:

        setMemoryLimit('768M');
    */
    static function setMemoryLimit(string $limit)
    {
        if (!static::isINIChangeable('memory_limit')){
            return false;
        }

        ini_set('memory_limit', $limit);
    }

    /*
        dd(System::getMemoryUsage(), 'Memory usage');
        dd(System::getMemoryUsage(true), 'Memory usage (real)');
    */
    static function getMemoryUsage(bool $real_usage = false){
        return (round(memory_get_usage($real_usage) / 1048576,2)) . 'M'; 
    }

    /*      
        dd(System::getMemoryPeakUsage(), 'Memory peak usage');
        dd(System::getMemoryPeakUsage(true), 'Memory peak usage (real)');
    */
    static function getMemoryPeakUsage(bool $real_usage = false){
        return (round(memory_get_peak_usage($real_usage) / 1048576, 2)) . 'M';
    }

    /*
        CPU usage como porcentaje %

        @author Rick <rick@rctonline.nl>

        Ver discusion:
        https://www.reddit.com/r/PowerShell/comments/arxr8h/getwmiobject_win32_processor_returns_load/
    */
    static function getServerLoad() {
    
        if (stristr(PHP_OS, 'win')) {
        
            $wmi = new \COM("Winmgmts://");
            $server = $wmi->execquery("SELECT LoadPercentage FROM Win32_Processor");
            
            $cpu_num = 0;
            $load_total = 0;
            
            foreach($server as $cpu){
                $cpu_num++;
                $load_total += $cpu->loadpercentage;
            }
            
            $load = round($load_total/$cpu_num);
            
        } else {
        
            $sys_load = sys_getloadavg();
            $load = $sys_load[0];
        
        }
        
        return (int) $load;
    }

    static function setMaxExecutionTime($seconds = -1){
        return @ini_set("max_execution_time", (string) $seconds);
    }

    /*
        Registra estadisticas al salir

        Ej:

        --| SYSTEM STATS AT SHUTDOWN
        CPU usage: 4%
        Memory limit: 256M
        Memory usage: 139.27M
        Memory peak: 139.27M
        Last error: 1
        Uncaught TypeError: shuffle(): Argument #1 ($array) must be of type array, null given in /home/fwbibudd/public_html/wp-content/plugins/giglio-sync/libs/Sync.php:352
        Stack trace:
        #0 /home/fwbibudd/public_html/wp-content/plugins/giglio-sync/libs/Sync.php(352): shuffle(NULL)
        #1 /home/fwbibudd/public_html/wp-content/plugins/giglio-sync/libs/Sync.php(495): boctulus\TutorNewCourses\libs\Sync::processCategory(Array)
        #2 /home/fwbibudd/public_html/wp-content/plugins/giglio-sync/sync.php(69): boctulus\TutorNewCourses\libs\Sync::init()
        #3 {main}
        thrown
        /home/fwbibudd/public_html/wp-content/plugins/giglio-sync/libs/Sync.php
        352
        Exiting at 2023-11-25 11:21:02
    */
    static function registerStats(bool $stdout = false, $filename = 'sys_stats.txt')
    {
        register_shutdown_function(function() use ($stdout, $filename){
            $cpu_usage = static::getServerLoad();
            $mem_limit = static::getMemoryLimit();
            $mem_peak  = static::getMemoryPeakUsage();
            $mem_usage = static::getMemoryUsage();
            
            $msg = "CPU usage: $cpu_usage%\r\n".
            "Memory limit: $mem_limit\r\n".
            "Memory usage: $mem_usage\r\n".
            "Memory peak: $mem_peak\r\n";

            if (!empty(error_get_last())){
                $last_err = implode("\r\n", error_get_last());
                $msg     .= "Last error: $last_err\r\n";
            }

            $msg .= "Exiting at ". at();
    
            if ($stdout){
                dd($msg, 'SYSTEM STATS AT SHUTDOWN');
            }

            if ($filename !== false){
                file_put_contents(Constants::LOGS_PATH . $filename, $msg);
            }
        });
    }
}

