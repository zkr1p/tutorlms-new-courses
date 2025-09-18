<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
    Alternative to OrderReactor

    Same behaviour but using WooCommerce specific-hooks
*/
abstract class OrderReactorV2
{
    protected $action;

    public function __construct() {
        // Registrar los hooks en el constructor
        add_action('woocommerce_new_order', [$this, 'sync_on_create']);
        add_action('woocommerce_update_order', [$this, 'sync_on_update']);
        add_action('woocommerce_delete_order', [$this, 'sync_on_trash']);
        add_action('woocommerce_restore_order', [$this, 'sync_on_untrash']);
    }

    // Sincronización de creación de pedido
    public function sync_on_create($order_id) {
        $this->action = 'create';
        $this->__onCreate($order_id);
    }

    // Sincronización de actualización de pedido
    public function sync_on_update($order_id) {
        $this->action = 'edit';
        $this->__onUpdate($order_id);
    }

    // Sincronización de eliminación de pedido (tras la papelera)
    public function sync_on_trash($order_id) {
        $this->action = 'trash';
        $this->__onDelete($order_id);
    }

    // Sincronización de restauración de pedido
    public function sync_on_untrash($order_id) {
        $this->action = 'untrash';
        $this->__onRestore($order_id);
    }

    /*
    Hooks de eventos
    */

    // Evento para cuando un pedido es creado
    protected function __onCreate($order) {
        $this->onCreate($order);
    }

    // Evento para cuando un pedido es actualizado
    protected function __onUpdate($order) {
        $this->onUpdate($order);
    }

    // Evento para cuando un pedido es eliminado (tras la papelera)
    protected function __onDelete($order) {
        $this->onDelete($order);
    }

    // Evento para cuando un pedido es restaurado
    protected function __onRestore($order) {
        $this->onRestore($order);
    }

    // Métodos abstractos que deben ser implementados por la clase hija
    public function onCreate($order){}
    public function onUpdate($order){}
    public function onDelete($order){}
    public function onRestore($order){}

    // Obtener el objeto de pedido a partir del ID
    public function getOrder($order_id) {
        return wc_get_order($order_id);
    }
}
