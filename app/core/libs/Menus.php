<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
	@author boctulus

    Dashboard WP menus

    Implementar tambien ----------->

    add_menu_page(): Esta función se utiliza para agregar un nuevo menú principal al Dashboard de WordPress. Permite crear una nueva página de menú con su propio contenido y submenús asociados.

    remove_menu_page(): Permite eliminar un menú principal específico del Dashboard. Puede ser útil para ocultar menús que no sean necesarios o relevantes para tu sitio.

    add_dashboard_page(): Agrega una página personalizada al tablero de WordPress. Esta función te permite crear una nueva página dentro del tablero de administración con tu propio contenido.

    add_options_page(): Agrega una página de opciones al menú de configuración de WordPress. Esta función se utiliza para agregar una nueva página de opciones donde los administradores pueden configurar ajustes específicos del tema o plugin.

    add_theme_page(): Permite agregar una página personalizada al menú de apariencia de WordPress. Esta función se utiliza para agregar páginas relacionadas con la apariencia y personalización del tema actual.

    add_plugins_page(): Agrega una página personalizada al menú de plugins de WordPress. Esta función se utiliza para agregar páginas relacionadas con la gestión y configuración de plugins.


    Chequear librerias de "White Label PRO" (en lo posible de la version PRO)

    Ver tambien
    https://wordpress.stackexchange.com/a/9377/99153

  */

class Menus
{
    static protected $capability = 'manage_options';

    static function create($callback){
        add_action('admin_menu', $callback);
    }

    /*
        Crea un Menu con sub-menus

        Cada child debe tener esta estructura:

        [
            $child_title,
            $child_callback,
            $child_capability,  -- opcional --
            $child_slug         -- opcional --
        ]

        Ej:

        Menus::tree('', 'Super Menu', null, null, null, function() {
            echo 'TOP LEVEL';
        }, [
            [
                'Sub 1',
                function()
                {
                    dd('L2-1');
                }
            ],
            [
                'Sub 2',
                function()
                {
                    dd('L2-2');
                }
            ]
        ]);

    */
    static function tree($icon_url = '', $page_title = null, $menu_title = null, $capability = null, $slug = null, $callback = null, Array $children, bool $menu_as_submenu = false)
    {
        static::create(function () use ($icon_url, $page_title, $menu_title, $capability, $slug, $callback, $children, $menu_as_submenu) {
            //$slug       = 'your_unique_slug';
            $capability = $capability ?? static::$capability; 
        
            if (empty($page_title)){
                throw new \InvalidArgumentException("Page title is required");
            }
            
            if ($menu_title == null){
                $menu_title = $page_title; 
            }
    
            $menu_title = Strings::getUpTo($menu_title, null, 18);
    
            if ($slug == null){    
                $slug = Strings::toSnakeCase($menu_title);
            }
    
            // Menus::main()
            add_menu_page   (
                $page_title,
                $menu_title,
                $capability,
                $slug,
                $callback,
                $icon_url
            );

            // Para remover el menu como primera opcion (submenu) de si mismo
            if ($menu_as_submenu === false){
                add_submenu_page    (
                    $slug,
                    $page_title,
                    '',
                    $capability,
                    $slug,
                    ''
                );
            }            

            foreach ($children as $child){                
                $child_page_title = $child[0];
                $child_cb         = $child[1];
                $child_capability = $child[2] ?? null;
                $child_slug       = $child[3] ?? null;

                if (empty($child_page_title)){
                    throw new \InvalidArgumentException("Page title for submenu is required");
                }
                
                $child_menu_title = $child_page_title; 
                $child_menu_title = Strings::getUpTo($child_menu_title, null, 18);
        
                if ($child_slug == null){    
                    $child_slug = Strings::toSnakeCase($child_menu_title);
                }
        
                add_submenu_page(
                    $slug,
                    $child_page_title,
                    $child_menu_title,
                    $child_capability ?? static::$capability,
                    $child_slug,
                    $child_cb
                );
            }
        
        });
    }

