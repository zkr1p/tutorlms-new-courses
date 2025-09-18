<?php

namespace boctulus\TutorNewCourses\models;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Model;

class MyModel extends Model {
    function wp(){
		global $wpdb;
		return $this->prefix($wpdb->prefix);
	}

    protected function boot(){
        if (empty($this->prefix) && DB::isDefaultOrNoConnection()){
			$this->wp();
		}       
    }

    protected function init(){		
		
	}
}