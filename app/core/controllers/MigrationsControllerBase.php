<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\controllers;

use Migrations;
use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Schema;
use boctulus\TutorNewCourses\core\libs\StdOut;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\controllers\MakeController;
use boctulus\TutorNewCourses\core\libs\PHPLexicalAnalyzer;

/*
    Migration commands
*/
class MigrationsControllerBase /* extends Controller */
{
    function make(...$opt) {
        return (new MakeController)->migration(...$opt);
    }
    
    /*
        Migrating: 2014_10_12_000000_create_users_table
        Migrated:  2014_10_12_000000_create_users_table
        Migrating: 2014_10_12_100000_create_password_resets_table
        Migrated:  2014_10_12_100000_create_password_resets_table
        Migrating: 2020_10_28_145609_as_d_f
        Migrated:  2020_10_28_145609_as_d_f

        php com migrations migrate --file=2021_09_14_27905675_user_sp_permissions.php
    */
    function migrate(...$opt) {
        $filenames = [];

        $file_opt  = false;
        $dir_opt   = false;
        $to_db     = null;
        $steps     = PHP_INT_MAX;
        $skip      = 0;
        $retry     = false;
        $ignore    = null; 
        $fresh     = false;
        $make      = false;
        
        $path = Constants::MIGRATIONS_PATH . DIRECTORY_SEPARATOR;

        StdOut::showResponse();

        foreach ($opt as $o)
        {
            if (preg_match('/^--to[=|:](.*)$/', $o, $matches)){
                $match = $matches[1];
                if (!preg_match('/^([a-z0-9_-]+)$/i', $match, $matches)){
                    throw new \InvalidArgumentException("Invalid indentifier '{$match}' for tenant id");
                }    

                $to_db = $matches[1];
            }

            if ('--retry' == $o || 'retry' == $o || '--force' == $o || 'force' == $o){
                $retry = true;
            }

            if ('--ignore' == $o){
                $ignore = true;
            }

            if ('--fresh' == $o){
                $fresh = true;
            }


            if (preg_match('/^--make[=|:](.*)$/', $o, $matches)){
                $make = $matches[1];
            }

            if (Strings::startsWith('--file=', $o)){
                $file_opt = true;

                $_f = substr($o, 7);

                if (Files::isAbsolutePath($_f)){
                    $path = Files::getDir($_f);
                    $_f = basename($_f);
                } else {
                    if (Strings::contains(DIRECTORY_SEPARATOR, $_f)){
                        $fr = explode(DIRECTORY_SEPARATOR, $_f);

                        $_f = $fr[count($fr)-1];

                        unset($fr[count($fr)-1]);
                        $path = implode(DIRECTORY_SEPARATOR, $fr) . DIRECTORY_SEPARATOR;

                        if (!Strings::startsWith(DIRECTORY_SEPARATOR, $path)){
                            $path = Constants::MIGRATIONS_PATH . DIRECTORY_SEPARATOR . $path;
                        }
                    } 
                }

                $path = str_replace('//', '/', $path);
                $filenames = [ $_f ];
            } 

            if (Strings::startsWith('--step=', $o)){
                $steps = Strings::slice($o, '/^--step=([0-9]+)$/');
            }

            if (Strings::startsWith('--skip=', $o)){
                $skip = Strings::slice($o, '/^--skip=([0-9]+)$/');
            }

            /*
                Ahora acepto rutas absolutas -útiles para manejarse dentro de Service Providers-
            */
            if (Strings::startsWith('--dir=', $o)){
                $dir_opt = true;
                $_dir    = substr($o, 6);

                if (Files::isAbsolutePath($_dir)){
                    $path = $_dir;
                } else {
                    $path = Constants::MIGRATIONS_PATH . $_dir;
                }                

                if (!is_dir($path)){
                    throw new \Exception("Directory $path doesn't exist");
                }
            }
        } // end foreach


        if (!$file_opt){
            foreach (new \DirectoryIterator($path) as $fileInfo) {
                if($fileInfo->isDot()  || $fileInfo->isDir()) continue;
                $filenames[] = $fileInfo->getFilename();
            }   
    
            sort($filenames);    
        } else {

            if ($ignore){
                $filename = $filenames[0];
                
                if (Schema::hasTable('migrations')){
                    /*
                        En todos loa casos debería agregar la DB y el directorio de la migración !
                    */
                    $ok = table('migrations')
                    ->create([
                        'filename' => $filename
                    ]);

                    if ($ok){
                        StdOut::pprint("Migration file '$filename' was marked as ignored");
                        return;
                    }

                    StdOut::pprint("Error trying to ignore file '$filename'd");

                    return;
                }
               
            }
    
        } 

        $cnt = min($steps, count($filenames));
        
        get_default_connection();

        if ($fresh){
            $this->fresh(...$opt);
        }

        if (!Schema::hasTable('migrations')){ 
            $filename_mg = '0000_00_00_00000000_migrations.php';
            $path_mg = Constants::MIGRATIONS_PATH;

            if (!file_exists(Constants::MIGRATIONS_PATH . $filename_mg)){
                StdOut::pprint("$filename_mg not found");
            }

            $full_path_mg = str_replace('//', '/', $path_mg . '/'. $filename_mg);
            require_once $full_path_mg;
            
            $class_name_mg = PHPLexicalAnalyzer::getClassNameByFileName($full_path_mg);

            if (!class_exists($class_name_mg)){
                throw new \Exception ("Class '$class_name_mg' doesn't exist in $filename_mg");
            }

            StdOut::pprint("Migrating '$filename_mg'\r\n");

            try {
                DB::disableForeignKeyConstraints();
                (new Migrations())->up();
            } finally {
                DB::enableForeignKeyConstraints();
            }
        }

        $ix = 0;
        $skipped = 0;
        foreach ($filenames as $filename)
        { 
            if (!$retry){
                $m = (object) table('migrations');

                if ($m
                ->where([
                    'filename' => $filename
                ])
                ->when($to_db != null, function ($q) use ($to_db) {

                    $q->group(function($q) use ($to_db){
                        $q
                        //->whereNull('db', $to_db)
                        ->where(['db', $to_db]);
                    });

                })
                ->exists()){
                    //d('SKIPING');
                    continue;
                }

                //d($m->getLog(), 'SQL');
            }

            if ($ix >= $cnt){
                break;
            }

            if (!empty($skip) && $skipped<$skip){
                $skipped++;
                continue;
            }

            $full_path = str_replace('//', '/', $path . '/'. trim($filename));

            require_once $full_path;

            $class_name = PHPLexicalAnalyzer::getClassNameByFileName($full_path);

            if (!class_exists($class_name)){
                throw new \Exception ("Class '$class_name' doesn't exist in $filename");
            }

            StdOut::pprint("Migrating '$filename'\r\n");

            if (!in_array('--simulate', $opt)){
                if (!empty($to_db)){
                    DB::setConnection($to_db);
                }

                DB::beginTransaction();
	
                try {
                    DB::disableForeignKeyConstraints();

                    (new $class_name())->up();
                    
                    DB::commit(); 

                }catch(\Exception $e){
                    try {
                        DB::rollback();
                        throw $e;
                    } catch (\Exception $e){
                        throw $e;
                    }
                } finally {
                    DB::enableForeignKeyConstraints();
                }

            } else {
                StdOut::pprint("*** This is a Simulation ***" . PHP_EOL);

                $ix++;
                continue;
            }
            
            StdOut::pprint("Migrated  '$filename' --ok\r\n");
            
            /*
                Main connection restore
            */

            get_default_connection();

            $data = [
                'filename' => $filename
            ];

            $main = get_default_connection_id();

            if ($to_db == 'default'){
                $to_db = $main;
            } else {
                if ($to_db != null && $to_db != $main){
                    $data['db'] = $to_db;
                }
            }

            $m = (object) table('migrations');

            $ok = $m
            ->create($data);

            $ix++;
        }     
        
        DB::enableForeignKeyConstraints();

        /*
            Soporte para:

            make:model
            make:schema
            make:schema,model
        */

        if (!empty($make)){
            $actions = explode(',', $make);

            foreach ($actions as $action){
                if ($action == 'schema'){
                    $make_o = (new MakeController())->schema('all');
                }

                if ($action == 'model'){
                    $make_o = (new MakeController())->model('all');
                }
            }
        }

    }
    
