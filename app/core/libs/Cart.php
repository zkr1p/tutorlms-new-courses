<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
	@author boctulus
*/

class Cart 
{
	static function getCart($user_id = null)
	{
		if (is_cli()){
			dd("Cart no puede funcionar sin session de usuario y por ende con modo 'cli'");	
			exit;
		}

		if ( null === WC()->cart ) {
			WC()->cart = new \WC_Cart();
		}	

		if (empty($user_id)){			
			return WC()->cart;
		} else {
			return WC()->cart->get_cart_for_session($user_id);
		}
	}

	/*
		Devuelve la cantidad de productos distintos
	*/
	static function count(bool $distinct){
		if (!$distinct){
			return static::getCart()->get_cart_contents_count();
		}

		return count(static::getItems());
	}

	static function getItems(){
        $items = static::getCart()->get_cart();

        $arr = [];
        foreach($items as $item) { 
            $prod = wc_get_product( $item['product_id'] );

            $p = [];

            $p['id']  = $item['data']->get_id();

			// accepts 2 arguments ( size, attr )
            $p['img']  = $prod->get_image();

			$empty_img = Strings::contains('woocommerce-placeholder-', $p['img']);
		
			// Check if the product is a variable product and the parent product doesn't have an image
			if ($prod->is_type('variable') && $empty_img) 
			{	
				// Get variations of the parent product
				$variations = $prod->get_available_variations();

				// Find the first variation with an image
				foreach ($variations as $variation) {
					if ($variation['variation_id'] == $p['id']){
						$p['sku']           = $variation['sku'];
						$p['attrs']         = $variation['attributes'] ?? [];
						$p['color']         = $variation['attributes']['attribute_pa_color'] ?? null;
						$p['img_url']       = $variation['image']['gallery_thumbnail_src'];	
						$p['description']   = $variation['variation_description'];
						$p['weight']		= $variation['weight'];
						$p['dimensions']    = $variation['dimensions'];
						$p['in_stock']		= $variation['is_in_stock'];
					}
				}
			} else {
				$p['sku']			= get_post_meta($item['product_id'] , '_sku', true);
				$p['title']         = $prod->get_title();
				$p['description']   = $prod->get_description();
				$p['weight']		= $prod->get_weight();
				$p['dimensions']    = $prod->get_dimensions();
				$p['in_stock']		= $prod->is_in_stock();
			}

            $p['title']         = $prod->get_title();
            $p['url']           = get_post_permalink($item['id']);
            $p['link']          = '<a href="'. get_post_permalink($p['id']). '">'. $p['title'] .'</a>';
            $p['qty']           = $item['quantity'];

            $p['line_subtotal']      = $item['line_subtotal']; 
            $p['line_subtotal_tax']  = $item['line_subtotal_tax'];

            // gets the cart item total
            $p['line_total']         = $item['line_total'];
            $p['line_tax']           = $item['line_tax'];

            // unit price of the product
            $p['item_price']         = $p['line_subtotal'] / $p['qty'];
            $p['item_tax']           = $p['line_subtotal_tax'] / $p['qty'];

            /*
                Product object data

                Nota: los precios en el carrito pueden no corresponderse a los precios actuales
            */

            $p['price']         = get_post_meta($item['product_id'] , '_price', true);
            $p['regular_price'] = get_post_meta($item['product_id'] , '_regular_price', true);
            $p['sale_price']    = get_post_meta($item['product_id'] , '_sale_price', true);
            
        	// Generate variation URL if product is variable
			if ($prod->is_type('variation')) {
				$parent_product_id = $prod->get_parent_id();
				$parent_product = wc_get_product($parent_product_id);

				$variation_url = $parent_product->get_permalink();

				$attributes = $item['variation'];

				foreach ($attributes as $attribute => $value) {
					$variation_url .= '&attribute_' . $attribute . '=' . $value;
				}

				$p['url'] = $variation_url;
			} else {
				$p['url'] = get_post_permalink($item['product_id']);
			}
            
            $arr[] = $p;
        }

        return $arr;    
    }

	/*
		Devuelve identificador dentro del carrito para productos de tipo Simple
	*/
	static function findSimple($product_id){
		$cart = static::getCart();

		$product_cart_id = $cart->generate_cart_id(	 $product_id );
   		$cart_item_key   = $cart->find_product_in_cart( $product_cart_id );

		return $cart_item_key;
	}

