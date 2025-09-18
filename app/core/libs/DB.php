<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\models\MyModel;
use boctulus\TutorNewCourses\core\libs\Config;
use boctulus\TutorNewCourses\core\libs\Schema;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\exceptions\SqlException;

class DB 
{
	protected static $connections = [];
	protected static $current_id_conn;	
	protected static $model_instance;  
	protected static $raw_sql;
	protected static $values = [];
	protected static $tb_name;
	protected static $inited_transaction = false; 
	protected static $default_primary_key_name = 'id';

	const INFOMIX    = 'infomix';
	const MYSQL      = 'mysql';
	const SQLITE     = 'sqlite';
	const SQLSRV     = 'mssql';
	const PGSQL      = 'pgsql';
	const DB2        = 'db2';
	const ORACLE     = 'oracle';
	const SYBASE     = 'sybase';
	const FIREBIRD   = 'firebird';

	// Util para establecer la PRIMARY KEY por defecto en caso de que no haya scheme definido
	public static function setPrimaryKeyName(string $name){
		static::$default_primary_key_name = $name;
	}

	public static function setConnection(string $id)
	{
		if ($id === null){
			throw new \InvalidArgumentException("Connection identifier can not be NULL");
		}

		if (!isset(Config::get()['db_connections'][$id])){
			throw new \InvalidArgumentException("Unregistered connection identifier for '$id'");
		}

		static::$current_id_conn = $id;
	}

    public static function getConnection(string $conn_id = null) {	
		$config = Config::get();

		$cc = count($config['db_connections']);
		
		if ($cc == 0){
			throw new \Exception('No database');
		}

		if ($conn_id != null){
			static::$current_id_conn = $conn_id;	
		} else {
			if (static::$current_id_conn == null){
				if ($cc == 1){
					static::$current_id_conn = array_keys($config['db_connections'])[0];
				} elseif (!empty($config['db_connection_default'])) {
					static::$current_id_conn = Config::get()['db_connection_default'];
				} else {	
					throw new \InvalidArgumentException('No database selected');
				}	
			}
		}

		if (isset(self::$connections[static::$current_id_conn]))
			return self::$connections[static::$current_id_conn];

		
		if (!isset($config['db_connections'][static::$current_id_conn])){
			throw new \InvalidArgumentException('Invalid database selected for '.static::$current_id_conn);
		}	
		
		if (!isset( $config['db_connections'][static::$current_id_conn]['driver'] )){
			throw new \Exception("Driver is required");
		}

		if (!isset( $config['db_connections'][static::$current_id_conn]['db_name'] )){
			throw new \Exception("DB Name is required");
		}

		$host    = $config['db_connections'][static::$current_id_conn]['host'] ?? 'localhost';
		$driver  = $config['db_connections'][static::$current_id_conn]['driver'];	
		$port    = $config['db_connections'][static::$current_id_conn]['port'] ?? NULL;
        $db_name = $config['db_connections'][static::$current_id_conn]['db_name'];
		$user    = $config['db_connections'][static::$current_id_conn]['user'] ?? 'root';
		$pass    = $config['db_connections'][static::$current_id_conn]['pass'] ?? '';
		$pdo_opt = $config['db_connections'][static::$current_id_conn]['pdo_options'] ?? NULL;
		$charset = $config['db_connections'][static::$current_id_conn]['charset'] ?? NULL;

		/*
			Aliases
		*/

		if ($driver == 'mariadb'){
			$driver = static::MYSQL;
		}

		if ($driver == 'postgres'){
			$driver = static::PGSQL;
		}

		if ($driver == 'sqlsrv' || $driver == 'mssql'){
			$driver = static::SQLSRV;
		}

		// faltaría dar soporte a ODBC
		// es algo como odbc:$dsn

		try {
			switch ($driver) {
				case static::MYSQL:
					self::$connections[static::$current_id_conn] = new \PDO(
						"$driver:host=$host;dbname=$db_name;port=$port",  /* DSN */
						$user, 
						$pass, 
						$pdo_opt);				
					break;
				case static::SQLITE:
					$db_file = Strings::contains(DIRECTORY_SEPARATOR, $db_name) ?  $db_name : STORAGE_PATH . $db_name;
	
					self::$connections[static::$current_id_conn] = new \PDO(
						"sqlite:$db_file", /* DSN */
						null, 
						null, 
						$pdo_opt);
					break;

				case static::PGSQL:
					self::$connections[static::$current_id_conn] = new \PDO(
						"pgsql:host=$host;dbname=$db_name;port=$port", /* DSN */
						$user, 
						$pass, 
						$pdo_opt);
					break;	
				
				case static::SQLSRV:
					self::$connections[static::$current_id_conn] = new \PDO(
						"sqlsrv:Server=$host,$port;Database=$db_name", /* DSN */
						$user, 
						$pass,
						$pdo_opt);
					break;

				default:
					throw new \Exception("Driver '$driver' not supported / tested.");
			}

			$conn = &self::$connections[static::$current_id_conn];

			if ($charset != null){
				switch (static::driver()){
					case static::MYSQL:
					case static::PGSQL:
						$charset = str_replace('-', '', $charset);
						$cmd = "SET NAMES '$charset'";
						break;
					case static::SQLITE:
						$charset = preg_replace('/UTF([0-9]{1,2})/i', "UTF-$1", $charset);
						$cmd = "PRAGMA encoding = '$charset'";
						break;
					case static::SQLSRV:
						// it could be unnecesary
						// https://docs.microsoft.com/en-us/sql/connect/php/constants-microsoft-drivers-for-php-for-sql-server?view=sql-server-ver15
						if ($charset == 'UTF8' || $charset == 'UTF-8'){
							$conn->setAttribute(\PDO::SQLSRV_ATTR_ENCODING, \PDO::SQLSRV_ENCODING_UTF8);
						}
						break;
				}

				$conn->exec($cmd);	
			}	

			//dd("CONNECTION MADE TO $db_name"); //

		} catch (\PDOException $e) {
			$msg = 'PDO Exception: '. $e->getMessage();

			if (Config::get()['debug']){
				$conn_arr = $config['db_connections'][static::$current_id_conn];
				$msg .= ". Connection = ". var_export($conn_arr, true);
			}
			
			throw new \PDOException($msg);	
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage());
		}	
		