    /*
        Regresa migraciones (por defecto solo una)

        Va borrando registros de `migrations` 
    */
    function rollback(...$opt) 
    {
        $path = Constants::MIGRATIONS_PATH . DIRECTORY_SEPARATOR;

        $steps = 1;
        $simulate = false;

        StdOut::showResponse();

        foreach ($opt as $o){
            if (isset($opt[0]) && $opt[0] !== NULL){
                if (Strings::startsWith('--step=', $o)){
                    $steps = Strings::slice($o, '/^--step=([0-9]+)$/');
                }
                
                if ($o == '--all'){
                    $steps = PHP_INT_MAX;
                }

                if (preg_match('/^--to[=|:]([a-z][a-z0-9A-Z_]+)$/', $o, $matches)){
                    $to_db = $matches[1];

                    $main = Config::get()['db_connection_default'];

                    if ($to_db == $main || $to_db == 'default'){
                        $to_db = '__NULL__';
                    }
                }

                if (Strings::startsWith('--dir=', $o)){
                    $dir_opt = true;
                    $_dir    = substr($o, 6);
    
                    if (Files::isAbsolutePath($_dir)){
                        $path = $_dir;
                    } else {
                        $path = Constants::MIGRATIONS_PATH . $_dir;
                    }                
    
                    if (!file_exists($path)){
                        throw new \Exception("Directory $path doesn't exist");
                    }
                }

                if (Strings::startsWith('--file=', $o)){
                    $file_opt = true;
    
                    $_f = substr($o, 7); 
                                
                    if (Files::isAbsolutePath($_f)){
                        $path = Files::getDir($_f);
                        $_f = basename($_f);
                    } else {
                        if (Strings::contains(DIRECTORY_SEPARATOR, $_f)){
                            $fr = explode(DIRECTORY_SEPARATOR, $_f);
        
                            $_f = $fr[count($fr)-1];
        
                            unset($fr[count($fr)-1]);
                            $path = implode(DIRECTORY_SEPARATOR, $fr) . DIRECTORY_SEPARATOR;
        
                            if (!Strings::startsWith(DIRECTORY_SEPARATOR, $path)){
                                $path = Constants::MIGRATIONS_PATH . DIRECTORY_SEPARATOR . $path;
                            }
                        } 
                    }
    
                    $path = str_replace('//', '/', $path);
                    $filenames = [ $_f ];
                } 
     

                if (in_array($o, ['--simulate', 'simulate', '--sim'])){
                    StdOut::pprint("*** This is a Simulation ***" . PHP_EOL);
                    $simulate = true;
                }
            }
        }

        if (!isset($to_db)){
            StdOut::pprint("--to= is not optional\r\n");
            exit;
        }

        StdOut::pprint("Rolling back up to $steps migrations\r\n");

        if (!isset($filenames)){
            $m = (object) table('migrations');

            $filenames = $m
            ->when($to_db == '__NULL__', 
                function($q){
                    $q->whereNull('db');
                },
                function($q) use($to_db){
                    $q->where(['db' => $to_db]);
                }
            )
            ->orderBy(['id' => 'DESC'])
            ->pluck('filename');
        }    

        if (empty($filenames)){
            return;
        }

        DB::getDefaultConnection();
        DB::disableForeignKeyConstraints();

        $cnt = min($steps, count($filenames));
        for ($i=0; $i<$cnt; $i++){
            $filename   = $filenames[$i];
            $path = Constants::MIGRATIONS_PATH . DIRECTORY_SEPARATOR;          
            

            $full_path = $path . '/'. ( isset($_dir) ? $_dir . '/' : '' ). $filename;
            $full_path = preg_replace('#/+#','/',$full_path);
           
            if (!file_exists($full_path)){
                StdOut::pprint("File '$full_path' doesn't exist");
                exit;   
            }

            require_once $full_path;
            
            $class_name = PHPLexicalAnalyzer::getClassNameByFileName($full_path);

            if (!class_exists($class_name)){
                StdOut::pprint("Class '$class_name' doesn't exist in $filename");
                exit;
            }

            StdOut::pprint("Rolling back '$filename'\r\n");

            if (!method_exists($class_name, 'down')){
                StdOut::pprint("Method down() is not present. Impossible to rollback $filename\r\n");
                exit(1);
            }

            if (!$simulate){
                if (!empty($to_db) && $to_db != '__NULL__'){
                    DB::setConnection($to_db);
                }

                try {
                    DB::disableForeignKeyConstraints();
                    (new $class_name())->down();
                } finally {
                    DB::enableForeignKeyConstraints();
                }    
                
                DB::getDefaultConnection();

                $m = (object) table('migrations');

                $aff = $m
                ->when($to_db == '__NULL__', 
                    function($q){
                        $q->whereNull('db');
                    },
                    function($q) use($to_db){
                        $q->where(['db' => $to_db]);
                    }
                )
                ->where(['filename' => $filename])
                ->delete();

                //dd(DB::getLog()); ///

                if (empty($aff)){
                    StdOut::pprint("There was an error rolling back '$filename' because it was not found in `migrations` table\r\n");
                }
            }

            if (!empty($aff)){
                StdOut::pprint("Rolled back  '$filename' --ok\r\n");
            }            
        }

        DB::enableForeignKeyConstraints();
    }

