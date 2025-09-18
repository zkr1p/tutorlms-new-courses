<?php

use boctulus\TutorNewCourses\core\interfaces\IMigration;
use boctulus\TutorNewCourses\core\libs\Factory;
use boctulus\TutorNewCourses\core\libs\Schema;
use boctulus\TutorNewCourses\core\Model;
use boctulus\TutorNewCourses\core\libs\DB;

class DownloadableProductPermissionsNew implements IMigration
{
    /**
	* Run migration.
    *
    * @return void
    */
    public function up()
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $table = new Schema("{$prefix}downloadable_product_permissions_new");

        $table->increments('permission_id'); // Primary key
        $table->mediumtext('download_id');
        $table->ubig('product_id');
        $table->ubig('user_id');
        $table->ubig('download_count')->default(0);
        $table->timestamp('access_granted')/*->useCurrent()*/;

        // Índice único para evitar duplicados (no esta funcionando)
        // $table->addUnique(['download_id', 'product_id', 'user_id']);

        // $table->foreign('product_id')
        // ->references('post_id')
        // ->on("{$prefix}posts")
        // ->onDelete('cascade');

        // $table->foreign('user_id')
        // ->references('id')
        // ->on("{$prefix}users")
        // ->onDelete('cascade');

        $table->timestamps();

		$table->create();

        // UNIQUE sobre varios campos
        // DB::statement("ALTER TABLE `{$prefix}downloadable_product_permissions_new`
        //     ADD UNIQUE KEY `download_product_user_unique` (`download_id`, `product_id`, `user_id`);
        // ");
    }

    /**
	* Run undo migration.
    *
    * @return void
    */
    public function down()
    {
        ### DOWN
        Schema::dropIfExists('downloadable_product_permissions_new');
    }
}

