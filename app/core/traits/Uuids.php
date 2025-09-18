<?php

namespace boctulus\TutorNewCourses\core\traits;

trait Uuids
{
    // protected function boot()
    // {
    //     parent::boot();

    //     // ...
    // }    

    protected function init()
    {
        parent::init();

        $this->registerInputMutator($this->getIdName(), function($id){ 
			return uuid_create(UUID_TYPE_RANDOM); 
		}, function($op, $dato){
            return ($op == 'CREATE');
		});  
    }    
}
