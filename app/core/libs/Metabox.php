<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\interfaces\MetaboxType;

/*
    @author Pablo Bozzolo <boctulus@gmail.com>

    Al constructor o a setMetaAtts() pasar un array con los nombres de los atributos.

    # Uso basico

    Ej:

        $mt = new Metabox([
            'Att name 1',
            'Att name 2',
        ]);
    
    O si es solo uno...

    Ej:

        $mt = new Metabox( [
            'explanation'
        ], 'sfwd-question');

    # Se puede limitar la aparicion del Metabox a determina "screen"(que se corresponde a un "post_type")

    Ej:

        $mt = new Metabox( [
            ['explanation', 'Explicacion']
        ], 'sfwd-question');

    O enviando arrays asociativos

        $mt = new Metabox( [
            ['explanation' => 'Explicacion']
        ], 'sfwd-question');


    # Callback

    Tambien es posible setear un callback para cada metabox.

    Ej:
    
        $atts = [
            'Precio TecnoGlobal',
            'Ganancia %'
        ];

        $mt = new Metabox($atts);

        $mt->setCallback('Ganancia %', function($pid, $meta_key, $meta_value, &$ganancia){
            $price = Posts::getMeta($pid, 'Precio TecnoGlobal');
            $price = $price * (1 + 0.01* $ganancia);

            posts::updatePrice($pid, $price);
        });

    Otro ejemplo:


        $mt->setCallback('students_allowed_to_enroll' , function($pid, $meta_key, $meta_value){
                Logger::log("CURSO PID=$pid < $meta_key > con valor $meta_value");
        });



    # Read-only

    Se pueden setear campos como "read-only"

    Ej:
    
        $mt = new Metabox( [
            'Precio TecnoGlobal',
            'Ganancia %'
        ]);

        $mt->setCallback('Ganancia %', function($pid, $meta_id, &$ganancia){
            $price = posts::getMeta($pid, 'Precio TecnoGlobal');
            $price = $price * Quotes::dollar();
            $price = $price * (1 + 0.01* $ganancia);

            posts::updatePrice($pid, $price);
        });

        $mt->setReadOnly([
            'Precio TecnoGlobal'
        ]);

    # Tipo de Metabox

    Ej:

    $mt = new Metabox( [
            ['students_allowed_to_enroll' => 'Usuarios admitidos (correos)']
    ], 'courses', 'AREA');

    Actualmente se soportan (TEXT y AREA)
*/

class Metabox
{
    protected $meta_atts    = [];
    protected $callbacks    = [];
    protected $element_atts = [];

    // 'post', 'page', .., 'post',... array()
    protected $screen       = null;
    protected $default_type = MetaboxType::TEXT; // Default metabox type

    function __construct(Array $meta_atts = [], $screen = null, $default_type = 'TEXT')
    {
        $this->setMetaAtts($meta_atts);
        $this->setScreen($screen);
        $this->setDefaultType($default_type);

        add_action('add_meta_boxes', [$this, 'post_meta_box']);
        add_action('save_post', [$this, 'save_post_meta_box_data']);
    }

    // Element type (TEXT, TEXTAREA,...)
    function setDefaultType($type)
    {
        // Check if the provided type is one of the supported types
        if (!in_array($type, [MetaboxType::TEXT, MetaboxType::AREA])) {
            throw new \InvalidArgumentException('Invalid metabox type');
        }

        $this->default_type = $type;
        return $this;
    }

    function setMetaAtts(Array $meta_atts){
        $this->meta_atts = $meta_atts;
        return $this;
    }

    static function set($pid, $meta_key, $dato, bool $sanitize = true){
        return Posts::setMeta($pid, $meta_key, $dato, $sanitize);
    }

    // Las metakeys son sensibles a mayusculas !!!
    static function get($pid, $meta_key){
        return Posts::getMeta($pid, $meta_key);
    }

    /*
        Ej:

        'My campo',

        [
            'readonly' => 'readonly'
        ]
    */
    function setElementAtts($field, Array $atts){        
        $meta_key = $field;

        if (!isset($this->element_atts[$meta_key])){
            $this->element_atts[$meta_key] = [];
        }

        foreach ($atts as $at => $val){
            $this->element_atts[$meta_key][$at] = $val;
        }
    }   