		return self::$connections[static::$current_id_conn];
	}

	static function getDefaultConnectionId(){
		return Config::get()['db_connection_default'];
	}
	
	static function getDefaultConnection(){
		return self::getConnection(Config::get()['db_connection_default']);
	}

	public static function isDefaultConnection(){
		if (static::$current_id_conn === null){
			throw new \Exception("No current db connection");
		}

		return static::getDefaultConnectionId() === static::$current_id_conn;
	}

	public static function isDefaultOrNoConnection(){
		if (static::$current_id_conn === null){
			return true;
		}

		return static::getDefaultConnectionId() === static::$current_id_conn;
	}
	
    static function closeConnection(string $conn_id = null) {
		if ($conn_id == null){
			unset(static::$connections[static::$current_id_conn]);
			static::$current_id_conn = NULL; // undefined
		} else {
			static::$connections[$conn_id] = null;
		}
		//echo 'Successfully disconnected from the database!';
	}

	static function getConnectionConfig(){
		return Config::get()['db_connections'];
	}

	static function closeAllConnections(){
		static::$connections = null;
	}
	
	public function __destruct()
    {
        static::closeAllConnections();        
    }
	
	static function countConnections(){
		return count(static::$connections ?? []);
	}

	
	public static function getAllConnectionIds(){
		return array_keys(Config::get()['db_connections']);
	}

	// alias
	public static function getConnectionIds(){
		return static::getAllConnectionIds();
	}

	public static function getCurrentConnectionId(bool $auto_connect = false){
		if ($auto_connect && !static::$current_id_conn){
			static::getConnection();
		}

		return static::$current_id_conn;
	}

	public static function getCurrent(){
		if (static::$current_id_conn === null){
			return null;
		}

		return Config::get()['db_connections'][static::$current_id_conn];
	}

	public static function database(){
		$current = self::getCurrent();

		if ($current === null){
			return null;
		}
		
		return self::getCurrent()['db_name'];
	}

	// alias
	public static function getCurrentDB(){
		return self::database();
	}

	public static function getTableName(){
		return static::$tb_name;
	}

	public static function getTableNames($db_conn_id = null){
		if ($db_conn_id === null){
			$db_conn_id = static::getCurrentConnectionId();
		} else {
			static::getConnection($db_conn_id);
		}

		switch (static::driver()){
			case static::MYSQL:
				$db_name = static::getCurrentDB();

				$sql = "SELECT table_name FROM information_schema.tables
				WHERE table_schema = '$db_name';";
				
				return array_column(static::select($sql), 'TABLE_NAME');
			// case static::PGSQL:
			// 	break;
			// case static::SQLSRV:
			// 	break;
			// case static::SQLITE:
			// 	break;
			// case static::INFOMIX:
			// 	break;
			// case static::ORACLE:
			// 	break;
			// case static::DB2:
			// 	break;
			// case static::SYBASE:
			// 	break;
			default:
				throw new \Exception("Method " . __METHOD__ . " not supported for ". static::driver());
		}
	}


	/*
		Returns a tenant for each individual or cluster of databases

		It's possible to override tentant representants giving $db_representants array
	*/
	public static function getAllTenantRepresentants(Array $db_representants = []){
		$grouped = static::getDatabasesGroupedByTenantGroup(true);
		
		$db_conn_ids = [];
		foreach ($grouped as $group_name => $db_name){
			// elijo la conexión a una DB cualquiera de cada grupo como representativa
			// o la que me especifiquen
			$db_conn_id = $db_name[0];
	
			if (isset($db_representants) && !empty($db_representants)){
				if (isset($db_representants[$group_name])){
					$db_conn_id = $db_representants[$group_name];
				}
			} 
	
			$db_conn_ids[] = $db_conn_id;
		}

		return $db_conn_ids;
	}

	public static function driver(){
		$drv = self::getCurrent()['driver'] ?? NULL;

		if ($drv === null){
			throw new \Exception("No db driver");
		}

		return $drv;
	}

	/*
		Returns driver version from current DB connection
	*/
	public static function driverVersion(bool $only_number = false){
		$conn = self::$connections[static::$current_id_conn] ?? null;
	
		if ($conn === null){
			return false;
		}

		$ver  = $conn->getAttribute(\PDO::ATTR_SERVER_VERSION);

		if ($only_number){
			return Strings::matchOrFail($ver, '/^([^-]+)/');
		}

		return $ver;
	}

	public static function isMariaDB(){
		static $it_is;

		$conn_id = self::getCurrentConnectionId();

		if (isset($it_is[$conn_id])){
			return $it_is[$conn_id];
		}

		$ver = self::driverVersion();

		$it_is = [];
		$it_is[$conn_id] = Strings::contains('MariaDB', $ver);

		return $it_is[$conn_id];
	}

	public static function schema(){
		return self::getCurrent()['schema'] ?? NULL;
	}
	
	static function getTenantGroupNames() : Array {
		if (!isset(Config::get()['tentant_groups'])){
			throw new \Exception("File config.php is outdated. Lacks 'tentant_groups' section");
		}

		return array_keys(Config::get()['tentant_groups']);
	}

	static function getTenantGroupName(?string $tenant_id = null) : ?string {
		static $gns;

		if ($tenant_id === null){
			return get_default_connection_id();
		}

		if (is_null($gns)){
			$gns = [];
		}	

		if (in_array($tenant_id, $gns)){
			return $gns[$tenant_id];
		}

		if (!isset(Config::get()['tentant_groups'])){
			throw new \Exception("File config.php is outdated. Lacks 'tentant_groups' section");
		}

        foreach (Config::get()['tentant_groups'] as $group_name => $tg){
            foreach ($tg as $conn_pattern){
                if (preg_match("/$conn_pattern/", $tenant_id)){
                    $gns[$tenant_id] = $group_name;
					return $gns[$tenant_id];
                }
            }
        }

		$gns[$tenant_id] = false;

        return $gns[$tenant_id];
    }

	/*
		@return databas connections grouped by tenanant group name
	*/
	static function getGroupedDatabases(bool $include_main_database = false){
        $grouped_dbs = [];

        $dbs = Schema::getDatabases();

        foreach ($dbs as $db){
            $group = static::getTenantGroupName($db);
            
            if (!$group){
                continue;
            }

            if (!isset($grouped_dbs[$group])){
                $grouped_dbs[$group] = [];
            }

            $grouped_dbs[$group][] = $db;
        }

		if ($include_main_database){
			$def_con = get_default_connection_id();
        	$grouped_dbs[$def_con] = [ $def_con ];
		}

        return $grouped_dbs;
    }

	static function getDatabasesGroupedByTenantGroup(bool $include_main_database = false){
		return static::getGroupedDatabases($include_main_database);
	}

	/*
		Lista conexiones de bases de datos registradas que *no* forman parte de ningún tenant group
	*/
	static function getUngroupedDatabases(bool $exclude_default_conn = true){
		$db_conns = static::getAllConnectionIds();
        $grouped  = static::getGroupedDatabases();
        
		$grouped_flat = [];
        foreach ($grouped as $g_name => $gc){
			foreach ($gc as $c){
				$grouped_flat[] = $c;
			}
        }

		$ungrouped = array_diff($db_conns, $grouped_flat);

		if ($exclude_default_conn){
			$ungrouped = array_diff($ungrouped, [get_default_connection_id()]);
		}

		return $ungrouped;
	}

	public static function setModelInstance(Object $model_instance){
		static::$model_instance = $model_instance;
	}

	public static function setRawSql(string $sql){
		static::$raw_sql = $sql;
	}

	public static function getRawSql(){
		return static::$raw_sql;
	}

	// Returns last executed query 
	static public function getLog(){
		if (!is_null(static::$raw_sql)){
			$sql = Arrays::strReplace('?', static::$values, static::$raw_sql);
			$sql = trim(preg_replace('!\s+!', ' ', $sql));

			if (!Strings::endsWith(';', $sql)){
				$sql .= ';';
			}

			return $sql;	
		}

		if (static::$model_instance != NULL){
			return static::$model_instance->getLog();
		}
	}

	static private function dd($pre_compiled_sql, $bindings){		
		foreach($bindings as $ix => $val){			
			if(is_null($val)){
				$bindings[$ix] = 'NULL';
			}elseif(isset($vars[$ix])){
				$bindings[$ix] = "'$val'";
			}elseif(is_int($val)){
				// pass
			}
			elseif(is_bool($val)){
				// pass
			} elseif(is_string($val))
				$bindings[$ix] = "'$val'";	
		}

		$sql = Arrays::strReplace('?', $bindings, $pre_compiled_sql);
		$sql = trim(preg_replace('!\s+!', ' ', $sql));

		if (!Strings::endsWith(';', $sql)){
			$sql .= ';';
		}
				
		return $sql;
	}

	// SET autocommit=0;
	static function disableAutoCommit(){
		static::getConnection()->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
	}

	// SET autocommit=1;
	static function enableAutoCommit(){
		static::getConnection()->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
	}
	
	public static function table($from, $alias = NULL, bool $connect = true) {
		// Usar un wrapper y chequear el tipo
		if (!Strings::contains(' FROM ', $from))
		{
			$model_instance = Strings::camelToSnake($from);

			$class = get_model_name($from);
		
			$obj = new $class($connect);

			if ($alias != null){
				$obj->setTableAlias($alias);
			}			

			static::$model_instance = $obj;	
			static::$tb_name = static::$model_instance->getTableName();  //
					
			return $obj;	
		}

		static::$model_instance = (new MyModel($connect));
		static::$tb_name = static::$model_instance->getTableName();  //

		$st = static::$model_instance->fromRaw($from);	
		return $st;
	}

	/*
		Resolver problema de anidamiento !!

		Ej:

		Solicitud de inicio de transacción para => main
		Inicio de transacción para => main

		Solicitud de inicio de transacción para => db_185
		;Inicio de transacción para => db_185              -nunca ocurre !!!-

			=>

		--[ PDO error ]-- 
		There is no active transaction	
		
		El rollback() fallará ya que no puede hacer rollback de la más interna ya que nunca se hizo el beginTransaction()
		dado que... ya había comenzado uno y la bandera no lo dejará iniciar.

		Termina pasando algo como esto:

		beginTransaction()	- main
		// beginTransaction() - db_xxx   
		// rollback() 			- db_yyy   -nunca ocurre-
		rollback() 			- db_xxx   <------------------- There is no active transaction


	*/
	public static function beginTransaction(){
		#dd("Solicitud de inicio de transacción para => ". static::getCurrentConnectionId());

		if (static::$inited_transaction){
			// don't start it again!
			return;
		}

		#dd("Inicio de transacción para => ". static::getCurrentConnectionId());

		try {
			static::getConnection()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			static::getConnection()->beginTransaction();

			static::$inited_transaction = true;
		} catch (\Exception $e){
			
		} finally {
        	static::$inited_transaction = false;
    	}		
	}

	public static function commit(){
		if (phpversion() >= 8){
			return;
		}

		if (!static::$inited_transaction){
			// nothing to do
			return;
		}

		static::getConnection()->commit();
		static::$inited_transaction = false;
	}

	public static function rollback(){
		if (phpversion() >= 8){
			return;
		}

		if (!static::$inited_transaction){
			// nothing to do
			return;
		}

		static::getConnection()->rollback();
		static::$inited_transaction = false;
	}

	// https://github.com/laravel/framework/blob/4.1/src/Illuminate/DB/Connection.php#L417
	public static function transaction(\Closure $callback)
    {		
		if (phpversion() >= 8){
			return $callback();
		}

		if (static::$inited_transaction){
			// don't start it again!
			return;
		}

		static::beginTransaction();

		try
		{
			$result = $callback();
			static::commit();
		}catch (\Exception $e){
			static::rollBack();
			throw $e;
		}

		return $result;
    }
		
	//
	// https://laravel.com/docs/5.0/database
	//
	public static function select(string $raw_sql, $vals = null, $fetch_mode = 'ASSOC', $tenant_id = null, bool $only_one = false, bool $close_cursor = false, bool $tb_prefix = true, &$st = null){
		if ($vals === null){
			$vals = [];
		}
		
		if ($tb_prefix){
			$raw_sql = Model::addPrefix($raw_sql);
		}		

		static::$raw_sql = $q = $raw_sql;
		static::$values  = $vals; 

		if (empty($fetch_mode)){
			$fetch_mode = 'ASSOC';
		}


		///////////////[ BUG FIXES ]/////////////////

		$driver = static::driver();

		if (!empty($vals))
		{
			$_vals = [];
			$reps  = 0;

			foreach($vals as $ix => $val)
			{				
				if($val === NULL){
					$q = Strings::replaceNth('?', 'NULL', $q, (int) $ix+1-$reps);
					$reps++;

				/*
					Corrección para operaciones entre enteros y floats en PGSQL
				*/
				} elseif($driver == 'pgsql' && is_float($val)){ 
					$q = Strings::replaceNth('?', 'CAST(? AS DOUBLE PRECISION)', $q, $ix+1-$reps);
					$reps++;
					$_vals[] = $val;
				} else {
					$_vals[] = $val;
				}
			}

			$vals = $_vals;
		}
		
		///////////////////////////////////////////

		$current_id_conn = static::getCurrentConnectionId();
		$conn = static::getConnection($tenant_id);
		
		try {
			$st = $conn->prepare($q);			

			foreach($vals as $ix => $val)
			{				
				if(is_null($val)){
					$type = \PDO::PARAM_NULL; // 0
				}elseif(is_int($val))
					$type = \PDO::PARAM_INT;  // 1
				elseif(is_bool($val))
					$type = \PDO::PARAM_BOOL; // 5
				elseif(is_string($val)){
					if(mb_strlen($val) < 4000){
						$type = \PDO::PARAM_STR;  // 2
					} else {
						$type = \PDO::PARAM_LOB;  // 3
					}
				}elseif(is_float($val))
					$type = \PDO::PARAM_STR;  // 2
				elseif(is_resource($val))	
					// https://stackoverflow.com/a/36724762/980631
					$type = \PDO::PARAM_LOB;  // 3
				elseif(is_array($val)){
					throw new \Exception("where value can not be an array!");				
				}else {
					throw new \Exception("Unsupported type: " . var_export($val, true));
				}	

				$st->bindValue($ix +1 , $val, $type);
			}

			
			$st->execute();

			$fetch_const = constant("\PDO::FETCH_{$fetch_mode}");


			if ($only_one){
				$result = $st->fetch($fetch_const);
			} else {
				$result = $st->fetchAll($fetch_const);

				if ($close_cursor){
					$st->closeCursor();
				}
			}

		} catch (\Exception $e){
			// logger($e->getMessage());
			// log_sql(static::getLog());

			throw ($e);
		} finally {	
			// Restore previous connection
			if (!empty($current_id_conn)){
				static::setConnection($current_id_conn);
			}
		}

		return $result;
	}

	/*
		SP SELECT

		Resuelve varios problemas presentes al hacer un fetchAll sobre un Store Procedure (SP)

		Ej:

		DB::safeSelect("CALL partFinder(?, ?, ?)", [$partNumExact, $namePartial, $descriptionPartial])

		Ver

		https://stackoverflow.com/a/17582620/980631
	*/
	public static function SafeSelect(string $raw_sql, $vals = null, $fetch_mode = 'ASSOC', $tenant_id = null, &$st = null){
		$conn = static::getConnection($tenant_id);
		$conn->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

		return static::select($raw_sql, $vals, $fetch_mode, $tenant_id, false, true, $st);
	}

	public static function selectOne(string $raw_sql, ?Array $vals = null, $fetch_mode = 'ASSOC', ?string $tenant_id = null, bool $only_one = false){
		return static::select($raw_sql, $vals, $fetch_mode, $tenant_id, true);
	}

	public static function truncate(string $table, ?string $tenant_id = null){
		static::getConnection($tenant_id);
		static::statement("TRUNCATE TABLE `$table`");
	}

	public static function insert(string $raw_sql, Array $vals = [], $tenant_id = null, $prikey_name = 'id')
	{
		static::$raw_sql = $raw_sql;
		static::$values  = $vals; 
		
		$raw_sql = Model::addPrefix($raw_sql);
	
		$current_id_conn = static::getCurrentConnectionId();
		$conn = static::getConnection($tenant_id);

		try {
			$st = $conn->prepare($raw_sql);

			if (is_null($vals)){
				$vals = [];
			}

			foreach($vals as $ix => $val)
			{				
				if(is_null($val)){
					$type = \PDO::PARAM_NULL; // 0
				}elseif(is_int($val))
					$type = \PDO::PARAM_INT;  // 1
				elseif(is_bool($val))
					$type = \PDO::PARAM_BOOL; // 5
				elseif(is_string($val)){
					if(mb_strlen($val) < 4000){
						$type = \PDO::PARAM_STR;  // 2
					} else {
						$type = \PDO::PARAM_LOB;  // 3
					}
				}elseif(is_float($val))
					$type = \PDO::PARAM_STR;  // 2
				elseif(is_resource($val))	
					// https://stackoverflow.com/a/36724762/980631
					$type = \PDO::PARAM_LOB;  // 3
				elseif(is_array($val)){
					throw new \Exception("WHERE clasuule value can not be an array: " . var_export($val, true));				
				}else {
					throw new \Exception("Unsupported type: " . var_export($val, true));
				}	

				$st->bindValue($ix +1 , $val, $type);
			}
		
			$result = $st->execute();

			if (!isset($result)){
				return;
			}

			$table = Strings::match($raw_sql, '/insert[ ]+(ignore[ ]+)?into[ ]+[`]?([a-z_]+[a-z0-9]?)[`]? /i', 2);

			if (!empty($table)){
				$schema = has_schema($table) ? get_schema($table) : null;
			} else {
				$schema = null;
			}

			if ($result){
				// sin schema no hay forma de saber la PRI Key. Intento con 'id' 
				$id_name = ($schema != NULL) ? $schema['id_name'] : ($prikey_name ?? static::$default_primary_key_name);		

				if (isset($data[$id_name])){
					$last_inserted_id =	$vals[$id_name]; // probable fix -19/02/2024
				} else {
					$last_inserted_id = $conn->lastInsertId();
				}
			}else {
				$last_inserted_id = false;	
			}
	
		} finally {
			// Restore previous connection
			if (empty(!$current_id_conn)){
				static::setConnection($current_id_conn);
			}
		}
		
		return $last_inserted_id;	
	}


	public static function statement(string $raw_sql, Array $vals = [], ?string $tenant_id = null)
	{
		static::$raw_sql = $raw_sql;
		static::$values  = $vals; 
		
		$sql = Model::addPrefix($raw_sql);
	
		$current_id_conn = static::getCurrentConnectionId();
		$conn            = static::getConnection($tenant_id);

		try {
			$st = $conn->prepare($sql);

			if (is_null($vals)){
				$vals = [];
			}

			foreach($vals as $ix => $val)
			{				
				if(is_null($val)){
					$type = \PDO::PARAM_NULL; // 0
				}elseif(is_int($val))
					$type = \PDO::PARAM_INT;  // 1
				elseif(is_bool($val))
					$type = \PDO::PARAM_BOOL; // 5
				elseif(is_string($val)){
					if(mb_strlen($val) < 4000){
						$type = \PDO::PARAM_STR;  // 2
					} else {
						$type = \PDO::PARAM_LOB;  // 3
					}
				}elseif(is_float($val))
					$type = \PDO::PARAM_STR;  // 2
				elseif(is_resource($val))	
					// https://stackoverflow.com/a/36724762/980631
					$type = \PDO::PARAM_LOB;  // 3
				elseif(is_array($val)){
					throw new SqlException("The value for WHERE can not be an array!");				
				}else {
					throw new SqlException("Unsupported type: " . var_export($val, true));
				}	

				$st->bindValue($ix +1 , $val, $type);
			}
		

			if($st->execute()) {
				$count = $st->rowCount();
			} else 
				$count = false;

		} catch (\Exception $e){
			logger($e->getMessage());
			log_sql(static::getLog());
			throw ($e);				
		} finally {
			// Restore previous connection
			if (!empty($current_id_conn)){
				static::setConnection($current_id_conn);
			}
		}
		
		return $count;
	}

	public static function update(string $raw_sql, Array $vals = [], ?string $tenant_id = null)
	{
		return static::statement($raw_sql, $vals, $tenant_id);
	}

	public static function delete(string $raw_sql, Array $vals = [], ?string $tenant_id = null)
	{
		return static::statement($raw_sql, $vals, $tenant_id);
	}

	// faltan otras funciones raw para DELETE, UPDATE e INSERT

	public static function disableForeignKeyConstraints(){
		return Schema::disableForeignKeyConstraints();
	}

	public static function enableForeignKeyConstraints(){
		return Schema::enableForeignKeyConstraints();
	}

	/*
		Escapa strings que pudieran contener comillas dobles

		Util cuando se hace un INSERT de un campo tipo JSON
	*/
	static function quoteValue(string $val){
		$conn = static::getConnection();
		return $conn->quote($val);
	}

	/*
		Rodea con las comillas correctas campos y nombres de tablas

		https://stackoverflow.com/a/10574031/980631
		https://dba.stackexchange.com/questions/23129/benefits-of-using-backtick-in-mysql-queries
	*/
	static function quote(string $str){
		$d1 = '';
		$d2 = '';

		switch (static::driver()){
			case static::MYSQL:
				$d1 = $d2 = "`";
				break;
			case static::PGSQL:
				$d1 = $d2 = '"';
				break;
			case static::SQLSRV:
				// SELECT [select] FROM [from] WHERE [where] = [group by];
				$d1 = '[';
				$d2 = ']';
				break;
			case static::SQLITE:
				$d1 = $d2 = '"';
				break;
			case static::INFOMIX:
				return $str;
			case static::ORACLE:
				$d1 = $d2 = '"';
				break;
			case static::DB2:
				$d1 = $d2 = '"';
				break;
			case static::SYBASE:
				$d1 = $d2 = '"';
				break;
			default:
				$d1 = $d2 = '"';
		}

		$str = Strings::removeMultipleSpaces(trim($str));

		if (Strings::contains(' as ', $str)){
			$s1 = Strings::before($str, ' as ');
			$s2 = Strings::after($str, ' as ');
			
			return "{$d1}$s1{$d2} as {$d1}$s2{$d2}";
		}

		return Strings::enclose($str, $d1, $d2);
	}

	/*
		https://www.petefreitag.com/item/466.cfm
		https://stackoverflow.com/questions/19412/how-to-request-a-random-row-in-sql
	*/
	static function random(){
		switch (static::driver()){
			case static::MYSQL:
			case static::SQLITE:
			case static::INFOMIX:
			case static::FIREBIRD:
				return ' ORDER BY RAND()';
			case static::PGSQL:
				return ' ORDER BY RANDOM()';
			case static::SQLSRV:
				// SELECT TOP 1 * FROM MyTable ORDER BY newid()
				return ' ORDER BY newid()';
			default: 
				throw new \Exception("Not implemented");	
		}
	}

	/*
		Similar a SHOW TABLES pero incluye mucha info extra como el tipo de "engine" y "collation" de cada tabla, etc

		Valida para MYSQL
	*/
	static function status(){
		return static::select("SHOW TABLE STATUS");
	}

	/*
		Optimiza tablas en MySQL -- chequear si existe equivalente para otros motores

		Retorna algo como:

		--| api_keys
		Array
		(
			[0] => Array
				(
					[Table] => simplerest.api_keys
					[Op] => optimize
					[Msg_type] => status
					[Msg_text] => OK
				)

		)

	*/
	static function optimize($tables, bool $quote = false){
		if (is_array($tables)){
			if ($quote){
				$tables = array_map([static::class, 'quote'], $tables);
			}

			$tables = implode(', ', $tables);
		} else {
			if ($quote){
				$tables = static::quote($tables);
			}
		}

		return static::select("OPTIMIZE TABLE $tables");
	}

	/*
		Efectua proceso de reparacion de tablas en MYSQL

		Retorna algo como:

		--| api_keys
		Array
		(
			[0] => Array
				(
					[Table] => simplerest.api_keys
					[Op] => repair
					[Msg_type] => note
					[Msg_text] => The storage engine for the table doesn't support repair <--- tomar nota de esto
				)
		)
	*/
	static function repair($tables, bool $quote = false){
		if (is_array($tables)){
			if ($quote){
				$tables = array_map([static::class, 'quote'], $tables);
			}

			$tables = implode(', ', $tables);
		} else {
			if ($quote){
				$tables = static::quote($tables);
			}
		}

		return static::select("REPAIR TABLE $tables");
	}

	/*
		Queues
	*/
	
	/*
		Ej:

		enqueue([        
			'user_id' => $user_id
		]);
	*/
	static function enqueue($data, $category = null) {
		$tb = (object) table("queue");

		$tb->insert([
			'category' => $category,
			'data'     => json_encode($data)
		]);
	}

	/*
		Ej:

		$row     = deque();

		$user_id = $row['user_id'];
		// ....
	*/
	static function deque($category = null, bool $full_row = false) {
		$tb = (object) table("queue");

		$row = $tb
		->when(!empty($category), function ($q) use ($category) {
			$q->where(["category" => $category]);
		})
		->orderBy([
			'id' => 'asc'
		])
		->getOne();
		
		if (empty($row)){
			return false;
		}

		$id          = $row['id'];
		$row['data'] = json_decode($row['data'], true);

		$tb = (object) table("queue");
		$tb->where(['id' => $id])->delete();

		return $full_row ? $row : $row['data'];
	}

}
