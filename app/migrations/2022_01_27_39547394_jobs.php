<?php

use boctulus\TutorNewCourses\core\interfaces\IMigration;
use boctulus\TutorNewCourses\core\libs\Schema;

class Jobs implements IMigration
{
    /**
	* Run migration.
    *
    * @return void
    */
    public function up()
    {
        $sc = new Schema('jobs');
        $sc
        ->int('id')->primary()->auto()
        ->varchar('class', 60)
        ->varchar('queue', 60)    // usar para que los workers sean específicos de una cola
        ->blob('object')
        ->blob('params')
        ->bool('taken')->default(0)
        ->datetime('created_at')
        ;
		$sc->create();		
    }

    function down(){
        Schema::dropIfExists('jobs');
    }
}