    /*
        Clears migrations table
    */
    function clear(...$opt){
        foreach ($opt as $o)
        {
            if (preg_match('/^--to[=|:]([a-z][a-z0-9A-Z_]+)$/', $o, $matches)){
                $to_db = $matches[1];
            }
        }

        if (!isset($to_db)){
            StdOut::pprint("--to= is not optional\r\n");
            exit;
        }

        DB::getDefaultConnection();

        $m = (object) table('migrations');

        $affected = $m
        ->when($to_db != DB::getDefaultConnectionId(), function($q) use($to_db){
            $q->where(['db' => $to_db]);
        },function($q){
            $q->whereRaw('1');
        })
        ->delete();

        // Por alguna razon falla el conteo justo con esta tabla
        StdOut::pprint("$affected entries were cleared from migrations table for database `$to_db`\r\n");
    }

    /*
        Rollback de todas las migraciones. Equivale a "rollback --all"
    */
    function reset(...$opt) {
        $opt[] = "--all";
        $this->rollback(...$opt);
    }

    /*  
        rollback + migrate
    */
    function redo(...$opt){
        $this->rollback(...$opt);
        $this->migrate(...$opt);
    }

    /*
        This command will drop all tables from an specific database, even those one are not affected by migrations.

        At the end it clear all records in `migrations` table for the database.

        Solo ejecuta un migrate si se le pasa --migrate

        TODO: falta implementar --seed
    */
    function fresh(...$opt) 
    {   
        $force   = false;
        $migrate = false;
        $_f      = null;
        $_dir    = null;

        StdOut::showResponse();
        
        foreach ($opt as $o){
            if ($o == '--force'){
                $force = true;
                continue;
            }

            if ($o == '--migrate'){
                $migrate = true;
                continue;
            }
            
            if (preg_match('/^--to[=|:]([a-z][a-z0-9A-Z_]+)$/', $o, $matches)){
                $to_db = $matches[1];
            }

            if (Strings::startsWith('--file=', $o)){
                $_f = substr($o, 7);
            }

            if (Strings::startsWith('--dir=', $o)){
                $_dir    = substr($o, 6);
            }
        }

        if (!isset($to_db)){
            StdOut::pprint("--to= is not optional\r\n");
            exit;
        }

        if (!$force){
            StdOut::pprint("fresh: this method is destructive. " .
            (!isset($_f) ? "Every table for '$to_db' will be dropped." : ''). 
            "Please use option --force if you want to procede.\r\n");
            exit;
        }

        if (!is_null($_f) || !is_null($_dir)){
            $arr[] = "--to=$to_db";

            if ($_f !== null){
                $arr[] = "--file=$_f";  
            }

            if ($_dir !== null){
                $arr[] = "--dir=$_dir";  
            }

            return $this->redo(...$arr);
        }
        
        $conn = DB::getConnection($to_db);  

        $tables  = Schema::getTables($to_db);
        $dropped = [];

        if (empty($tables)){
            if ($migrate){
                $this->migrate(...$opt);
            }

            return;
        }

        /*
            Si en la DB existe la tabla 'migrations' (es la DB principal?) => la dejo para el final.
        */

        $delete_migrations_tb = false;
        if ($ix = array_search('migrations', $tables)){
            unset($tables[$ix]);
            $delete_migrations_tb = true;
        }

        try{
            Schema::FKcheck(false);

            $table = '';
            foreach($tables as $table) {
                StdOut::pprint("Dropping table '$table'\r\n");
                $res = DB::statement("DROP TABLE IF EXISTS `$table`;");
                
                if ($res){
                    StdOut::pprint("Dropped table  '$table' --ok\r\n");
                    $dropped[] = $table;
                } else {
                    StdOut::pprint("Dropped table failure for '$table'\r\n");
                }
            } 

            $this->clear("--to=$to_db");  

            if ($delete_migrations_tb){
                $table = 'migrations';

                StdOut::hideResponse();

                StdOut::pprint("Dropping table '$table'\r\n");
                $res = DB::statement("DROP TABLE IF EXISTS `$table`;");

                if ($res){
                    StdOut::pprint("Dropped table  '$table' --ok\r\n");
                    $dropped[] = $table;
                } else {
                    StdOut::pprint("Dropped table failure for '$table'\r\n");
                }
            }

            if ($migrate){
                StdOut::showResponse();
                $this->migrate(...$opt);
            }

        } catch (\PDOException $e) {    
            log_error($e->getMessage());
            throw $e;
        } finally {
            Schema::FKcheck(true);     
            StdOut::showResponse();
        }             
    }

