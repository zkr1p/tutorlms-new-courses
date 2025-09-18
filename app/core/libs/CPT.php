<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Strings;

/*
    Custom Post Type

    @author Pablo Bozzolo <boctulus@gmail.com>

    Ver Custom Post Type creados para agencia de conduccion
*/
abstract class CPT 
{    
    protected $type;
    protected $single;
    protected $plural;
    protected $custom_fields  = [];
    protected $hidden         = [];
    protected $readonly       = [];
    protected $pluralize_cb;
    protected $computed       = []; // 


    public function __construct($single=null, $plural=null, $custom_fields=[], $readonly = [])
    {
        $this->single = $this->single ?? $single ?? ucfirst(Strings::snakeToCamel($this->type));
    
        $this->plural = $this->plural ?? $plural ?? (is_callable($this->pluralize_cb) ? $this->pluralize_cb($this->single) : $this->single .'s');

        if (!empty($custom_fields)){
            $this->custom_fields = $custom_fields;
        }

        if (!empty($readonly)) {
            $this->readonly = $readonly;
        }

        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'addTaxonomies']);
        add_action('add_meta_boxes', [$this, 'add_custom_metaboxes']);
        add_action('save_post', [$this, 'save']);

        $this->setCustomColumns();       

        add_filter("manage_{$this->type}_posts_columns", [$this, 'custom_manage_columns']);
        add_filter("manage_{$this->type}_posts_columns", [$this, 'custom_columns_head']);

        add_action("manage_{$this->type}_posts_custom_column", [$this, 'custom_columns_content'], 10, 2);

        enqueue_admin(function(){ 
            css_file('css/admin_styles_cpt.css');
        });
    }

    function setPluralize(callable $callback){
        if (!is_callable($callback)){
            throw new \InvalidArgumentException("Pluralize function is invalid");
        }
        
        $this->pluralize_cb = $callback;
    }

    function setCustomColumns(){
        if (empty($this->type)){
            wp_die("The type must be defined before to set custom columns");
        }

        // Agregado: Configuración de las columnas personalizadas
        add_filter("manage_edit-{$this->type}_columns", [$this, 'custom_columns_head']);
        add_action("manage_{$this->type}_posts_custom_column", [$this, 'custom_columns_content'], 10, 2);
    }

    function setCustomFields($custom_fields){
        $this->custom_fields = $custom_fields;
    }

    abstract function register_post_type();

    function addTaxonomies(){
       // 
    }

    public function metaboxCallback($post)
    {
        wp_nonce_field(plugin_basename(__FILE__), 'noncename');

        foreach ($this->custom_fields as $field_name => $field_data) {
            if (!isset($field_data['type'])){
                continue;
            }

            $value       = get_post_meta($post->ID, $field_name, true);
            $is_readonly = in_array($field_name, $this->readonly);
            ?>
            <p>
                <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_data['label']); ?></label>
                <?php
                if ($field_data['type'] === 'select_users') {
                    $this->render_user_select($field_name, $value, $is_readonly);
                } elseif ($field_data['type'] === 'select' && isset($field_data['options'])) {
                    $this->render_select($field_name, $field_data['options'], $value, $is_readonly);
                } else {
                    ?>
                    <input type="text" class="frm_ctrl" id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($value); ?>" <?php echo $is_readonly ? 'readonly' : ''; ?> />
                    <?php
                }
                ?>
            </p>

            <?php
        }
    }

    protected function render_select($field_name, $options, $selected, $is_readonly)
    {
        ?>
        <select name="<?php echo esc_attr($field_name); ?>" class="frm_ctrl" id="<?php echo esc_attr($field_name); ?>" <?php echo $is_readonly ? 'disabled' : ''; ?>>
            <?php
            foreach ($options as $value => $label) {
                echo '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
            }
            ?>
        </select>
        <?php
    }

    protected function render_user_select($field_name, $selected_user, $is_readonly)
    {
        // Obten los usuarios de WordPress
        $users = get_users();
        ?>
        <select name="<?php echo esc_attr($field_name); ?>" class="frm_ctrl" id="<?php echo esc_attr($field_name); ?>" <?php echo $is_readonly ? 'disabled' : ''; ?>>
            <option value="">Seleccione un usuario</option>
            <?php
            foreach ($users as $user) {
                echo '<option value="' . esc_attr($user->ID) . '" ' . selected($selected_user, $user->ID, false) . '>' . esc_html($user->display_name) . '</option>';
            }
            ?>
        </select>
        <?php
    }

    public function save($post_id)
    {
        if (!isset($_POST['noncename']) || !wp_verify_nonce($_POST['noncename'], plugin_basename(__FILE__))) {
            return $post_id;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        if ('page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return $post_id;
            }
        }

        if ($_POST['post_type'] == $this->type) {
            foreach ($this->custom_fields as $field_name => $field_data) {
                if (isset($_POST[$field_name])) {
                    $value = sanitize_text_field($_POST[$field_name]);
                    if ($field_data['type'] === 'select_users') {
                        update_post_meta($post_id, $field_name, $value);
                    } else {
                        update_post_meta($post_id, $field_name, $value);
                    }
                }
            }
        }
    }

    /*
        Custom columns
    */

    protected function hideColumns(&$columns){
        foreach ($this->hidden as $col){
            unset($columns[$col]);
        }
    }

    function custom_columns_head($columns)
    {
        foreach ($this->custom_fields as $key => $attrs){                     
            if (!in_array($key, $this->hidden)) {
                $columns[$key] = $attrs['label'];
            }          
        }

        // Oculta columnas
        $this->hideColumns($columns);

        return $columns;
    }


    /*
        El callback seria una funcion que recibe ($column)
        y deberia poder inyectarse
    */
    function custom_columns_content($column, $post_id)
    {
        $callback = function($label){
            return is_string($label) ? ucfirst($label) : $label;
        };

        // Comprueba si es una propiedad computada
        if (isset($this->computed[$column])) {
            echo $this->computed[$column]($post_id);
        } else {
            // No es una propiedad computada, procede como antes
            echo $callback(get_post_meta($post_id, $column, true));
        }
    }

    
    // Función para cambiar la etiqueta de la columna en la tabla de administración
    function custom_manage_columns($columns) {
        if (isset($this->custom_fields['title'])){
            $columns['title'] = $this->custom_fields['title']['label'];
        }

        return $columns;
    }


    /*
        Custom filters
    */

    // Devuelve valores posibles de un campo tipo "select"
    // (y quizas de radios)
    protected function getSelectOptions(string $field_name) {
        return $this->custom_fields[$field_name]['options'];
    }

    protected function filterCustomPosts($query, $field_name) {
        global $pagenow;

        if (is_admin() && $pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == $this->type && isset($_GET[$field_name.'_filter']) && $_GET[$field_name.'_filter'] != '') {
            $query->query_vars['meta_key'] = $field_name;
            $query->query_vars['meta_value'] = sanitize_text_field($_GET[$field_name.'_filter']);
        }
    }

    static function getAll($post_type = 'post', $status = 'publish', $limit = -1, $order = null){
        global $wpdb;
        
        $sql = "SELECT SQL_CALC_FOUND_ROWS  * FROM wp_posts  WHERE 1=1  AND ((wp_posts.post_type = '$post_type' AND (wp_posts.post_status = '$status')));";
    
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    static function getOne($post_type = 'post', $status = 'publish', $limit = -1, $order = null){
        global $wpdb;
        
        $sql = "SELECT SQL_CALC_FOUND_ROWS  * FROM wp_posts  WHERE 1=1  AND ((wp_posts.post_type = '$post_type' AND (wp_posts.post_status = '$status')));";
    
        return $wpdb->get_row($sql, ARRAY_A);
    }
}