    // a nivel de root
    static function main($callback = '', $icon_url = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        if (empty($page_title)){
            throw new \InvalidArgumentException("Page title is required");
        }
        
        if ($menu_title == null){
            $menu_title = $page_title; 
        }

        $menu_title = Strings::getUpTo($menu_title, null, 18);

        if ($menu_slug == null){    
            $menu_slug = Strings::toSnakeCase($menu_title);
        }
        
        add_menu_page(
            $page_title,
            $menu_title,
            $capability ?? static::$capability,
            $menu_slug,
            $callback,
            $icon_url,
            $position
        );    
    }

    // alias de main (menu)
    static function root($callback = '', $icon_url = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        return static::main($callback, $icon_url, $page_title, $menu_title, $menu_slug, $capability, $menu_slug, $position);
    }

    static function submenu($callback = '', $parent_slug = 'index.php', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        if (empty($page_title)){
            throw new \InvalidArgumentException("Page title is required");
        }
        
        if ($menu_title == null){
            $menu_title = $page_title; 
        }

        $menu_title = Strings::getUpTo($menu_title, null, 18);

        if ($menu_slug == null){    
            $menu_slug = Strings::toSnakeCase($menu_title);
        }
        
        add_submenu_page(
            $parent_slug,
            $page_title,
            $menu_title,
            $capability ?? static::$capability,
            $menu_slug,
            $callback,
            $position
        );    
    }

    /*

    */
    static function edit($callback = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::submenu(
            $callback,
            'edit.php',
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $position
        );    
    }

    /*
        Este slug te permite agregar un submenú a la sección "Plugins" en el panel de administración de WordPress, donde puedes gestionar los plugins instalados. --ok

        Plugins > el-sub-menu
    */
    static function plugins($callback = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::submenu(
            $callback,
            'plugins.php',
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $position
        );    
    }

    /*
        Puedes usar este slug para agregar un submenú a la sección "Usuarios" en el panel de administración de WordPress, donde puedes administrar los usuarios y sus permisos
    */  
    static function usersPlugins($callback = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::submenu(
            $callback,
            'users.php',
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $position
        );    
    }

    /*
        Utiliza este slug para agregar un submenú a la sección "Herramientas" en el panel de administración de WordPress, donde puedes acceder a diversas utilidades y configuraciones.
        
        > Tools --ok
    */
    static function tools( $callback = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::submenu(
            $callback,
            'tools.php',
            $page_title,
            $menu_title,
            $capability ?? 'manage_options',
            $menu_slug,
            $position
        );    
    }

    /*
        Este slug te permite agregar un submenú a la sección "Ajustes" en el panel de administración de WordPress, donde puedes configurar opciones generales de tu sitio.

        > Settings ("Ajustes")  --ok    

        Ej:

        if ( is_admin() ) {
            add_action('admin_menu', function () {
                
                // Settings > MyStore
                Menus::options(
                    function() {
                        echo '<h1>Hola Mundo</h1>';
                    },
                    'MyStore Settings',
                    'MyStore',
                    null,
                    'mystore-settings'
                );    

            });
        }
    */
    static function options($callback = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::submenu(
            $callback,
            'options-general.php',
            $page_title,
            $menu_title,
            $capability ?? 'manage_options',
            $menu_slug,
            $position
        );    
    }

    /*
        Puedes usar este slug para agregar un submenú a la sección "Páginas" en el panel de administración de WordPress, donde puedes administrar las páginas.
    */
    static function CPT($callback = '', $post_type = 'post', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::submenu(
            $callback,
            'edit.php?post_type=' . $post_type,
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $position
        );    
    }

    /*
        Puedes usar este slug para agregar un submenú a la sección "Páginas" en el panel de administración de WordPress, donde puedes administrar las páginas.
    */
    static function page($callback = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::CPT(
            $callback,
            'page',
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $position
        );    
    }

    /*
        Utiliza este slug para agregar un submenú a la sección "Apariencia" en el panel de administración de WordPress, donde puedes administrar los temas y personalizar el diseño.
    */
    static function themes($callback = '', $page_title = null, $menu_title = null, $capability = null, $menu_slug = null, $position = null){
        static::submenu(
            $callback,
            'themes.php',
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $position
        );    
    }
}
