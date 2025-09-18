<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\Constants;

/*
    @author boctulus
*/

class Posts
{
    static $post_type = 'post';
    static $cat_metakey = 'category';

    // 6-ene-24
    static function getByID(int $term_id)
    {
        return get_term_by('id', $term_id, static::$cat_metakey);
    }

    static function getRandom($qty = 1, bool $ret_pid = false){
        global $wpdb;

        if (empty($qty) || $qty < 0){
            throw new \InvalidArgumentException("Quantity can not be 0 or null or negative");
        }

        $post_type = static::$post_type;
        $sql       = "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type IN ('{$post_type}') ORDER BY RAND() LIMIT $qty";

        $res = $wpdb->get_results($sql, ARRAY_A);

        return $ret_pid ? array_column($res, 'ID') : $res;
    }

    /*
        Devuelve la lista de post_type(s)
    */
    static function getPostTypes(){
        $prefix = tb_prefix();

        DB::getConnection();

        return DB::select("SELECT DISTINCT post_type FROM {$prefix}posts;");
    }

    static function create($title, $content, $status = 'publish', $post_type = null)
    {
        $status = $status ?? 'publish';

        $data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_type' => $post_type
        );

        // Insertar el nuevo evento
        $post_id = wp_insert_post($data);

        // Verificar si la inserción fue exitosa
        if (is_wp_error($post_id)) {
            Logger::logError("Error al crear CPT de tipo '$post_type'. Detalle: " . $post_id->get_error_message());
        }

