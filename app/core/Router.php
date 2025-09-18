<?php

namespace boctulus\TutorNewCourses\core;

use boctulus\TutorNewCourses\core\libs\Url;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Request;
use boctulus\TutorNewCourses\core\libs\Strings;

class Router
{
	static protected $instance;
	static protected $routes;
	static protected $verbs;

	protected function __construct(){}	

	static function getInstance(){
		if(static::$instance == NULL){
            static::setup();
            static::$instance = new static();
        }

        return static::$instance;
	}

	static protected function setup(){
		add_action('wp', Config::get('namespace') . '\core\Router::router');
	}

	static function resolve(){
		$routes = include Constants::CONFIG_PATH . 'routes.php';

		if (Config::get()['router'] ?? true){ 
			static::routes($routes);
			static::getInstance();
		}
	}

	// set routes
	static function routes(Array $routes){
		foreach ($routes as $fn => $route)
		{
			// Before if Exists
			$pos = strpos($fn, ':');
			$verb = ($pos === false ? null : substr($fn, 0, $pos));

			static::$verbs[substr($fn, $pos+1)]  = $verb;

			if ($pos !== false){
				static::$routes[substr($fn, $pos+1)] = $route;
			} else {
				static::$routes[$fn] = $route;
			}	
		} 
	}

	static function router () {
		if (Strings::startsWith('/wp-content/plugins/', $_SERVER['REQUEST_URI'])){
			return;
		}

		$req = Request::getInstance();
	
		$config = Config::get();
			
		if (php_sapi_name() != 'cli'){
			$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			$path = preg_replace('/(.*)\/index.php/', '/', $path);
	
			$base_url = $config['base_url'] ?? Url::getBaseUrl();

			$config['base_url'] = Strings::addTrailingSlash($base_url);
	
			if ($base_url != '/' && strpos($path, $base_url) === 0) {
				$path = substr($path, strlen($base_url));
			}   
	
			if ($path === false || ! Url::urlCheck($_SERVER['REQUEST_URI']) ){
				error("Malformed URL", 400); 
			}
	
			$_params = explode('/', $path);
	
			if (empty($_params[0]))  
				array_shift($_params);
		}
		
		if (!isset($_params[0])){
			return; // *
		}


		// dd($_params, 'RUTA ACTUAL');

		/*
			Resolve
		*/
	
		if (!empty(static::$routes)){
			foreach (static::$routes as $route => $fn){
				$verb  = static::$verbs[$route] ?? null;

				$route = ltrim($route, '/');
				
				// dd($route, 'PROBANDO ROUTE,...');
		
				$route_frag = explode('/', $route);
		
				// dd($route_frag, 'Fragments');
		
				$match = true;
				foreach ($route_frag as $ix => $f){			
					if (!isset($_params[$ix]) || $f != $_params[$ix]){
						$match = false;
						continue;
					}
				}
		
				if ($match){
					$class_name     = Strings::before($fn, '@');
					$controller_obj = new $class_name();
					
					$method = Strings::after($fn, '@');
					
					if (empty($method)){
						$method = 'index';
					}

					if ($verb != null && $verb != $_SERVER['REQUEST_METHOD']){
						error('Incorrect verb ('.$_SERVER['REQUEST_METHOD'].'), expecting '. $verb,405);
					}

					// De momento, nada por aqui
					$args = [];
					
					$data = call_user_func_array([$controller_obj, $method], $args);
					echo $data; 

					exit;
				}
			}	
		}
		
	} // end method

} // end class



