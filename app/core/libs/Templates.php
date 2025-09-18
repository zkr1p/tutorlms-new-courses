<?php 

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Strings;

// Theme
class Templates
{
    const THEME_DIR = ABSPATH .  'wp-content' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR;
    
    static $default_themes = 
    array(
		'classic'           => 'WordPress Classic',
		'default'           => 'WordPress Default',
		'twentyten'         => 'Twenty Ten',
		'twentyeleven'      => 'Twenty Eleven',
		'twentytwelve'      => 'Twenty Twelve',
		'twentythirteen'    => 'Twenty Thirteen',
		'twentyfourteen'    => 'Twenty Fourteen',
		'twentyfifteen'     => 'Twenty Fifteen',
		'twentysixteen'     => 'Twenty Sixteen',
		'twentyseventeen'   => 'Twenty Seventeen',
		'twentynineteen'    => 'Twenty Nineteen',
		'twentytwenty'      => 'Twenty Twenty',
		'twentytwentyone'   => 'Twenty Twenty-One',
		'twentytwentytwo'   => 'Twenty Twenty-Two',
		'twentytwentythree' => 'Twenty Twenty-Three',
	);

    /*
        Verificar si es un tema

        Intenta determinarlo buscando archivos estandar dentro de la carpeta
    */
    static function isTheme($path){
        $path = Strings::trimTrailingSlash($path);

        if (!file_exists($path . DIRECTORY_SEPARATOR . 'style.css')){
            return false;
        }
        
        if (!file_exists($path . DIRECTORY_SEPARATOR . 'functions.php')){
            return false;
        }

        return true;
    }

    /*
        Tocaria revisar los metadados dentro de style.css para estar mas seguro
    */
    static function isChild($path){
        if (!static::isTheme($path)){
            throw new \Exception("It's not even a Theme");
        }

        if (file_exists($path . DIRECTORY_SEPARATOR . 'index.php')){
            return false;
        }

        return true;
    }
    
    static function isValid($path){    
        return static::isTheme($path);
    }

    // helper
    static function getFolder($path){
        $path = rtrim(Files::convertSlashes($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return Strings::lastSegment(Strings::before($path, DIRECTORY_SEPARATOR . 'style.css'), DIRECTORY_SEPARATOR);
    }

    /*
        Ej:

        Templates::getMeta('D:\tmp\.wp_downloads\2970\content\eduma')

        o

        Templates::getMeta('twentytwentythree')

        Devuelve algo como,...

        Array
        (
            [Theme Name] => Eduma
            [Theme URI] => https://eduma.thimpress.com/
            [Description] => Premium Online LMS & Education WordPress Theme.
            [Version] => 5.2.3
            [Author] => ThimPress
            [Author URI] => https://thimpress.com
            [Text Domain] => eduma
            [Template] =>
            [Tags] => two-columns, three-columns, left-sidebar, right-sidebar, custom-background, custom-header, custom-menu, editor-style, post-formats, rtl-language-support, sticky-post, theme-options, translation-ready, accessibility-ready
            [Status] =>
        )

    */
    static function getMeta($path)
    {
        if (!Strings::containsAny([Files::LINUX_DIR_SLASH, Files::WIN_DIR_SLASH], $path)){
            $path = static::THEME_DIR . $path;
        }
    
        if (!Strings::endsWith('style.css', $path)){
            $path = rtrim(Files::convertSlashes($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'style.css';
        }
        
        $folder = static::getFolder($path);
        
        $attr = [
            'Theme Name',
            'Theme URI',
            'Description',           
            'Version',          
            'Author',
            'Author URI',
            'Text Domain',
            'Tags'
        ];

        $_data = get_file_data($path, $attr);

        $data = [];
        foreach ($_data as $ix => $datum){
            $data[$attr[$ix]] = $datum;
        }

        $data['Folder'] = $folder;

        return $data;
    }

    /*
        Lista los themes
    */
    static function list(bool $active = false){
        $themes = wp_get_themes();
        $theme_names = array_keys($themes);

        if (!$active) {
            return $theme_names;
        }

        foreach ($theme_names as $name){
            if (static::isActive($name)){
                return $name;
            }
        }

        return false;
    }

    /*
        Dado el nombre de un theme (o sea el nombre exacto de la carpeta),
        devuelve la ruta completa hasta el archivo principal .php del theme
    */
    static function fullPath($theme_name = null){
        if (is_null($theme_name)){
            $theme_name = static::currentName();
        }

        return static::getDirectory() . DIRECTORY_SEPARATOR . $theme_name;
    }

    /*
        @param string Nombre del theme o nombre de la carpeta (preferido)
    */
    static function isActive(string $name){
        if (isset(static::$default_themes[$name])){
            $name = static::$default_themes[$name];
        }

        $name = trim(str_replace(['-', '_'], ' ', $name));
       
        $active_theme = wp_get_theme();
        $active_theme_name = $active_theme->get('Name');
        $active_theme_name = trim(str_replace(['-', '_'], ' ', $active_theme_name));

        return strtolower($name) === strtolower($active_theme_name);
    }

    /*
        No hay funcion "deactivate" porque no tiene sentido. 
        De hecho, solo se puede switchear porque siempre debe haber un theme activo.
    */

    static function activate(string $name){
        switch_theme($name);

        return true;
    }

    /*
        Retorna tema actual
    */
    static function get(){
        return get_option('template');
    }

    /*
        Cambia temporalmente el "theme" de WordPress 

        Ejemplo de uso:

        Template::set('kadence');

        @param string $template
    */  
    static function set(string $template)
    {
        require_once (ABSPATH . WPINC . '/pluggable.php');

        add_filter( 'template', function() use ($template) {
            return $template;
        });

        add_filter( 'stylesheet', function() use ($template) {
            return $template;
        });
    }

    static function getDirectory(bool $include_trailing_slash = false){
        return str_replace('/', DIRECTORY_SEPARATOR, ABSPATH . 'wp-content/themes') . ($include_trailing_slash ? DIRECTORY_SEPARATOR : '');
    }

    /*
        @param  string|null     $name Nombre del theme
        @return int|arrray      Array con info sobre el theme o false sino lo encuentra    

        Devuelve false o un array como

        Ej:

        Array
        (
            [folder] => D:\www\woo2\wp-content\themes\kadence
            [is_active] => 1
            [version] => 1.1.18
        )
    */
    static function search($name = null, bool $full_path = true) {
        $themes = wp_get_themes();
        $active_theme = wp_get_theme();
      
        if ($name === null) {
            $theme = $active_theme;
        } else {
            foreach ($themes as $theme_slug => $theme_obj) {
                $theme_name = $theme_obj->get('Name');
                
                if (strcasecmp($theme_name, $name) === 0) {
                    $theme = $theme_obj;
                    break;
                }
            }
        }

        if (isset($theme)) {
            $theme_folder = $theme->get_stylesheet_directory();
            $is_active    = $theme->get_stylesheet() === $active_theme->get_stylesheet();
            $version      = $theme->get('Version');

            $theme_folder = str_replace('/', DIRECTORY_SEPARATOR, $theme_folder);

            if ($full_path === false){
                $theme_folder = Strings::lastSegment($theme_folder, DIRECTORY_SEPARATOR);  
            } 
            
            return array(
                'folder' => $theme_folder,
                'is_active' => $is_active,
                'version' => $version
            );
        }
      
        return false;
    }
    
    static function currentName(bool $full_info = false){
        if ($full_info){
            return static::search();
        }

        return static::get();
    }
}