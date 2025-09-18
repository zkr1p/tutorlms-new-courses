<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
   	@author Pablo Bozzolo (2023)

	Reacciona a la creacion, modificacion, borrado y restauracion de posts
	incluidos Custom Post Types

	NO funciona con otras tablas como las de Users 

	Para Users extender UserReactor en su lugar
*/

abstract class Reactor
{
	protected $action;
	protected $type;
	protected $ignored_types = [];

	function __construct($type = null)
	{
		if ($type != null){
			$this->type = $type;
		}		
		
		add_action('publish_post',       [$this, 'sync_on_create'], 10, 1 );
		add_action('save_post',          [$this, 'sync_on_update'], 10, 1 );
		add_action('before_delete_post', [$this, 'sync_on_trash'], 10, 1);
		add_action('untrash_post',       [$this, 'sync_on_untrash'], 10, 1);
	}	

	protected function __get_pid($input)
    {
        if (empty($input)){
            return null;
        }

        if (is_numeric($input)){
            return $input;
        }
    
        $input_data = json_decode($input, true);

        if ($input_data !== null) {
            return Strings::match($input_data, '/post_id=([\d]+)/');
        } else {
            return null;
        }
    }

	function sync_on_create($pid) {
		$post_type = get_post_type($pid);

		if ($this->type !== null && $this->type != $post_type){
			return;
		}

		if (in_array($post_type, $this->ignored_types)){
			return;
		}

		$this->action = 'create';
	
		$this->__onCreate($this->__get_pid($pid));
	}

	function sync_on_update($pid) {
		$post_type = get_post_type($pid);

		if ($this->type !== null && $this->type != $post_type){
			return;
		}

		if (in_array($post_type, $this->ignored_types)){
			return;
		}

		$this->action = 'edit';
	
		$this->__onUpdate($this->__get_pid($pid));
	}

	function sync_on_trash($pid)
	{
		$post_type = get_post_type($pid);

		if ($this->type !== null && $this->type != $post_type){
			return;
		}

		if (in_array($post_type, $this->ignored_types)){
			return;
		}

		$this->action = 'trash';
		
		$this->__onDelete($this->__get_pid($pid));
	}

	function sync_on_untrash($pid)
	{
		$post_type = get_post_type($pid);

		if ($this->type !== null && $this->type != $post_type){
			return;
		}

		if (in_array($post_type, $this->ignored_types)){
			return;
		}

		$this->action = 'untrash';
		
		$this->__onRestore($this->__get_pid($pid));
	}

    /*
		Event Hooks
	*/
	
	protected function __onCreate($pid){	
        $this->onCreate($pid);
	}

	protected function __onUpdate($pid)
	{
        $this->onUpdate($pid);
	}

	protected function __onDelete($pid)
	{
        $this->onDelete($pid);
	}

	protected function __onRestore($pid)
	{	
        $this->onRestore($pid);
	}


	function onCreate ($pid){}
	function onUpdate ($pid){}
	function onDelete ($pid){}
	function onRestore($pid){}

}