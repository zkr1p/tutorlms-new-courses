<?php

use boctulus\TutorNewCourses\core\interfaces\IMigration;
use boctulus\TutorNewCourses\core\libs\DB;

class Migrations implements IMigration
{
    function __construct(){
        get_default_connection();
    }
    
    /**
	* Run migration.
    *
    * @return void
    */
    public function up()
    {
        $driver = DB::driver();

        switch ($driver){
            case 'sqlite':
                DB::statement("
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    db varchar(50) DEFAULT NULL,
                    filename varchar(255) NOT NULL,
                    created_at DATETIME NULL
                );");
                break;

            case 'mysql':
                DB::statement("
                CREATE TABLE IF NOT EXISTS `migrations` (
                    `id` int(11) PRIMARY KEY NOT NULL,
                    `db` varchar(50) DEFAULT NULL,
                    `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `created_at` DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                DB::statement("
                ALTER TABLE `migrations`
                    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;");    

            break;

            default:
                throw new \InvalidArgumentException("Driver $driver is not fully supported for migrations");   
        }
    }
}

