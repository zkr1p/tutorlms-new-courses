<?php

use boctulus\TutorNewCourses\core\interfaces\IMigration;
use boctulus\TutorNewCourses\core\libs\Factory;
use boctulus\TutorNewCourses\core\libs\Schema;
use boctulus\TutorNewCourses\core\Model;
use boctulus\TutorNewCourses\core\libs\DB;

class StarRating implements IMigration
{
    /**
	* Run migration.
    *
    * @return void
    */
    public function up()
    {
        $sc = new Schema('star_rating');

        $sc
        ->integer('id')->auto()->pri()
        ->text('comment')->nullable()
        ->int('score')
        ->varchar('author')
        ->datetime('deleted_at')
        ->datetime('created_at');

		$sc->create();
    }

    /**
	* Run undo migration.
    *
    * @return void
    */
    public function down()
    {
        $sc = new Schema('star_rating');
        $sc->dropTableIfExists();
    }
}