	/*
		Devuelve identificador dentro del carrito para Variantes
	*/	
	static function findVariation($variation_id){
		$cart = static::getCart();
		$cart_items = $cart->get_cart();
	
		foreach ($cart_items as $cart_item_key => $cart_item) {
			if ($cart_item['variation_id'] == $variation_id) {
				return $cart_item_key;
			}
		}
	
		return false;
	}

	static function find($product_id){
		$prod = wc_get_product($product_id);

		// variante
		if (!$prod->is_type('simple')){
			$cart_item_key = static::findVariation($product_id);
		} else {
			// simple
			$cart_item_key = static::findSimple($product_id);
		}

		return $cart_item_key;
	}

	static function getQuantity($product_id){
		$cart_item_quantities = WC()->cart->get_cart_item_quantities();

		if (isset($cart_item_quantities[$product_id])) {
			return $cart_item_quantities[$product_id];
		} else {
			return 0;
		}
	}

	static function setQuantity($product_id, int $qty)
	{
		$cart_item_key = static::find($product_id);
		
		if (empty($cart_item_key)){
			Logger::logError("No se pudo encontrar item con pid=$product_id en carrito");
			return false;
		}

		$cart = static::getCart();

		//Logger::log("Seteando '$qty' unidades de $product_id");
		$cart->set_quantity( $cart_item_key, $qty );

		//Logger::dd(static::getQuantity($product_id), "Nueva cantidad para pid=$product_id en carrito");

		if (static::getQuantity($product_id) != $qty){
			//Logger::dd("No se pudo cambiar cantidad para item con pid=$product_id en carrito");
			return false;
		}

		return true;
	}

	/*
		Add to cart X units 
	*/
	static function add($product_id, $qty){
		$prev_qty = static::getQuantity($product_id); 

		$expected = $prev_qty + $qty;

		static::setQuantity($product_id, $expected);

		if (static::getQuantity($product_id) != $expected){
			return false;
		}

		// Logger::dd(static::getQuantity($product_id), "Nueva cantidad para pid=$product_id en carrito");

		return true;
	}

	// probar
	static function increment($product_id){
		return static::add($product_id, 1);
	}

	// substract N-units
	static function sub($product_id, int $qty){
		$prev_qty = static::getQuantity($product_id); 

		$expected = $prev_qty - $qty;

		if ($expected <= 1){
			return null;
		}

		static::setQuantity($product_id, $expected);

		if (static::getQuantity($product_id) != $expected){
			return false;
		}

		// Logger::dd(static::getQuantity($product_id), "Nueva cantidad para pid=$product_id en carrito");

		return true;
	}

	// decrementa en 1 unidad
	static function decrement($product_id){
		return static::sub($product_id, 1);
	}

	// delete
	static function remove($product_id){
		return static::setQuantity($product_id, 0);
	}

	static function addRandomly($qty = null, Array $product_ids = null){
		$product_ids = Products::getRandomProductIds($qty ?? 10);

		foreach ($product_ids as $pid){
			static::add($pid, rand(1,10));
		}
	}

	/*
		Vacia completamente el carrito
	*/
	static function empty() 
	{
		$cart = static::getCart(); 
		
		$cart_items = $cart->get_cart();
	
		foreach ($cart_items as $cart_item_key => $cart_item) {
			$cart->set_quantity( $cart_item_key, 0 );
		}
	}

	/*
		Deshabilita /cart

		Podria cargar vistas de header y footer y mostrar mensaje diciendo que esta des-habilitado
	*/
	static function disableCart(){		
		if (rtrim(Url::currentUrl(), '/') == rtrim(wc_get_cart_url(), '/')){
			exit;
		}
	}

	static function disableCheckout(){
		if (rtrim(Url::currentUrl(), '/') == rtrim(wc_get_checkout_url(), '/')){
			exit;
		}
	}

	/*
		Redirecciona desde /cart a otro slug

		Precond: no se debe haber aplicado el filtro para cambiar el slug
		ya que cambiaria la url y ya no habria coincidencia
	*/
	static function cartRedirect(string $slug){        
		if (rtrim(Url::currentUrl(), '/') == rtrim(wc_get_cart_url(), '/')){
			wp_redirect(home_url($slug));
			exit;
		}
	}

	/*
		Redirecciona desde /checkout a otro slug

		Precond: no se debe haber aplicado el filtro 'woocommerce_get_checkout_url'
		ya que cambiaria la url del checkout y ya no habria coincidencia
	*/
	static function checkoutRedirect($slug){
		if (rtrim(Url::currentUrl(), '/') == rtrim(wc_get_checkout_url(), '/')){
			wp_redirect(home_url($slug));
			exit;
		}
	}
}