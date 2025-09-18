<?php

use boctulus\TutorNewCourses\core\interfaces\IMigration;
use boctulus\TutorNewCourses\core\libs\Schema;

class JobProcess implements IMigration
{
    /**
	* Run migration.
    *
    * @return void
    */
    public function up()
    {
        $sc = new Schema('job_process');
        
        $sc->int('id')->pri()->auto();
        $sc->varchar('queue')->nullable()->index();
        $sc->int('job_id')->unique(); 
        $sc->int('pid')->unique(); 
        $sc->datetime('created_at');
		$sc->create();			
    }

    public function down()
    {
        Schema::dropIfExists('job_process');
    }
}


