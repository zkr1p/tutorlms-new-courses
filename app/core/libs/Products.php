<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Arrays;
use boctulus\TutorNewCourses\core\libs\Strings;

/*
    Product utility class

    TO-DO

    - Investigar '_transient_wc_attribute_taxonomies' como option_name en wp_options

    - Debe existir siempre una versio de cada metodo con &$product por eficiencia
*/
class Products extends Posts
{
    static $post_type   = 'product';
    static $cat_metakey = 'product_cat';

    /*
        Fuente:

        \wp-content\plugins\woocommerce\includes\data-stores\class-wc-product-data-store-cpt.php

        Indgar todos los metodos que aparecen ahi. Ej: update_post_meta()

        Usar en dump() e createProduct() y updateProduct() de forma concistente

        Crear el metodo importProduct() que de existir o no el "ID" o el "sku" actualice o bien cree los productos
    */
    static $meta_key_to_props = array(
        '_sku'                   => 'sku',
        '_regular_price'         => 'regular_price',
        '_sale_price'            => 'sale_price',
        '_price'                 => 'price',
        '_sale_price_dates_from' => 'date_on_sale_from',
        '_sale_price_dates_to'   => 'date_on_sale_to',
        'total_sales'            => 'total_sales',
        '_tax_status'            => 'tax_status',
        '_tax_class'             => 'tax_class',
        '_manage_stock'          => 'manage_stock',
        '_backorders'            => 'backorders',
        '_low_stock_amount'      => 'low_stock_amount',
        '_sold_individually'     => 'sold_individually',
        '_weight'                => 'weight',
        '_length'                => 'length',
        '_width'                 => 'width',
        '_height'                => 'height',
        '_upsell_ids'            => 'upsell_ids',
        '_crosssell_ids'         => 'cross_sell_ids',
        '_purchase_note'         => 'purchase_note',
        '_default_attributes'    => 'default_attributes',
        '_virtual'               => 'virtual',
        '_downloadable'          => 'downloadable',
        '_download_limit'        => 'download_limit',
        '_download_expiry'       => 'download_expiry',
        '_thumbnail_id'          => 'image_id',
        '_stock'                 => 'stock_quantity',
        '_stock_status'          => 'stock_status',
        '_wc_average_rating'     => 'average_rating',
        '_wc_rating_count'       => 'rating_counts',
        '_wc_review_count'       => 'review_count',
        '_product_image_gallery' => 'gallery_image_ids',
    );

    /*
        Devuelve el ID del producto de la pagina de producto

        Ej:

        add_shortcode('price-qty', function(){
            $pid = Products::getCurrentProductID();
            
            // ...
        });
    */
    static function getCurrentProductID() {
        global $post;
        if (is_singular('product')) {
            return $post->ID;
        }
        return 0;
    }
    
    static function productExists($sku){
        global $wpdb;

        $product_id = $wpdb->get_var(
            $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s'", $sku)
        );

        return is_numeric($product_id) && $product_id !== 0;
    }

    static function productIDExists($prod_id){
        $p = \wc_get_product($prod_id);

        return !empty($p);
    }

    static function isSimple($product){
        $product = static::getProduct($product);

        return $product->get_type() === 'simple';
    }

    static function isExternal($product){
        $product = static::getProduct($product);

        if ($product instanceof \WC_Product_External){
            // extra-check

            if (empty($product->get_product_url())){
                throw new \Exception("External producto without url");
            }

            return true;
        } 

        return false;
    }

    static function getExternalProductData(){
        $prods = static::getAllProducts();

        $arr = [];
        foreach ($prods as $prod){
            if ($prod instanceof \WC_Product_External)
            {
                $prod_url = $prod->get_product_url();

                if (empty($prod_url)){
                    break;
                }

                $arr[] = [
                    'id'         => $prod->get_id(),
                    'prod_url'   => $prod_url,
                    'price'      => $prod->get_price(),
                    'sale_price' => $prod->get_sale_price(),
                    'status'     => $prod->get_status()
                ];
            } 
        }

        return $arr;
    }
        
    /*
        @param int|object $product
        @param string $return que pueede ser 'OBJECT' o 'ARRAY' 

    */
    static function getProduct($product, string $return = 'OBJECT'){
        $ret =  is_object($product) ? $product : \wc_get_product($product);

        if ($return == 'ARRAY'){
            $ret = $ret->get_data();
        }
        
        return $ret;
    }

    static function getProductById($pid){
        return \wc_get_product($pid);
    }

    /*
        A futuro podria reemplazar a dumpProduct() 

        pero implicaria modificar createProduct() para que pueda leer este otro formato.
    */
    static function getAllProducts($status = false, bool $as_array = false){
        $cond = array( 'limit' => -1 );
        
        if ($status === null) {
            $cond['status'] = 'publish';
        }elseif ($status !== false){
            $cond['status'] = $status;
        }

        $prods = wc_get_products($cond);

        if (!$as_array){
            return $prods;
        }

        $prods_ay = [];
        foreach ($prods as $product){
            $p_ay = $product->get_data();
        
            $p_ay['date_created']  = $p_ay['date_created']->__toString();
            $p_ay['date_modified'] = $p_ay['date_modified']->__toString();

            $p_ay['type']          = $product->get_type();
            $p_ay['product_url']   = ($product instanceof \WC_Product_External) ? $product->get_product_url() : null; 

            foreach($p_ay['attributes'] as $at_key => $at_data){
                $p_ay['attributes'][$at_key] = $at_data->get_data();
            }

            $prods_ay[] = $p_ay;
        }

        return $prods_ay;
    }

    /*
        Ej:  // controlador //

        function get($sku = null)
        {
            if (empty($sku)){
                $limit  = $_GET['limit']  ?? 10;
                $offset = $_GET['offset'] ?? null;
                $order  = $_GET['order']  ?? ['id' => 'DESC'];

                $pids = Products::getProductIds('publish', $limit, $offset, null, $order);

                $data = [];
                foreach ($pids as $pid){
                    $data['products'][] = Products::dumpProduct($pid);
                }
            } else {
                $pid  = Products::getIdBySKU($sku);
                $data = Products::dumpProduct($pid);
            }   

            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
            $data = str_replace("\r\n", '', $data);

            header('Content-Type: application/json; charset=utf-8');

            return $data;
        }
    */
    static function getProductIds($post_status = null, $limit = null, $offset = null, $attributes = null, $order_by = null){
        return static::getIDs('product', $post_status, $limit, $offset, $attributes, $order_by);
    }

    static function searchProduct($keywords, $attributes = null, $select = '*', $post_status = null, $limit = null, $offset = null){
        return parent::search(
            $keywords,
            $attributes,
            $select,
            true,
            'product',
            $post_status,
            $limit,
            $offset
        );
    }

    static function getPostsByCategory(string $by, Array $category_ids, $post_status = null)
    {
        return static::getPostsByTaxonomy(static::$cat_metakey, $by, $category_ids, static::$post_type, $post_status);
    }

    static function getPostIDsContainingMeta($meta_key, $prepend = false)
    {
        if ($prepend && !empty($meta_key)) {
            $meta_key = ltrim($meta_key, '_') . '_' . Strings::slug($meta_key);
        }

        return parent::getPostIDsContainingMeta($meta_key);
    }

