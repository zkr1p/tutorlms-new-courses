<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\Constants;

/*
	@author boctulus
*/

if ( ! function_exists( 'get_plugins' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

class Plugins
{   
    const PLUGIN_DIR = ABSPATH .  'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;

    /*
        https://wordpress.stackexchange.com/a/286761/99153

        Devuelve una lista como esta:

        [
            [forminator/forminator.php] => Array
            (
                [Name] => Forminator
                [PluginURI] => https://wpmudev.com/project/forminator/
                [Version] => 1.22.1
                [Description] => Capture user information (as detailed as you like), engage users with interactive polls that show real-time results and graphs, “no wrong answer” Facebook-style quizzes and knowledge tests.
                [Author] => WPMU DEV
                [AuthorURI] => https://wpmudev.com
                [TextDomain] => forminator
                [DomainPath] => /languages/
                [Network] =>
                [RequiresWP] =>
                [RequiresPHP] =>
                [UpdateURI] =>
                [Title] => Forminator
                [AuthorName] => WPMU DEV
            ),

            // ...
        ]
    */
    static function list(bool $active = true){
        if (!$active){
            return get_plugins();
        }   
        else {           
            $active_plugins = get_option('active_plugins');
            $all_plugins = get_plugins();
            $activated_plugins = [];

            foreach ($active_plugins as $plugin){           
                if(isset($all_plugins[$plugin])){
                    array_push($activated_plugins, $all_plugins[$plugin]);
                }           
            }

            return $activated_plugins;
        }
    }
    
    /*
    * Verificar si el archivo contiene los metadatos del plugin
    */
    static protected function hasPluginMetadata($file_path) {
        $plugin_data = get_file_data($file_path, array('Plugin Name'));
        $plugin_name = $plugin_data[0];

        // Verificar si el archivo contiene la clave 'Plugin Name' en los metadatos
        return !empty($plugin_name);
    }

    /*
        Retorna archivo .php que contiene la metadata del plugin

        Ej:

        Plugins::getIndexFile('wp-asset-clean-up-pro')

    */
    static function getIndexFile($plugin_folder_path) {
        if (!Files::isAbsolutePath($plugin_folder_path)) {
            $plugin_folder_path = static::PLUGIN_DIR . $plugin_folder_path;
        }

        if (!is_dir($plugin_folder_path)){
            return false;
        }

        // Obtener todos los archivos en el directorio del plugin
        $plugin_files = scandir($plugin_folder_path);

        // Buscar el archivo .php que contiene los metadatos del plugin
        foreach ($plugin_files as $file) {
            if (Strings::endsWith('.php', $file)) {
                $file_path = $plugin_folder_path . '/' . $file;

                // Verificar si el archivo contiene los metadatos del plugin
                if (static::hasPluginMetadata($file_path)) {
                    return Files::normalize($file_path);
                }
            }
        }

        // Si no se encuentra un archivo con los metadatos del plugin, retornar false
        return false;
    }

    static function getDirectory(bool $include_trailing_slash = false){
        return str_replace('/', DIRECTORY_SEPARATOR, ABSPATH . 'wp-content/plugins') . ($include_trailing_slash ? DIRECTORY_SEPARATOR : '');
    }

    static function fullPath(string $plugin_name, bool $include_index_file = false){
        if ($include_index_file === false){
            $dir = static::PLUGIN_DIR . $plugin_name;
            return is_dir($dir) ? $dir : false;
        }

        return static::getIndexFile($plugin_name);
    }

    // Verificar si es un tema
    static function isPlugin($path){
        // Si es un theme, no puede ser un plugin
        if (Templates::isValid($path)){
            return false;
        }

        return static::getIndexFile($path) !== false;
    }
    
    static function isValid($path){    
        return static::isPlugin($path);
    }

    /* 
        Helper

        Dada una ruta, devuelve el directorio del plugin

        Ej:

            Plugins::getFolder('D:\tmp\.wp_downloads\2729\content\ultimate-elementor')

        devuelve ...

            ultimate-elementor
    */
    static function getFolder($path){
        $path = Files::convertSlashes($path);

        if (Strings::endsWith('.php', $path)){
            $path = Strings::beforeLast($path, DIRECTORY_SEPARATOR);
        }
        
        return Strings::lastSegment(
            rtrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR
        );
    }

    /*
        Uso

        Ej:

            Plugins::getMeta('D:\www\woo2\wp-content\plugins\wp-asset-clean-up-pro\wpacu.php')

        o si el plugin se encuentra dentro de wp-content/plugins

            Plugins::getMeta('wp-asset-clean-up-pro\wpacu.php')

        Devuelve algo como:

            Array
                [Plugin Name] => Asset CleanUp Pro: Page Speed Booster
                [Plugin URI] => https://www.gabelivan.com/items/wp-asset-cleanup-pro/
                [Description] => Prevent Chosen Scripts & Styles from loading to reduce HTTP Requests and get faster page load | Add "async" & "defer" attributes to loaded JS | Combine/Minify CSS/JS files
                [Version] => 1.2.3.2
                [Requires at least] => 4.5
                [Requires PHP] => 5.6                
                [Author] => Gabe Livan
                [Author URI] => http://www.gabelivan.com/
                [Text Domain] => wp-asset-clean-up
                [Domain Path] => /languages
                [Network] =>
                [Update URI] =>
            )
    */
    static function getMeta($path = null){
        if ($path === null){
            $path = static::path();
        };

        $path = Files::normalize($path);

        if (!Strings::endsWith('.php', $path)){
            $path  = static::getIndexFile($path);
        } else {
            if (!Files::isAbsolutePath($path)){
                $path = static::PLUGIN_DIR . $path;
            }
        }
        
        $attr = [
            'Plugin Name',
            'Plugin URI',
            'Description',           
            'Version',
            'Requires at least',
            'Requires PHP',            
            'Author',
            'Author URI',
            'Text Domain',
            'Domain Path',
            'Network',
            'Update URI'
        ];

        $plugin_data = get_file_data($path, $attr);

        $data = [];
        foreach ($plugin_data as $ix => $datum){
            $data[$attr[$ix]] = $datum;
        }

        //$data['Index']  = $path;
        $data['Folder'] = static::getFolder($path);

        return $data;
    }

    /*
        Retorna si un plugin esta activo

        Funciona igualmente con la carpeta del plugin o la ruta absoluta

        Ej:

        Plugins::isActive('D:\www\woo2\wp-content\plugins\woocommerce')
        Plugins::isActive('wp-asset-clean-up-pro')

    */
    static function isActive(string $name)
    {
        if (!Strings::endsWith('.php', $name)){
            $path  = static::getIndexFile($name);
        } else {
            if (!Files::isAbsolutePath($name)){
                $path = static::PLUGIN_DIR . $name;
            }
        }

        $path = Files::removePath($path, static::PLUGIN_DIR);
        $path = Files::convertSlashes($path, Files::LINUX_DIR_SLASH);

        return is_plugin_active($path);
    }

    /*
        $path Array|string
    */
    static function deactivate(string $name){
        $full_path = static::fullPath($name);

        if (empty($full_path)){
            return false;
        }

        deactivate_plugins($full_path); 
    }

    /*
        $path Array|string
    */
    static function activate(string $name){
        $full_path = static::fullPath($name);

        if (empty($full_path)){
            return false;
        }

        activate_plugins($full_path); 
    }

    /*
        @param  string          $name Nombre del plugin (debe coincidir exactamente)
        @return int|arrray      Array con info sobre el plugin o false sino lo encuentra    

        Devuelve false o un array como

        Ej:

        Array
        (
            [folder] => woocommerce
            [is_active] => 1
            [version] => 6.5.1
        )

        Esta funcion tiene la "limitante" de que el nombre de la carpeta debe coincidir

    */
    static function search($name = null, bool $full_path = true) {
        if ($name === null){
            $name = static::currentName();
        }

        $plugin_dir = WP_PLUGIN_DIR;
        $plugins    = get_plugins();
      
        foreach ($plugins as $plugin_path => $plugin_info) {
            $plugin_name = $plugin_info['Name'];
            
            if (strcasecmp($plugin_name, $name) === 0) {
                $plugin_folder = dirname($plugin_path);
                $plugin_folder_path = str_replace($plugin_dir, '', $plugin_folder);
                $is_active = is_plugin_active($plugin_path);
                $version = $plugin_info['Version'];
                
                if ($full_path){
                    $plugin_folder_path = ($full_path ? $plugin_dir . '/' : '') . $plugin_folder_path;
                    $plugin_folder_path = str_replace('/', DIRECTORY_SEPARATOR, $plugin_folder_path);
                }

                return array(
                    'folder'    => $plugin_folder_path,
                    'is_active' => $is_active,
                    'version'   => $version
                );
            }
        }
      
        return false;
    }

    /*
        Path del propio plugin
    */
    static function path(){
        $path = realpath(__DIR__ . '/../..');
        return $path;
    } 

    static function currentName(){
        $path = static::path();
        $_pth = explode(DIRECTORY_SEPARATOR, $path);
        $name = $_pth[count($_pth)-1];
 
        return $name;
    } 

    static function name(){
        return static::currentName();
    } 

    static function getVersion($plugin_name =  null){
        $path = is_null($plugin_name) ? Constants::ROOT_PATH : static::fullPath($plugin_name);

        return Plugins::getMeta($path)['Version'] ?? '0.0.1';
    }

    static function getTextDomain($plugin_name =  null){
        $path = is_null($plugin_name) ? Constants::ROOT_PATH : static::fullPath($plugin_name);

        return Plugins::getMeta($path)['Text Domain'] ?? '0.0.1';
    }
}