    /*
        Rolling back: 2014_10_12_100000_create_password_resets_table
        Rolled back:  2014_10_12_100000_create_password_resets_table
        Rolling back: 2014_10_12_000000_create_users_table
        Rolled back:  2014_10_12_000000_create_users_table
        Migrating: 2014_10_12_000000_create_users_table
        Migrated:  2014_10_12_000000_create_users_table
        Migrating: 2014_10_12_100000_create_password_resets_table
        Migrated:  2014_10_12_100000_create_password_resets_table

        Lo que hace exactamente es un reset() seguido un migrate()
    */
    function refresh(...$opt) {
        $this->reset(...$opt);
        $this->migrate(...$opt);
    }

    function index(...$opt){
        if (!isset($opt[0])){
            $this->help();
            return;
        }
    }

    /*
        Sería ideal que cada comando tuviera su propia sección de ayuda y este comando "concatenara"
        esas secciones.
    */
    function help(){
        echo <<<STR
        MIGRATIONS COMMAND HELP

        migrations make [name] [ --dir= | --file= ] [ --table= ] [ --class_name= ] [ --to= ]         
        make migration --class_name=Filesss --table=files --to:main --dir='test\sub3 
        migrations migrate [ --step= ] [ --skip= ] [ --simulate ] [ --fresh ] [ --retry ] [ --ignore ] [ --make= ]
        migrations rollback --to={some_db_conn} [ --dir= ] [ --file= ] [ --step=={N} | --all] [ --simulate ]
        migrations fresh [ --dir= ] [ --file= ] --to=some_db_conn [ --force ] [ --migrate ]
        migrations redo --to={some_db_conn} [ --dir= ] [ --file= ] [ --simulate ]


        Examples:

        migrations make my_table
        migrations make my_table --dir=my_folder  
        
        migrations make my_table --table=my_table   
        migrations make my_table --to=db_connection
        migrations make my_table --table=my_table --class_name=MyTableAddDate --to:main
        migrations make --class_name=Files
        migrations make --table=files
        migrations make --class_name=Filesss --table=files
        migrations make --class_name=Files --to:main
        migrations make --class_name=Filesss --table=files --to:main --dir='test\sub3

        migrations migrate
        
        migrations rollback --to=db_195 --dir=compania
        migrations rollback --to=db_195 --dir=compania --simulate
        migrations rollback --to=main --step=2
        migrations rollback --file=2021_09_14_27910581_files.php --to:main
        migrations rollback --file=/some/absolute/path/2021_09_14_27910581_files.php --to:main

        migrations migrate --file=2021_09_13_27908784_user_roles.php
        migrations migrate --file=/some/absolute/path/2021_09_13_27908784_user_roles.php
        migrations migrate --dir=compania_new --to=db_flor
        migrations migrate --dir=/some/absolute/path --to=db_flor

        migrations migrate --dir=compania --to=db_153 --step=2
        migrations migrate --dir=compania --to=db_153 --skip=1

        migrations migrate --file=users/0000_00_00_00000001_users.php --simulate
        migrations migrate --dir=compania_new --to=db_flor --simulate
        migrations migrate --dir=compania --file=some_migr_file.php --to=db_189 --retry

        migrations migrate --from:main --file=some_migr_file.ph --ignore

        migrations migrate --fresh --to:main --force
        
        migrations redo  --file=2021_09_14_27910581_files.php --to:main

        migrations fresh --to=db_195 --force
        migrations fresh --to=the_tenant --force --migrate
        migrations fresh --file=2021_09_14_27910581_files.php --to:main
        migrations fresh --dir=compania --to:db_149 --force

        --make:model
        --make:schema
        --make:schema,model

        migrations migrate --make=schema,model
        migrations migrate --fresh --to:main --force --make=schema

        Inline migrations
        
        make migration foo --dropColumn=algun_campo
        make migration foo --renameColumn=viejo_nombre,nuevo_nombre
        make migration foo --renameTable=viejo_nombre,nuevo_nombre
        make migration foo --nullable=campo
        make migration foo --dropNullable=campo
        make migration foo --primary=campo
        make migration foo --dropPrimary=campo
        make migration foo --unsigned=campo
        make migration foo --zeroFill=campo
        make migration foo --binaryAttr=campo
        make migration foo --dropAttributes=campo
        make migration foo --addUnique=campo
        make migration foo --dropUnique=campo
        make migration foo --addSpatial=campo
        make migration foo --dropSpatial=campo
        make migration foo --dropForeign=campo
        make migration foo --addIndex=campo
        make migration foo --dropIndex=campo
        make migration foo --trucateTable=campo
        make migration foo --comment=campo

        Ex:

        php com make migration --dir=test --table=my_table --dropPrimary --unique=some_field,another_field

        STR;
        
        print_r(PHP_EOL);
    }    
}