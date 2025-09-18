<?php 

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Config;

/*
	Idealmente implementar PSR 3 logger

	https://www.php-fig.org/psr/psr-3/

	Change log:

	- Posibilidad de cambiar archivos a escribir por defecto
	
	- Modo de depuracion
*/
class Logger
{
    static protected $logfile     = 'log.txt';
	static protected $err_logfile = 'errors.txt';
	static protected $sql_logfile = 'sql_log.txt';

	static protected $debug       = false;
	static protected $trace       = false; 

	
	static function debug(bool $debug = true){
		static::$debug = $debug;
	}

	static function trace(bool $show_trace = true){
		static::$trace = $show_trace; 
	}

	static public function traceMe(){
		$trace = debug_backtrace();

		static::dd($trace, "LOCATION", true);
	}

	static function setLogFilename($name){
		static::$logfile = $name;
	}

	static function setErrFilename($name){
		static::$err_logfile = $name;
	}

	static function setSqlfilename($name){
		static::$err_logfile = $name;
	}

    static function getLogFilename(bool $full_path = false)
    {
        if (static::$logfile == null){
            static::$logfile = Config::get('log_file');
        }

        return ($full_path ? Constants::LOGS_PATH : '') . static::$logfile;
    }
    
    static function truncate($log_file = null){
        Files::writeOrFail(Constants::LOGS_PATH . ($log_file ?? static::getLogFilename()), '');
    }

	// Podria recibir archivos a borrar
	static function killLogs(){
		if (file_exists(Constants::LOGS_PATH . 'log.txt')){
            unlink(Constants::LOGS_PATH . 'log.txt');
        }

        if (file_exists(Constants::LOGS_PATH . 'errors.txt')){
            unlink(Constants::LOGS_PATH . 'errors.txt');
        }
	}
    
    static function getContent( $file = null){
        if ($file == null){
	        $file = static::getLogFilename();
        }

        $path = Constants::LOGS_PATH . $file;

		if (!file_exists($path)){
			return false;
		}

		return Files::readOrFail($path);
    }

	static function log($data,  $path = null, $append = true, bool $datetime = true, bool $extra_cr = false)
	{
		$custom_path = true;
		
		if ($path === null){
			$path = Constants::LOGS_PATH . static::getLogFilename();
		} else {
			if (!Strings::contains('/', $path) && !Strings::contains(DIRECTORY_SEPARATOR, $path)){
				$path = Constants::LOGS_PATH . $path;
			}
		}

		if (is_array($data) || is_object($data)){
			$data = json_encode($data, JSON_UNESCAPED_SLASHES);
		}

		$extra = '';
		if (static::$trace)
		{
			$calls = debug_backtrace();

			$trace = [];
			foreach ($calls as $ix => $call){
				$trace[] = "{$call['file']}:{$call['line']}:{$call['function']}";
			}

			$trace = implode(' > ', $trace);

			$extra = " | TRACE: " . json_encode($trace, JSON_UNESCAPED_SLASHES);			
		}

		if (static::$debug){
			dd($data, date('[d-M-Y H:i:s e]') . $extra) ;
		}

		$data .= " $extra";

		if (Config::get('error_log') ?? true){   
			$mode = 0;
			if ($custom_path){
				$mode = 3;
			}

			$prefix = '';
			if ($mode === 3 && $datetime){
				$prefix = date('[d-M-Y H:i:s e]') . ' ';
			}	

			error_log($prefix . $data . ($mode == 3 ? PHP_EOL : '') . ($extra_cr ? PHP_EOL : "") , $mode, $path);
		} else {
			$prefix = '';
			if ($datetime){
				$prefix = date('[d-M-Y H:i:s e]') . ' ';
			}			
			
			return Files::writeOrFail($path, $prefix . $data . "\n" . ($extra_cr ? "\n" : ""),  $append ? FILE_APPEND : 0);
		}
	}

	static function dd($data, $msg = '', bool $append = true){
		if (empty($msg)){
			static::log($data, null, $append);
			return;
		}

		static::log([$msg => $data], null, $append);
	}

	static function logError($error){
		if ($error instanceof \Exception){
			$error = $error->getMessage();
		}

		static::log($error, static::$err_logfile);
	}

	static function logSQL(string $sql_str){
		static::log($sql_str, static::$sql_logfile);
	}

	
	/*
		Resultado:

		<?php 

		$arr = array (
		'x' => 'Z',
		);
	*/
	static function varExport($data, $path = null, $variable = null){
		if ($path === null){
			$path = Constants::LOGS_PATH . 'export.php';
		} else {
			if (!Strings::contains('/', $path) && !Strings::contains(DIRECTORY_SEPARATOR, $path)){
				$path = Constants::LOGS_PATH . $path;
			}
		}

		$export = var_export($data, true);

		if ($variable === null){
			$bytes = Files::writeOrFail($path, '<?php '. "\r\n\r\n" . 'return ' . $export . ';');
		} else {
			if (!Strings::startsWith('$', $variable)){
				$variable = '$'. $variable;
			}
			
			$bytes = Files::writeOrFail($path, '<?php '. "\r\n\r\n" . $variable . ' = ' . $export . ';');
		}

		if (static::$debug){
			dd($export, $path);
		}

		return ($bytes > 0);
	}

}