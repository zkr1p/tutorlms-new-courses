<?php 

namespace boctulus\TutorNewCourses\core\libs;

/*
    Hooks 

    "admin_notices": esta acción se utiliza para mostrar notificaciones generales en el panel de administración.
        
    "all_admin_notices": Esta acción se ejecuta después de todas las notificaciones. Puedes usarla para mostrar notificaciones generales que deben aparecer después de otras notificaciones.

    "network_admin_notices": Similar a admin_notices, pero para la red multisitio de WordPress. Se utiliza para mostrar notificaciones en el panel de administración de la red.

    "user_admin_notices": Esta acción se utiliza para mostrar notificaciones en la página de edición de perfiles de usuario.

    "admin_enqueue_scripts": No es una acción de notificación directa, pero se utiliza para encolar estilos y scripts en el panel de administración. Puedes usarlo para agregar estilos personalizados a tus notificaciones.

    "in_admin_header": Se utiliza para mostrar contenido en la parte superior del encabezado del panel de administración. Puede usarse para contenido persistente, como un banner o un aviso.

    "admin_footer": Esta acción se utiliza para mostrar contenido en el pie de página del panel de administración.

    "in_admin_footer": Similar a in_admin_header, pero para el pie de página del panel de administración.

    "admin_head": Se utiliza para agregar contenido en la sección <head> del panel de administración. Puede ser útil para enlazar estilos o scripts adicionales.

    "admin_print_styles": Similar a admin_enqueue_scripts, se utiliza para encolar estilos en el panel de administración. Puedes utilizarlo para agregar estilos específicos a tus notificaciones.

*/
class WPNotices
{
    const SEVERITY_INFO    = 'info';
    const SEVERITY_SUCCESS = 'success';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR   = 'error';

    // Oculta notices
    static function hide(){
        add_action('admin_init', function(){
            remove_all_actions('admin_notices');
        }); 
    }
    
    /*
        En caso de "cerrar" la notificacion, el mensaje no se volvera a mostrar
        (en caso de seguir siendo enviado) lo que dure la cookie

        @param string $msg
        @param string $severity  'info', 'success', 'warning', 'error' o el color RGB
        @param bool   $dismissible  
    */
    static function send($msg, $severity, bool $dismissible = true)
    {    
        $style = '';
        if (Strings::startsWith('#', $severity)){
            $style = 'style="border-left-color:'.$severity.'"';
        } else {
            if (!in_array($severity, ['info', 'success', 'warning', 'error'])){
                throw new \InvalidArgumentException("Severity value can only be 'success', 'warning' or 'error'");
            }
        }
    
        if (empty($msg)){
            Logger::log("admin_notice() con mensaje *vacio* de severidad '$severity'");
            return;
        }
    
        $id      = md5("$severity;$msg");
        $ck_name = 'dismissed_notice_' . $id;

        require_once(ABSPATH . 'wp-admin/includes/screen.php');
    
        add_action('admin_notices', function() use ($msg, $severity, $dismissible, $id, $ck_name, $style){   
            $extras  = $dismissible ? 'is-dismissible' : '';      
            $classes = trim("notice notice-$severity $extras");
            
            $notice = <<<EOT
                <div class="$classes" $style id="$id">
                    <p>$msg</p>
                </div>
            EOT;
    
            echo $notice;
            ?>
    
            <script>
            (function($) {
                $(document).on('click', '#' + '<?= $id ?>', function() {
                    var date = new Date();
                    date.setTime(date.getTime() + (86400 * 1000)); // Caducidad de la cookie: 1 dia
                    document.cookie = '<?= $ck_name ?>' + '=1; expires=' + date.toUTCString() + '; path=/';
                });
            })(jQuery);
            </script>
    
            <?php
        }, 10, 2);
    
        add_action('admin_enqueue_scripts', function() {
            $screen = get_current_screen();
            if ($screen->id === 'dashboard') {
                wp_enqueue_script('jquery');
            }
        });
        
        add_action('admin_init', function() use ($id, $ck_name) {
            if (isset($_COOKIE[$ck_name]) && $_COOKIE[$ck_name] == 1) {
                add_action('admin_enqueue_scripts', function() use ($id) {
                    wp_add_inline_script('jquery', "jQuery(document).ready(function($) { $(\"#$id\").remove(); });");
                });
            }
        });
    }
}