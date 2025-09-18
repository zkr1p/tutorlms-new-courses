<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Logger;

/*
    Integracion con WooCommerce Memberships 

    (indepediente de WooCommerce Suscriptions)
*/
class Memberships
{    
    static function hasMembership($user_id = null, $membership_id = null){
        if (!function_exists('wc_memberships_get_user_memberships'))
        {
            admin_notice("WooCommerce Memberships es requerido", 'error');
            Logger::log("WooCommerce Memberships es requerido");

            return false;
        }

        if ($user_id === null){
            $user_id = Users::getCurrentUserId();

            // If it's Guest
            if ($user_id === 0){
                return false;
            }
        }

        $has_membership = false;

        if($membership_id !== null){
            // Verificar si el usuario tiene la membresía específica
            $has_membership = wc_memberships_is_user_active_member($user_id, $membership_id);
        } else {
            // Verificar si el usuario tiene cualquier membresía
            $memberships = wc_memberships_get_user_memberships($user_id);
            $has_membership = !empty($memberships);
        }

        return $has_membership;
    }
    
}