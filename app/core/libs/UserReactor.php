<?php

namespace boctulus\TutorNewCourses\core\libs;

abstract class UserReactor
{
    protected $action;
    
    function __construct()
    {
        // Agregar acciones específicas de usuarios
        add_action('user_register', [$this, 'sync_on_create_user'], 10, 1);
        add_action('profile_update', [$this, 'sync_on_update_user'], 10, 1);
        add_action('delete_user', [$this, 'sync_on_delete_user'], 10, 1);
    }

    function log($pid){
		$title     = get_the_title($pid);
    	$post_type = get_post_type($pid);

		Logger::dd("$title [$post_type][post_id=$pid]", $this->action);
	}

    // Métodos específicos para usuarios
    function sync_on_create_user($user_id)
    {
        $this->action = 'create_user';
        $this->__onCreate($user_id);
    }

    function sync_on_update_user($user_id)
    {
        $this->action = 'edit_user';
        $this->__onUpdate($user_id);
    }

    function sync_on_delete_user($user_id)
    {
        $this->action = 'delete_user';
        $this->__onDelete($user_id);
    }

   
    // Métodos abstractos para implementar en las subclases
    protected function __onCreate($user_id)
    {
        $this->onCreate($user_id);
    }

    protected function __onUpdate($user_id)
    {
        $this->onUpdate($user_id);
    }

    protected function __onDelete($user_id)
    {
        $this->onDelete($user_id);
    }

    // Métodos que las subclases podrian implementar
    function onCreate($user_id){}
    function onUpdate($user_id){}
    function onDelete($user_id){}
}
