<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

namespace boctulus\TutorNewCourses\core\libs;

class Request
{
    static protected $query_arr;
    static protected $raw;
    static protected $body;
    static protected $body_as_obj;
    static protected $body_param_destroyed = [];
    static protected $params;
    static protected $headers;
    static protected $accept_encoding;
    static protected $instance  = null;

    protected $as_object = true;

    protected function __construct() { }

    function as_array(){
        $this->as_object = false;
        return $this;
    }

    static function getInstance(){
        if(static::$instance == null){
            if (php_sapi_name() != 'cli'){
                if (isset($_SERVER['QUERY_STRING'])){
					static::$query_arr = Url::query();

                    if (isset(static::$query_arr["accept_encodig"])){
                        static::$accept_encoding = static::$query_arr["accept_encodig"];
                        unset(static::$query_arr["accept_encodig"]);
                    }
				}
                
                static::$headers = apache_request_headers();

                $tmp = [];
                foreach (static::$headers as $key => $val){
                    $tmp[strtolower($key)] = $val;
                }
                static::$headers = $tmp;
                
            }
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    function getRaw(){
        if (static::$raw === null){
            static::$raw = file_get_contents("php://input");
        }

        return static::$raw;
    }

    function getBody($as_obj = false)
    {
        if ($as_obj && static::$body_as_obj === null){
            static::$body_as_obj = json_decode($this->getRaw(), false);            
        } else {
            if (!$as_obj && static::$body === null){
                static::$body = json_decode($this->getRaw(), true);            
            }
        }

        return $as_obj ? static::$body_as_obj : static::$body;
    }

    function getBodyParam($key){
        if (static::$body === null){
            if (static::$body_as_obj === null){
                $this->getBody(false);

                $val = static::$body[$key] ?? null;
            } else {
                $val = static::$body->$key ?? null;
            }           
        } else {
            $val = static::$body[$key] ?? null;
        }

        if ($val == null){
            return null;
        }

        if (in_array($key, static::$body_param_destroyed)){
            return null;
        }

        return $val;
    }

    // getter destructivo sobre el body --> deberia usar un array para guardar keys destruidas
    function shiftBodyParam($key){
        $val = $this->getBodyParam($key);

        if ($val == null){
            return null;
        }

        if (!in_array($key, static::$body_param_destroyed)){
            static::$body_param_destroyed[] = $key;
        } else {
            return null;
        }

        return $val;
    }

    function getFormData(){
        return $_POST;
    }

    /*
        Intenta recuperar via $_POST un JSON enviado como body en modo "raw"

        $_POST solo funciona con

            Content-Type: application/x-www-form-urlencoded

        y

            Content-Type: multipart/form-data (usado principalmente para file uploads)

        Ver
        https://stackoverflow.com/a/8893792

    */
    function parseFormData(){
        $data = $_POST;

        if (static::getHeader('Content-type') == 'application/x-www-form-urlencoded'){
            $json = Arrays::arrayKeyFirst($data);
            $json = preg_replace('/_(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/i', ' ', $json);
            $data = json_decode($json, true);

            if (empty($data)){
                return false;
            }

            foreach($data as $k => $v){
                if (is_string($v)){
                    $data[$k] = str_replace('_', ' ', $v);
                }
            }
        }

        return $data;
    }
    
    function setParams($params){
        static::$params = $params;
        return static::getInstance();
    }

    function headers(){
        return static::$headers;
    }

    function header(string $key){
        return static::$headers[strtolower($key)] ?? null;
    }

    // alias
    function getHeader(string $key){
        return $this->header($key);
    }

    function shiftHeader(string $key){
        $key = strtolower($key);

        $out = static::$headers[$key] ?? null;
        unset(static::$headers[$key]);

        return $out;
    }

    function getAuth(){
        return static::$headers['authorization'] ?? null;
    }

    function hasAuth(){
        return $this->getAuth() != null; 
    }

    function getApiKey(){
        return  static::$headers['x-api-key'] ?? 
                $this->shiftQuery('api_key') ??                
                null;
    }

    function hasApiKey(){
        return $this->getApiKey() != null; 
    }

    function getTenantId(){
        return  
            $this->shiftQuery('tenantid') ??
            static::$headers['x-tenant-id'] ??             
            null;
    }

    function hasTenantId(){
        return $this->getTenantId() !== null; 
    }

    function authMethod(){
        if ($this->hasApiKey()){
            return 'API_KEY';
        }elseif ($this->hasAuth()){
            return 'JWT';
        }
    }

    /*  
        https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept-Encoding
    */
    function acceptEncoding() : ?string {
        if (static::$accept_encoding){
            return static::$accept_encoding;
        }

        return static::shiftHeader('Accept-Encoding');
    }

    function gzip(){
        return in_array('gzip', explode(',', static::acceptEncoding() ?? ''));
    }

    function deflate(){
        return in_array('deflate', explode(',', static::acceptEncoding() ?? ''));
    }

    function getQuery(string $key = null)
    {
        if ($key == null)
            return static::$query_arr;
        else 
             return static::$query_arr[$key];   
    }    

    // getter destructivo sobre $query_arr
    function shiftQuery($key, $default_value = null)
    {
        static $arr = [];

        if (isset($arr[$key])){
            return $arr[$key];
        }

        if (isset(static::$query_arr[$key])){
            $out = static::$query_arr[$key];
            unset(static::$query_arr[$key]);
            $arr[$key] = $out;
        } else {
            $out = $default_value;
        }

        return $out;
    }

    function getParam($index){
        return static::$params[$index];
    } 

    function getParams(){
        return static::$params;
    } 

    function getCode(){
        return http_response_code();
    }

    static function ip(){
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    }

    static function user_agent(){
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /* Arrayable Interface */ 

    function toArray(){
        return static::$params;
    }

}