    /*
        Retorna post(s) contienen determinado valor en una meta_key
    */
    static function getPostsByMeta($meta_key, $meta_value, $post_type = null, $post_status = 'publish', bool $prepend = false)
    {
        global $wpdb;

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        if ($prepend && !empty($meta_key)) {
            $meta_key = ltrim($meta_key, '_') . '_' . Strings::slug($meta_key);
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
    static function countByMeta($meta_key, $meta_value, $post_type = null, $post_status = 'publish', bool $prepend = false)
    {
        global $wpdb;

        if ($post_type == null) {
            $post_type = static::$post_type;
        }

        if ($prepend && !empty($meta_key)) {
            $meta_key = ltrim($meta_key, '_') . '_' . Strings::slug($meta_key);
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
        Recupera el/los ID(s) de producto dado el SKU
        
        En caso de que la DB este corrupta y admita dos productos con el mismo SKU,
        devolvera un array con los IDs
    */
    static function getProductIDBySKU($sku, $post_status = 'publish'){
        global $wpdb;

        $sql = "SELECT pm.post_id
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_sku'
        AND pm.meta_value = %s";

        $params = [ $sku ]; 

        if (!empty($post_status)) {
            $sql .= " AND post_status = %s";
            $params[] = $post_status;
        }

        $query = $wpdb->prepare($sql, ...$params);
        
        $product_ids = $wpdb->get_col($query);

        if (empty($product_ids)){
            return;
        }
    
        return count($product_ids) == 1 ? $product_ids[0] : $product_ids;
    }    

    static function getProductBySKU($sku){
        global $wpdb;

        $product_id = $wpdb->get_var(
            $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s'", $sku)
        );

        return static::getProduct($product_id);
    }    

    static function getIdBySKU($sku, $post_status = null){
        $pid = wc_get_product_id_by_sku($sku);

        if ($pid != null){
            return $pid;
        }

        $result_ay = static::getByMeta('SKU', $sku, 'product', $post_status);

        if (empty($result_ay)){
            return;
        }

        return $result_ay[0]['ID'];
    }

    static function getPrice($p){
        $p = static::getProduct($p);

        return $p->get_price();
    }

    static function setStock($product_id, $qty, bool $update_visibility = false)
    {
        $qty = (int) $qty;

        $stock_staus = $qty > 0 ? 'instock' : 'outofstock';

        static::setMeta($product_id, '_stock', $qty);

        // 2. Updating the stock quantity
        update_post_meta($product_id, '_stock_status', $stock_staus);

        if ($update_visibility){
            wp_set_post_terms( $product_id, $stock_staus, 'product_visibility', true );
        }
    }

    // Tiene sentido ->save() en este caso ?
    static function setStockStatus($product_id, $stock_status, bool $save = true)
    {
        if (!in_array($stock_status, ['instock', 'outofstock'])){
            throw new \InvalidArgumentException("Invalid stock_status '$stock_status'");
        }

        $p = \wc_get_product($product_id);

        $p->set_stock_status($stock_status);

        if ($save){
            $p->save();
        }
    }

    static function getStock($product)
    {
        $product = static::getProduct($product);
        return $product->get_stock_quantity();
    }

    /*
        Depende de si tiene stock (para productos no-virtuales) y acepta o no backorders
    */
    static function isPurchasable($product){
        $product = static::getProduct($product);

        return $product->is_purchasable;
    }

    /*
        Para el caso de productos variables usar getTitle()
        excepto que se quiera solo el texto de la pura variacion 

        Ej:

        Yellow
    */
    static function getName($product_id)
    {
        $product = wc_get_product($product_id);
        return $product->get_name();
    }

    /*
        Devuelve algo como:

        Antillas -06te502yel- Color: Yellow
    */
    static function getTitle($product_id, bool $html = false){
        if (Products::getPostType($product_id) == 'product_variation'){
            $variation = Products::getProduct($product_id);
            $title     = $variation->get_formatted_name(); 
        } else {
            $product = wc_get_product($product_id);
            $title   = $product->get_name();
        }

        if ($html === false){
            $title = str_replace(['(', ')'], '-', $title);
            $title = str_replace([
                    '<span class="description">',
                    '</span>'
            ], ' ', $title);
        }

        return $title;
    }

    /*
        Setea precios
    */
    static function setPrices(&$product, $args, $allow_zero_for_sale_price = false, bool $format_money = true)
    {
        $price = $args['price'] ?? $args['display_price'] ?? null;

        if ($price !== null){
            if ($format_money){
                $price = static::formatMoney($price);
            }
            
            $product->set_price($price);
        }
        
        // precio sin descuentos
        $regular_price = $args['regular_price'] ?? $args['price'] ?? null;

        if ($regular_price !== null){
            if ($format_money){
                $regular_price = static::formatMoney($regular_price);
            }

            $product->set_regular_price($regular_price);
        }

        if (isset($args['sale_price'])){
            if ($allow_zero_for_sale_price || (!$allow_zero_for_sale_price && $args['sale_price'] != 0)){

                if ($format_money){
                    $args['sale_price'] = static::formatMoney($args['sale_price']);
                }
                
                $product->set_sale_price($args['sale_price']);
            }
        }
    }

    /*
        Setea cantidades de 9999 para todos los productos a fines de poder hacer pruebas
    */
    static function setHighAvailability($pid = null){
        if ($pid == null){
            $pids = static::getIDs();
        } else {
            $pids = [ $pid ];
        }

        foreach($pids as $pid){
            static::setStock($pid, 99999);
        }   
    }

    static function setProductStatus($product, $status){
        $product = static::getProduct($product);

        // Status ('publish', 'pending', 'draft' or 'trash')
        if (in_array($status, ['publish', 'pending', 'draft', 'trash'])){
            $product->set_status($status);
            $product->save();
        } else {
            throw new \InvalidArgumentException("Estado '$status' invalido.");
        }
    }

    static function setAsDraft($pid){
        static::setProductStatus($pid, 'draft');
    }

    static function setAsPublish($pid){
        static::setProductStatus($pid, 'publish');
    }

    static function restoreBySKU($sku){
        $pid = static::getProductIDBySKU($sku);
        return static::setStatus($pid, 'publish');
    }

    static function trashBySKU($sku){
        $pid = static::getProductIDBySKU($sku);
        return static::setStatus($pid, 'trash');
    }

    static function getAttributeValueFromProduct($product, $attr_name){
        $product = static::getProduct($product);

        if ($product === null){
            throw new \Exception("Producto no puede ser nulo");
        }

        if ($product === false){
            throw new \Exception("Producto no encontrado");
        }

        return $product->get_attribute($attr_name);
    }

    /*
        Verificar funcione y documentar

        Generalizada del filtro Ajax de tallas para cliente peruano
        con productos variables
    */
    function getAttributeValuesByCategory($catego, $attr_name){
        global $config;
        
        if (empty($catego)){
            throw new \InvalidArgumentException("Category can not be avoided");
        }

        // if (isset($config['cache_expires_in'])){
        //     $cached = get_transient("$attr_name-$catego");
            
        //     if ($cached != null){
        //         return $cached;
        //     }
        // }
    
        $arr = [];
    
        // WC_Product_Variable[]
        $products = static::getProductsByCategoryName($catego);
    
        foreach ($products as $p){
            // id, slug, name
            $attr = static::getAttributeValueFromProduct($p, $attr_name);

            $id = $p['id'];

            if (!isset($arr[$id])){
                $arr[$id] = [
                    'slug' => $attr['slug'],
                    'name' => $attr['name']
                ];
            }
            
        }
    
        // if (isset($config['cache_expires_in'])){
        //     set_transient("{$attr_name}-$catego", $arr, $config['cache_expires_in']);
        // }        
    
        return $arr;
    }

    /*
        Devuelve si un termino existe para una determinada taxonomia
    */
    static function termExists($term_name, string $taxonomy)
    {
        if (!Strings::startsWith('pa_', $taxonomy)) {
            $taxonomy = 'pa_' . $taxonomy;
        }

        return (term_exists($term_name, $taxonomy) !== null);
    }


    /*
        Size (attribute)
        small  (term)
        medium (term)
        large  (term)

        In WooCommerce, they are all prepended with 'pa_'
    */
    static function getTermIdsByTaxonomy(string $taxonomy)
    {
        global $wpdb;

        if (!Strings::startsWith('pa_', $taxonomy)) {
            $taxonomy = 'pa_' . $taxonomy;
        }

        $sql = "SELECT term_id FROM `{$wpdb->prefix}term_taxonomy` WHERE `taxonomy` = '$taxonomy';";

        return $wpdb->get_col($sql);
    }

    /*
        Delete Attribute Term by Name

        Borra los terminos agregados con insertAttTerms() de la tabla 'wp_terms' por taxonomia (pa_forma_farmaceutica, etc)
    */
    static function deleteTermByName(string $taxonomy, $args = [])
    {
        if (!Strings::startsWith('pa_', $taxonomy)) {
            $taxonomy = 'pa_' . $taxonomy;
        }

        $term_ids = static::getTermIdsByTaxonomy($taxonomy);

        foreach ($term_ids as $term_id) {
            wp_delete_term($term_id, $taxonomy, $args);
        }
    }

    static function deleteMeta($post_id, $meta_key, bool $prepend = false)
    {
        if ($prepend && !empty($meta_key)) {
            $meta_key = ltrim($meta_key, '_') . '_' . Strings::slug($meta_key);
        }

        delete_post_meta($post_id, $meta_key);
    }
    
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
    static function getMeta($pid, $meta_key = '', bool $single = true, bool $prepend = false)
    {
        if ($prepend && !empty($meta_key)) {
            $meta_key = ltrim($meta_key, '_') . '_' . Strings::slug($meta_key);
        }

        return get_post_meta($pid, $meta_key, $single);
    }

    /*  
        Get metas "ID" by meta key y value 
    */
    static function getMetaIDs($meta_key, $dato, bool $prepend = false)
    {
        global $wpdb;

        if ($prepend && !empty($meta_key)) {
            $meta_key = ltrim($meta_key, '_') . '_' . Strings::slug($meta_key);
        }

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

    static function setMeta($post_id, $meta_key, $dato, bool $sanitize = false, bool $prepend = false)
    {
        if ($prepend && !empty($meta_key)) {
            $meta_key = ltrim($meta_key, '_') . '_' . Strings::slug($meta_key);
        }

        if ($sanitize) {
            $dato = sanitize_text_field($dato);
        }

        update_post_meta($post_id, $meta_key, $dato);
    }

    // gets featured image
    static function getImage($product, $size = 'woocommerce_thumbnail', $attr = [], $placeholder = true){
        $p =  is_object($product) ? $product : static::getProduct($product);

        $image = $p->get_image($size, $attr, $placeholder);

        $src = Strings::match($image, '/< *img[^>]*src *= *["\']?([^"\']*)/i');
        return $src;
    }

    static function hasFeatureImage($product){
        $src = static::getImage($product);

        return !Strings::endsWith('/placeholder.png', $src);
    }

    static function deleteImagesByPostID($post_id){
        $images = get_attached_media( 'image', $post_id );

        foreach ( $images as $image ) {
            wp_delete_attachment( $image->ID, true );
        }
    }

    static function getTagsByPostID($pid){
		global $wpdb;

		$pid = (int) $pid;

		$sql = "SELECT T.name, T.slug FROM {$wpdb->prefix}term_relationships as TR 
		INNER JOIN `{$wpdb->prefix}term_taxonomy` as TT ON TR.term_taxonomy_id = TT.term_id  
		INNER JOIN `{$wpdb->prefix}terms` as T ON  TT.term_taxonomy_id = T.term_id
		WHERE taxonomy = 'product_tag' AND TR.object_id='$pid'";

		return $wpdb->get_results($sql);
	}
    
    // ok
    static function updateProductTypeByProductID($pid, $new_type){
        $types = ['simple', 'variable', 'grouped', 'external'];
    
        if (!in_array($new_type, $types)){
            throw new \Exception("Invalid product type $new_type");
        }
    
        // Get the correct product classname from the new product type
        $product_classname = \WC_Product_Factory::get_product_classname( $pid, $new_type );
    
        // Get the new product object from the correct classname
        $new_product       = new $product_classname( $pid );
    
        // Save product to database and sync caches
        $new_product->save();
    
        return $new_product;
    }
    

    /**
     * Method to delete Woo Product
     * 
     * $force true to permanently delete product, false to move to trash.
     * 
     */
    static function deleteProduct($id, $force = false)
    {
        $product = wc_get_product($id);

        if(empty($product)){
            throw new \Exception(sprintf(__('No %s is associated with #%d', 'woocommerce'), 'product', $id));
        }            

        // If we're forcing, then delete permanently.
        if ($force)
        {
            if ($product->is_type('variable'))
            {
                foreach ($product->get_children() as $child_id)
                {
                    $child = wc_get_product($child_id);
                    $child->delete(true);
                }
            }
            elseif ($product->is_type('grouped'))
            {
                foreach ($product->get_children() as $child_id)
                {
                    $child = wc_get_product($child_id);
                    $child->set_parent_id(0);
                    $child->save();
                }
            }

            $product->delete(true);
            $result = $product->get_id() > 0 ? false : true;
        }
        else
        {
            $product->delete();
            $result = 'trash' === $product->get_status();
        }

        if (!$result)
        {
            throw new \Exception(sprintf(__('This %s cannot be deleted', 'woocommerce'), 'product'));
        }

        // Delete parent product transients.
        if ($parent_id = wp_get_post_parent_id($id))
        {
            wc_delete_product_transients($parent_id);
        }
        return true;
    }

    static function deleteProductBySKU($sku, bool $permanent = false){
        $pid = static::getProductIDBySKU($sku);
		return static::deleteProduct($pid, $permanent);
    }

    static function deleteLastProduct($force = false){
        $pid = static::getLastID();
        static::deleteProduct($pid, $force);
    }

    static function deleteAllProducts(){
        global $wpdb;

        $prefix = $wpdb->prefix;

        $wpdb->query("DELETE FROM {$prefix}terms WHERE term_id IN (SELECT term_id FROM {$prefix}term_taxonomy WHERE taxonomy LIKE 'pa_%')");
        $wpdb->query("DELETE FROM {$prefix}term_taxonomy WHERE taxonomy LIKE 'pa_%'");
        $wpdb->query("DELETE FROM {$prefix}term_relationships WHERE term_taxonomy_id not IN (SELECT term_taxonomy_id FROM {$prefix}term_taxonomy)");
        $wpdb->query("DELETE FROM {$prefix}term_relationships WHERE object_id IN (SELECT ID FROM {$prefix}posts WHERE post_type IN ('product','product_variation'))");
        $wpdb->query("DELETE FROM {$prefix}postmeta WHERE post_id IN (SELECT ID FROM {$prefix}posts WHERE post_type IN ('product','product_variation'))");
        $wpdb->query("DELETE FROM {$prefix}posts WHERE post_type IN ('product','product_variation')");
        $wpdb->query("DELETE pm FROM {$prefix}postmeta pm LEFT JOIN {$prefix}posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL");
    } 

    static function getFirst($qty = 1, $type = 'product'){
        global $wpdb;

        if (empty($qty) || $qty < 0){
            throw new \InvalidArgumentException("Quantity can not be 0 or null or negative");
        }

        $sql = "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type IN ('$type') ORDER BY ID DESC LIMIT $qty";

        $res     = $wpdb->get_results($sql, ARRAY_A);
        $res_ids = array_column($res, 'ID');

        return ($qty == 1) ? ($res_ids[0] ?? false) : $res_ids;
    }
    
    static function getFeaturedImageID($product){
        if (!is_object($product) && is_numeric($product)){
            $product = wc_get_product($product);    
        }

        if (empty($product)){
            return;
        }

        $image_id  = $product->get_image_id();

        if ($image_id == ''){
            $image_id = null;
        }    

        return $image_id;
    }

    static function getFeaturedImage($product, $size = 'thumbnail'){
        if ($size != 'thumbnail' && $size != 'full'){
            throw new \InvalidArgumentException("Size parameter value is incorrect");
        }

        $image_id = static::getFeaturedImageID($product);
        
        if (empty($image_id)){
            return;
        }

        return wp_get_attachment_image_url( $image_id, $size);
    }

    static function setProductTagNames($pid, Array $names){
        if (count($names) >0 && is_array($names[0])){
            throw new \InvalidArgumentException("Categories can not be array of arrays");
        }

        wp_set_object_terms($pid, $names, 'product_tag');
    }

    static function updatePrice($pid, $price){
        update_post_meta($pid, '_price', $price);
        update_post_meta($pid, '_regular_price', $price );
    }

    static function updateSalePrice($pid, $sale_price){
        update_post_meta($pid, '_sale_price', $sale_price );
    }

    static function updateStock($product, $qty, bool $save = true){      
        $product = static::getProduct($product);  

        if ($product === null){
            return;
        }

        $product->set_stock_quantity($qty);

        if ($qty < 1){
            $status = 'outofstock';
        } else {
            $status = 'instock';
        }

        $product->set_stock_status($status);
        
        if ($save){
            $product->save();
        }        
    }

    /*
        Images and Gallery

        Ej:

        $images = [
            "https://d2wuoo4cuot0vy.cloudfront.net/0026-0010/0026-0010_1134924986.jpg",
            "https://d2wuoo4cuot0vy.cloudfront.net/0026-0010/0026-0010_1434593415.jpg"
        ];

        $featured = $images[0];

        Products::setImages($pid, $images, $featured);
    */
    static function setImages(int $pid, array $images, $featured_img = null){
        $att_ids = [];

        $featured_img = $featured_img ?? is_array($images) ? ($images[0] ?? null) : $images;

        // dd([
        //     'pid'      => $pid,
        //     'images'   => $images,
        //     'featured' => $featured_img
        // ], "IM"); 

        if (count($images) > 0){
            foreach ($images as $img){
                $img_url = is_array($img) ? $img[0] : $img;

                $att_id = static::uploadImage($img_url);

                if (!empty($att_id)){
                    $att_ids[] = $att_id;
                }  
            }        
        }     

        // Featured image
        if ($featured_img != null) {           
            if (!empty($att_ids) && in_array($featured_img, $images)){
                $att_id    = $att_ids[0];
            } else {
                $att_id    = static::uploadImage($featured_img);
                $att_ids[] = $att_id;
            }

            static::setDefaultImage($pid, $att_id);
        }

        if (count($att_ids) >0){
            static::setImagesForPost($pid, $att_ids);

            return $att_ids;
        }
    }

    /*
        Obtener la URL de la imagen subida a la Galeria de Medios

        Ej:

            $featured_img = 'https://www.iconsdb.com/icons/preview/red/house-xxl.png';
        
            $att_id       = Products::uploadImage($featured_img);

            dd(Products::getImageURL($att_id));

        o ...

        $pid = Products::getIdBySKU('0026-0010');

        $images = [
            "https://d2wuoo4cuot0vy.cloudfront.net/0026-0010/0026-0010_1134924986.jpg",
            "https://d2wuoo4cuot0vy.cloudfront.net/0026-0010/0026-0010_1434593415.jpg"
        ];

        $featured = $images[0];

        $att_ids = Products::setImages($pid, $images, $featured);

        foreach ($att_ids as $att_id){
            dd(Products::getImageURL($att_id));
        }
    */
    static function getImageURL($att_id)
    {
        return wp_get_attachment_url($att_id);
    }


    // Setea categorias por ID a un producto dado su ID
    static function setProductCategoryById(&$product, array $cat_ids, bool $save = true)
    {
        $product->set_category_ids( $cat_ids); 

        if ($save){
            $product->save();
        }
    }


    // Agrega categorias por ID a un producto dado su ID
    static function addProductCategoryById(&$product, array $cat_ids, bool $save = true)
    {
        // Get product categories (if there is any)
        $term_ids = (array) $product->get_category_ids();

        foreach($cat_ids as $cat_id){
            if (!in_array($cat_id, $term_ids)){
                $term_ids[] = $cat_id;
            }
        }

        $product->set_category_ids($term_ids); 

        if ($save){
            $product->save();
        }
    }

    /*
        Ej:

        $args  =  [
            'sku'   => '0514-0082',   // campo requerido
            'type'  => 'simple',      // campo requerido 
            'price' => 500
            // podria o no tener mas campos
        ];

        Products::updateProductBySKU($args, 'ARRAY');
    */
    static function updateProductBySKU($args, string $return = 'OBJECT', $allow_zero_for_sale_price = false)
    {
        if (!isset($args['sku']) || empty($args['sku'])){
            dd($args);
            throw new \InvalidArgumentException("SKU is required");
        }

        $pid = static::getProductIDBySKU($args['sku']);

        // Parche para eliminar productos simples que duplican SKU 
        if (is_array($pid) && count($pid) > 1){
            $product = wc_get_product($pid[0]);
            $type    = $product->get_type();

            if ($type == 'simple'){
                $pid_arr = $pid;
                $pid     = $pid_arr[0];

                for ($i=1; $i<count($pid_arr); $i++){
                    // dd("Borrando producto con SKU = {$pid_arr[$i]} [!]");
                    static::deleteProduct($pid_arr[$i], true);
                }
            }
        }

        if (empty($pid)){
            throw new \InvalidArgumentException("SKU not found: {$args['sku']}");
        }

        if (!isset($product)){
            $product = wc_get_product($pid);
        }       

        if (!isset($type)){
            $type = $product->get_type();
        }

        // Si hay cambio de tipo de producto, lo actualizo
        if (!empty($args['type']) && $type != $args['type']){
            self::updateProductTypeByProductID($pid, $args['type']);
        }

        // Product name (Title) and slug
        if (isset($args['name'])){
            $product->set_name( $args['name'] ); 
        }
           
        // Description and short description:
        if (isset($args['description'])){
            $product->set_description($args['description']);
        }

        if (isset($args['short_description'])){
            $product->set_short_description( $args['short_description'] ?? '');
        }

        // Status ('publish', 'pending', 'draft' or 'trash')
        if (isset($args['status'])){
            $product->set_status($args['status']);
        }

        // Featured (boolean)
        if (isset($args['featured'])){
            $product->set_featured($args['featured']);
        }        

        // Visibility ('hidden', 'visible', 'search' or 'catalog')
        if (isset($args['visibility'])){
            $product->set_catalog_visibility($args['visibility']);
        }  

        // Sku --nuevo--
        if (isset($args['sku:new'])){
            $product->set_sku($args['sku:new']);
        }

        // Prices

        static::setPrices($product, $args, $allow_zero_for_sale_price);
        
        if( isset($args['sale_from'])){
            $product->set_date_on_sale_from($args['sale_from']);
        }

        if( isset($args['sale_to'])){
            $product->set_date_on_sale_to($args['sale_to']);
        }
        
        // Downloadable (boolean)
        $product->set_downloadable(  isset($args['downloadable']) ? $args['downloadable'] : false );
        if( isset($args['downloadable']) && $args['downloadable'] ) {
            $product->set_downloads(  isset($args['downloads']) ? $args['downloads'] : array() );
            $product->set_download_limit(  isset($args['download_limit']) ? $args['download_limit'] : '-1' );
            $product->set_download_expiry(  isset($args['download_expiry']) ? $args['download_expiry'] : '-1' );
        }

        // Taxes
        if ( get_option( 'woocommerce_calc_taxes' ) === 'yes' ) {
            if (isset($args['tax_status'])){
                $product->set_tax_status($args['tax_status']);
            }
            
            if (isset($args['tax_class'])){
                $product->set_tax_class($args['tax_class']);
            }            
        }

        // Virtual
       
        if (isset($args['virtual'])){
            $product->set_virtual($args['virtual']);
        }  else {
            $args['virtual'] =  false;
        }
   
        // Stock    
       
        if(!$args['virtual']) {
            if (isset($args['stock_status'])){
                if ($args['stock_status'] === true || $args['stock_status'] === 1 || $args['stock_status'] === 'instock'){
                    $stock_status = true;
                } else {
                    $stock_status = false;
                }
            }

            $product->set_stock_status($stock_status ?? 'instock'); 

            // Stock && manage status

            $stock = $args['stock_quantity'] ?? $args['stock'] ?? null;

            if ($stock !== null){
                $product->set_stock_quantity($stock);
            }

            $manage_stock = ($stock !== null) ? true : ($args['manage_stock'] ?? null);

            if ($manage_stock !== null){
                $product->set_manage_stock($manage_stock);
            }

            $product->set_backorders( isset( $args['backorders'] ) ? $args['backorders'] : 'no' ); // 'yes', 'no' or 'notify'    
        }

        // Sold Individually
        if (isset($args['sold_individually'])){
            $product->set_sold_individually($args['is_sold_individually'] != 'no');
        }

        // Weight, dimensions and shipping class
        if (isset($args['weight'])){
            $product->set_weight($args['weight']);
        }
        
        if (isset($args['length'])){
            $product->set_length($args['length']);
        }
        
        if (isset($args['width'])){
            $product->set_width($args['width']);
        }
        
        if (isset( $args['height'])){
            $product->set_height($args['height']);
        }        

        /*
        if( isset( $args['shipping_class_id'] ) ){
            $product->set_shipping_class_id( $args['shipping_class_id'] );
        }
        */        

        // Upsell and Cross sell (IDs)
        //$product->set_upsell_ids( isset( $args['upsells'] ) ? $args['upsells'] : '' );
        //$product->set_cross_sell_ids( isset( $args['cross_sells'] ) ? $args['upsells'] : '' );

        /*            
            Insercion de atributos  
        */
        
        if( isset( $args['attributes'] ) ){
            static::simplifyAttributes($args['attributes']);

            if ($args['type'] == 'variable'){
                static::insertVariableAttrs($product, $args['attributes']);                
            } elseif($args['type'] == 'simple'){
                static::addAttributesForSimpleProducts($pid, $args['attributes']);
            }             
        }
            
        if( isset($args['default_attributes']) /* && !empty($args['default_attributes']) */ ){   
            $product->set_default_attributes( $args['default_attributes'] );
        }

        // Reviews, purchase note and menu order
        
        $product->set_reviews_allowed( isset( $args['reviews'] ) ? $args['reviews'] : false );
        $product->set_purchase_note( isset( $args['note'] ) ? $args['note'] : '' );
        
        if( isset( $args['menu_order'] ) )
            $product->set_menu_order( $args['menu_order'] );

            
        ## --- SAVE PRODUCT --- ##
        $pid = $product->save();

        if (isset($args['category'])){
            $args['categories'] = [ $args['category'] ];
        }

        if( isset( $args['categories'] ) ){
            if (is_numeric($args['categories'][0])){
                static::setProductCategoryById($product, $args['categories']);
            } else {
                static::setCategoriesByNames($pid, $args['categories']);
            }            
        }            

        if( isset( $args['tags'] ) ){
            $names = isset($args['tags'][0]['name']) ? array_column($args['tags'], 'name') : $args['tags'];
            static::setProductTagNames($pid, $names);
        }
            

        // Images and Gallery    

        $galery_imgs = $args['gallery_images'] ?? $args['images'] ?? [];
        $featured    = $args['image'] ?? null;

        if (!empty($galery_imgs)){
            static::setImages($pid, $galery_imgs, $featured);     
        }

        if (isset($args['type']) && $args['type'] == 'variable'){
            $variation_ids = $product->get_children();
            //dd($variation_ids, 'V_IDS');
            
            // elimino variaciones para volver a crearlas
            foreach ($variation_ids as $vid){
                static::deleteProduct($vid, true);
            }

            if (isset($args['variations'])){
                foreach ($args['variations'] as $variation){
                    $var_id = static::addVariation($pid, $variation);                    
                }      
            }  
        }

        if ($return == 'INTEGER'){
            return $pid; //
        }   

        return $product; //
    }

    /*
        Debe usarse luego de crear el producto

        @param $pid product id
        @param array[] $attributes - This needs to be an array containing *ALL* your attributes so it can insert them in one go

        Ex.

        array (
            'Laboratorio' => 'Mintlab',
            'Enfermedades' => '',
            'Bioequivalente' => '',
            'Principio activo' => 'Cafeína|Clorfenamina|Ergotamina|Metamizol',
            'Forma farmacéutica' => 'Comprimidos',
            'Control de Stock' => 'Disponible',
            'Otros medicamentos' => 'Fredol|Migragesic|Ultrimin|Migratan|Cefalmin|Cinabel|Migranol|Migra-Nefersil|Tapsin m|Sevedol',
            'Dosis' => '100/4/1/300 mg',
            'Código ISP' => 'F-9932/16',
            'Es medicamento' => 'Si',
            'Mostrar descripción' => 'No',
            'Precio por fracción' => '99',
            'Precio por 100 ml o 100 G' => '',
            'Requiere receta' => 'Si',
        )
    */
    static function setProductAttributesForSimpleProducts($pid, Array $attributes, array $hidden = []){
        $i = 0;

        if (empty($attributes)){
            return;
        }

        // Loop through the attributes array
        foreach ($attributes as $name => $value) {
            $is_hidden = in_array($name, $hidden) ? 0 : 1;

            $product_attributes[$i] = array (
                'name' => htmlspecialchars( stripslashes( $name ) ), // set attribute name
                'value' => $value, // set attribute value
                'position' => 1,
                'is_visible' => $is_hidden,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );

            $i++;
        }

        if (empty($product_attributes)){
            return;
        }

        // Now update the post with its new attributes
        update_post_meta($pid, '_product_attributes', $product_attributes);
    }

    /*
        Forma de uso:

        static::addAttributeForSimpleProducts($pid, 'vel', '80');
    */
    static function addAttributeForSimpleProducts($pid, $key, $val){
        /*
            array (
                0 =>
                array (
                    'name' => 'stock',
                    'value' => 'out of stock',
                    'position' => 1,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 0,
                ),
            ), ..
        */
        
        $_attrs = static::getCustomAttr($pid);
        $attrs  = [];
    
        foreach($_attrs as $att){
            $attrs[ $att['name'] ] = $att['value'];
        }
    
        $attrs[ $key ] = $val;
    
        static::setProductAttributesForSimpleProducts($pid, $attrs);
    }
    
    /*
        Forma de uso:

        static::addAttributesForSimpleProducts($pid, [
            'fuerza' => 45,
            'edad' => 29
        ]);
    */
    static function addAttributesForSimpleProducts($pid, Array $attributes)
    {
        if (!Arrays::isAssocc($attributes)){
            throw new \InvalidArgumentException("El Array de atributos debe ser asociativo");
        }

        $_attrs = static::getCustomAttr($pid);
        $attrs  = [];
    
        foreach($_attrs as $att){
            $attrs[ $att['name'] ] = $att['value'];
        }

        /*
            Nuevos atributos
        */
        foreach ($attributes as $key => $val){
            $attrs[ $key ] = $val;
        }
    
        static::setProductAttributesForSimpleProducts($pid, $attrs);
    }

    // alias
    static function updateProductAttributesForSimpleProducts($pid, Array $att){
        static::addAttributesForSimpleProducts($pid, $att);
    }   

    static function removeAllAttributesForSimpleProducts($pid){
        update_post_meta($pid, '_product_attributes', []);
    }

    /*
        $atttributes = [
            'Size'  => ['S', 'M', 'L'],
            'Color' => ['Red', 'Yellow'],
        ]

	    @return void
    */
    static function setVariableAttrs(\WC_Product_Variable &$product, Array $attributes, $default_attrs = null){
        $attribute_ay = [];

        foreach ($attributes as $name => $options){
            $attribute  = new \WC_Product_Attribute();
            
            // 'Color'
            $attribute->set_name($name);

            // ['Red', 'Blue', 'Black', 'Green', 'Grey']
            $attribute->set_options($options);
            
            $attribute->set_variation(true);
        
            // for each
            $attribute_ay[] = $attribute;
        }

        $product->set_attributes($attribute_ay);

        if (!empty($default_attrs)){
            $product->set_default_attributes($default_attrs);
        }

        $product->save();
    }

    /*
        A diferencia de setVariableAttrs() agrega *nuevos* atributos

        $attributes = [
            'Size'  => ['S', 'M', 'L'],
            'Color' => ['Red', 'Yellow'],
        ]

	    @return void
    */
    static function insertVariableAttrs(\WC_Product_Variable &$product, Array $attributes, $default_attrs = null){
        $attribute_ay = [];
        
        $atts = $product->get_attributes();

        $_options = [];
        foreach($atts as $ix => $at){
            if ($at instanceof \WC_Product_Attribute){
                $array  = $at->get_data();
                $name   = $at['name'];
                $values = $at['value'];

                // dd($values, $name);

                $_vals = explode(' | ', $values);

                
                $_options[$name] = $_vals;
            }
        }

        // dd($_options, '$_options all');
        // exit;

        foreach ($attributes as $name => $options){
            $attribute  = new \WC_Product_Attribute();
            
            // 'Color'
            $attribute->set_name($name);

            // dd($options, "$name | before");
            // dd($_options[$name], '$_options');

            if (isset($_options[$name])){
                $options = array_unique(array_merge($options, $_options[$name]));
            }

            // dd($options, "$name | after");

            // ['Red', 'Blue', 'Black', 'Green', 'Grey']
            $attribute->set_options($options);
            
            $attribute->set_variation(true);
        
            // for each
            $attribute_ay[] = $attribute;
        }

        $product->set_attributes($attribute_ay);

        if (!empty($default_attrs)){
            $product->set_default_attributes($default_attrs);
        }

        $product->save();
    }

    /*
        Para cada atributo extrae la diferencia

        Ej: 

        $a = array (
            array (
            'name' => 'Laboratorio',
            'value' => 'UnLab2',
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 0,
            ),

            array (
            'name' => 'Enfermedades',
            'value' => 'Pestesssss',
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 0,
            ),
            array (
            'name' => 'Bioequivalente',
            'value' => 'Otrox',
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 0,
            )
        );

        $b = array (
            array (
            'name' => 'Laboratorio',
            'value' => 'UnLab2',
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 0,
            ),

            array (
            'name' => 'Enfermedades',
            'value' => 'SIDA|Herpes',
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 0,
            ),
            array (
            'name' => 'Bioequivalente',
            'value' => 'Otrox|NuevoMed',
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 0,
            )
        );

        Salida:

        Array
        (
            [Enfermedades] => Array
            (
                    [prev] => Pestesssss
                    [current] => SIDA|Herpes
            )

            [Bioequivalente] => Array
            (
                    [prev] => Otrox
                    [current] => Otrox|NuevoMed
            )
        )

    */
    function termDiff(Array $prev, Array $current){
        $dif = [];
        foreach ($prev as $ix => $at) {
            $name  = $at['name'];
            $val_p = $prev[$ix]['value'];
            $val_c = $current[$ix]['value'];

            if ($val_p !== $val_c){
                $dif[$name] = [
                    'prev'    => $val_p,
                    'current' => $val_c 
                ];
            }
        }

        return $dif;
    }

    // Obtén el precio formateado según la configuración de WooCommerce
    static function formatMoney($price){
        $price = str_replace(',', '.', $price);
        return wc_format_decimal($price);
    }

    /*
        Crea producto

        @param string $return puede ser 'OBJECT' o 'INTEGER'
    */
    static function createProduct(Array $args, string $return = 'OBJECT', $allow_zero_for_sale_price = false)
    {
        if (isset($args['sku']) && !empty($args['sku']) && !empty(static::getProductIDBySKU($args['sku']))){
            throw new \InvalidArgumentException("SKU {$args['sku']} ya está en uso.");
        }

        // Get an empty instance of the product object (defining it's type)
        $product = static::createProductByObjectType( $args['type'] );
        if( ! $product )
            return false;

        if (isset($args['product_url'])){
            $product->set_product_url($args['product_url']);
        }

        // Product name (Title) and slug
        $product->set_name( $args['name'] ); // Name (title).
    
        // Description and short description:
        $product->set_description( $args['description'] ?? '' );
        $product->set_short_description( $args['short_description'] ?? '');

        // Status ('publish', 'pending', 'draft' or 'trash')
        $product->set_status( isset($args['status']) ? $args['status'] : 'publish' );

        // Featured (boolean)
        $product->set_featured(  isset($args['featured']) ? $args['featured'] : false );

        // Visibility ('hidden', 'visible', 'search' or 'catalog')
        $product->set_catalog_visibility( isset($args['visibility']) ? $args['visibility'] : 'visible' );

        // Sku
        if (isset($args['sku'])){
            $product->set_sku($args['sku']);
        }

        // Prices

        static::setPrices($product, $args, $allow_zero_for_sale_price);
        
        if( isset($args['sale_from'])){
            $product->set_date_on_sale_from($args['sale_from']);
        }

        if( isset($args['sale_to'])){
            $product->set_date_on_sale_to($args['sale_to']);
        }
        
        // Downloadable (boolean)
        $product->set_downloadable(  isset($args['downloadable']) ? $args['downloadable'] : false );
        if( isset($args['downloadable']) && $args['downloadable'] ) {
            $product->set_downloads(  isset($args['downloads']) ? $args['downloads'] : array() );
            $product->set_download_limit(  isset($args['download_limit']) ? $args['download_limit'] : '-1' );
            $product->set_download_expiry(  isset($args['download_expiry']) ? $args['download_expiry'] : '-1' );
        }

        // Taxes
        if ( get_option( 'woocommerce_calc_taxes' ) === 'yes' ) {
            $product->set_tax_status(  isset($args['tax_status']) ? $args['tax_status'] : 'taxable' );
            $product->set_tax_class(  isset($args['tax_class']) ? $args['tax_class'] : '' );
        }

        // Virtual

        if (isset($args['virtual'])){
            $product->set_virtual($args['virtual']);
        }  else {
            $args['virtual'] =  false;
        }

        // Stock    
       
        if(!$args['virtual']) {     
            if (isset($args['stock_status'])){
                if ($args['stock_status'] === true || $args['stock_status'] === 1 || $args['stock_status'] === 'instock'){
                    $stock_status = true;
                } else {
                    $stock_status = false;
                }
            }

            $product->set_stock_status($stock_status ?? 'instock'); 

            // Stock && manage status
                          
            $stock = $args['stock_quantity'] ?? $args['stock'] ?? null;

            if ($stock !== null){
                $product->set_stock_quantity($stock);
            }

            $manage_stock = ($stock !== null) ? true : ($args['manage_stock'] ?? null);

            if ($manage_stock !== null){
                $product->set_manage_stock($manage_stock);
            }
            
            $product->set_backorders( isset( $args['backorders'] ) ? $args['backorders'] : 'no' ); // 'yes', 'no' or 'notify'
        
        }

        // Sold Individually
        if (isset($args['sold_individually'])){
            $product->set_sold_individually($args['is_sold_individually'] != 'no');
        }

        // Weight, dimensions and shipping class
        $product->set_weight( isset( $args['weight'] ) ? $args['weight'] : '' );
        $product->set_length( isset( $args['length'] ) ? $args['length'] : '' );
        $product->set_width( isset(  $args['width'] ) ?  $args['width']  : '' );
        $product->set_height( isset( $args['height'] ) ? $args['height'] : '' );
        
        if( isset( $args['shipping_class_id'] ) ){
            $product->set_shipping_class_id( $args['shipping_class_id'] );
        }   

        // Upsell and Cross sell (IDs)
        $product->set_upsell_ids( isset( $args['upsells'] ) ? $args['upsells'] : '' );
        $product->set_cross_sell_ids( isset( $args['cross_sells'] ) ? $args['upsells'] : '' );

        // Reviews, purchase note and menu order
        $product->set_reviews_allowed( isset( $args['reviews'] ) ? $args['reviews'] : false );
        $product->set_purchase_note( isset( $args['note'] ) ? $args['note'] : '' );
        if( isset( $args['menu_order'] ) )
            $product->set_menu_order( $args['menu_order'] );

            
        ## --- SAVE PRODUCT --- ##
        $pid = $product->save();


        if (isset($args['category'])){
            $args['categories'] = [ $args['category'] ];
        }

        if( isset( $args['categories'] ) ){
            if (is_numeric($args['categories'][0])){
                static::setProductCategoryById($product, $args['categories']);
            } else {
                static::setCategoriesByNames($pid, $args['categories']);
            }            
        }         

        if( isset( $args['tags'] ) ){
            $names = isset($args['tags'][0]['name']) ? array_column($args['tags'], 'name') : $args['tags'];
            static::setProductTagNames($pid, $names);
        }

        /*
            Insercion de atributos
        */

        if( isset( $args['attributes'] ) ){
            static::simplifyAttributes($args['attributes']);
            
            if ($args['type'] == 'variable'){
                static::insertVariableAttrs($product, $args['attributes']);                
            } elseif($args['type'] == 'simple'){
               static::addAttributesForSimpleProducts($pid, $args['attributes']);
            }             
        }
            
        if (isset($args['default_attributes'])){
            $product->set_default_attributes( $args['default_attributes'] ); 
        }            

        // Images and Gallery    

        $galery_imgs = $args['gallery_images'] ?? $args['images'] ?? [];
        $featured    = $args['image'] ?? null;

        if (!empty($galery_imgs)){
            static::setImages($pid, $galery_imgs, $featured);     
        }

        if (isset($args['type']) && $args['type'] == 'variable' && isset($args['variations'])){
            foreach ($args['variations'] as $variation){
                static::addVariation($pid, $variation);
            }     
            
            //@$product->variable_product_sync();
        }

        if ($return == 'INTEGER'){
            return $pid; //
        }   

        return $product; //
    }



    /*
        Setea una imagen asociada a un post de tipo "attachment" al post del producto
    */
    static function setFeaturedImage(int $pid, int $img_post_id){
        Products::setDefaultImage($pid, $img_post_id);
    }

    /*
        Sube una imagen y asocia el post de tipo "attachment" al post del producto
    */
    static function uploadFeaturedImage(int $pid, string $img_url){
        $att_id = Products::uploadImage($img_url);
        Products::setDefaultImage($pid, $att_id);
    }

    static function dumpProduct($product){
        if ($product === null){
            throw new \InvalidArgumentException("Product can not be null");
        }

		$obj = [];
	
		$get_src = function($html) {
			$parsed_img = json_decode(json_encode(simplexml_load_string($html)), true);
			$src = $parsed_img['@attributes']['src']; 
			return $src;
		};

		// Get Product General Info
	  
        if (is_object($product)){
            $pid = $product->get_id();
        } else {
            $pid = $product;
            $product = wc_get_product($pid);
        }

		$obj['id']                 = $pid;;
		$obj['type']               = $product->get_type();
        $obj['product_url']        = ($product instanceof \WC_Product_External) ? $product->get_product_url() : null;  //
		$obj['name']               = $product->get_name();
		$obj['slug']               = $product->get_slug();
		$obj['status']             = $product->get_status();
		$obj['featured']           = $product->get_featured();
		$obj['catalog_visibility'] = $product->get_catalog_visibility();
		$obj['description']        = $product->get_description();
		$obj['short_description']  = $product->get_short_description();
		$obj['sku']                = $product->get_sku();
		#$obj['virtual'] = $product->get_virtual();
		#$obj['permalink'] = get_permalink( $product->get_id() );
		#$obj['menu_order'] = $product->get_menu_order(
		$obj['date_created']       = $product->get_date_created()->date('Y-m-d H:i:s');
		$obj['date_modified']      = $product->get_date_modified()->date('Y-m-d H:i:s');
		
		// Get Product Prices
		
		$obj['price']              = $product->get_price();
		$obj['regular_price']      = $product->get_regular_price();
		$obj['sale_price']         = $product->get_sale_price();
		#$obj['date_on_sale_from'] = $product->get_date_on_sale_from();
		#$obj['date_on_sale_to'] = $product->get_date_on_sale_to();
		#$obj['total_sales'] = $product->get_total_sales();
		
		// Get Product Tax, Shipping & Stock
		
		#$obj['tax_status'] = $product->get_tax_status();
		#$obj['tax_class'] = $product->get_tax_class();
		$obj['manage_stock']      = $product->get_manage_stock();
		$obj['stock_quantity']    = $product->get_stock_quantity();
		$obj['stock_status']      = $product->get_stock_status();
		#$obj['backorders'] = $product->get_backorders();
		$obj['is_sold_individually'] = $product->get_sold_individually();   /// deberia ser     sold_individually
		#$obj['purchase_note'] = $product->get_purchase_note();
		#$obj['shipping_class_id'] = $product->get_shipping_class_id();
		
		// Get Product Dimensions
		
		$obj['weight']           = $product->get_weight();
		$obj['length']           = $product->get_length();
		$obj['width']            = $product->get_width();
		$obj['height']           = $product->get_height();
		
		// Get Linked Products
		
		#$obj['upsell_ids'] = $product->get_upsell_ids();
		#$obj['cross_sell_id'] = $product->get_cross_sell_ids();
		$obj['parent_id']        = $product->get_parent_id();
		
		// Get Product Taxonomies
		
		$obj['tags']             = static::getTagsByPostID($pid); /// deberia ser tag_ids


		$obj['categories'] = [];
		$category_ids = $product->get_category_ids(); 
	
		foreach ($category_ids as $cat_id){
			$terms = get_term_by( 'id', $cat_id, static::$cat_metakey );
			$obj['categories'][] = [
				'name' => $terms->name,
				'slug' => $terms->slug,
				'description' => $terms->description
			];
		}
			
		
		// Get Product Downloads
		
		#$obj['downloads'] = $product->get_downloads();
		#$obj['download_expiry'] = $product->get_download_expiry();
		#$obj['downloadable'] = $product->get_downloadable();
		#$obj['download_limit'] = $product->get_download_limit();
		
		// Get Product Images
		
		$obj['image_id'] = $product->get_image_id();
		$obj['image']    = wp_get_attachment_image_src($obj['image_id'], 'large');  

		$obj['gallery_image_ids'] = $product->get_gallery_image_ids();
			
		$obj['gallery_images'] = [];
		foreach ($obj['gallery_image_ids'] as $giid){
			$obj['gallery_images'][] = wp_get_attachment_image_src($giid, 'large');
		}	
	
		// Get Product Reviews
		
		#$obj['reviews_allowed'] = $product->get_reviews_allowed();
		#$obj['rating_counts'] = $product->get_rating_counts();
		#$obj['average_rating'] = $product->get_average_rating();
		#$obj['review_count'] = $product->get_review_count();
	
		// Get Product Variations and Attributes

		if($obj['type'] == 'variable'){
			$obj['attributes'] = self::getVariationAttributes($product);
			
			$_default_atts = $product->get_default_attributes();

            $default_atts  = [];
            foreach ($_default_atts as $def_at_ix => $def_at_val){
                $def_at_key = $def_at_ix;

                if (!Strings::startsWith('pa_', $def_at_ix)){
                    $def_at_key = 'pa_' . $def_at_ix;
                }

                $default_atts[$def_at_key] = $def_at_val;
            }

			if (!empty($default_atts)){
				$obj['default_attributes'] = $default_atts;
			}		

			$obj['variations'] = $product->get_available_variations();	

			foreach ($obj['variations'] as $var_ix => $var)
            {	
				if ($var['sku'] == $obj['sku']){
					$obj['variations'][$var_ix]['sku'] = '';
				}
			}
		} else {
			// Simple product

			$atts = $product->get_attributes();

            foreach($atts as $ix => $at){
                if ($at instanceof \WC_Product_Attribute){
                    $at_array  = $at->get_data();
                    
                    $at_name   = $at_array['name'];
                    $at_value  = $at_array['value'];

                    $obj['attributes'][$at_name] = $at_value;
                }
            }
		}		
	
		return $obj;		
	}

    // alias
    static function dd($product){
        return static::dumpProduct($product);
    }

    static function getProductType($product)
    {
        if (is_int($product)){
            $product = static::getProductById($product);
        }

        return $product->get_type();
    }

    /*
        Para el caso de productos variables devuelve algo como:

        Array
        (
            [attributes] => Array
                (
                    [pa_size] => Array
                        (
                            [term_names] => Array
                                (
                                    [0] => 42
                                    [1] => 44
                                )

                            [is_visible] => 1
                        )

                )

            [sku] => FS7891ANR6
            [variations] => Array
                (
                    [0] => Array
                        (
                            [attributes] => Array
                                (
                                    [attribute_pa_size] => 42
                                )

                            [sku] =>
                        )

                    [1] => Array
                        (
                            [attributes] => Array
                                (
                                    [attribute_pa_size] => 44
                                )

                            [sku] =>
                        )

                )
            )
        )
    */
    static function getProductAttributes($pid, bool $include_attr_prefixes = false)
    {
        $product = static::getProductById($pid);

        $obj = [];

        if(static::getProductType($product) == 'variable'){
			$obj['attributes'] = self::getVariationAttributes($product);

            if (!$include_attr_prefixes){
                foreach ($obj['attributes'] as $name => $val){
                    if (Strings::startsWith('pa_', $name)){
                        $key = substr($name, 3);

                        Arrays::renameKey($obj['attributes'], $name, $key);
                    }
                }
            }
			
			$_default_atts = $product->get_default_attributes();

            $default_atts  = [];
            foreach ($_default_atts as $def_at_ix => $def_at_val){
                $def_at_key = $def_at_ix;

                if ($include_attr_prefixes){
                    if (!Strings::startsWith('pa_', $def_at_ix)){
                        $def_at_key = 'pa_' . $def_at_ix;
                    }
                } else {
                    if (Strings::startsWith('pa_', $def_at_ix)){
                        $def_at_key = substr($def_at_ix, 3);
                    }
                }

                $default_atts[$def_at_key] = $def_at_val;
            }

			if (!empty($default_atts)){
				$obj['default_attributes'] = $default_atts;
			}		

            $obj['sku'] = $product->get_sku();

			$variations = $product->get_available_variations();	

			foreach ($variations as $var_ix => $var)
            {	
                /*
                    Necesito que cada atributo sea de la forma

                    array (
                        'attribute_pa_color' => 'marron',
                    ),
                */

                $atts = [];
                foreach($var['attributes'] as $taxonomy_name => $at_val){
                    $key = $taxonomy_name;       
            
                    if (Strings::startsWith('attribute_', $key) && !Strings::after($key, 'attribute_pa_')){
                        $key = ($include_attr_prefixes ? 'attribute_pa_' : '') . Strings::after($key, 'attribute_');
                        $atts[$key] = $at_val;
                    }
                }

                $obj['variations'][$var_ix]['attributes'] = $atts;

				if (!empty($obj['sku']) && $var['sku'] == $obj['sku']){
					$obj['variations'][$var_ix]['sku'] = '';
				}
			}
		} else {
			// Simple product

			$atts = $product->get_attributes();

            foreach($atts as $ix => $at){
                if ($at instanceof \WC_Product_Attribute){
                    $at_ay     = $at->get_data();
                    $at_name   = $at['name'];
                    $at_value  = $at['value'];

                    $obj['attributes'][$at_name] = $at_value;
                }
            }
		}	

        return $obj;
    }

    /*
        Conversion de array de atributos de algo como

        "pa_size" => [
            "term_names" => [
                "40",
                "42",
                "44"
            ],
            "is_visible" => true
        ]

        en algo como

        [
            [size] => Array
            (
                [0] => 40
                [1] => 42
                [2] => 44
            )
        ]
    */
    static function simplifyAttributes(Array &$attributes)
    {
        foreach ($attributes as $name => $val){
            if (Strings::startsWith('pa_', $name)){
                $key = substr($name, 3);

                Arrays::renameKey($attributes, $name, $key);

                if (is_array($val) && isset($val['term_names'])){
                    $attributes[$key] = $val['term_names'];
                }
            }

            if (Strings::startsWith('attribute_pa_', $name)){
                $key = str_replace('attribute_pa_', 'attribute_', $name);

                Arrays::renameKey($attributes, $name, $key);

                if (is_array($val) && isset($val['term_names'])){
                    $attributes[$key] = $val['term_names'];
                }
            }
        }
    }

    /*
        @param  Array $attrs
        @param  WC_Product_Variation &$variation
        @return int
    */
    static function setAttributesForVariation(Array $attrs, \WC_Product_Variation &$variation)
    {
        
        /*
            Cada atributo debe tener como key la forma 'attribute_{name}'

            Ej:

            attribute_size
        */
        foreach ($attrs as $name => $value)
        {
            if ($value === null){
                $variation_id = $variation->get_id();
                Logger::logError("Attribute with name='$name' has NULL value for variation_id=$variation_id");
            }

            $attrs[$name] = trim($value);

            if (!Strings::startsWith('attribute_', $name)){
                $key = 'attribute_' . $name;

                Arrays::renameKey($attrs, $name, $key);
            }

            if (Strings::startsWith('attribute_pa_', $name)){
                $key = str_replace('attribute_pa_', 'attribute_', $name);

                Arrays::renameKey($attrs, $name, $key);
            }
        }
      
        $variation->set_attributes($attrs);
        $variation_id = $variation->save();

        if (!isset($variation_id)){
            $variation_id = $variation->get_id();
        }

        // Metodo #2 por seguridad (redundancia)
        foreach ($attrs as $name => $value){
            update_post_meta($variation_id, $name, $value);
        }

        return $variation_id;
    }

    /*
        @param  int   $parent_id   
        @param  Array $attrs
        @return int

        Ej:

        $attrs = [
            'color' => 'Black',
            'size'  => 'S'
        ];

        $args = [
            'regular_price' => 1002,
            'attrs' => $attrs,
            'featured_image' => 
                'http://woo1.lan/wp-content/uploads/2023/01/190120231674088916.jpeg'
            
        ];

        $var_id = Products::addVariation($product_id, $args);
    */
    static function addVariation($parent_id, Array $args){
        return static::createOrUpdateVariation($args, $parent_id);
    }

    /*
        @param  int   $variation_id   
        @param  Array $attrs
        @return int

        Ej:

        $attrs = [
            'color' => 'Black',
            'size'  => 'S'
        ];

        $args = [
            'regular_price' => 1002,
            'attrs' => $attrs,
            'featured_image' => 
                'http://woo1.lan/wp-content/uploads/2023/01/190120231674088916.jpeg'
            
        ];

        Products::updateVariation($variation_id, $args);
    */
    static function updateVariation($variation_id, Array $args){
        return static::createOrUpdateVariation($args, null, $variation_id);
    }

    /*
        @param  int   $parent_id   
        @param  Array $attrs
        @param  int   $variation_id  
        @return int
    */
    static function createOrUpdateVariation(Array $args, $parent_id = null, $variation_id = null)
    {
        $variation = new \WC_Product_Variation($variation_id);

        if ($parent_id != null /* && $variation_id === null */){
            $variation->set_parent_id($parent_id);

            // Parent (variable product)
            $product    = wc_get_product($parent_id);
            $parent_sku = $product->get_sku();
        }
    
        $attrs = $args['attributes'] ?? $args['attrs'] ?? null;
    
        if (empty($attrs)){
            throw new \Exception("Attributes not found");
        }
    
        $variation_id = static::setAttributesForVariation($attrs, $variation);
    
        // SKU
        if (isset($args['sku']) && !empty($args['sku']) && ((isset($parent_sku) && $args['sku'] != $parent_sku) || !isset($parent_sku))){
            $variation->set_sku($args['sku']);
        }
    
        $variation->set_description($args['description'] ?? null);
    
        // Prices

        static::setPrices($variation, $args);
        
        if( isset($args['sale_from'])){
            $product->set_date_on_sale_from($args['sale_from']);
        }

        if( isset($args['sale_to'])){
            $product->set_date_on_sale_to($args['sale_to']);
        }
    
        // Sold Individually
        if (isset($args['sold_individually'])){
            $variation->set_sold_individually($args['is_sold_individually'] != 'no');
        }
    
        // Weight, dimensions and shipping class
        $variation->set_weight( isset( $args['weight'] ) ? $args['weight'] : '' );
    
        // Virtual

        if (isset($args['virtual'])){
            $product->set_virtual($args['virtual']);
        }  else {
            $args['virtual'] =  false;
        }

        // Stock    
       
        if(!$args['virtual']) {    
            $qty = $args['stock_quantity'] ?? $args['stock'] ?? $args['qty'] ?? null;

            // boolean
            if (isset($args['is_in_stock'])){
                $stock_status = $args['is_in_stock'] ? 'instock' : 'outofstock';
            } else {
                if (isset($args['stock_status'])){
                    if ($args['stock_status'] === true || $args['stock_status'] === 1 || $args['stock_status'] === 'instock'){
                        $stock_status = 'instock';
                    } else {
                        if ($args['stock_status'] !== 'onbackorder'){
                            $stock_status = 'outofstock';
                        }
                        
                    }
                } else {
                    if ($qty !== null && $qty > 0){
                        $stock_status = 'instock';
                    }
                }
            }

            if($args['manage_stock'] ?? true) 
            {
                if ($qty !== null){
                    update_post_meta($variation_id, '_stock', $qty);
                }
                
                if (isset($args['manage_stock'])){
                    update_post_meta($variation_id, '_manage_stock', $args['manage_stock'] ? 'yes' : 'no');
                }

                update_post_meta($variation_id, '_stock_status', $stock_status);

                $backorders = $args['backorders_allowed'] ?? $args['backorders'] ?? null;
                if ($backorders !== null){
                    if (is_bool($backorders)){
                        $backorders == ($backorders ? 'yes' : 'no');
                    }

                    update_post_meta($variation_id, '_backorders', $backorders);  // 'yes', 'no' or 'notify'
                }

                if (isset($args['low_stock'])){
                    update_post_meta($variation_id, '_low_stock_amount', $args['low_stock']); 
                }                
            }
        }
     
        // Featured image
        $featured_img = $args['featured_image'] ?? $args['image'] ?? null;

        if (is_array($featured_img) && !isset($featured_img['src'])){
            throw new \InvalidArgumentException("Invalid Array format for featured_image");
        }

        $img_url      = is_string($featured_img) ? $featured_img : ($featured_img['src'] ?? null);
        $att_id       = static::uploadImage($img_url);

        if (!empty($att_id)){
            static::setDefaultImage($variation_id, $att_id);
        }

        // Completar con demas campos posiblemente presentes en variacions	
    
        $variation->save();
    
        return $variation_id;
    }

    // Utility function that returns the correct product object instance
    static function createProductByObjectType( $type = 'simple') {
        // Get an instance of the WC_Product object (depending on his type)
        if($type === 'variable' ){
            $product = new \WC_Product_Variable();
        } elseif($type === 'grouped' ){
            $product = new \WC_Product_Grouped();
        } elseif($type === 'external' ){
            $product = new \WC_Product_External();
        } elseif($type === 'simple' )  {
            $product = new \WC_Product_Simple(); 
        } 
        
        if( ! is_a( $product, 'WC_Product' ) )
            return false;
        else
            return $product;
    }

    /*
        Devuelve variation o falla sino es variation o no existe

        @param int $pid
        @param string $return que pueede ser 'OBJECT' o 'ARRAY' 

        Ej:

        $data = Products::getVariation($variation_id, 'ARRAY');
        $attr = $data['attributes'];
    */
    static function getVariation($pid, string $return = 'OBJECT'){
        $type = static::getProductType($pid);

        if ($type != 'variation'){
            throw new \InvalidArgumentException("PID do not correspond to Product variation");
        } 

        $ret = static::getProductById($pid);

        if ($return == 'ARRAY'){
            $ret = $ret->get_data();
        }

        return $ret;
    }

    /*
        Devuelve variation ids
    */
    static function getVariationIds($product)
    {
        $product = static::getProduct($product);

        if (static::getProductType($product) != 'variable'){
            throw new \InvalidArgumentException("Unexpected product type. Expected 'variable'");
        }

        return $product->get_children();
    }

    /*
		$product es el objeto producto
		$taxonomy es opcional y es algo como '`pa_talla`'
	*/
	static function getVariationAttributes($product, $taxonomy = null){
		$attr = [];

		if ( $product->get_type() == 'variable' ) {
			foreach ($product->get_available_variations() as $values) {
				foreach ( $values['attributes'] as $attr_variation => $term_slug ) {
					if (!isset($attr[$attr_variation])){
						$attr[$attr_variation] = [];
					}

					if ($taxonomy != null){
						if( $attr_variation === 'attribute_' . $taxonomy ){
							if (!in_array($term_slug, $attr[$attr_variation])){
								$attr[$attr_variation][] = $term_slug;
							}                        
						}
					} else {
						if (!in_array($term_slug, $attr[$attr_variation])){
							$attr[$attr_variation][] = $term_slug;
						} 
					}

				}
			}
		}

		$arr = [];
		foreach ($attr as $taxonomy_name => $ar){            
            $key = Strings::after($taxonomy_name, 'attribute_');
            
            if (!Strings::startsWith('pa_', $key)){
                $key = 'pa_' . $key;
            }
            
            foreach ($ar as $e){
				$arr[$key]['term_names'][] = $e;
			}

			$arr[$key]['is_visible'] = true; 
		}

		/*
			array(
				// Taxonomy and term name values
				'pa_color' => array(
					'term_names' => array('Red', 'Blue'),
					'is_visible' => true,
					'for_variation' => false,
				),
				'pa_tall' =>  array(
					'term_names' => array('X Large'),
					'is_visible' => true,
					'for_variation' => false,
				),
			),
  		*/
		return $arr;
	}


    /*
        Obtener todos los custom attributes disponibles para productos variables

        Salida:

        array (
            'id:18' => 'att_prueba',
            'id:14' => 'bioequivalente',
            'id:3' => 'codigo_isp',
            'id:15' => 'control_de_stock',
            'id:2' => 'dosis',
            'id:8' => 'enfermedades',
            'id:10' => 'es_medicamento',
            'id:13' => 'forma_farmaceutica',
            'id:5' => 'laboratorio',
            'id:9' => 'mostrar_descr',
            'id:11' => 'otros_medicamentos',
            'id:17' => 'precio_easyfarma_plus',
            'id:7' => 'precio_fraccion',
            'id:6' => 'precio_x100',
            'id:12' => 'principio_activo',
            'id:4' => 'req_receta',
            'id:16' => 'size',
        )

        antes getAttributeTaxonomies()
    */
    static function getCustomAttributeTaxonomies(){
        $attributes = wc_get_attribute_taxonomies();
        $slugs      = wp_list_pluck( $attributes, 'attribute_name' );
        
        return $slugs;
    }

    /*
        Crea los atributos (sin valores) que se utilizan normalmente con productos variables
        (re-utilizables)
     
        Uso:
				
		static::createAttributeTaxonomy(Precio EasyFarma Plus', 'precio_easyfarma_plus');

        Los atributos son creados en la tabla `wp_woocommerce_attribute_taxonomies`

     */
    static function createAttributeTaxonomy($name, $new_slug, $translation_domain = null) 
    {
        $attributes = wc_get_attribute_taxonomies();

        $slugs = wp_list_pluck( $attributes, 'attribute_name' );

        if ( ! in_array( $new_slug, $slugs ) ) {

            if ($translation_domain != null){
                $name  = __($name, $translation_domain );
            }

            $args = array(
                'slug'    => $new_slug,
                'name'    => $name,
                'type'    => 'select',
                'orderby' => 'menu_order',
                'has_archives'  => false,
            );

            $result = wc_create_attribute( $args );

        }
    }

    /*
        @param $product product object
        @param $attr size | color, etc
        @param $by_term_id bool by default gets name.  
        @return Array of terms (values)

        Podría cachearse !
    */
    static function getAttributesInStock($product, $attr, $by_term_id = false) {
        if (!$product->is_type('variable') ) {
            //throw new \InvalidArgumentException("Only variable products are accepted");
            return;
        }    

        $taxonomy    = 'pa_' . $attr; // The product attribute taxonomy
        $sizes_array = []; 

        // Loop through available variation Ids for the variable product
        foreach( $product->get_children() as $child_id ) {
            $variation = wc_get_product( $child_id ); // Get the WC_Product_Variation object

            if( $variation->is_purchasable() && $variation->is_in_stock() ) {
                $term_name = $variation->get_attribute( $taxonomy );

                if ($by_term_id){
                    $term = get_term_by('name', $term_name, 'pa_' . $attr);

                    if ($term === null || !is_object($term)){
                        continue;
                    }

                    $sizes_array[$term_name] = $term->term_id;
                } else {
                    $sizes_array[$term_name] = $term_name;
                }
            }
        }

        return $sizes_array;
    }

    /*
        Quizás de poca utilidad porque no toma en cuenta el stock
    */
    static function getProductsByTermID($term_id){
        global $wpdb;

		$sql = "SELECT * from `{$wpdb->prefix}term_relationships` WHERE term_taxonomy_id = $term_id";
		$arr = $wpdb->get_results($sql, ARRAY_A);

        return array_column($arr, 'object_id');
    }

    /*
        @param $att attribute, ej: 'talla-de-ropa'
        @param $cat category
        @return Array of terms

        No permite filtrar por stock (!)

        array (
            280 => 
            array (
                'slug' => '10-2',
                'name' => '10',
            ),
            281 => 
            array (
                'slug' => '12-2',
                'name' => '12',
            ),
            //  ...
        )
    */
    static function getAttrByCategory($att, $cat){
        $arr = [];

        if (!is_array($cat)){
            $cat = [ $cat ];
        }

        $args = array(
            'category'  => $cat
        );
        
        foreach( wc_get_products($args) as $product ){	
            foreach( $product->get_attributes() as $attr_name => $attr ){
                if ($attr_name != 'pa_' . $att){
                    continue;
                }
        
                foreach( $attr->get_terms() as $term ){

                    if (!in_array($term->name, $arr)){
                        $term_name = $term->name; // name
                        $term_slug = $term->slug; // slug
                        $term_id   = $term->term_id; // Id
                        $term_link = get_term_link( $term ); // Link

                        $arr[$term_id] = [
                            'slug' => $term->slug,
                            'name' => $term->name
                        ];
                    }
                }
            }
        }

        return $arr;
    }

    /*
        Devuelve custom attributes de productos simples. NO confundir con metas

        Ejemplo de uso:

        static::getCustomAttr($pid)

        o

        static::getCustomAttr($pid, 'Código ISP')

        Salida:

         array (
            'name' => 'Código ISP',
            'value' => 'F-983/13',
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 0,
        )
    */
    static function getCustomAttr($pid, $attr_name = null){
        global $wpdb;

        // if (!is_int($pid)){
        //     throw new \InvalidArgumentException("PID debe ser un entero.");
        // }

        // $pid = (int) $pid;

        $sql = "SELECT meta_value FROM `{$wpdb->prefix}postmeta` WHERE post_id = '$pid' AND meta_key = '_product_attributes'";

        $res = $wpdb->get_results($sql, ARRAY_A);   

        if (empty($res)){
            return;
        }

        $attrs = unserialize($res[0]['meta_value']);

        if (!empty($attr_name)){
            foreach ($attrs as $at){
                if ($at['name'] == $attr_name){
                    return $at;
                }
            }

            return;
        }

        return $attrs;
    }

    /*
        Uso:

        static::getCustomAttrByLabel('Precio por 100 ml o 100 G')

        Salida:

        array (
            'attribute_id' => '6',
            'attribute_name' => 'precio_x100',
            'attribute_label' => 'Precio por 100 ml o 100 G',
            'attribute_type' => 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public' => '0',
        )
    */

    static function getCustomAttrByLabel($label){
        global $wpdb;

        $sql = "SELECT * FROM `{$wpdb->prefix}woocommerce_attribute_taxonomies` WHERE attribute_label = '$label'";

        $res = $wpdb->get_results($sql, ARRAY_A);   

        if (empty($res)){
            return;
        }

        return $res[0];
    }


    /*
        Forma de uso:

        static::getCustomAttrByName('forma_farmaceutica')

        Salida:

        array (
            'attribute_id' => '6',
            'attribute_name' => 'precio_x100',
            'attribute_label' => 'Precio por 100 ml o 100 G',
            'attribute_type' => 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public' => '0',
        )        
    */
    static function getCustomAttrByName($name){
        global $wpdb;

        $sql = "SELECT * FROM `{$wpdb->prefix}woocommerce_attribute_taxonomies` WHERE attribute_name = '$name'";

        $res = $wpdb->get_results($sql, ARRAY_A);   

        if (empty($res)){
            return;
        }

        return $res[0];
    }

    /*
        Get attribute(s) from VARIANT or VARIATION
    */
    static function getAttributesByVariation($product, $taxonomy = null){
        if (!empty($taxonomy)){
            if (substr($taxonomy, 0, 3) != 'pa_'){
                $taxonomy = 'pa_' .$taxonomy;
            }
        }

        if ( $product->get_type() != 'variation' ) {

            if ($product->get_type() == 'variable'){
                $variations = $product->get_available_variations();

                $ret = [];
                foreach($variations as $variation){
                    $attrs = $variation["attributes"];

                    if (!empty($taxonomy)){
                        if (!isset($attrs['attribute_' . $taxonomy])){
                            if (isset($attrs[$taxonomy])){
                                $ret[] = $attrs[$taxonomy];
                            }
                        } else {
                            $ret[] = $attrs['attribute_' . $taxonomy];
                        }
                    } else {
                        $ret[] = $attrs;     
                    }
                }

                return $ret;
            }

            //throw new \InvalidArgumentException("Expected variation. Given ". $product->get_type());
        }

        $attrs = $product->get_attributes($taxonomy);

        if (!empty($taxonomy)){
            if (isset($attrs[$taxonomy])){
                return $attrs[$taxonomy];
            } else {
                // caso de que pertenezcan a otra taxonomía
                return [];
            }
        }

        return $attrs;
    }


    static function getProductsByCategoryName(string $cate_name, $in_stock = true, $conditions = []){
        $categos = !is_array($cate_name) ? [ $cate_name ] : $cate_name;

        $query_args = array(
            'category' => $categos,
        );

        if ($in_stock){
            $query_args['stock_status'] = 'instock';
        }

        if (!empty($conditions)){
            $query_args = array_merge($query_args, $conditions);
        }

        return wc_get_products( $query_args );
    }

     /*
		Status

		En WooCommerce puede ser publish, draft, pending
		En Shopify serían active, draft, archived
	*/
    static function convertStatusFromShopifyToWooCommerce(string $status, bool $strict = false){
        $arr = [
            'active'   => 'publish',
            'archived' => 'draft',
            'draft'    => 'draft' 
        ];

        if (in_array($status, $arr)){
            return $arr[$status];
        }

        if ($strict){
            throw new \InvalidArgumentException("Status $status no válido para Shopify");
        }

        return $status;
    }

    static function convertStatusFromWooCommerceToShopify(string $status, bool $strict = false) {
        $arr = [
            'publish' => 'active',
            'draft'   => 'draft', 
            'pending' => 'draft'
        ];

        if (in_array($status, $arr)){
            return $arr[$status];
        }

        if ($strict){
            throw new \InvalidArgumentException("Status $status no válido para Shopify");
        }

        return $status;
    }

    static function hide($product){
        if (is_object($product)){
            $pid = $product->get_id();
        } else {
            $pid = $product;
        }

        $terms = array('exclude-from-search', 'exclude-from-catalog' ); // for hidden..
        wp_set_post_terms($pid, $terms, 'product_visibility', false); 
    }

    static function unhide($product){
        if (is_object($product)){
            $pid = $product->get_id();
        } else {
            $pid = $product;
        }

        $terms = array();
        wp_set_post_terms($pid, $terms, 'product_visibility', false); 
    }

    static function duplicate($pid, callable $new_sku = null, Array $props = []){
        $p_ay = static::dumpProduct($pid);

        if (!is_null($new_sku) && is_callable($new_sku)){
            // Solo valido para un solo duplicado porque sino deberia mover el contador
            $p_ay['sku'] = $new_sku($p_ay['sku']);

            if (static::productExists($p_ay['sku'])){
                //dd("Producto con SKU '{$p_ay['sku']}' ya existe. Abortando,...");
                return;
            }

        } else {        
            $p_ay['sku'] = null;
        }

        $p_ay = array_merge($p_ay, $props);

        if (is_cli()){
            dd($p_ay, $pid);
        }

        $dupe = static::createProduct($p_ay);

        return $dupe;
    }

    /*
        Mejor que $p->get_sku() ya que no requiere sea estrictamente numerico el SKU

        Realmente es el $product_id que es buscado como _sku
    */
    static function getSkuByProductId($product_id)
    {
        return static::getMeta($product_id, '_sku', true);
    }

    // 9-ene 
    static function getSKUs($post_status = null, $limit = null, $offset = null, $attributes = null, $order_by = null)
    {
        $pids = static::getIDs(static::$post_type, $post_status, $limit, $offset, $attributes, $order_by);
        
        $skus = [];
        foreach ($pids as $pid){
            $skus[$pid] = static::getSkuByProductId($pid);
        }

        return $skus;
    }

    // before load_template_part()
    static function loadTemplatePart($slug, $name  = '') {
        ob_start();
        wc_get_template_part($slug, $name); // WC
        $var = ob_get_contents();
        ob_end_clean();
        return $var;
    }
    
    /*
        Fuente:

        \wp-content\plugins\woocommerce\includes\data-stores\class-wc-product-data-store-cpt.php
    */
    static function clearCache(&$product)
    {
        wc_delete_product_transients($product->get_id());

        if ( $product->get_parent_id( 'edit' ) ) {
            wc_delete_product_transients( $product->get_parent_id( 'edit' ) );
            \WC_Cache_Helper::invalidate_cache_group( 'product_' . $product->get_parent_id( 'edit' ) );
        }
        
        \WC_Cache_Helper::invalidate_attribute_count( array_keys( $product->get_attributes() ) );
        \WC_Cache_Helper::invalidate_cache_group( 'product_' . $product->get_id() );    
    }

    // Get Products by Category

    /*  
        Alias de getPostsByCategory()

        Nota: 
        
        Hace AND entre cada categoria en la query
    */
    static function getProductsByCategory(string $by, Array $catego_ids, $post_status = null)
    {
        return static::getPostsByCategory($by, $catego_ids, $post_status);
    }

    static function getProductsByCategoryID(Array $cate_ids, $post_status = null){
        return static::getProductsByCategory('id', $cate_ids, $post_status);  // no seria "ID" ?
    }


    // Get category

    /*
        CategoryName  <- ProductID
    */
    static function getCategoryNameByProductID($cat_id){
        return parent::getCategoryNameByID($cat_id);
    }

    // Get categories

    /*
        Category IDs  <- ProductID
    */
    static function getCategoriesByProductID($pid){
        return static::getCategoriesById($pid);
    }

    /*
        Category IDs  <- ProductID
    */    
    static function getCategoriesById($pid){
        return wc_get_product_term_ids($pid, static::$cat_metakey);
    }

    /*
        Category IDs  <- SKU
    */
    static function getCategoriesByProductSKU($sku){
        $pid = static::getProductIDBySKU($sku);
        
        return static::getCategoriesByProductID($pid);
    }  

    /*
        Devuelve toda las categorias de un post

        Ej:

        $post_cats = [];
        foreach ($pids as $pid){
            $post_cats[$pid] = array_column(Products::getCategoriesByPost($pid), 'slug');
        }
    */
    static function getCategoriesByPost($pid, $output = ARRAY_A){
        $post_cats = [];

        $cat_ids = static::getCategoriesById($pid);

        foreach ($cat_ids as $cid){
            $post_cats[] = static::getCategoryById($cid, $output);
	    }

        return $post_cats;
    }

    public static function getDownloadableProducts(bool $only_ids = false) {
        global $wpdb;
        
        $query = "
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm
                ON p.ID = pm.post_id
            WHERE p.post_type IN ('product','product_variation')
              AND p.post_status IN ('publish','private')
              AND pm.meta_key = '_downloadable'
              AND pm.meta_value = 'yes'
        ";
        
        // Ejecuta la consulta y obtiene los resultados
        $downloadable_products = $wpdb->get_results($query);

        if ($only_ids){
            return array_column($downloadable_products, 'ID');
        }

        // Retorna los resultados como un array de productos descargables
        return $downloadable_products;
    }

    /*
        Devuelve algo como:

        Array
        (
        [0] => Array
            (
                [ID] => 15
                [title] => Archivo 1B
                [files] => Array
                    (
                        [0] => http://taxes4pros.lan/wp-content/uploads/woocommerce_uploads/2024/11/400_parole_composte-bk62hr.txt
                    )

            )

        [1] => Array
            (
                [ID] => 20
                [title] => Archivo 2B
                [files] => Array
                    (
                        [0] => http://taxes4pros.lan/wp-content/uploads/2024/11/1000_parole_italiane_comuni.txt
                    )

            ),
        ...
        )
    */
    static function getDownloadableProductsWithFiles() {
        global $wpdb;

        // Consulta para obtener los IDs y títulos de productos descargables
        $query = "
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm
                ON p.ID = pm.post_id
            WHERE p.post_type IN ('product','product_variation')
              AND p.post_status IN ('publish','private')
              AND pm.meta_key = '_downloadable'
              AND pm.meta_value = 'yes'
        ";

        // Ejecuta la consulta y obtiene los resultados
        $downloadable_products = $wpdb->get_results($query);

        // Array para almacenar los productos con sus archivos descargables
        $products_with_files = [];

        foreach ($downloadable_products as $product) {
            // Obtiene los archivos descargables del producto
            $files_meta = get_post_meta($product->ID, '_downloadable_files', true);
            
            // Inicializa el array de URLs
            $file_urls = [];

            // Verifica y procesa los archivos si existen
            if (!empty($files_meta) && is_array($files_meta)) {
                foreach ($files_meta as $file) {
                    $file_urls[] = $file['file']; // Añade la URL del archivo al array
                }
            }

            // Añade el producto con sus archivos al array final
            $products_with_files[] = [
                'ID' => $product->ID,
                'title' => $product->post_title,
                'files' => $file_urls,
            ];
        }

        return $products_with_files;
    }

    /*
        Devuelve algo como:

        Array
        (
            [0] => Array
                (
                    [ID] => 15
                    [title] => Archivo 1B
                    [files] => Array
                        (
                            [0] => http://taxes4pros.lan/wp-content/uploads/woocommerce_uploads/2024/11/400_parole_composte-bk62hr.txt
                        )

                    [downloads_remaining] => -1
                    [access_expires] =>
                )
        ,...
    */
    static function getDownloadableProductsWithDetails($product_id = null) {
        global $wpdb;
    
        // Obtener el prefijo de las tablas
        $prefix = $wpdb->prefix;
    
        // Construir la consulta SQL con el prefijo dinámico
        $query = "
            SELECT p.ID, p.post_title, pm.meta_value AS downloadable, exp.meta_value AS download_expiry
            FROM {$prefix}posts AS p
            INNER JOIN {$prefix}postmeta AS pm ON p.ID = pm.post_id
            LEFT JOIN {$prefix}postmeta AS exp ON p.ID = exp.post_id AND exp.meta_key = '_download_expiry'
            WHERE p.post_type IN ('product','product_variation')
              AND p.post_status IN ('publish','private')
              AND pm.meta_key = '_downloadable'
              AND pm.meta_value = 'yes'
        ";
    
        // Si se pasa un $product_id, agregarlo a la consulta para filtrar por ese producto
        if ($product_id) {
            $query .= $wpdb->prepare(" AND p.ID = %d", $product_id);
        }
    
        // Ejecuta la consulta y obtiene los resultados
        $downloadable_products = $wpdb->get_results($query);
    
        // Array para almacenar los productos con sus archivos descargables y detalles
        $products_with_details = [];
    
        foreach ($downloadable_products as $product) {
            // Obtiene los archivos descargables del producto
            $files_meta = get_post_meta($product->ID, '_downloadable_files', true);
            $file_urls = [];
    
            if (!empty($files_meta) && is_array($files_meta)) {
                foreach ($files_meta as $file) {
                    $file_urls[] = $file['file'];
                }
            }
    
            // Obtiene el límite de descargas
            $download_limit = get_post_meta($product->ID, '_download_limit', true);
            // Procesa los valores para 'downloads_remaining'
            $downloads_remaining = ($download_limit === '') ? '∞' : $download_limit;
    
            // Procesar la fecha de expiración
            $access_expires = $product->download_expiry;
    
            // Añade el producto con todos los detalles al array final
            $products_with_details[] = [
                'ID' => $product_id ?? $product->ID,
                'title' => $product->post_title,
                'files' => $file_urls,
                'downloads_remaining' => $downloads_remaining,
                'access_expires' => $access_expires,
            ];
        }
    
        return $products_with_details;
    }

    static function getPurchasedDownloableProducts($user_id, $day_period = null){
        $prods = static::getPurchasedProducts($user_id, $day_period);

        $ret = [];
        foreach ($prods as $p){
            if ($p['is_downloadable']){
                $ret[] = $p;
            }
        }

        return $ret;
    }
    

    /*
        Devuelve un array como:
        
        Array
        (
            [0] => Array
                (
                    [ID] => 21
                    [type] => simple
                    [is_virtual] => 1
                    [is_downloadable] => 1
                )
            , ...
        )
    */
    static function getPurchasedProducts($user_id, $day_period = null) {
        // Verificar que el user_id sea válido
        if (!is_numeric($user_id) || $user_id <= 0) {
            return [];
        }
        
        $purchased_products = [];
        $args = [
            'customer_id' => $user_id,
            'status'      => 'completed',
            'limit'       => -1
        ];
        
        // Si se especifica day_period, agregar filtro de fecha
        if ($day_period !== null && is_numeric($day_period)) {
            $today = Date::datetime('Y-m-d');
            $start_date = Date::subDays($today, $day_period);
            
            $args['date_created'] = '>=' . $start_date;
        }
        
        // Obtener las órdenes del usuario con los filtros aplicados
        $orders = wc_get_orders($args);
        
        // Iterar sobre cada orden
        foreach ($orders as $order) {
            // Obtener todos los productos de la orden
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    // Agregar los datos del producto al arreglo de resultados
                    $purchased_products[] = [
                        'ID'   => $product->get_id(),
                        'name' => $product->get_name(),
                        'type' => $product->get_type(),
                        'is_virtual' => $product->is_virtual(),
                        'is_downloadable' => $product->is_downloadable(),                        
                        'order_date'    => $order->get_date_created()->format('Y-m-d H:i:s'),
                        'order_number'  => $order->get_order_number(),
                        'regular_price' => $product->get_regular_price(),
                        'paid_price'    => $item->get_total()
                    ];
                }
            }
        }
        
        return $purchased_products;
    }
    

}