        return $post_id;
    }

    /*
        Ej:

        $site_url = 'xxx bla bla';

        dd(Posts::exists([
            '_site_url' => $site_url
        ], [
            'category' => 'active'
        ], 'publish', 'wsevent'));

    */
    static function exists($metas = null, $taxonomy = null, $post_status = 'publish', $post_type = null): bool
    {
        $args = array(
            'post_type' => $post_type,
            'post_status' => $post_status,
            'posts_per_page' => 1,
        );

        if ($taxonomy !== null) {
            $args['tax_query'] = array(
                'relation' => 'AND',
                array(
                    'taxonomy' => key($taxonomy),
                    'field' => 'slug',
                    'terms' => current($taxonomy),
                ),
            );
        }

        if ($metas !== null && is_array($metas)) {
            $meta_query = array('relation' => 'AND');

            foreach ($metas as $key => $value) {
                $meta_query[] = array(
                    'key' => $key,
                    'value' => $value,
                    'compare' => '=',
                );
                $meta_query[] = array(
                    'key' => $key,
                    'value' => $value,
                    'compare' => 'BINARY',
                );
            }

            if (isset($args['meta_query'])) {
                $args['meta_query']['relation'] = 'AND';
                $args['meta_query'][] = $meta_query;
            } else {
                $args['meta_query'] = $meta_query;
            }
        }

        $query = new \WP_Query($args);

        return $query->have_posts();
    }

    static function deleteByID($post_id, bool $permanent = false){
        return wp_delete_post($post_id, $permanent);
    }

    static function deleteByIDOrFail($post_id, bool $permanent = false) {
        if (!is_numeric($post_id) || $post_id <= 0) {
            throw new \InvalidArgumentException("Post ID not found");
        }

        // Attempt to delete the post
        $result = wp_delete_post($post_id, $permanent);

        if (!$result){
            throw new \Exception("Post was unable to be deleted");
        }
    }


    static function getCategoryNames($pid){
        return wp_get_post_terms( $pid, static::$cat_metakey, array('fields' => 'names') );
    }
    

    static function setTaxonomy($pid, $category_name, $cat_slug)
    {
        wp_set_object_terms($pid, $category_name, $cat_slug);
    }

    /*
        Actualiza categoria
    */
    static function setCategory($pid, $category_name, $cat_slug = null)
    {
        $cat_slug = $cat_slug ?? static::$cat_metakey;

        wp_set_object_terms($pid, $category_name, $cat_slug);
    }

    
        /*
        Sobre-escribe cualquier categoria previa
    */
    static function setCategoriesByNames($pid, array $category_names){
        foreach ($category_names as $cat){
            wp_set_object_terms($pid, $cat, static::$cat_metakey);
        }
    }

    /*
        Agrega nuevas categorias por nombre

        No las asigna a ningun post
    */
    static function addCategoriesByNames($pid, Array $categos){
        $current_categos = static::getCategoryNames($pid);

        if (!empty($categos)){
            $current_categos = array_diff($current_categos, ['Uncategorized']);
        }

        $categos = array_merge($current_categos, $categos);

        static::setCategoriesByNames($pid, $categos);
    }

    static function getPostType($post_id)
    {
        // Obtener el objeto del post usando el ID
        $post = get_post($post_id);

        // Comprobar si se encontró el post y obtener el post_type
        if ($post) {
            $post_type = $post->post_type;
            return $post_type;
        } else {
            return false; // Si el post no existe, puedes manejarlo de acuerdo a tus necesidades.
        }
    }

    /*
        Puede que no sea la mejor forma porque se salta el mecanismo de cache
    */
    static function setStatus($pid, $status, bool $validate = true)
    {
        global $wpdb;

        if ($pid == null) {
            throw new \InvalidArgumentException("PID can not be null");
        }

        if ($validate && !in_array($status, ['publish', 'pending', 'draft', 'trash', 'private'])) {
            throw new \InvalidArgumentException("Estado '$status' invalido.");
        }

        $sql = "UPDATE `{$wpdb->prefix}posts` SET `post_status` = '$status' WHERE `{$wpdb->prefix}posts`.`ID` = $pid;";
        return $wpdb->get_results($sql);
    }

    // alias
    static function updateStatus($pid, $status)
    {
        return static::setStatus($pid, $status);
    }

    static function setAsDraft($pid)
    {
        static::setStatus($pid, 'draft');
    }

    static function setAsPublish($pid)
    {
        static::setStatus($pid, 'publish');
    }

    static function setAsPrivate($pid)
    {
        static::setStatus($pid, 'private');
    }

    static function trash($pid)
    {
        return static::setStatus($pid, 'trash');
    }

    // 'publish'
    static function restore($pid)
    {
        return static::setStatus($pid, 'publish');
    }

    static function getAttr($key = null)
    {
        return post_custom($key);
    }

    /*
        Ej:
        
        Array
        (
            [_edit_lock] => Array
                (
                    [0] => 1685355749:1
                )

            [gdrive_actualizacion] => Array
                (
                    [0] => 2000-10-10
                )

        )
    */
    static function getAttrByID($id, $key = null)
    {
        $attrs = get_post_custom($id);

        if (!empty($key)) {
            return $attrs[$key] ?? null;
        }

        return $attrs;
    }

    static function addAttr($post_id, $attr_name, $attr_value)
    {
        add_post_meta($post_id, $attr_name, $attr_value, true);
    }

    /*
        @return int post_id

        Ej:

        $pid = Posts::getBySlug('woocommerce-y-a-vender');
        dd($pid, 'PID');

        $pid = Posts::getBySlug('introduccion-a-php', null, 'publish');
        dd($pid, 'PID');

    */
    static function getBySlug(string $slug, string $post_type = null, $post_status = null)
    {
        // No considero post_type de momento
        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        $include_post_status = '';
        if (!empty($post_status)) {
            $include_post_status = "AND post_status = '$post_status'";
        }

        $table_name = 'posts'; 

        $sql =  "
            SELECT ID
            FROM $table_name
            WHERE post_name = ?
            $include_post_status 
            LIMIT 1
        ";

        DB::getConnection();

        return DB::selectOne($sql, [ $slug ]);       
    }


    /*
        Version basada con consultas SQL

        Ej:

        $pids  = static::getPosts(null, null, $limit, $offset, [
            '_downloadable' => 'yes'
        ]);

        No alterar el orden de los parametros ya que es usada en mutawp_admin !!!
    */
    static function getPosts($select = '*', $post_type = null, $post_status = null, $limit = -1, $offset = null, $attributes = null, $order_by = null, bool $include_metadata = false)
    {
        global $wpdb;

        if (is_array($select)) {
            // podria hacer un enclose con ``
            $select = implode(', ', $select);
        }

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        $include_post_status = '';
        if ($post_status !== null) {
            $include_post_status = "AND post_status = '$post_status'";
        }

        $attributes_condition = '';
        if (!empty($attributes) && is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                $attributes_condition .= "AND ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '$key' AND meta_value = '$value') ";
            }
        }

        $limit_clause = '';
        if ($limit !== null) {
            $limit_clause = "LIMIT $limit";
        }

        $offset_clause = '';
        if ($offset !== null) {
            $offset_clause = "OFFSET $offset";
        }

        $order_clause = 'ORDER BY ID ASC';
        // if ($order_by !== null) {
        //     $order_clause = "ORDER BY $order_by";
        // }

        $sql = "SELECT $select FROM `{$wpdb->prefix}posts` WHERE ID <> 1 AND post_type = '$post_type' $include_post_status $attributes_condition $order_clause $limit_clause $offset_clause";

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if ($include_metadata) {
            foreach ($rows as $ix => $row) {
                $rows[$ix]['meta'] = Posts::getMeta($row['ID']);
            }
        }

        return $rows;
    }

    /*
        Ej:

        Posts::getIDs('sfwd-question', 'publish', 5)
    */
    static function getIDs($post_type = null, $post_status = null, $limit = null, $offset = null, $attributes = null, $order_by = null)
    {
        $res = static::getPosts('ID', $post_type, $post_status, $limit, $offset, $attributes, $order_by);
        return array_column($res, 'ID') ?? null;
    }

    static function getPost($id)
    {
        return get_post($id, ARRAY_A);
    }

    /* 
        El operador aplicado es AND 

        Podria haber implementacion mas eficiente con FULLSEARCH

        O usar 

        https://www.advancedcustomfields.com/resources/query-posts-custom-fields/
        https://qirolab.com/posts/example-of-wp-query-to-search-by-post-title-in-wordpress
    */
    static function search($keywords, $attributes = null, $select = '*', bool $include_desc = true, $post_type = null, $post_status = null, $limit = null, $offset = null)
    {
        global $wpdb;

        $tb = $wpdb->prefix . 'posts';

        if (is_array($select)) {
            // podria hacer un enclose con ``
            $select = implode(', ', $select);
        }

        $select_multi = Strings::contains(',', $select);

        if (!is_array($keywords) && Strings::contains('+', $keywords)) {
            $keywords = explode('+', $keywords);
        }

        if (!is_array($keywords) && Strings::contains(' ', $keywords)) {
            $keywords = explode(' ', $keywords);
        }

        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }

        $conds = [];
        foreach ($keywords as $ix => $keyword) {
            $keyword = '%' . $wpdb->esc_like($keyword) . '%';
            $conds[] = "(" . "post_title LIKE '$keyword'" . ($include_desc ? " OR post_excerpt LIKE '$keyword'" : '') . ")";
        }

        $conditions = implode(' AND ', $conds);


        ////////////////////////////////////////////////

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        $include_post_status = '';
        if ($post_status !== null) {
            $include_post_status = "AND post_status = '$post_status'";
        }

        $attributes_condition = '';
        if (!empty($attributes) && is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                $attributes_condition .= "AND ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '$key' AND meta_value = '$value') ";
            }
        }

        $limit_clause = '';
        if ($limit !== null) {
            $limit_clause = "LIMIT $limit";
        }

        $offset_clause = '';
        if ($offset !== null) {
            $offset_clause = "OFFSET $offset";
        }

        ////////////////////////////////////////////////    

        $sql = "SELECT $select FROM `$tb` 
            WHERE 
            post_type = '$post_type' $include_post_status $attributes_condition
            AND post_status = 'publish' 
            AND ($conditions)
            ORDER BY ID ASC $limit_clause $offset_clause;";

        $results = $wpdb->get_results($sql, ARRAY_A);

        if (!$select_multi) {
            $results = array_column($results, trim($select));
        }

        return $results;
    }


    /*
        Retorna el ultimos post

        Ej:
        
        Posts::getLastNPost('*', 'shop_coupon')
    */
    static function getLastPost($select = '*', $post_type = null, $post_status = null, $attributes = null, bool $include_metadata = false)
    {
        return static::getPosts($select, $post_type, $post_status, 1, 0, $attributes, "ID DESC", $include_metadata);
    }

    /*
        Retorna los ultimos N-posts

        Ej:
        
        Posts::getLastNPost('*', 'shop_coupon', null, 2	)
    */
    static function getLastNPost($select = '*', $post_type = null, $post_status = null, int $limit = -1, $attributes = null, bool $include_metadata = false)
    {
        return static::getPosts($select, $post_type, $post_status, $limit, 0, $attributes, "ID DESC", $include_metadata);
    }

    static function getLastID($post_type = null, $post_status = null)
    {
        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        $post = static::getLastPost($post_type, $post_status);

        if ($post == null) {
            return null;
        }

        return (int) $post['ID'] ?? null;
    }

    static function getAll($post_type = null, $status = 'publish', $limit = -1, $order = null)
    {
        global $wpdb;

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS  * FROM {$wpdb->prefix}posts  WHERE 1=1  AND (({$wpdb->prefix}posts.post_type = '$post_type' AND ({$wpdb->prefix}posts.post_status = '$status')));";

        return $wpdb->get_results($sql, ARRAY_A);
    }

    static function getOne($post_type = null, $status = 'publish', $limit = -1, $order = null)
    {
        global $wpdb;

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS  * FROM {$wpdb->prefix}posts WHERE 1=1 AND (({$wpdb->prefix}posts.post_type = '$post_type' AND ({$wpdb->prefix}posts.post_status = '$status')));";

        return $wpdb->get_row($sql, ARRAY_A);
    }

    
    /*
        Nota:
        
        Hace un AND entre cada categoria en la query
    */

    static function getPostsByTaxonomy(string $taxo, string $by, Array $term_ids, $post_type = null, $post_status = null)
    {   
        if (!in_array($by, ['slug', 'id', 'name'])){
            throw new \InvalidArgumentException("Invalid field '$by' for quering");
        }

        if ($post_status != null && !in_array($post_status, ['publish', 'draft', 'pending', 'private', 'trash', 'auto-draft'])){
            throw new \InvalidArgumentException("Invalid post_status '$post_status'");
        }

        // When you have more term_id's seperate them by comma.
        $str_term_ids = implode(',', $term_ids);

        $args = array(
            'post_type' => $post_type ?? static::$post_type,
            'numberposts' => -1,
            'post_status' => $post_status,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => $taxo,
                    'field' => $by,
                    'terms' => $str_term_ids,
                    'operator' => 'IN',
                    )
                 ),
        );

        return get_posts( $args);
    }

    static function getPostsByCategory(string $by, Array $category_ids, $post_status = null)
    {
        return static::getPostsByTaxonomy(static::$cat_metakey, $by, $category_ids, static::$post_type, $post_status);
    }

    /*
        Retorna Posts contienen determinado valor en una meta_key

        Uso:

            Posts::getByMeta('_Quiz name', 'examen-clase-b', 'sfwd-question')
            Posts::getByMeta('_Quiz name', 'examen-clase-b', 'sfwd-question', null, 2, null, 'RAND()')
            Posts::getByMeta('_Quiz name', 'examen-clase-b', 'sfwd-question', null, 2, null, 'RAND()', 'ID,post_content');
            etc.       

        Genera query como:

            SELECT p.*, pm.* FROM wp_postmeta pm
            LEFT JOIN wp_posts p ON p.ID = pm.post_id 
            WHERE  
                pm.meta_key   = '_Quiz name' 
            AND pm.meta_value = 'examen-clase-b'
            AND p.post_type   = 'sfwd-question'
            AND p.post_status = 'publish'            
    
    */
    static function getByMeta($meta_key, $meta_value, $post_type = null, $post_status = null, $limit = null, $offset = null, $order_by = null, $select = '*')
    {
        global $wpdb;

        if ($select != '*') {
            if (is_array($select)) {
                // podria hacer un enclose con ``
                $select = implode(', ', $select);
            }
            // bool
            $select_multi = Strings::contains(',', $select);
        } else {
            $select = 'p.*, pm.*';
        }

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        $limit_clause = '';
        if ($limit !== null) {
            $limit_clause = "LIMIT $limit";
        }

        $offset_clause = '';
        if ($offset !== null) {
            $offset_clause = "OFFSET $offset";
        }

        $order_clause = 'ORDER BY ID ASC';
        // if ($order_by !== null) {
        //     $order_clause = "ORDER BY $order_by";
        // }

        $sql = "SELECT $select FROM {$wpdb->prefix}postmeta pm
            LEFT JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id 
            WHERE  
            pm.meta_key = '%s' 
            AND pm.meta_value='%s'";

        $sql_params = array($meta_key, $meta_value);

        if ($post_type !== null) {
            $sql .= " AND p.post_type = %s";
            $sql_params[] = $post_type;
        }

        if ($post_status !== null) {
            $sql .= " AND p.post_status = %s";
            $sql_params[] = $post_status;
        }

        $sql .= " $order_clause $limit_clause $offset_clause";

        $results = $wpdb->get_results($wpdb->prepare($sql, $sql_params), ARRAY_A);

        return $results;
    }

    static function getPostIDsContainingMeta($meta_key)
    {
        global $wpdb;

        // Preparar la consulta SQL para buscar el ID de la meta
        $query = $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key
        );

        return array_column($wpdb->get_results($query, ARRAY_A), 'post_id');
    }

    /*
        Retorna post(s) contienen determinado valor en una meta_key
    */
    static function getPostsByMeta($meta_key, $meta_value, $post_type = null, $post_status = 'publish')
    {
        global $wpdb;

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        /*
            SELECT COUNT(*) FROM wp_postmeta pm
            LEFT JOIN wp_posts p ON p.ID = pm.post_id 
            WHERE p.post_type = 'product' 
            AND pm.meta_key = '_forma_farmaceutica' 
            AND pm.meta_value='crema'
            AND p.post_status = 'publish'
            ;
        */

        $sql = "SELECT * FROM {$wpdb->prefix}postmeta pm
            LEFT JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id 
            WHERE  
            pm.meta_key = '%s' 
            AND pm.meta_value='%s'";

        $sql_params = [$meta_key, $meta_value];

        if ($post_type !== null) {
            $sql .= " AND p.post_type = '%s'";
            $sql_params[] = $post_type;
        }

        if ($post_status !== null) {
            $sql .= " AND p.post_status = '%s'";
            $sql_params[] = $post_status;
        }

        // dd($sql);

        $r = $wpdb->get_results($wpdb->prepare($sql, ...$sql_params));

        return $r;
    }
    
    /*
        Retorna la cantidad de posts contienen determinado valor en una meta_key
    */
    static function countByMeta($meta_key, $meta_value, $post_type = null, $post_status = 'publish')
    {
        global $wpdb;

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        /*
            SELECT COUNT(*) FROM wp_postmeta pm
            LEFT JOIN wp_posts p ON p.ID = pm.post_id 
            WHERE p.post_type = 'product' 
            AND pm.meta_key = '_forma_farmaceutica' 
            AND pm.meta_value='crema'
            AND p.post_status = 'publish'
            ;
        */

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta pm
            LEFT JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id 
            WHERE  
            pm.meta_key = '%s' 
            AND pm.meta_value='%s'";

        $sql_params = array($meta_key, $meta_value);

        if ($post_type !== null) {
            $sql .= " AND p.post_type = %s";
            $sql_params[] = $post_type;
        }

        if ($post_status !== null) {
            $sql .= " AND p.post_status = %s";
            $sql_params[] = $post_status;
        }

        $r = (int) $wpdb->get_var($wpdb->prepare($sql, ...$sql_params));

        return $r;
    }

    /*
        Obtiene un valores de un meta para todos los productos de un tipo

        Ej:

        Products::getMetaValues('_regular_price')
    */
    static function getMetaValues($meta_key, $post_type = null, $post_status = 'publish') {
        global $wpdb;

        $sql = "
            SELECT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key = %s
        ";

        $params = [$post_type ?? static::$post_type, $meta_key];

        if (!empty($post_status)){
            $sql .= " AND p.post_status = %s";
            $params[] = $post_status;
        }

        $query = $wpdb->prepare($sql, ...$params);

        $meta_values = $wpdb->get_col($query);

        return $meta_values;
    }

    /*
        Obtiene todos los valores asi como post_id para cada ocurrencia
    */
    static function getMetaValuesAndPostIds($meta_key, $post_type = null, $post_status = null) {
        global $wpdb;

        $params = [ $meta_key ];

        $sql = "
            SELECT p.ID, p.post_type, pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s
        ";

        if (!empty($post_type)){
            $sql   .= " AND p.post_type = %s";
            $params[] = $post_type;
        }        

        if (!empty($post_status)){
            $sql .= " AND p.post_status = %s";
            $params[] = $post_status;
        }

        $query = $wpdb->prepare($sql, ...$params);

        $rows = $wpdb->get_results($query);

        return $rows;
    }


    static function deleteMeta($post_id, $meta_key)
    {
       delete_post_meta($post_id, $meta_key);
    }

    /*
        Uso. Ej:

        static::getTaxonomyFromTerm('Crema')
    */
    static function getTaxonomyFromTerm(string $term_name)
    {
        global $wpdb;

        /*  
            SELECT * FROM wp_terms AS t 
            LEFT JOIN wp_termmeta AS tm ON t.term_id = tm.term_id 
            LEFT JOIN wp_term_taxonomy AS tt ON tt.term_id = t.term_id
            WHERE t.name = 'Crema'
        */

        $sql = "SELECT taxonomy FROM {$wpdb->prefix}terms AS t 
            LEFT JOIN {$wpdb->prefix}termmeta AS tm ON t.term_id = tm.term_id 
            LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt ON tt.term_id = t.term_id
            WHERE name = '%s'";

        $r = $wpdb->get_col($wpdb->prepare($sql, $term_name));

        return $r;
    }

    /*
        Size (attribute)
        small  (term)
        medium (term)
        large  (term)
    */
    static function getTermIdsByTaxonomy(string $taxonomy)
    {
        global $wpdb;

        $sql = "SELECT term_id FROM `{$wpdb->prefix}term_taxonomy` WHERE `taxonomy` = '$taxonomy';";

        return $wpdb->get_col($sql);
    }

    // static function getMetaByPostID_2($pid, $taxonomy = null){
	// 	global $wpdb;

	// 	$pid = (int) $pid;

    //     if ($taxonomy != null){
    //         $and_taxonomy = "AND taxonomy = '$taxonomy'";
    //     }

	// 	$sql = "SELECT T.*, TT.* FROM {$wpdb->prefix}term_relationships as TR 
	// 	INNER JOIN `{$wpdb->prefix}term_taxonomy` as TT ON TR.term_taxonomy_id = TT.term_id  
	// 	INNER JOIN `{$wpdb->prefix}terms` as T ON  TT.term_taxonomy_id = T.term_id
	// 	WHERE 1=1 $and_taxonomy AND TR.object_id='$pid'";

	// 	return $wpdb->get_results($sql);
	// }

    /*
        Devolucion de array de metas incluidos atributos de posts

        array (
            '_sku' =>
            array (
                0 => '7800063000770',
            ),
            '_regular_price' =>
            array (
                0 => '2790',
            ),

            // ...
            
            '_product_attributes' =>
            array (
                0 => 'a:0:{}',
            ),
            
            // ...

            '_laboratorio' =>
            array (
                0 => 'Mintlab',
            ),
            '_enfermedades' =>
            array (
                0 => 'Gripe',
            ),        
        )

        Si $single es true, en vez de devolver un array, se devuelve un solo valor,
        lo cual tiene sentido con $key != ''
    */
    static function getMeta($pid, $meta_key = '', bool $single = true)
    {
        return get_post_meta($pid, $meta_key, $single);
    }

    
    /*  
        Get metas "ID" by meta key y value 
    */
    static function getMetaIDs($meta_key, $dato)
    {
        global $wpdb;
       
        // Preparar la consulta SQL para buscar el ID de la meta
        $query = $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s",
            $meta_key,
            $dato
        );

        // Ejecutar la consulta
        $result = $wpdb->get_results($query);

        return $result;
    }

    static function setMeta($post_id, $meta_key, $dato, bool $sanitize = false)
    {
        if ($sanitize) {
            $dato = sanitize_text_field($dato);
        }

        update_post_meta($post_id, $meta_key, $dato);
    }

    /*
        Devuelve si un termino existe para una determinada taxonomia
    */
    static function termExists($term_name, string $taxonomy)
    {       
        return (term_exists($term_name, $taxonomy) !== null);
    }


    static function getTermBySlug($slug)
    {
        global $wpdb;

        $sql = "SELECT * from `{$wpdb->prefix}terms` WHERE slug = '$slug'";
        return $wpdb->get_row($sql);
    }

    static function getTermById(int $id)
    {
        // global $wpdb;

        // $sql = "SELECT * from `{$wpdb->prefix}terms` WHERE term_id = '$id'";
        // return $wpdb->get_row($sql);

        return get_term($id);
    }

    /*
        Delete Attribute Term by Name

        Borra los terminos agregados con insertAttTerms() de la tabla 'wp_terms' por taxonomia
    */
    static function deleteTermByName(string $taxonomy, $args = [])
    {
        $term_ids = static::getTermIdsByTaxonomy($taxonomy);

        foreach ($term_ids as $term_id) {
            wp_delete_term($term_id, $taxonomy, $args);
        }
    }


    static function getTaxonomyBySlug($slug, $taxo = null)
    {
        $category = get_term_by('slug', $slug, $taxo);

        if ($category === null || $category === false) {
            return null;
        }

        return $category;
    }

    static function getTaxonomyIdBySlug($slug, $taxo = null)
    {
        $cat_obj = static::getTaxonomyBySlug($slug, $taxo);

        if (empty($cat_obj)) {
            return null;
        }

        return $cat_obj->term_id;
    }

    /*
        Categories
    */

    static function getCategoryById($id, $output = 'OBJECT'){
        $category = get_term_by('term_id', $id, static::$cat_metakey, $output);

        if ($category === null || $category === false) {
            return null;
        }

        return $category;
    }

    /*
        Ej:

        Products::getCategoryBySlug('stivali-da-uomo-versace', ARRAY_A)
    */
    static function getCategoryBySlug($slug, $output = 'OBJECT')
    {
        $category = get_term_by('slug', $slug, static::$cat_metakey, $output);

        if ($category === null || $category === false) {
            return null;
        }

        return $category;
    }

    static function getCategoryIdBySlug($slug, $output = 'OBJECT')
    {
        $cat_obj = static::getCategoryBySlug($slug, $output);

        if (empty($cat_obj)) {
            return null;
        }

        return $cat_obj->term_id;
    }

    /*
        $only_subcategos  determina si las categorias de primer nivel deben o no incluirse 
    */
    static function getCategories(bool $only_subcategos = false)
    {
        static $ret;

        if ($ret !== null) {
            return $ret;
        }

        $taxonomy = static::$cat_metakey;
        $orderby = 'name';
        $show_count = 1;      // 1 for yes, 0 for no
        $pad_counts = 0;      // 1 for yes, 0 for no
        $hierarchical = 1;      // 1 for yes, 0 for no  
        $title = '';
        $empty = 0;

        $args = array(
            'taxonomy' => $taxonomy,
            'orderby' => $orderby,
            'show_count' => $show_count,
            'pad_counts' => $pad_counts,
            'hierarchical' => $hierarchical,
            'title_li' => $title,
            'hide_empty' => $empty
        );

        $all_categories = get_categories($args);

        if (!$only_subcategos) {
            return $all_categories;
        }

        $ret = [];
        foreach ($all_categories as $cat) {
            if ($cat->category_parent == 0) {
                $category_id = $cat->term_id;
                $link = '<a href="' . get_term_link($cat->slug, static::$cat_metakey) . '">' . $cat->name . '</a>';

                $args2 = array(
                    'taxonomy' => $taxonomy,
                    'child_of' => 0,
                    'parent' => $category_id,
                    'orderby' => $orderby,
                    'show_count' => $show_count,
                    'pad_counts' => $pad_counts,
                    'hierarchical' => $hierarchical,
                    'title' => $title,
                    'hide_empty' => $empty
                );
                $sub_cats = get_categories($args2);

                if ($sub_cats) {
                    foreach ($sub_cats as $sub_category) {
                        $ret[] = $sub_category;
                    }
                }
            }
        }

        return $ret;
    }

    static function getCategoSlugs()
    {
        $categos = static::getCategories();

        $ret = [];
        foreach ($categos as $catego) {
            $ret[] = $catego->slug;
        }

        return $ret;
    }

    static function getCategoryChildren($category_id)
    {
        return get_term_children($category_id, static::$cat_metakey);
    }

    static function getCategoryChildrenBySlug($category_slug)
    {
        $cat = static::getTermBySlug($category_slug);

        if ($cat === null) {
            return null;
        }

        $category_id = $cat->term_id;
        return static::getCategoryChildren($category_id);
    }

    /*
        https://devwl.pl/wordpress-get-all-children-of-a-parent-product-category/
    */
    static function getTopLevelCategories()
    {
        global $wpdb;

        $cat_metakey = static::$cat_metakey;

        $sql = "SELECT t.*,  tt.* FROM {$wpdb->prefix}terms t
            LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = '$cat_metakey' 
            AND tt.parent = 0
            ORDER BY tt.taxonomy;";

        return $wpdb->get_results($sql);
    }

    static function getAllCategories(bool $only_ids = false)
    {
        global $wpdb;

        $cat_metakey = static::$cat_metakey;

        $sql = "SELECT t.*,  tt.* 
            FROM {$wpdb->prefix}terms t
            LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = '$cat_metakey' 
            ORDER BY tt.taxonomy;";

        $res = $wpdb->get_results($sql, ARRAY_A);

        if ($only_ids) {
            return array_column($res, 'term_id');
        }

        return $res;
    }

    static function getCategoByDescription(string $desc, bool $strict = true)
    {
        global $wpdb;

        $w_desc = "tt.description = '$desc'";

        if (!$strict) {
            $w_desc = "tt.description LIKE '%$desc%'";
        }

        $sql = "SELECT t.*,  tt.* 
            FROM {$wpdb->prefix}terms t
            LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_taxonomy_id
            WHERE $w_desc";

        $res = $wpdb->get_results($sql, ARRAY_A);

        return $res;
    }

    /*
        Categories  <- PostID
    
        Retorna algo como:

        [
            {
                "id": 193,
                "name": "Plugins para WordPress",
                "slug": "plugins-para-wordpress"
            },
            {
                "id": 197,
                "name": "SEO",
                "slug": "seo"
            }
        ]
    */
    static function getCategoryAttributes($post)
    {
        $obj = [];

        $category_ids = $post->get_category_ids();

        foreach ($category_ids as $cat_id) {
            $terms = get_term_by('id', $cat_id, static::$cat_metakey);

            $obj[] = [
                'id' => $terms->term_id,
                'name' => $terms->name,
                'slug' => $terms->slug
            ];
        }

        return $obj;
    }

    /*
        Category  <----- CategoryName

        Solo devuelve una aunque el nombre se repita con distinto slug
    */
    static function getCategoryByName($name, string $output = OBJECT)
    {
        $category = get_term_by('name', $name, static::$cat_metakey, $output);

        if ($category === null || $category === false) {
            return null;
        }

        return $category;
    }

    /*
        CategoryId  <- CategoryName
    */
    static function getCategoryIdByName($name)
    {
        $category = get_term_by('name', $name, static::$cat_metakey);

        if ($category === null || $category === false) {
            return null;
        }

        return $category->term_id;
    }

    static function getCategoryNameByID($cat_id)
    {
        if ($term = get_term_by('id', $cat_id, static::$cat_metakey)) {
            return $term->name;
        }

        throw new \InvalidArgumentException("Category ID '$cat_id' not found");
    }

    /*
        Para categorias "built-in"

        Ej:

        getPostsByCategoryId(7, 10, ['name' => 'ASC'])
    
    */
    static function getPostsByBuiltInCategoryId($id, $limit = -1, $order_by = null)
    {
        if (!empty($order_by)) {
            $col = array_key_first($order_by);
            $order = $order_by[$col];
        }

        $args = array(
            'category' => $id,
            'posts_per_page' => $limit,
            'orderby' => $col,
            'order' => $order,
            'post_type' => static::$post_type
        );

        return get_posts($args);
    }

    // 6-ene-24
    static protected function getPostsByTaxonomy__($field, $terms, $taxonomy, $limit = -1, $offset = 0, $post_type = null, $include_children = false, $args = [])
    {
        $_args = [
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'field' => $field, // 'term_id' o 'slug'
                    'terms' => $terms,
                    'include_children' => $include_children // Remove if you need posts from term child terms
                ],
            ],
            // Rest your arguments
        ];

        if ($post_type !== false) {
            $_args['post_type'] = $post_type ?? static::$post_type;
        }

        if ($limit != null) {
            $_args['posts_per_page'] = $limit;
        }

        if (!empty($offset)) {
            $_args['offset'] = $offset;
        }

        $_args = array_merge($_args, $args);

        $query = new \WP_Query($_args);

        return $query->posts;
    }

    /*
        6-ene-24

        En una clase derivada podria hacer esto:

        static::getPostsByTaxonomyId(273)

        o

        static::getPostsByTaxonomyId(273, null, null "my-taxonomy")
    */
    static function getPostsByTaxonomyId($id, $taxonomy, $limit = -1, $offset = 0, $post_type = null, $include_children = false, $args = [])
    {
        return static::getPostsByTaxonomy__('term_id', $id, $taxonomy, $limit, $offset, $post_type, $include_children, $args);
    }

    // 6-ene-24
    static function getPostsByTaxonomySlug($slug, $taxonomy, $limit = -1, $offset = 0, $post_type = null, $include_children = false, $args = [])
    {
        return static::getPostsByTaxonomy__('slug', $slug, $taxonomy, $limit, $offset, $post_type, $include_children, $args);
    }

    // 6-ene-24        
    static function getPostsByCategoryId($id, $limit = -1, $offset = 0, $post_type = null, $include_children = false, $args = [])
    {
        return static::getPostsByTaxonomy__('term_id', $id, static::$cat_metakey, $limit, $offset, $post_type, $include_children, $args);
    }

    // 6-ene-24
    static function getPostsByCategorySlug($slug, $limit = -1, $offset = 0, $post_type = null, $include_children = false, $args = [])
    {
        return static::getPostsByTaxonomy__('slug', $slug, static::$cat_metakey, $limit, $offset, $post_type, $include_children, $args);
    }

    static function dumpCategories()
    {
        $terms = get_terms([
            'taxonomy' => static::$cat_metakey,
            'hide_empty' => false,
        ]);

        return $terms;
    }

    // 6-ene-24

    /*
        Borra una categoria y puede hacerlo recursivamente <--- algo esta mal con la recursividad

        Ademas puede borrar los posts internos

        Ej:

        static::deleteCategoryById(305, true, true, true)
    */
    static function deleteCategoryById($term_id, bool $recursive = false, bool $include_posts = false, bool $force_post_deletion = true)
    {
        if ($include_posts) {
            $posts = static::getPostsByCategoryId($term_id);

            // delete all posts
            foreach ($posts as $post) {
                wp_delete_post($post->ID, $force_post_deletion);
            }
        }

        if ($recursive) {
            //delete all subcategories
            $args = array('child_of' => $term_id);
            $categories = get_categories($args);

            foreach ($categories as $category) {
                static::deleteCategoryById($category->term_id, $recursive, $include_posts, $force_post_deletion);
            }
        }

        return wp_delete_term($term_id, static::$cat_metakey);
    }

    // 6-ene-24 --ok
    static function deleteCategoryBySlug($slug, bool $recursive = false, bool $include_posts = false, bool $force_post_deletion = true){
        $cat = static::getCategoryBySlug($slug);

        if (empty($cat)){
            return;
        }

        return static:: deleteCategoryById($cat->term_id, $recursive, $include_posts, $force_post_deletion);
    }

    // 6-ene-24 -- ok
    static function deleteAllCategories(bool $include_posts, bool $force_post_deletion = false)
    {
        $cats = static::getAllCategories();

        foreach ($cats as $cat) {
            $term_id = $cat['term_id'];

            static::deleteCategoryById($term_id, false, $include_posts, $force_post_deletion);
        }
    }

    /*
        Borra categorias vacias

        Sin ensayar
    */
    static function deleteEmptyCategoriesV1($min = 1)
    {
        $count = $min - 1;
        $wp_ = tb_prefix();

        // https://stackoverflow.com/a/52413755
        $sql = "DELETE FROM {$wp_}terms WHERE term_id IN (SELECT term_id FROM {$wp_}term_taxonomy WHERE count = $count)";

        DB::statement($sql);
    }

    /*
        Esta funcion *debe ser revisasada* porque una categoria 
        no se puede considerar vacia si tiene sub-categorias !!!! !!!

        Su uso es peligroso

        Ej:

        $cats = static::deleteEmptyCategories(1, [
            'shop', 'uomo', 'donna'
        ]);

        <-- en ese caso cualquier categoria con menos de 1 producto
        excepto 'shop', 'uomo', 'donna' son eliminadas 

        Los posts en esas categorias son asignados a la categoria
        padre
    */
    static function deleteEmptyCategories($min = 1, $keep_cats = [])
    {
        // Convierto todas las categorias en cat_ids
        foreach ($keep_cats as $ix => $keep_cat) {
            if (is_string($keep_cat)) {
                $cat_id = static::getCategoryIdByName($keep_cat);

                if (!empty($cat_id)) {
                    $keep_cats[] = $cat_id;
                }

                $cat_id = static::getCategoryIdBySlug($keep_cat);

                if (!empty($cat_id)) {
                    if (!in_array($cat_id, $keep_cats)) {
                        $keep_cats[] = $cat_id;
                    }
                }

                unset($keep_cats[$ix]);
            }
        }

        $cats = static::getAllCategories();

        $affected = 0;
        foreach ($cats as $cat) {
            if (in_array($cat['term_id'], $keep_cats)) {
                continue;
            }

            //dd($cat['count'], $cat['name']);

            if (intval($cat['count']) < $min) {
                //dd("Borrando {$cat['name']}");

                $ok = static::deleteCategoryById($cat['term_id']);

                if ($ok) {
                    $affected++;
                }
            }
        }

        return $affected;
    }

    /*  
        En la mayoria dew casos, la taxonomia se crea con slug y cuando se edita es por id 

        Sin embargo, en teoria se podria pasar el "term_id" en $args, creando la taxonomia con su id
    */
    static function createTaxonomy($taxo, $name, $slug = null, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        // Verificar si la taxonomía ya existe
        $existing_term = term_exists($name, $taxo);

        if ($existing_term !== 0 && $existing_term !== null) {
            // La taxonomía ya existe, puedes manejarlo como desees
            return $existing_term['term_id'];
        }
        
        $args = array_merge($args, [
            'description' => $description,
            'slug' => $slug,
            'parent' => $id_parent
        ]);

        $cat = wp_insert_term(
            $name, // the term 
            $taxo, // the taxonomy
            $args
        );

        if ($cat instanceof \WP_Error) {
            dd([
                'name' => $name, // the term 
                'taxo' => $taxo, // the taxonomy
                'args' => $args
            ], 'GENERA ERROR');

            throw new \Exception($cat->get_error_message());
        }

        if (!empty($image_url)) {
            $img_id = static::uploadImage($image_url);
        }

        if (isset($img_id) && !is_wp_error($cat)) {
            $cat_id = isset($cat['term_id']) ? $cat['term_id'] : 0;
            update_term_meta($cat_id, 'thumbnail_id', absint($img_id));
        }

        return $cat['term_id'];
    }

    static function updateTaxonomyById($taxo, $term_id, $name, $slug = null, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        //dd("Updating ... $name");

        $args = array_merge($args, [
            'name' => $name,
            'description' => $description,
            'parent' => $id_parent
        ]);

        if (!empty($slug)){
            $args['slug'] = $slug;
        }

        $cat = wp_update_term(
            $term_id,
            $taxo, // the taxonomy
            $args
        );

        if ($cat instanceof \WP_Error) {
            dd([
                'name' => $name, // the term 
                'taxo' => $taxo, // the taxonomy
                'args' => $args
            ], 'GENERA ERROR');

            throw new \Exception($cat->get_error_message());
        }

        if (!empty($image_url)) {
            $img_id = static::uploadImage($image_url);
        }

        if (isset($img_id) && !is_wp_error($cat)) {
            $cat_id = isset($cat['term_id']) ? $cat['term_id'] : 0;
            update_term_meta($cat_id, 'thumbnail_id', absint($img_id));
        }

        return $cat;
    }

    static function updateTaxonomyBySlug($taxo, $slug, $name, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        $term_id = static::getTaxonomyIdBySlug($slug, $taxo);

        //dd("Updating ... $name");

        $args = array_merge($args, [
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
            'parent' => $id_parent
        ]);

        $cat = wp_update_term(
            $term_id,
            $taxo, // the taxonomy
            $args
        );

        if ($cat instanceof \WP_Error) {
            dd([
                'term_id' => $term_id, // the term 
                'taxo' => $taxo, // the taxonomy
                'args' => $args
            ], 'GENERA ERROR');

            throw new \Exception($cat->get_error_message());
        }

        if (!empty($image_url)) {
            $img_id = static::uploadImage($image_url);
        }

        if (isset($img_id) && !is_wp_error($cat)) {
            $cat_id = isset($cat['term_id']) ? $cat['term_id'] : 0;
            update_term_meta($cat_id, 'thumbnail_id', absint($img_id));
        }

        return $cat;
    }

    static function createOrUpdateTaxonomyBySlug($taxo, $slug, $name, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        $id = static::getTaxonomyIdBySlug($slug, $taxo);

        if (empty($id)){
            return static::createTaxonomy($taxo, $name, $slug, $description, $id_parent, $image_url, $args);
        } else {
            return static::updateTaxonomyBySlug($taxo, $slug, $name, $description, $id_parent, $image_url, $args);
        }
    }

    /*  
        En la mayoria dew casos, la categoria se crea con slug y cuando se edita es por id 

        Sin embargo, en teoria se podria pasar el "term_id" en $args, creando la taxonomia con su id <-------------- *
    */
    static function createCatego($name, $slug = null, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        return static::createTaxonomy(static::$cat_metakey, $name, $slug, $description, $id_parent, $image_url, $args);
    }

    static function updateCatego($name, $slug, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        return static::updateTaxonomyBySlug(static::$cat_metakey, $slug, $name, $description, $id_parent, $image_url, $args);
    }

    static function createOrUpdateCategoBySlug($slug, $name, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        return static::createOrUpdateTaxonomyBySlug(static::$cat_metakey, $slug, $name, $description, $id_parent, $image_url, $args);
    }

    static function updateCategoById($term_id, $name, $slug = null, $description = null, $id_parent = null, $image_url = null, $args = [])
    {
        return static::updateTaxonomyById(static::$cat_metakey, $term_id, $name, $slug, $description, $id_parent, $image_url, $args);
    }

    /*
        Crea categoria 

        Con $preserve_inherence si la categoria existe y tenia parent no-nulo pero el nuevo parent es nulo => no la modifca. 
        
        <-- Esto evita resetear el padre con valor nulo
        
        @return category_id

        TO-DO

        Migrar la funcionalidad a createOrUpdateTaxonomyBySlug()
    */
    static function createOrUpdateCategory($name, $slug = null, $description = null, $id_parent = null, $preserve_inherence = true){
        if (!empty($id_parent)){
            return static::createOrUpdateCategoBySlug($slug, $name, $description,  $id_parent);
        }

        // objeto de la categoria
        $_cat   = static::getCategoryByName($name, ARRAY_A);

        // Si existe
        if (!empty($_cat)){
            // padre actual
            $_parent = $_cat['parent'];
        }

        // Solo si la categoria no existe O ... si existe pero no tiene actualmente padre 
        if (empty($_cat) || ($preserve_inherence && !empty($_cat) && empty($_parent))){
            $cid  = static::createOrUpdateCategoBySlug($slug, $name, $description,  $id_parent);
        } else {
            $cid  = $_cat['term_id'];
        }

        return $cid;
    }

    /*
        Crea todas las categorias seteando la categoria padre en cada caso.

        Dentro de la descripcion de la categoria deja "<!-- slug:$slug -->"
        que es usado para identificar de forma inequivoca la categoria
        aunque fuera rennombrada y su slug cambiara.

        - Debe recibir un Array como:  --al menos con slug y name--
        
        [
            ...

            [151] => Array
            (
                [slug] => /lifestyle/complementi-d-arredo/cuscini/
                [name] => Cuscini
                [link] => https://www.giglio.com//lifestyle/complementi-d-arredo/cuscini/
            )
        ]

        - Es requisito que el array este ordenado alfabeticamente por slug en orden ascendente
    */
    static function createCategoryTree(array $categories)
    {
        foreach ($categories as $cat) {
            $name = $cat['name'] ?? '';
            $slug = $cat['slug'] ?? '';
            $img = $cat['image'] ?? null; // image url

            if (empty($name) || empty($slug)) {
                continue;
            }

            ////////////////////////////////////

            // Prevengo "A term with the name provided already exists with this parent."

            $cat_id = static::getCategoryIdBySlug(str_replace('/', '-', $slug));

            if (!empty($cat_id)) {
                // dd("SKIPING para $slug");
                continue;
            }

            ////////////////////////////////////

            $_slug = trim($slug, '/');

            if (substr_count($slug, '/') < 2) {
                continue;
            }

            $_f = explode('/', $_slug);

            unset($_f[count($_f) - 1]);

            $parent_slug = '/' . implode('/', $_f) . '/';
            $parent_cat = static::getCategoByDescription("<!-- slug:$parent_slug -->", false);

            // dd(["$slug -- $name", "PARENT SLUG: $parent_slug"]);

            $parent_id = null;
            if (!empty($parent_cat)) {
                $parent_id = $parent_cat[0]['term_id'];
                $parent_slug = $parent_cat[0]['slug'];
            }

            $cat_id = static::createCatego($name, str_replace('/', '-', $slug), "<!-- slug:$slug -->", $parent_id, $img);
            // dd("Creada categoria '$name' con id = $cat_id (parent_id = $parent_id)");
        }
    }


    /*
        Dado el id de una categoria devuelve algo como

        A > A2 > A2-1 > A2-1a

        Ej:

        $cats = static::getAllCategories(true);

        foreach ($cats as $cat){
            dd(
                Posts::breadcrumb($cat), null, false
            );
        }
    */
    static function breadcrumb(int $cat_id)
    {
        $search = $cat_id;

        $path = [];
        $parent_id = null;

        while ($parent_id !== 0) {
            $catego = get_term($cat_id);
            $parent_id = $catego->parent;

            if ($parent_id == 0) {
                break;
            }

            $path[] = get_the_category_by_ID($parent_id);
            $cat_id = $parent_id;
        }

        $path = array_reverse($path);
        $last_sep = empty($path) ? '' : '>';
        $first_sep = '/';

        $breadcrumb = ltrim($first_sep . ' ') . ltrim(implode(' > ', $path) . " $last_sep " . get_the_category_by_ID($search));

        return $breadcrumb;
    }

    /*
        Images
    */
    static function getImages($pid, $featured_img = false)
    {
        $images = get_attached_media('image', $pid);

        if ($featured_img === false) {
            $urls = [];
            foreach ($images as $img) {
                $urls[] = $img->guid;
            }

            return $urls;
        }

        // Obtener la URL de la imagen destacada si está definida
        $featured_img_id = get_post_thumbnail_id($pid);
        if ($featured_img_id) {
            $featured_img_url = wp_get_attachment_image_src($featured_img_id, 'full')[0];
            return $featured_img_url;
        }

        return null; // No se encontró imagen destacada
    }

    static function getAttachmentIdFromSrc($image_src)
    {
        global $wpdb;

        $query = "SELECT ID FROM {$wpdb->posts} WHERE guid='$image_src'";
        $id = $wpdb->get_var($query);
        return $id;
    }

    /*
        Otra implentación:

        https://wordpress.stackexchange.com/questions/64313/add-image-to-media-library-from-url-in-uploads-directory
    */
    static function uploadImage($url, $title = '', $alt = '', $caption = '')
    {
        if (!function_exists('wp_crop_image')) {
            include_once(__DIR__ . '/../../../../../../wp-admin/includes/image.php');
        }
        
        if (!function_exists('media_sideload_image')) {
            include_once(__DIR__ . '/../../../../../../wp-includes/media.php');
        }
        
        if (!function_exists('download_url')) {
            include_once(__DIR__ . '/../../../../../../wp-admin/includes/file.php');
        }
        
        if (empty($url)){
            return;
        }

        if (strlen($url) < 10 || !Strings::startsWith('http', $url)){
            throw new \InvalidArgumentException("Image url '$url' is not valid");
        }

        $att_id = static::getAttachmentIdFromSrc($url);
        if ( $att_id !== null){
            return $att_id;
        }

        // mejor,
        // Files::file_get_contents_curl($url)
        $img_str = @file_get_contents($url);

        if ($img_str === false){
            Logger::logError("Img no accesible. URL: $url");
            return;
        }

        /*	
            Array
            (
                [0] => 600
                [1] => 600
                [2] => 2
                [3] => width="600" height="600"
                [bits] => 8
                [channels] => 3
                [mime] => image/jpeg
            )
        */

        $img_info = getimagesizefromstring($img_str);
        $mime     = $img_info['mime'] ?? null;

        if (empty($mime)){
            Logger::logError("MIME could not be determinated for $url");
            return false;
        }

        $img_type = Strings::afterIfContains($mime, "image/");

        if (empty($img_type)){
            Logger::logError("Formato incorrecto. El MIME indica que no es una imagen");
            return false;
        }

        $uniq_name = date('dmY').''.(int) microtime(true); 
        $filename = $uniq_name . '.' . $img_type;

        $uploaddir  = wp_upload_dir();
        $uploadfile = $uploaddir['path'] . '/' . $filename;


        $savefile = fopen($uploadfile, 'w');
        $bytes    = fwrite($savefile, $img_str);
        fclose($savefile);

        if (empty($bytes)){
            return;
        }

        $wp_filetype = wp_check_filetype(basename($filename), null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => $filename,
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $url,
            'title'          => $title,
            'alt'            => $alt,
            'caption'        => $caption
        );

        $att_id = wp_insert_attachment( $attachment, $uploadfile );

        if (empty($att_id)){
            return;
        }

        $imagenew = get_post( $att_id );
        $fullsizepath = get_attached_file( $imagenew->ID );
        $attach_data = wp_generate_attachment_metadata( $att_id, $fullsizepath );
        wp_update_attachment_metadata( $att_id, $attach_data ); 

        return $att_id;
    }

    static function setImagesForPost($pid, array $image_ids)
    {
        //dd("Updating images for post with PID $pid");
        $image_ids = implode(",", $image_ids);
        update_post_meta($pid, '_product_image_gallery', $image_ids);
    }

    // Setea imagen destacada
    static function setDefaultImage($pid, $image_id)
    {
        update_post_meta($pid, '_thumbnail_id', $image_id);
    }

    /*
        Borra imagenes de la Galeria de Medios para un determinado post

        Otra implementación:

        https://wpsimplehacks.com/how-to-automatically-delete-woocommerce-images/
    */
    static function deleteGaleryImages($pid)
    {
        // Delete Attachments from Post ID $pid
        $attachments = get_posts(
            array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'post_parent' => $pid,
            )
        );

        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }

    static function deleteAllGaleryImages()
    {
        global $wpdb;

        $wpdb->query("DELETE FROM `{$wpdb->prefix}posts` WHERE `post_type` = \"attachment\";");
        $wpdb->query("DELETE FROM `{$wpdb->prefix}postmeta` WHERE `meta_key` = \"_wp_attached_file\";");
        $wpdb->query("DELETE FROM `{$wpdb->prefix}postmeta` WHERE `meta_key` = \"_wp_attachment_metadata\";");
    }

    // retunrs author_id
    static function getAuthorID($post)
    {
        if (is_numeric($post)) {
            $post = get_post($post);
        }

        return $post->post_author;
    }

}