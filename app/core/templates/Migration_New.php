<?php

use boctulus\TutorNewCourses\core\interfaces\IMigration;
use boctulus\TutorNewCourses\core\libs\Factory;
use boctulus\TutorNewCourses\core\libs\Schema;
use boctulus\TutorNewCourses\core\Model;
use boctulus\TutorNewCourses\core\libs\DB;

class __NAME__ implements IMigration
{
    protected $table = '__TB_NAME__';

    /**
	* Run migration.
    *
    * @return void
    */
    public function up()
    {
        $sc = new Schema($this->table);
		// ...
        $sc->create();
    }

    /**
	* Run undo migration.
    *
    * @return void
    */
    public function down()
    {
        Schema::dropIfExists($this->table);
    }
}