    /*
        Idealmente debe desactivar el setter correspondiente
        para que no se pueda hackaer desde el frontend
    */
    function setReadOnly($fields){
        foreach($fields as $field){
            $this->setElementAtts($field, [
                'readonly' => 'readonly'
            ]);
        }
    }

    function setScreen($screen){
        $this->screen = $screen;
        return $this;
    }

    function setCallback($meta_key, callable $callback){
        $this->callbacks[$meta_key] = $callback;
    }

    // Puede ser protected?
    function post_meta_box($screen = null) {
        $screen = $screen ?? $this->screen;
        
        foreach ($this->meta_atts as $meta){
            if (is_array($meta)){  /// <---- enviar siempre como array de arrays
                if (is_array($meta)) {
                    $meta_id    = isset($meta[0]) ? $meta[0] : array_key_first($meta);
                    $meta_title = isset($meta[1]) ? $meta[1] : $meta[$meta_id];
                    $meta_type  = isset($meta[2]) ? $meta[2] : $this->default_type;
                } else {
                    $meta_id    = $meta;
                    $meta_title = $meta;
                    $meta_type  = $this->default_type;
                }           
            } else {
                $meta_id    = $meta;
                $meta_title = $meta;
                $meta_type  = $this->default_type;
            }

            $atts = '';

            $meta_callback = function ($post) use ($meta_id, $meta_title, $meta_type, $atts) {
                // Add a nonce field so we can check for it later.
                wp_nonce_field( 'post_nonce', 'post_nonce' );
                
                $value = get_post_meta($post->ID, $meta_id, true);

                // Mostrar en lineas separadas
                $value         = preg_replace('/\s+/', "\r\n", $value);
                $textarea_rows = 4;
                
                $value = esc_attr($value);
        
                // Usar HTML helper idealmente
                switch ($meta_type) {
                    case 'AREA':
                        echo "<textarea style=\"width:100%\" id=\"$meta_id\" rows=\"$textarea_rows\" name=\"$meta_title\" $atts>$value</textarea>";
                        break;
                    default: // TEXT
                        echo "<input type=\"text\" style=\"width:100%\" id=\"$meta_id\" name=\"$meta_title\" value=\"$value\" $atts>";
                        break;
                }
            };
    
            add_meta_box(
                $meta_id,
                $meta_title,
                $meta_callback,
                $screen
            );    
        }

        // exit;
    }    
    
    /**
     * When the post is saved, saves our custom data.
     *
     * @param int $pid
     */
    function save_post_meta_box_data( $pid) {
        // dd($_POST, 'POST'); exit;

        // Check if our nonce is set.
        if ( ! isset( $_POST['post_nonce'] ) ) {
            return;
        }
    
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $_POST['post_nonce'], 'post_nonce' ) ) {
            return;
        }
    
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
    
        // Check the user's permissions.
        if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {    
            if ( ! current_user_can( 'edit_page', $pid ) ) {
                return;
            }    
        }
        else {
            if ( ! current_user_can( 'edit_post', $pid ) ) {
                return;
            }
        }
    
        /* OK, it's safe for us to save the data now. */
    
        foreach ($this->meta_atts as $meta){
            if (is_array($meta)){  /// <---- enviar siempre como array de arrays
                if (isset($meta[0])){
                    $meta_id    = $meta[0];
                    $meta_title = $meta[1];
                } else {
                    $meta_id    = array_key_first($meta);
                    $meta_title = $meta[$meta_id];
                }
            } else {    
                $meta_id    = $meta;
                $meta_title = $meta;
            }

            $meta_title = str_replace(' ', '_', $meta_title);

            if (isset( $_POST[$meta_title])) {
                $data = sanitize_text_field( $_POST[$meta_title] );
                //dd($data, $meta_id);

                if (isset($this->callbacks[$meta_id])){
                    $cb = $this->callbacks[$meta_id];
                    $cb($pid, $meta_id, $data);
                }

                update_post_meta( $pid, $meta_id, $data ); 
            }
        }
    }
}

