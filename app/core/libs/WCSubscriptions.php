<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

namespace boctulus\TutorNewCourses\core\libs;

/**
 * Classe per gestire le sottoscrizioni WooCommerce per WooCommerce Subscriptions plugin
 * 
 * @author Pablo Bozzolo < boctulus@gmail.com >
 */
class WCSubscriptions
{    
    /**
     * Costruttore della classe.
     * 
     * Verifica se la funzione wcs_get_users_subscriptions è disponibile, altrimenti lancia un'eccezione.
     */
    function __construct(){
        if (!function_exists('wcs_get_users_subscriptions'))
        {
            throw new \Exception("WooCommerce Subscriptions è richiesto");
        }
    }

    /*
        Riceve l'ID del post dal record di abbonamento e restituisce l'ID dell'utente.
    */
    function getUserSubscriptor($subscription_id)
    {
        $subscription = wcs_get_subscription( $subscription_id );
        
        return $subscription->get_user_id();
    }

    /*  
        Restituisce le sottoscrizioni.

        Accetta user_id e status come filtri.
    */
    function get($user_id = null, $status = null)
    {
        $subs = [];

        $uids    = ($user_id == null) ? Users::getUserIDList() : [ $user_id ];

        foreach ($uids as $user_id){
            $subscriptions = wcs_get_users_subscriptions( $user_id );
            
            foreach ($subscriptions as $subscription) {
                // filtro. Potrebbe essere "active", "on-hold" o "cancelled"
                if ($status != null && $subscription->get_status() != $status) {
                    continue;
                }

                $subs[] = [
                    'id'      => $subscription->get_id(),
                    'status'  => $subscription->get_status(),
                    'user_id' => !empty($user_id) ? $user_id : $subscription->get_user_id(), 
                ];
            }
        }

        return $subs;
    }

    /*
        Devuelve la frecuencia de renovacion de subscripcion
        asumiento tiene una sola

        @return int frequency

        Se puede usar en conjunto con hasActive() ya que si tiene
        una subscripcion activa sera la ultima tambien

        $wc_subs   = new WCSubscriptions();
        $freq      = $wc_subs->getRenovationFrequency($uid));
        $is_active = $wc_subs->hasActive($uid));
    */
    function getRenovationFrequency($user_id){
        static $freq_ay;

        if (!is_array($freq_ay)){
            $freq_ay = [];
        } else {
            if (isset($freq_ay[$user_id]) && $freq_ay[$user_id] !== null){
                return $freq_ay[$user_id];
            }
        }

        $orders = Orders::getOrdersByUserId($user_id);

        $freq  = null;
        // Las ordenes son recuperadas por ID de forma DESC (la ultima primero)
        foreach ($orders as $order){
            // dd($order->get_id());

            $items = $order->get_items(); 
            // dd($items, "ITEMS for $order_id");
            
            foreach ($items as $item){
                $product_id = $item->get_product_id();
                $freq       = Products::getMeta($product_id, '_subscription_period');

                if (!empty($freq)){
                    break;
                }
            }
        }

        $freq_ay[$user_id] = $freq;

        return $freq;
    }

    /**
     * Verifica se un utente ha una sottoscrizione attiva in WooCommerce Subscriptions.
     *
     * @param int $user_id L'ID dell'utente.
     * @return bool True se l'utente ha una sottoscrizione attiva, altrimenti False.
     * 
     * Nome precedente: isActive
     */
    function hasActive($user_id) {
        // Controlla se l'utente ha sottoscrizioni attive escludendo gli stati "on-hold" e "cancelled".
        $subscriptions = wcs_get_users_subscriptions( $user_id );
        
        foreach ($subscriptions as $subscription) {            
            // Verifica che lo stato della sottoscrizione non sia "on-hold" né "cancelled".
            $status = $subscription->get_status();

            if ($status == 'active' ) {
                return true;
            }
        }
        
        return false;
    }
    
}