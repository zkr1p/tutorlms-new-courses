<?php

use boctulus\TutorNewCourses\core\interfaces\IMigration;
use boctulus\TutorNewCourses\core\libs\Schema;

class BackgroundProcess implements IMigration
{
    /**
	* Run migration.
    *
    * @return void
    */
    public function up()
    {
        $sc = new Schema('background_process');
        $sc->int('id')->pri()->auto();
        $sc->varchar('filename')->unique();
        $sc->int('pid', 5)->unique(); 
        $sc->datetime('created_at');
		$sc->create();		
    }

    public function down()
    {
        Schema::dropIfExists('background_process');
    }
}

