<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Date;
use boctulus\TutorNewCourses\core\libs\Users;

/*
	@author boctulus
*/

class Coupons 
{
    static function hasGeneratedCoupons($user_id) {
        // Verificar si el usuario ya ha generado el grupo de cupones
        return get_user_meta($user_id, 'coupons_generated', true);
    }

    static function setCouponsGenerated($user_id) {
        // Establecer el estado de que el usuario ha generado el grupo de cupones
        update_user_meta($user_id, 'coupons_generated', true);
    }

	// https://stackoverflow.com/questions/55387296/first-order-discount-for-guest-customers-checking-woocommerce-orders-billing-ema
	static function hasBought( $customer_email ){
		$orders = get_posts( array(
			'numberposts' => -1,
			'post_type' => 'shop_order',
			'post_status' => array('wc-processing', 'wc-completed'),
		) );
	
		$email_array = array();
	
		foreach($orders as $order) {
			$order_obj = wc_get_order($order->ID);
			$order_obj_data = $order_obj->get_data();
			array_push($email_array, $order_obj_data['billing']['email']); 
		}

		if (in_array($customer_email, $email_array)) {
			return true;
		} else {
			return false;
		}
	}

    /*
        Obtener cupones restringidos a un correo $email
    */
    static function getByEmail(string $email)
    {
        $last_coupons = Posts::getLastNPost('*', 'shop_coupon', null, 184467440737095516, null, true);

        $coupons = [];
        foreach ($last_coupons as $c){
            $code = $c['post_title'];
            $date = $c['post_date'];	
            $mail = unserialize($c['meta']['customer_email'][0])[0];
        
            if ($mail == $email){          
                // dd([
                //     'code' => $code,
                //     'mail' => $mail
                // ], "Creation time: $date");

                $coupons[] = $c;
            }
        }

        return $coupons;
    }

    /*
        usage_limit_per_use: Este parámetro define el límite de veces que un usuario específico puede aplicar un cupón. Es decir, establece cuántas veces un mismo usuario puede utilizar el mismo cupón

        usage_limit: Este parámetro establece el límite total de veces que un cupón puede ser utilizado en total, sin importar el usuario.

        En WooCommerce, individual_use es otro parámetro relacionado con la configuración de cupones y se utiliza para determinar si un cupón específico se puede combinar con otros cupones en una única transacción. Vamos a explicar individual_use y cómo se relaciona con usage_limit_per_use y usage_limit:

        individual_use:

        Este parámetro toma un valor booleano (true o false).

        Si se establece en true, el cupón solo se puede aplicar si no hay otros cupones presentes en la transacción. En otras palabras, no se pueden combinar varios cupones.
        
        Si se establece en false (o no se establece), el cupón se puede utilizar en combinación con otros cupones en una única transacción.
    */
	
    static function createCoupon($discount, $discount_type, bool $individual_use, $usage_limit = false, $usage_limit_per_user = false, $user_ids = [], $emails = [], $code = null, $days_to_expire = false, $product_ids = [], $allow_free_shipping = true) {
        $exp_date = null;

        if (!in_array($discount_type, array('percent', 'fixed_cart'))) {
            throw new \Exception('Invalid discount_type');
        }

        if ($emails === null){
            $emails = [];
        }

        if (is_string($emails)){
            $emails = [$emails];
        }

        // Crear el cupón
        $coupon = array(
            'post_title'   => $code,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => Users::getAdminID(),
            'post_type'    => 'shop_coupon',
        );

        #Logger::log($coupon); ///

        // Insertar el cupón en la base de datos
        $coupon_id = wp_insert_post($coupon);

        #Logger::dd($coupon_id, 'coupon_id'); ///

        if (is_wp_error($coupon_id)) {
            wp_die($coupon_id->get_error_message());
        }

        // Asignar el cupón al usuario específico
        if (!empty($user_ids) && $coupon_id !== false) {
            if (!is_array($user_ids)) {
                $user_ids = [ $user_ids ];
            }

            foreach ($user_ids as $uid){
                $emails[] = Users::getEmailById($uid);
            }
        }

        if (!empty($emails)){
            update_post_meta($coupon_id, 'customer_email', $emails);       
        }

        // Configurar los detalles del cupón
        update_post_meta($coupon_id, 'discount_type', $discount_type);
        update_post_meta($coupon_id, 'coupon_amount', $discount);
        update_post_meta($coupon_id, 'individual_use', $individual_use ? 'yes' : 'no');

        if (!empty($usage_limit_per_user )){
            update_post_meta($coupon_id, 'usage_limit_per_use', $usage_limit_per_user);
        }

        if ($usage_limit !== false) {
            $exp_date = Date::addDays('', $days_to_expire);
            update_post_meta($coupon_id, 'usage_limit', $usage_limit);
        }

        if (!empty($days_to_expire) && !empty($exp_date)) {
            update_post_meta($coupon_id, 'expiry_date', strtotime($exp_date));
        }

        // Asignar product_ids al cupón
        if (!empty($product_ids)) {
            update_post_meta($coupon_id, 'product_ids', $product_ids);
        }

        // Permitir el envío gratuito
        if ($allow_free_shipping){
            update_post_meta($coupon_id, 'free_shipping', 'yes');
        }

        return $coupon_id;
    }
}