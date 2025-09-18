<?php

namespace boctulus\TutorNewCourses\core\libs;

use PDO;
use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Arrays;
use boctulus\TutorNewCourses\core\libs\Factory;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\Paginator;
use boctulus\TutorNewCourses\core\libs\Validator;
use boctulus\TutorNewCourses\exceptions\SqlException;
use boctulus\TutorNewCourses\core\interfaces\IValidator;
use boctulus\TutorNewCourses\core\traits\ExceptionHandler;
use boctulus\TutorNewCourses\exceptions\InvalidValidationException;


class Model {
	use ExceptionHandler;

	// for internal use
	protected $table_alias = [];
	protected $table_name;
	protected $prefix;

	// Schema
	protected $schema;

	protected $fillable     = [];
	protected $not_fillable = [];
	protected $hidden   = [];
	protected $attributes = [];
	protected $joins  = [];
	protected $show_deleted = false;
	protected $conn;
	protected $fields = [];
	protected $where  = [];
	protected $where_group_op  = [];
	protected $where_having_op = [];
	protected $group  = [];
	protected $having = [];
	protected $w_vars = [];
	protected $h_vars = [];
	protected $w_vals = [];
	protected $h_vals = [];
	protected $order  = [];
	protected $raw_order = [];
	protected $select_raw_q;
	protected $select_raw_vals = [];
	protected $where_raw_q;
	protected $where_raw_vals  = [];
	protected $having_raw_q;
	protected $having_raw_vals = [];
	protected $table_raw_q;
	protected $from_raw_vals   = [];
	protected $union_q;
	protected $union_vals = [];
	protected $union_type;
	protected $join_raw = [];
	protected $aggregate_field_alias;
	protected $randomize = false;
	protected $distinct  = false;
	protected $to_merge_bindings = [];
	protected $last_pre_compiled_query;
	protected $last_bindings = [];
	protected $last_compiled_sql;
	protected $limit;
	protected $offset;
	protected $pag_vals = [];
	protected $validator;
	protected $input_mutators = [];
	protected $output_mutators = [];
	protected $transformer;
	protected $controller;
	protected $exec = true;
	protected $bind = true;
	protected $strict_mode_having = false;
	protected $should_qualify = false; //
	protected $semicolon_ending = false;
	protected $fetch_mode;
	protected $soft_delete;
	protected $last_inserted_id;
	protected $paginator = true;	
	protected $fetch_mode_default = \PDO::FETCH_ASSOC;
	protected $last_operation;
	protected $current_operation;
	protected $insert_vars = [];
	protected $data = []; 

	protected $having_group_op;
	protected $config;

	protected $createdAt = 'created_at';
	protected $updatedAt = 'updated_at';
	protected $deletedAt = 'deleted_at'; 
	protected $createdBy = 'created_by';
	protected $updatedBy = 'updated_by';
	protected $deletedBy = 'deleted_by'; 
	protected $is_locked = 'is_locked';
	protected $belongsTo = 'belongs_to';

	/*	
		Nombres de los campos

		Ej:

		[
			"created_at" => "Created At",
			// ...
		]
	*/
	protected        $field_names = [];

	/*
		Solo como indicaicon para el FrontEnd
	*/
	protected 		 $field_order = [];

	/*
		Aca se especificaria si es un checkbox o radiobox por ejemplo

		Para tipo "dropdown" o "list" se utilizarian los valores de la regla de validacion
		o de la tabla relacionada 

		Tambien otros formatters que puedan estar disponibles en el frontend
	*/
	protected        $formatters = [];

	static protected $sql_formatter_callback;
	protected        $sql_formatter_status;
	
	static protected $current_sql;


	function getFieldNames(){
		return $this->field_names;
	}

	function getformatters(){
		return $this->formatters;
	}

	function getHidden(){
		return $this->hidden;
	}

	function createdAt(){
		return $this->createdAt;
	}

	function createdBy(){
		return $this->createdBy;
	}

	function updatedAt(){
		return $this->updatedAt;
	}

	function updatedBy(){
		return $this->updatedBy;
	}

	function deletedAt(){
		return $this->deletedAt;
	}

	function deletedBy(){
		return $this->deletedBy;
	}

	function isLocked(){
		return $this->is_locked;
	}

	// alias

	function locked(){
		return $this->is_locked;
	}

	function belongsTo(){
		return $this->belongsTo;
	}

	function getAutoFields(){
		return [
			$this->createdBy(),
			$this->createdAt(),
			$this->updatedBy(),
			$this->updatedAt(),
			$this->deletedBy(),
			$this->deletedAt(),
			$this->belongsTo(),
			$this->isLocked()
		];
	}

	function setSqlFormatter(callable $fn){
		static::$sql_formatter_callback = $fn;
	}

	function sqlformatterOff(){
		$this->sql_formatter_status = false;

		return $this;
	}

	function sqlformatterOn(){
		$this->sql_formatter_status = true;

		return $this;
	}

	static function sqlFormatter(string $query, ...$options) : string {
		if (!empty(static::$sql_formatter_callback) && is_callable(static::$sql_formatter_callback)){
			$fn = static::$sql_formatter_callback;

			return $fn($query, ...$options);
		}			

		return $query;
	} 

	function __construct(bool $connect = false, $schema = null, bool $load_config = true)
	{
		$this->boot();

		$this->prefix = tb_prefix();

		// static::$sql_formatter_callback = function(string $sql, bool $highlight = false){
		// 	return \SqlFormatter::format($sql, $highlight);
		// };

		if ($connect){
			$this->connect();
		}

		if ($schema != null){
			$this->schema = $schema::get(); //
			$this->table_name = $this->schema['table_name'];
		}

		if ($load_config){
			$this->config = Config::get();

			if ($this->config['error_handling']) {
				set_exception_handler([$this, 'exception_handler']);
			}
		}
		
		if ($this->schema == null){
			return;
		}	

		$this->attributes = array_keys($this->schema['attr_types']);

		if (in_array('', $this->attributes, true)){
			throw new \Exception("An attribute is invalid");
		}

		if ($this->fillable == null){
			$this->fillable = $this->attributes;
		}

		$this->schema['nullable'][] = $this->is_locked;		
		$this->schema['nullable'][] = $this->createdAt;
		$this->schema['nullable'][] = $this->updatedAt;
		$this->schema['nullable'][] = $this->deletedAt;
		$this->schema['nullable'][] = $this->createdBy;
		/*
			No incluir:

			$this->schema['nullable'][] = $this->updatedBy;

		*/
		$this->schema['nullable'][] = $this->deletedBy;
		$this->schema['nullable'][] = $this->belongsTo;	

		$to_fill = [];

		// if (!empty($this->schema['id_name'])){
		// 	$to_fill[] = $this->schema['id_name'];
		// }

		$this->fill($to_fill);		

		$this->unfill([ 
			$this->id() 
		]);
		
		$this->soft_delete = $this->inSchema([$this->deletedAt]);

		// Kill dupes
		$this->schema['nullable'] = array_unique($this->schema['nullable']);
	
		/*
		 Validations
		*/
		if (!empty($this->schema['rules'])){
			foreach ($this->schema['rules'] as $field => $rule){
				if (!isset($this->schema['rules'][$field]['type']) || empty($this->schema['rules'][$field]['type'])){
					$this->schema['rules'][$field]['type'] = strtolower($this->schema['attr_types'][$field]);
				}
			}
		}
		
		foreach ($this->schema['attr_types'] as $field => $type){
			if (!isset($this->schema['rules'][$field])){
				$this->schema['rules'][$field]['type'] = strtolower($type);
			}

			if (!$this->isNullable($field)){
				$this->schema['rules'][$field]['required'] = true;
			}
		}

		//dd($this->not_fillable, 'NF');

		/*
			Remuevo los campos no-fillables de los fillables
		*/
		foreach ($this->not_fillable as $f){
			$pos = array_search($f, $this->fillable);
			
			if ($pos !== false){
				unset($this->fillable[$pos]);
			}
		}

		//$this->setValidator(new Validator());

		// event handler
		$this->init();
	}

	/*	
		Returns table or its alias if exists for the referenced table
	*/
	function getTableAlias(){
		if (isset($this->table_alias[$this->table_name])){
			$tb_name = $this->table_alias[$this->table_name];
		} else {
			$tb_name = $this->table_name;
		}

		return $tb_name;
	}

	protected function getFullyQualifiedField(string $field){
		if (!$this->should_qualify){
			return $field;
		}

		if (!Strings::contains('.', $field)){
			$tb_name = $this->getTableAlias();
	
			return "{$tb_name}.$field";
		} else {
			return $field;
		}
	}

	protected function unqualifyField(string $field){
		if (Strings::contains('.', $field)){
			$_f = explode('.', $field);
			return $_f[1];
		}

		return $field;
	} 

	function addRules(ValidationRules $vr){
		$this->schema['rules'] = array_merge($this->schema['rules'], $vr->getRules());
	}

	function noValidation(){
		$this->validator = null;
		return $this;
	}

	/*
		Returns prmary key
	*/
	function getKeyName(){
		return $this->schema['id_name'];
	}

	function getTableName(){
		return $this->table_name;
	}

	/*
		Turns on / off pagination
	*/
	function setPaginator(bool $status){
		$this->paginator = $status;
		return $this;
	}

	function registerInputMutator(string $field, callable $fn, ?callable $apply_if_fn){
		$this->input_mutators[$field] = [$fn, $apply_if_fn];
		return $this;
	}

	function registerOutputMutator(string $field, callable $fn){
		$this->output_mutators[$field] = $fn;
		return $this;
	}

	// acepta un Transformer
	function registerTransformer($t, $controller = NULL){
		$this->unhideAll();
		$this->transformer = $t;
		$this->controller  = $controller;
		return $this;
	}
	
	function applyInputMutator(array $data, string $current_op){	
		if ($current_op != 'CREATE' && $current_op != 'UPDATE'){
			throw new \InvalidArgumentException("Operation '$current_op' is invalid for Input Mutator");
		}

		foreach ($this->input_mutators as $field => list($fn, $apply_if_fn)){
			if (!in_array($field, $this->getAttr()))
				throw new \Exception("Invalid accesor: $field field is not present in " . $this->table_name); 

			$dato = $data[$field] ?? NULL;
					
			if ($apply_if_fn == null || $apply_if_fn(...[$current_op, $dato])){				
				$data[$field] = $fn($dato);
			} 				
		}

		return $data;
	}

	/*
		Es complicado hacerlo funcionar y falla cuando se selecciona un único registro
		quizás por el FETCH_MODE

		Está confirmado que si el FETCH_MODE no es ASSOC, va a fallar
	*/
	function applyOutputMutators($rows){
		if (empty($rows))
			return;
		
		if (empty($this->output_mutators))
			return $rows;

		//$by_id = in_array('id', $this->w_vars);	
		
		foreach ($this->output_mutators as $field => $fn){
			if (!in_array($field, $this->getAttr()))
				throw new \Exception("Invalid transformer: $field field is not present in " . $this->table_name); 

			if ($this->getFetchMode() == \PDO::FETCH_ASSOC){
				foreach ($rows as $k => $row){
					$rows[$k][$field] = $fn($row[$field]);
				}
			}elseif ($this->getFetchMode() == \PDO::FETCH_OBJ){
				foreach ($rows as $k => $row){
					$rows[$k]->$field = $fn($row->$field);
				}
			}			
		}
		return $rows;
	}
	
	function applyTransformer($rows){
		if (empty($rows))
			return;
		
		if (empty($this->transformer))
			return $rows;
		
		foreach ($rows as $k => $row){
			//var_dump($row);

			if (is_array($row))
				$row = (object) $row;

			$rows[$k] = $this->transformer->transform($row, $this->controller);
		}

		return $rows;
	}


	function setFetchMode(string $mode){
		$this->fetch_mode = constant("PDO::FETCH_{$mode}");
		return $this;
	}

	function assoc(){
		$this->fetch_mode = \PDO::FETCH_ASSOC;
		return $this;
	}

	function column(){
		$this->fetch_mode = \PDO::FETCH_COLUMN;
		return $this;
	}

	protected function getFetchMode($mode_wished = null){
		if ($this->fetch_mode == NULL){
			if ($mode_wished != NULL) {
				return constant("PDO::FETCH_{$mode_wished}");
			} else {
				return $this->fetch_mode_default;
			}
		} else {
			return $this->fetch_mode;
		}
	}

	function setValidator($validator = null){
		$this->validator = $validator;
		return $this;
	}

	function setTableAlias(string $tb_alias, ?string $table = null){
		if ($table === null){
			$table = $this->table_name;
		}

		$this->table_alias[$table] = $tb_alias;
		return $this;
	}

	function alias(string $tb_alias, ?string $table = null){
		return $this->setTableAlias($tb_alias, $table);
	}

	/*
		Don't execute the query
	*/
	function dontExec(){
		$this->exec = false;
		return $this;
	}

	/*
		Don't bind params
	*/
	function dontBind(){
		$this->bind = false;
		return $this;
	}

	function doBind(){
		$this->bind = true;
		return $this;
	}

	function setStrictModeHaving(bool $state){
		$this->strict_mode_having = $state;
		return $this;
	}

	function dontQualify(){
		$this->should_qualify = false;
		return $this;
	}

	function qualify(){
		$this->should_qualify = true;
		return $this;
	}

	// set table and alias
	function table(string $table, $table_alias = null)
	{
		$this->table_name          = $table;
		$this->table_alias[$table] = $table_alias;

		if (!empty($this->prefix)){
			$this->table_name = $this->prefix . $this->table_name;
			$this->prefix     = null;
		}

		return $this;		
	}

	// alias for table();
	function setTable(string $table, $table_alias = null){
		return $this->table($table, $table_alias);		
	}

	function prefix($prefix = ''){
		$this->prefix     = $prefix;
		return $this;
	}

	// alias for prefix()
	function setPrefix($prefix = ''){
		return $this->prefix($prefix);
	}
	
	function removePrefix(string $prefix){
		$table = Strings::after($this->table_name, $prefix);
		$this->table($table);	
		$this->prefix = null;
		
		return $this;
	}

	protected function from(){
		if ($this->table_raw_q != null){
			return $this->table_raw_q;
		}

		if ($this->table_name == null){
			throw new \Exception("No table_name defined");
		}

		$tb_name = $this->table_name;

		if (DB::driver() == DB::PGSQL && DB::schema() != null){
			$tb_name = DB::schema() . '.' . $tb_name;
		}

		$from = isset($this->table_alias[$this->table_name]) ? ($tb_name. ' as '.$this->table_alias[$this->table_name]) : $tb_name.' ';  
		return trim($from);
	}

		
	/**
	 * unhide
	 * remove from hidden list of fields
	 * 
	 * @param  mixed $unhidden_fields
	 *
	 * @return void
	 */
	function unhide(array $unhidden_fields){
		if (!empty($this->hidden) && !empty($unhidden_fields)){			
			foreach ($unhidden_fields as $uf){
				$k = array_search($uf, $this->hidden);
				unset($this->hidden[$k]);
			}
		}
		return $this;
	}

	function unhideAll(){
		$this->hidden = [];
		return $this;
	}
	
	/**
	 * hide
	 * turn off field visibility from fetch methods 
	 * 
	 * @param  mixed $fields
	 *
	 * @return void
	 */
	function hide(array $fields){
		foreach ($fields as $f){
			if (!in_array($f, $this->hidden)){
				$this->hidden[] = $f;
			}
		}

		return $this;	
	}

	
	/**
	 * fill
	 * makes a field fillable
	 *
	 * @param  mixed $fields
	 *
	 * @return object
	 */
	function fill(array $fields){
		foreach ($fields as $f){
			if (!in_array($f, $this->fillable)){
				$this->fillable[] = $f;
			}
		
			/*
				Remuevo los campos fillables del array de los no-fillables	
			*/
			$pos = array_search($f, $this->not_fillable);
			
			if ($pos !== false){
				unset($this->not_fillable[$pos]);
			}
		}

		return $this;	
	}

	function unfill(array $fields){
		foreach ($fields as $f){
			if (!in_array($f, $this->not_fillable)){
				$this->not_fillable[] = $f;
			}

			/*
				Remuevo los campos no-fillables del array de los fillables	
			*/
			$pos = array_search($f, $this->fillable);
			
			if ($pos !== false){
				unset($this->fillable[$pos]);
			}
		}

		return $this;	
	}

	/*
		Make all fields fillable
	*/
	function fillAll(){
		$this->fillable     = $this->attributes;
		$this->not_fillable = [];

		return $this;	
	}
	
	/**
	 * unfill
	 * remove from fillable list of fields
	 * 
	 * @param  mixed $fields
	 *
	 * @return void
	 */
	protected function unfillAll(array $fields){
		if (!empty($this->fillable) && !empty($fields)){		
			foreach ($this->fillable as $ix => $f){
				foreach ($fields as $to_unset){
					if ($f == $to_unset){
						if (!in_array($f, $this->not_fillable)){
							$this->not_fillable[] = $f;							
						}	

						unset($this->fillable[$ix]);
						break;
					}
				}				
			}
		}

		return $this;
	}
	

	// INNER | LEFT | RIGTH JOIN
	function join($table, $on1 = null, $op = '=', $on2 = null, string $type = 'INNER JOIN')
	{
		$_table     = null;
		$this_alias = null;

		if (preg_match('/([a-z0-9_]+) as ([a-z0-9_]+)/i', $table, $matches)){
			$_table     = $matches[0];
			$table      = $matches[1];
			$this_alias = $matches[2];
		}

		$on_replace = function(&$on) use ($this_alias, $table)
		{	
			if (empty($on)){
				throw new \InvalidArgumentException("Paramter 1 in on_replace can not be null or empty");
			}

			$_on = explode('.', $on);

			if (count($_on) != 2){
				throw new \InvalidArgumentException("Paramter 1 format in on_replace is not well-formatted.");
			}
			
			if (isset($this->table_alias[$this->table_name])){
				if ($_on[0] ==  $this->table_name){
					$on = $this->table_alias[$this->table_name] . '.' . $_on[1];
				}
			}

			if (!is_null($this_alias)){
				if ($_on[0] ==  $table){
					$on = $this_alias . '.' . $_on[1];
				}
			}
		};

		// try auto-join
		if ($on1 == null && $on2 == null){
			if ($this->schema == NULL){
				throw new \Exception("Undefined schema for ". $this->table_name); 
			}

			if (!isset($this->schema['relationships'])){
				throw new \Exception("Undefined relationships for table '{$this->table_name}'"); 
			}

			$rel   = $this->schema['relationships'];
			$pivot = get_pivot([$this->table_name, $table], DB::getCurrentConnectionId());

			// Si la relación no existe => podría ser N:M o no existir
			if (!isset($rel[$table])){
				// **
				// Podría ser una relación N:M si hay pivote o...  1:1, 1:N

				if (!is_null($pivot)){
					// Relación N:M

					$bridge = $pivot['bridge'];
					$rels   = $pivot['relationships'];

					$keys = array_keys($rels);

					if ($keys[0] == $table){
						$rels = array_reverse($rels);
					}

					foreach ($rels as $tb => $rel){
						if ($tb == $table){
							if (!is_null($_table)){
								$t = $_table;
							} else {
								$t = $table;
							}
						} else {
							$t = $bridge;
						}

						$on1 = $rel[0][0];
						$on2 = $rel[0][1];

						$on_replace($on1);
						$on_replace($on2);

						$this->join($t, $on1, '=', $on2, $type);							
					}

					return $this;

				} else {
					// NUNCA DEBERÍA LLEGAR ACÁ PORQUE O ES N:M o NADA
				}   
						
			} // else...

		
			$relx  = $this->schema['expanded_relationships'];

			if (!isset($relx[$table])){
				throw new \Exception("Table '$table' is not 'present' in {$this->table_name}'s schema as if it had a relationship with it");
			}

			$relxs = $relx[$table];

			//dd($rels, 'RELS'); ///

			if (count($relxs) >= 2){
				//dd("Relación multiple entre las mismas dos tablas"); //

				foreach ($relxs as $r){
					if(!isset($r[0]['alias'])){
						$alias = '__' . $r[0][1];
					} else {
						$alias = $r[0]['alias'];
					}					

					$on1 = "{$alias}.{$r[0][1]}"; 
					$on2 = "{$r[1][0]}.{$r[1][1]}";
					
					$ori_tb_name = $table;
					$_table = "$ori_tb_name as $alias";
					
					// dd([
					// 	'table' => $_table,
					// 	'on1' => $on1,
					// 	'on2' => $on2,
					// 	'alias' => $alias
					// ]);

					$this->joins[] = [$_table, $on1, $op, $on2, $type];
				}

				
				return $this;

			} else {
				$on1 = $rel[$table][0][0];
				$on2 = $rel[$table][0][1];
			}
					
		}

		if (!is_null($_table)){
			$table = $_table;
		}

		$on_replace($on1);
		$on_replace($on2);

		$this->joins[] = [$table, $on1, $op, $on2, $type];
		return $this;
	}

	function joinRaw(string $str){
		$this->join_raw[] = $str;
		return $this;
	}

	function leftJoin($table, $on1 = null, $op = '=', $on2 = null) {
		$this->join($table, $on1, $op, $on2, 'LEFT JOIN');
		return $this;
	}

	function rightJoin($table, $on1 = null, $op = '=', $on2 = null) {
		$this->join($table, $on1, $op, $on2, 'RIGHT JOIN');
		return $this;
	}

	/*
		FULL (OUTER) JOIN puede ser emulado en MySQL

		https://stackoverflow.com/questions/7978663/mysql-full-join/36001694
	*/

	function crossJoin($table) {
		$this->joins[] = [$table, null, null, null, 'CROSS JOIN'];
		return $this;
	}

	function naturalJoin($table) {
		$this->joins[] = [$table, null, null, null, 'NATURAL JOIN'];;
		return $this;
	}
	
	function orderBy(array $o){
		$this->order = array_merge($this->order, $o);
		return $this;
	}

	function orderByRaw(string $o){
		$this->raw_order[] = $o;
		return $this;
	}


	function reorder(){
		$this->order = [];
		$this->raw_order = [];
		return $this;
	}

	function take(int $limit = null){
		if ($limit !== null){
			$this->limit = $limit;
		}

		return $this;
	}

	function limit(int $limit = null){
		return $this->take($limit);
	}

	function offset(int $n = null)
	{
		if ($n !== null){
			$this->offset = $n;
		}
		
		return $this;
	}

	function skip(int $n = null){
		return $this->offset($n);
	}

	function paginate(int $page, ?int $page_size = null){
		if ($page_size === null){
			$page_size = Config::get()['paginator']['default_limit'] ?? 10;
		}

		$this->limit  = $page_size;
        $this->offset = Paginator::calcOffset($page, $page_size);

		return $this;
	}

	function groupBy(array $g){
		$this->group = array_merge($this->group, $g);
		return $this;
	}

	function random(){
		$this->randomize = true;

		if (!empty($this->order))
			throw new SqlException("Random order is not compatible with OrderBy clausule");

		return $this;
	}

	function rand(){
		return $this->random();
	}

	function select(array $fields){
		$this->fields = $fields;
		return $this;
	}

	function addSelect(string $field){
		$this->fields[] = $field;
		return $this;
	}

	function selectRaw(string $q, array $vals = null){
		if (substr_count($q, '?') != count((array) $vals))
			throw new \InvalidArgumentException("Number of ? are not consitent with the number of passed values");
		
		if (empty($this->select_raw_q)){
			$this->select_raw_q = $q;

			if ($vals != null){
				$this->select_raw_vals = $vals;
			}
		} else {
			$this->select_raw_q = "{$this->select_raw_q}, $q";

			if ($vals != null){
				$this->select_raw_vals = array_merge($this->select_raw_vals, $vals);
			}
		}

		return $this;
	}

	function whereRaw(string $q, array $vals = null){
		$qm = substr_count($q, '?'); 

		if ($qm !=0){
			if (!empty($vals)){
				if ($qm != count((array) $vals))
					throw new \InvalidArgumentException("Number of ? are not consitent with the number of passed values");
				
				$this->where_raw_vals = $vals;
			}else{
				if ($qm != count($this->to_merge_bindings))
					throw new \InvalidArgumentException("Number of ? are not consitent with the number of passed values");
					
				$this->where_raw_vals = $this->to_merge_bindings;		
			}

		}
		
		$this->where_raw_q = $q;
	
		return $this;
	}

	/*
		Revisar contra:

		https://laravel.com/docs/9.x/queries#where-exists-clauses
	*/
	function whereExists(string $q, array $vals = null){
		$this->whereRaw("EXISTS $q", $vals);
		return $this;
	}

	function whereRegEx(string $field, $value){	
		$this->whereRaw("$field REGEXP ?", [$value]);
		return $this;
	}

	// alias
	function whereRegExp(string $field, $value){
		return $this->whereRegEx($field, $value);
	}

	function whereNotRegEx(string $field, $value){	
		$this->whereRaw("NOT $field REGEXP ?", [$value]);
		return $this;
	}

	// alias
	function whereNotRegExp(string $field, $value){
		return $this->whereNotRegEx($field, $value);
	}

	/*	
		Implementar también:

		whereDay()
		whereMonth()
		whereYear()
		whereTime()
	*/
	function whereDate(string $field, string $value, string $operator = '='){
		if (!in_array($operator, ['=', '>', '<'])){
			throw new \InvalidArgumentException("Invalid operator: '$operator' is invalid for date comparissions");
		}

		$len = strlen($value);

		if ($this->schema !== null){
			if (!isset($this->schema['rules'][$field])){
				throw new \InvalidArgumentException("Unknown field '$field'");
			}	
		}

		if ($len === 10){
			if (!Validator::isType($value, 'date')){
				throw new \InvalidArgumentException("Invalid type: '$value' is not a date");
			}

			switch ($this->schema['rules'][$field]['type']){
				case 'date':
					return $this->where([$field, $value, $operator]);
					break;
				case 'datetime':
					switch ($operator){
						case '=':
							return $this->where([$field, $value.'%', 'LIKE']);
						case '>':
							$value = (new \DateTime("$value +1 day"))->format('Y-m-d H:i:s');
							return $this->where([$field, $value, '>']);
						case '<':
							$value = (new \DateTime("$value -1 day"))->format('Y-m-d H:i:s');
							return $this->where([$field, $value, '<']);							
					}
				default:
					throw new \InvalidArgumentException("Filed type '$field' is not a date or datetime");
			}

		} else if ($len === 19){
			if (!Validator::isType($value, 'datetime')){
				throw new \InvalidArgumentException("Invalid type: '$value' is not a date");
			}

			switch ($this->schema['rules'][$field]['type']){
				case 'date':
					throw new \InvalidArgumentException("Presition can not exced yyyy-mm-dd");
					break;
				case 'datetime':
					switch ($operator){
						case '=':
							return $this->where([$field, $value.'%', 'LIKE']);
						case '>':
							return $this->where([$field, $value, '>']);
						case '<':
							return $this->where([$field, $value, '<']);							
					}
				default:
					throw new \InvalidArgumentException("Filed type '$field' is not a date or datetime");
			}
		} else {
			throw new \InvalidArgumentException("Invalid type: '$value' is not a date or datetime");
		}
		
	}

	function distinct(array $fields = null){
		if ($fields !=  null)
			$this->fields = $fields;
		
		$this->distinct = true;
		return $this;
	}

	function fromRaw(string $q){
		$this->table_raw_q = $q;
		return $this;
	}

	function union(Model $m){
		$this->union_type = 'NORMAL';
		$this->union_q = $m->toSql();
		$this->union_vals = $m->getBindings();
		return $this;
	}

	function unionAll(Model $m){
		$this->union_type = 'ALL';
		$this->union_q = $m->toSql();
		$this->union_vals = $m->getBindings();
		return $this;
	}

	function toSql(array $fields = null, array $order = null, int $limit = NULL, int $offset = null, bool $existance = false, $aggregate_func = null, $aggregate_field = null, $aggregate_field_alias = NULL)
	{		
		$this->aggregate_field_alias = $aggregate_field_alias;

		// dd($this->table_name, "TABLE NAME ======================>");

		if (!empty($fields))
			$fields = array_merge($this->fields, $fields);
		else
			$fields = $this->fields;	

		$paginator = null;

		if (!$existance)
		{			
			// remove hidden			
			if (!empty($this->hidden)){			
			
				if (empty($this->select_raw_q)){
					if (empty($fields) && $aggregate_func == null) {
						$fields = $this->attributes;
					}
		
					foreach ($this->hidden as $h){
						$k = array_search($h, $fields);
						if ($k != null)
							unset($fields[$k]);
					}
				}			

			}
							
			if ($this->distinct){
				$remove = [$this->schema['id_name']];

				if ($this->inSchema([$this->createdAt]))
					$remove[] = $this->createdAt;

				if ($this->inSchema([$this->updatedAt]))
					$remove[] = $this->updatedAt;

				if ($this->inSchema([$this->deletedAt]))
					$remove[] = $this->deletedAt;

				if (!empty($fields)){
					//dd($fields, '$fields +');
					//dd($aggregate_func, '$aggregate_func');
					if (!empty($aggregate_func)){
					 	$fields = array_diff($this->getAttr(), $remove);
					} else {
						$fields = array_diff($fields, $remove);
					}
				}
			} 		


			if ($this->paginator){
				$order  = (!empty($order) && !$this->randomize) ? array_merge($this->order, $order) : $this->order;
				$limit  = $limit  ?? $this->limit  ?? null;
				$offset = $offset ?? $this->offset ?? 0; 
				
				if($limit>0 || $order!=NULL){					
					try {
						$qualified_order = [];
						foreach ($order as $of => $o){
							$fq = $this->getFullyQualifiedField($of);
							$qualified_order[$fq] = $o;
						}

						$paginator = new Paginator();
						$paginator->setLimit($limit);
						$paginator->setOffset($offset);
						$paginator->setOrders($qualified_order);
						$paginator->setAttr($this->attributes);
						$paginator->compile();

						$this->pag_vals = $paginator->getBinding();
					}catch (SqlException $e){
						throw new SqlException("Pagination error: {$e->getMessage()}");
					}
				}else{
					$paginator = null;
				}
							
			} else {
				$paginator = null;	
			}	
		}		
	
		$imp = function(array $fields){
			if (!$this->should_qualify){
				return implode(',', $fields);
			}

			$ta = $this->getTableAlias();

			$arr = array_map(function($f) use ($ta) {
				return "$ta.$f";
			}, $fields);

			return implode(',', $arr);
		};
	
		if (!$existance){
			if (!empty($fields)){
				$_f_imp = $imp($fields);
				$_f     = $_f_imp. ',';
			}else
				$_f = '';
				
			if ($aggregate_func != null){
				if (strtoupper($aggregate_func) == 'COUNT'){					
					if ($aggregate_field == null)
						$aggregate_field = '*';

					if ($this->distinct)
						$q  = "SELECT $_f $aggregate_func(DISTINCT $aggregate_field)" . (!empty($aggregate_field_alias) ? " as $aggregate_field_alias" : '');
					else
						$q  = "SELECT $_f $aggregate_func($aggregate_field)" . (!empty($aggregate_field_alias) ? " as $aggregate_field_alias" : '');
				}else{
					$q  = "SELECT $_f $aggregate_func($aggregate_field)" . (!empty($aggregate_field_alias) ? " as $aggregate_field_alias" : '');
				}
					
			}else{
				$sq = 'SELECT ';
				
				// SELECT RAW
				if (!empty($this->select_raw_q)){
					$distinct = ($this->distinct == true) ? 'DISTINCT' : '';

					// $other_fields = !empty($fields) ? ', '.$_f_imp : '';
					// $q  .= $distinct .' '.$this->select_raw_q. $other_fields;
				
					$other_fields = !empty($fields) ? $_f_imp : '';
					$q  = $other_fields;
					$q .= (!empty(trim($q)) ? ',' : '') . $this->select_raw_q;

					$q = "$sq $distinct $q";
				}else {
					if (empty($fields))
						$q  = $sq . '*';
					else {
						$distinct = ($this->distinct == true) ? 'DISTINCT' : '';
						$q  = $sq . $distinct.' '.$_f_imp;
					}
				}					
			}
		} else {
			$q  = 'SELECT EXISTS (SELECT 1';
		}	

		$q  .= ' FROM ' . DB::quote($this->from());

		////////////////////////
		$values = array_merge($this->w_vals, $this->h_vals); 
		$vars   = array_merge($this->w_vars, $this->h_vars); 
		////////////////////////


		// Validación
		if (!empty($this->validator)){
			$validado = $this->validator->validate(array_combine($vars, $values), $this->getRules());

			if ($validado !== true){
				throw new InvalidValidationException(json_encode(
					$this->validator->getErrors()
				));
			} 
		}
		
		// JOINS
		$joins = '';
		foreach ($this->joins as $j){
			if ($j[4] == 'CROSS JOIN' || $j[4] == 'NATURAL JOIN'){
				$joins .= " $j[4] $j[0] ";
			} else {
				$joins .= " $j[4] $j[0] ON $j[1]$j[2]$j[3] ";
			}
		}

		$joins .= ' ' . implode(' ', $this->join_raw);

		$q  .= $joins;
		

		// WHERE
		$where_section = $this->whereFormedQuery();
		if (!empty($where_section)){

			// patch
			$where_section = str_replace(
							[
								'AND OR', 
								'(AND ',
								'(OR '
							], 
							[	'OR ',
								'( ',
								'( '
							], $where_section);

			$where_section = str_replace('(  NOT ', '(NOT ', $where_section);	

			$q  .= ' WHERE ' . $where_section;
		}
					

		$group = (!empty($this->group)) ? 'GROUP BY '.  $imp($this->group) : '';
		$q  .= " $group";

	
		// HAVING
		$having_section = $this->havingFormedQuery();
		
		if (!empty($having_section)){

			// patch
			$having_section = str_replace(
							[
								'AND OR', 
								'(AND ',
								'(OR '
							], 
							[	'OR ',
								'( ',
								'( '
							], $having_section);

			$having_section = str_replace('(  NOT ', '(NOT ', $having_section);	

			$q  .= ' HAVING ' . $having_section;
		}

		if ($this->randomize){
			$q .= DB::random();
		} else {
			if (!empty($this->raw_order))
				$q .= ' ORDER BY '.implode(', ', $this->raw_order);
		}
		
		// UNION
		if (!empty($this->union_q)){
			$q .= 'UNION '.($this->union_type == 'ALL' ? 'ALL' : '').' '.$this->union_q.' ';
		}


		$q = rtrim($q);
		$q = Strings::rTrim('AND', $q);
		$q = Strings::rTrim('OR',  $q);


		// PAGINATION
		if (!$existance && $paginator!==null){
			$q .= $paginator->getQuery();
		}

		$q  = rtrim($q);
		
		if ($existance)
			$q .= ')';


		if (isset($this->table_alias[$this->table_name])){
			$tb_name = $this->table_alias[$this->table_name];
		} else {
			$tb_name = $this->table_name;
		}

		$q = preg_replace_callback('/ \.([a-z0-9_]+)/', function($matches) use ($tb_name) {
			return ' '.$tb_name .'.'.  $matches[1]; 
		}, $q);

		$q = preg_replace_callback('/\(\.([a-z0-9_]+)/', function($matches) use ($tb_name){
			return '(' . $tb_name .'.'.  $matches[1]; 
		}, $q);


		$q = str_replace('WHERE AND', 'WHERE', $q);
		$q = str_replace('AND AND'  , 'AND',  $q);
		
		
		$this->last_bindings = $this->getBindings();
		$this->last_pre_compiled_query = $q;
		$this->last_operation = 'get';

		return $q;	
	}

	function whereFormedQuery()
	{	
		$where = $this->where_raw_q.' ';

		if (!empty($this->where)){
			$implode = '';

			$cnt = count($this->where);

			if ($cnt>0){
				$implode .= $this->where[0];
				for ($ix=1; $ix<$cnt; $ix++){
					$implode .= ' '.$this->where_group_op[$ix] . ' '.$this->where[$ix];
				}
			}			

			$where = trim($where);

			if (!empty($where)){
				$op = $this->where_group_op[0] ?? 'AND';
				$where = "($where) $op ". $implode. ' '; // <-------------
			}else{
				$where = "$implode ";
			}
		} 	
		
		$where = trim($where);
		
		if ($this->inSchema([$this->deletedAt])){
			if (!$this->show_deleted){

				$tb_name   = $this->getTableAlias();
				$deletedAt = $this->should_qualify ? "{$tb_name}.{$this->deletedAt}" : $this->deletedAt;
				
				if (empty($where))
					$where = "$deletedAt IS NULL";
				else
					$where =  ($where[0]=='(' && $where[strlen($where)-1] ==')' ? $where :   "($where)" ) . " AND $deletedAt IS NULL";

			}
		}
		
		return ltrim($where);
	}

	function havingFormedQuery(){
		$having = '';
		
		if (!empty($this->having_raw_q))
			$having = $this->having_raw_q.' ';

		if (!empty($this->having)){
			$implode = '';

			$cnt = count($this->having);

			if ($cnt>0){
				$implode .= $this->having[0];
				for ($ix=1; $ix<$cnt; $ix++){
					$implode .= ' '.$this->having_group_op[$ix] . ' '.$this->having[$ix];
				}
			}			

			$having = trim($having);
			
			if (!empty($having)){
				$having = "($having) AND ". $implode. ' ';
			}else{
				$having = "$implode ";
			}
		}		

		// acá viene la magia
		$having = preg_replace_callback('/([a-z]+)\(([a-z0-9_]+)\)/i', function($matches){
			$fn    = $matches[1];
			$field = $matches[2];
		
			$field = $this->getFullyQualifiedField($field);
			
			return "$fn($field)";
		}, $having);

		return trim($having);
	}

	function getBindings()
	{	
		$pag = [];
		if (!empty($this->pag_vals)){
			switch (count($this->pag_vals)){
				case 2:
					$pag = [ $this->pag_vals[0][1], $this->pag_vals[1][1] ];
				break;
				case 1: 	
					$pag = [ $this->pag_vals[0][1] ];
				break;
			} 
		}
		
		$values = array_merge(	
								$this->select_raw_vals,
								$this->from_raw_vals,
								$this->where_raw_vals,
								$this->w_vals,
								$this->having_raw_vals,
								$this->h_vals,
								$pag
							);
		return $values;
	}

	//
	function mergeBindings(Model $model){
		$this->to_merge_bindings = $model->getBindings();

		if (!empty($this->table_raw_q)){
			$this->from_raw_vals = $this->to_merge_bindings;	
		}

		return $this;
	}

	/*
		 https://www.php.net/manual/en/pdo.constants.php

	*/
	protected function bind(string $q)
	{
		if (!$this->bind){
			return;
		}

		if ($this->conn == null){
			$this->connect();
		}

		$vals = array_merge($this->select_raw_vals, 
							$this->from_raw_vals, 
							$this->where_raw_vals,
							$this->w_vals,
							$this->having_raw_vals,
							$this->h_vals,
							$this->union_vals);

		///////////////[ BUG FIXES ]/////////////////

		$_vals = [];
		$reps  = 0;
		foreach($vals as $ix => $val)
		{				
			if($val === NULL){
				$q = Strings::replaceNth('?', 'NULL', $q, $ix+1-$reps);
				$reps++;

			/*
				Corrección para operaciones entre enteros y floats en PGSQL
			*/
			} elseif(DB::driver() == DB::PGSQL && is_float($val)){ 
				$q = Strings::replaceNth('?', 'CAST(? AS DOUBLE PRECISION)', $q, $ix+1-$reps);
				$reps++;
				$_vals[] = $val;
			} else {
				$_vals[] = $val;
			}
		}

		$vals = $_vals;

		///////////////////////////////////////////


		if (!$this->exec){
			return; //
		}

		/*
			La idea es poder accederlo desde el Error Handler por ejemplo
			en caso de ser necesario

			Se supone que cualquier SQL generado y ejecutado sera bindeado
			asi que debe de pasar por este lugar 
			
			(excepto claro que se use la clase DB directamente)
		*/

		static::$current_sql = $q;

		try {
			$st = $this->conn->prepare($q);			
		} catch (\Exception $e){
			$vals_str = implode(',', $vals);

			$this->logSQL();
			throw new SqlException("Query '$q' - and vals = [$vals_str] | ". $e->getMessage());
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
				throw new \Exception("where value can not be an array!");				
			}else {
				throw new \Exception("Unsupported type: " . var_export($val, true));
			}	

			$st->bindValue($ix +1 , $val, $type);
			//echo "Bind: ".($ix+1)." - $val ($type)\n";
		}

		$sh = count($vals);

		$bindings = $this->pag_vals;
		foreach($bindings as $ix => $binding){
			$st->bindValue($ix +1 +$sh, $binding[1], $binding[2]);
		}		
		
		return $st;	
	}

	function getLastPrecompiledQuery(){
		return $this->last_pre_compiled_query;
	}

	function getLastBindingParamters(){
		return $this->last_bindings;
	}

	private function _dd($pre_compiled_sql, $bindings){		
		foreach($bindings as $ix => $val){			
			if(is_null($val)){
				$bindings[$ix] = 'NULL';
			}elseif(isset($vars[$ix]) && isset($this->schema['attr_types'][$val])){
				$const = $this->schema['attr_types'][$val];
				if ($const == 'STR')
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

		if ($this->semicolon_ending){
			$sql .= ';';
		}

		if ($this->sql_formatter_status){
			$sql = static::sqlFormatter($sql);
		}		

		return $sql;
	}

	// Debug query
	function dd(bool $sql_formatter = false){
		$this->sql_formatter_status = self::$sql_formatter_status ?? $sql_formatter;

		if ($this->last_operation == 'create'){
			return $this->last_compiled_sql;
		}

		return $this->_dd($this->toSql(), $this->getBindings());
	}

	function getLog(bool $sql_formatter = false){		
		$this->sql_formatter_status = self::$sql_formatter_status ?? $sql_formatter;

		if ($this->last_operation == 'create'){
			return $this->last_compiled_sql;
		}

		return $this->_dd($this->last_pre_compiled_query, $this->last_bindings);
	}

	function debug(){
		$op = $this->current_operation ?? $this->last_operation;

		if ($op == 'create'){
			$combined = array_combine($this->insert_vars, $this->getLastBindingParamters());
			$sql = $this->last_pre_compiled_query;
							
			return preg_replace_callback('/:([a-z][a-z0-9_\-ñáéíóú]+)/', function($matches) use ($combined) {
				$key = $matches[1];

				// para el debug ignoro los tipos
				return "'$combined[$key]'";
			}, $sql);
		
		} else {
			return $this->dd();
		}
	}

	function logSQL(){
		$config = Config::get();

		if ($config['debug'] && $config['log_sql']){
			log_sql($this->dd());
		}
	}

	function getLastOperation(){
		return $this->last_operation;
	}

	function getCurrentOperation(){
		return $this->current_operation;
	}

	function getOp(){
		return $this->current_operation ?? $this->last_operation;
	}

	function getWhere(){
		return $this->where;
	}

	function get(array $fields = null, array $order = null, int $limit = NULL, int $offset = null, $pristine = false){
		$this->onReading();

		$q = $this->toSql($fields, $order, $limit, $offset);
		$st = $this->bind($q);

		$count = null;
		if ($this->exec && $st->execute()){
			$output = $st->fetchAll($this->getFetchMode());
			
			$count  = $st->rowCount();
			if (empty($output)) {
				$ret = [];
			}else {
				$ret = $pristine ? $output : $this->applyTransformer($this->applyOutputMutators($output));
			}

			$this->onRead($count);
		}else
			$ret = false;
				
		return $ret;	
	}

	function first(array $fields = null, $pristine = false){
		$this->onReading();

		$q = $this->toSql($fields, NULL);
		$st = $this->bind($q);

		$count = null;
		if ($this->exec && $st->execute()){
			$output = $st->fetch($this->getFetchMode());
			$count = $st->rowCount();

			if (empty($output)) {
				$ret = []; // deberia retornar null !!!!!! Corregir pero analizar y probar luego ApiController
			}else {
				$ret = $pristine ? $output : $this->applyTransformer($this->applyOutputMutators($output));
			}

			$this->onRead($count);
		}else
			$ret = false;
				
		return $ret;
	}

	function firstOrFail(array $fields = null, $pristine = false){
		$ret = $this->first($fields, $pristine);

		if (empty($ret)){
			// Debería ser una Excepción personalizada
			throw new \Exception("No rows");
		}

		return $ret;
	}

	function getOne(array $fields = null, $pristine = false){
		return $this->first($fields, $pristine);
	}

	function top(array $fields = null, $pristine = false){
		return $this->first($fields, $pristine);
	}

	function value($field, ?string $cast_to = null){
		$this->onReading();

		$q  = $this->toSql([$field]);
		$st = $this->bind($q);

		$count = null;
		if ($this->exec && $st->execute()) {
			$ret = $st->fetch(\PDO::FETCH_NUM)[0] ?? false;
			
			$count = $st->rowCount();
			$this->onRead($count);
		} else
			$ret = false;

		/* 
			Castear false daria muchos errores
			ya que es el valor devuelto ante fallo
		*/
		if ($ret != false && !empty($cast_to)){
			switch ($cast_to){
				case 'string':
					$ret = (string) $ret;
					break;
				case 'int':
				case 'integer':
					$ret = (int) $ret;
					break;
				case 'float':
					$ret = (float) $ret;
					break;
				case 'double':
					$ret = (double) $ret;
					break;
				case 'bool':
					switch(gettype($ret)){
						case 'string':
							$_s = strtolower($ret);
							
							switch($_s){
								case '1':
								case 'on':
									$ret = true;
									break;
								case '0':
								case 'off':
									$ret = false;
									break;
							}
						break;

						case 'int':
							switch($ret){
								case 1:
									$ret = true;
									break;
								case 0:
									$ret = false;
									break;
							}
						break;
					}
					// sino cumple esas reglas estricas, el casting de bools no se efectua
					
					break;
				default:
					throw new \InvalidArgumentException("Invalid cast");
			}
		}
			
		return $ret;	
	}

	function exists(){
		$q = $this->toSql(null, null, null, null, true);
		$st = $this->bind($q);

		if ($this->exec && $st->execute()){
			return (bool) $st->fetch(\PDO::FETCH_NUM)[0];
		}else
			return false;	
	}

	function pluck(string $field){
		$this->setFetchMode('COLUMN');
		$this->fields = [$field];

		$q  = $this->toSql();
		$st = $this->bind($q);
	
		if ($this->exec && $st->execute()) {
			$res = $this->applyTransformer($this->applyOutputMutators(
				$st->fetchAll($this->getFetchMode()))
			);

			/*	
				Si el schema tiene algo como:

				'sql_data_types' => [
					'{campo}' => 'JSON',
					// ...
				],
			*/
			if ($this->schema != NULL && isset($this->schema['sql_data_types']) && isset($this->schema['sql_data_types'][$field]) )
			{
				if ($this->schema['sql_data_types'][$field] == 'JSON'){
					$res = array_map(function ($e){
						return json_decode($e, true);
					},
						$res
					);
				}
			}

			return $res;
		} else
			return false;	
	}

	protected function aggregate(string $fn_name, $field, $alias)
	{
		$fn_name = strtoupper($fn_name);

		if (!in_array($fn_name, [
			'MAX',
			'MIN',
			'COUNT',
			'SUM',
			'AVG'
		])){
			throw new \InvalidArgumentException("Invalid aggregate function");
		}
		
		$q = $this->toSql(null, null, null, null, false, $fn_name, $field, $alias);
		$st = $this->bind($q);

		if (empty($this->group)){
			if ($this->exec && $st->execute()){
				// fetch
				return(int) $st->fetch($this->getFetchMode('COLUMN'));
			}else
				return false;	
		}else{
			if ($this->exec && $st->execute()){
				// fetch all
				return (int) $st->fetchAll($this->getFetchMode('COLUMN'));
			}else
				return false;
		}	
	}

	function avg($field, $alias = NULL){
		return $this->aggregate(__FUNCTION__, $field, $alias);
	}

	function sum($field, $alias = NULL){
		return $this->aggregate(__FUNCTION__, $field, $alias);
	}

	function min($field, $alias = NULL){
		return $this->aggregate(__FUNCTION__, $field, $alias);
	}

	function max($field, $alias = NULL){
		return $this->aggregate(__FUNCTION__, $field, $alias);
	}

	function count($field = NULL, $alias = NULL){
		return $this->aggregate(__FUNCTION__, $field, $alias);
	}

	function getWhereVals(){
		return $this->w_vals;
	}

	function getWhereVars(){
		return $this->w_vars;
	}

	function getWhereRawVals(){
		return $this->where_raw_vals;
	}

	function getHavingVals(){
		return $this->h_vals;
	}

	function getHavingVars(){
		return $this->h_vars;
	}

	function getHavingRawVals(){
		return $this->having_raw_vals;
	}

	// crea un grupo dentro del where
	function group(callable $closure, string $conjunction = 'AND', bool $negate = false) 
	{	
		$not = $negate ? ' NOT ' : '';

		$m = new Model();		
		call_user_func($closure, $m);	

		$w_formed 	= $m->whereFormedQuery();

		if (!empty($w_formed)){
			$w_vars   	= $m->getWhereVars();
			$w_vals   	= $m->getWhereVals();
			$w_raw_vals = $m->getWhereRawVals();

			$this->where[] = "$conjunction $not($w_formed)";	
			$this->w_vars  = array_merge($this->w_vars, $w_vars);
			$this->w_vals  = array_merge($this->w_vals, $w_raw_vals, $w_vals); // *
			
			$this->where_group_op[] = '';
		}


		$h_formed 	= $m->havingFormedQuery();

		if(!empty($h_formed)){
			$h_vars   	= $m->getHavingVars();
			$h_vals   	= $m->getHavingVals();
			$h_raw_vals = $m->getHavingRawVals();

			$this->having[] = "$conjunction $not($h_formed)";	
			$this->h_vars  = array_merge($this->h_vars, $h_vars);
			$this->h_vals  = array_merge($this->h_vals, $h_raw_vals, $h_vals); // *
			
			$this->having_group_op[] = '';
		}

		return $this;
	}

	function and(callable $closure){
		return $this->group($closure, 'AND', false);
	}

	function or(callable $closure){
		return $this->group($closure, 'OR', false);
	}

	function andNot(callable $closure){
		return $this->group($closure, 'AND', true);
	}

	// alias
	function not(callable $closure){
		return $this->andNot($closure);
	}

	function orNot(callable $closure){
		return $this->group($closure, 'OR', true);
	}

	function when($precondition = null, ?callable $closure = null, ?callable $closure2 = null){
		if (!empty($precondition)){			
			call_user_func($closure, $this);	
		} elseif ($closure2 != null){
			call_user_func($closure2, $this);
		}
		
		return $this;	
	}

	static protected function _where_array(Array $cond_ay, $parent_conj = 'AND')
	{
		$accepted_conj = [
			'AND', 'OR', 'NOT', 'AND NOT', 'OR NOT'
		];

		$code = '';
		foreach ($cond_ay as $key => $ay){
			if (!is_array($ay)){
				continue;
			}

			$ay_str = var_export($ay, true);

			//dd($ay, "PARENT CONJ is $parent_conj");

			if (is_string($key)){
				if (!in_array($key, $accepted_conj)){
					throw new \Exception("Conjuntion '$key' is invalid");
				}

				$conj = $key;

				$is_simple = Arrays::areSimpleAllSubArrays($ay);
				$is_multi  = is_array($ay) && Arrays::isMultidim($ay);

				//dd($ay, "GRUPO con op $key " . ($is_simple ? ' -- simple' : ''));

				if ($is_simple || !$is_multi){
					$w_type = ($parent_conj == 'OR' ? 'whereOr' : 'where');
				
					// switch ($parent_conj){
					// 	case 'OR':
					// 		$w_type = 'whereOr';
					// 		break;
					// 	case 'AND':
					// 		$w_type = 'where';
					// 		break;

					// 	case 'NOT':
					// 		$w_type = 'andNot'; //
					// 		break;
					// 	case 'OR NOT':
					// 		$w_type = 'orNot'; //
					// 		break;
					// 	case 'AND NOT':
					// 		$w_type = 'andNot'; //
					// 		break;
				
					// 	default:
					// 		$w_type = 'where';
					// 		break;
					// }


					$code .= "\$q->$w_type($ay_str);\n";
				} else {
					$code  .=  "\$q->group(function (\$q) {". static::_where_array($ay, $conj) ."});\n";
				}
				
				
			} else {
				$is_simple = is_array($ay) && Arrays::areSimpleAllSubArrays($ay);
				$is_multi  = is_array($ay) && Arrays::isMultidim($ay);

				//dd($ay, "GRUPO - key $key" . ($is_simple ? ' -- simple' : ''));

				if ($is_simple || !$is_multi){
					
					$w_type = ($parent_conj == 'OR' ? 'orWhere' : 'where');

					// switch ($parent_conj){
					// 	case 'OR':
					// 		$w_type = 'orWhere';
					// 		break;
					// 	case 'AND':
					// 		$w_type = 'where';
					// 		break;

					// 	case 'NOT':
					// 		$w_type = 'andNot'; //
					// 		break;
					// 	case 'OR NOT':
					// 		$w_type = 'orNot'; //
					// 		break;
					// 	case 'AND NOT':
					// 		$w_type = 'andNot'; //
					// 		break;
				
					// 	default:
					// 		$w_type = 'where';
					// 		break;
					// }


					$code  .= "\$q->$w_type(". $ay_str .");\n";
				
				} else {

					// dd(
					// 	$ay, "Multi?" . ((int) $is_multi) .  " Simple? " . ((int) $is_simple)
					// );

					if (!in_array($key, $accepted_conj)){
						$conj = 'AND';
					} else {
						$conj = $key;
					}

					$code  .=  "\$q->group(function (\$q) {". static::_where_array($ay, $conj) ."});\n";
				}

			}
		}	

		return $code;
	}

	/*
	Interpreta un array como el siguiente:

      	[
			'AND' => [
				[
					'OR' => [
						'OR' => [
							['cost', 100, '<='],
							['description', NULL, 'IS NOT']
						],

						['name', '%Pablo', 'LIKE']
					]
				],

				['stars', 5]
			]    
		]

		Y debe poder interpretar lo siguiente:

		[
			'AND' => [
				['name', '%a%', 'LIKE'],

				[
					'AND' => [                     // <----- podria ser hibrido funcionando igual si falta la conjuncion y asumiendo es 'AND'
						['cost', 100, '>'],
						['id', 50, '<']
					]
				],
				
				[
					'OR' => [
						['is_active', 1],
						[
							'AND' => [ 
								['cost', 100, '<='],
								['description', NULL, 'IS NOT']
							]
						]
					]
				],
				
				['belongs_to', 150, '>']		
			]	
		]

		* Tambien deberia poder (a futuro) aceptar NOT, AND NOT y OR NOT

		De momento devuelve el codigo que debe evaluarse con eval()

		Ej:

		$q = Model::where_array($ay);

        $code = Strings::beforeLast("return table('products')$q", ';') . '->dd();';

        dd($code);

        DB::getConnection("az");
        
        dd(
            eval($code)
        );

		Podria eventualmente armar solamente el where() y encolar los parametros para hacer luego el binding sin requerir eval.
	*/
	static function where_array(Array $cond_ay){
		return ltrim(
			static::_where_array($cond_ay),
			'$q'
		);
	}

	protected function _where(?array $conditions = null, string $group_op = 'AND', $conjunction = null)
	{
		//dd($group_op, 'group_op');
		//dd($conjunction, 'conjuntion');

		if (empty($conditions)){
			return;
		}

		if (Arrays::isAssocc($conditions)){
			$conditions = Arrays::nonAssoc($conditions);
		}

		if (isset($conditions[0]) && is_string($conditions[0]))
			$conditions = [$conditions];

		$_where = [];

		$vars   = [];
		$ops    = [];
		if (count($conditions)>0){
			if(is_array($conditions[Arrays::arrayKeyFirst($conditions)])){

				foreach ($conditions as $ix => $cond) {
					$unqualified_field = $this->unqualifyField($cond[0]);
					$field = $this->getFullyQualifiedField($cond[0]);

					if ($field == null)
						throw new SqlException("Field can not be NULL");

					if(is_array($cond[1]) && (empty($cond[2]) || in_array($cond[2], ['IN', 'NOT IN']) ))
					{	
						if ((!isset($this->schema['attr_types']) || 
							(isset($this->schema['attr_types'][$unqualified_field]) && $this->schema['attr_types'][$unqualified_field] == 'STR')
						)){
							$cond[1] = array_map(function($e){ return "'$e'";}, $cond[1]);  
						}
						
						$in_val = implode(', ', $cond[1]);
						
						$op = isset($cond[2]) ? $cond[2] : 'IN';
						$_where[] = "$field $op ($in_val) ";	
					}else{
						$vars[]   = $field;
						$this->w_vals[] = $cond[1];

						if ($cond[1] === NULL && (empty($cond[2]) || $cond[2]=='='))
							$ops[] = 'IS';
						else	
							$ops[] = $cond[2] ?? '=';
					}	
				}

			}else{
				$vars[]   = $conditions[0];
				$this->w_vals[] = $conditions[1];
		
				if ($conditions[1] === NULL && (empty($conditions[2]) || $conditions[2]== '='))
					$ops[] = 'IS';
				else	
					$ops[] = $conditions[2] ?? '='; 
			}	
		}

		foreach($vars as $ix => $var){
			$_where[] = "$var $ops[$ix] ?";
		}

		$this->w_vars = array_merge($this->w_vars, $vars); //

		////////////////////////////////////////////
		// group
		$ws_str = implode(" $conjunction ", $_where);
		
		if (count($conditions)>1 && !empty($ws_str))
			$ws_str = "($ws_str)";
		
		$this->where_group_op[] = $group_op;	

		$this->where[] = ' ' .$ws_str;

		return;
	}

	function whereColumn(string $field1, string $field2, string $op = '='){
		$validation = Factory::validador()->validate(
			[
				'col1' => $field1, 
				'col2' => $field2
			],
			[ 
				'col1' => ['type' => 'alpha_num_dash'], 
				'col2' => ['type' => 'alpha_num_dash']
			]);

		if (!$validation){
			throw new InvalidValidationException(json_encode(
				$this->validator->getErrors()
			));
		}

		if (!in_array($op, ['=', '>', '<', '<=', '>=', '!='])){
			throw new \InvalidArgumentException("Invalid operator '$op'");
		}	

		$field1 = $this->getFullyQualifiedField($field1);
		$field2 = $this->getFullyQualifiedField($field2);

		$this->where_raw_q = "{$field1}{$op}{$field2}";
		return $this;
	}

	function where($conditions, $conjunction = 'AND')
	{
		// Si se hace where(1) lo convierte en WHERE 1=1
		if ($conditions == 1){
			$conditions = [1, 1];
		}

		/*
			Laravel compatibility
			
			In "Laravel mode", $conditions es la key y $conjunction el valor
		*/
		if (is_string($conditions)){
			$key              = $conditions;
			$conditions       = [];
			$conditions[$key] = $conjunction;
		}

		$this->_where($conditions, 'AND', $conjunction);
		return $this;
	}

	function orWhere($conditions, $conjunction = 'AND'){
		$this->_where($conditions, 'OR', $conjunction);
		return $this;
	}

	function whereOr($conditions){
		$this->_where($conditions, 'AND', 'OR');
		return $this;
	}

	// ok
	function orHaving($conditions, $conjunction = 'AND'){
		$this->_having($conditions, 'OR', $conjunction);
		return $this;
	}

	function orWhereRaw(string $q, array $vals = null){
		$this->or(function($x) use ($q, $vals){
			$x->whereRaw($q, $vals);
		});

		return $this;
	}

	/*
		Es un error usar or() ya que depende de group() que afecta solo a los WHERE
	*/
	function orHavingRaw(string $q, array $vals = null){
		// $this->or(function($x) use ($q, $vals){
		// 	$x->HavingRaw($q, $vals);
		// });

		// return $this;
	}

	function firstWhere($conditions, $conjunction = 'AND'){
		$this->where($conditions, $conjunction);
		return $this->first();
	}

	function find($id){
		if (empty($this->schema)){
			return $this->where(['id' => $id]);
		}		

		return $this->where([$this->getFullyQualifiedField($this->schema['id_name']) => $id]);
	}

	/*
		In Laravel,	works with Relationships
		
		The method also works with HasMany, HasManyThrough, BelongsToMany, MorphMany, and MorphToMany relations seamlessly.

		$user->posts()->findOr(1, fn () => '...');
	*/
	function findOr($id, ?callable $fn = null){
		$query = $this->find($id);

		if ($fn != null && !$this->exists()){
			return $fn($id);
		}   

		return $query;
	}

	function findOrFail($id){
		return $this->findOr($id, function($id){
			throw new \Exception("Resource for `{$this->table_name}` and id=$id doesn't exist");
		});
	}

	function whereNot(string $field, $val){
		$this->where([$this->getFullyQualifiedField($field), $val, '!=']);
		return $this;
	}

	function whereNull(string $field){
		$this->where([$this->getFullyQualifiedField($field), NULL]);
		return $this;
	}

	function whereNotNull(string $field){
		$this->where([$this->getFullyQualifiedField($field), NULL, 'IS NOT']);
		return $this;
	}

	function whereIn(string $field, array $vals){
		$this->where([$this->getFullyQualifiedField($field), $vals, 'IN']);
		return $this;
	}

	function whereNotIn(string $field, array $vals){
		$this->where([$this->getFullyQualifiedField($field), $vals, 'NOT IN']);
		return $this;
	}

	function whereBetween(string $field, array $vals){
		if (count($vals)!=2)
			throw new \InvalidArgumentException("whereBetween accepts an array of exactly two items");

		$min = min($vals[0],$vals[1]);
		$max = max($vals[0],$vals[1]);

		$this->where([$this->getFullyQualifiedField($field), $min, '>=']);
		$this->where([$this->getFullyQualifiedField($field), $max, '<=']);
		return $this;
	}

	function whereNotBetween(string $field, array $vals){
		if (count($vals)!=2)
			throw new \InvalidArgumentException("whereBetween accepts an array of exactly two items");

		$min = min($vals[0],$vals[1]);
		$max = max($vals[0],$vals[1]);

		$this->where([
						[$this->getFullyQualifiedField($field), $min, '<'],
						[$this->getFullyQualifiedField($field), $max, '>']
		], 'OR');
		return $this;
	}

	function whereLike(string $field, $val){
		$this->where([$this->getFullyQualifiedField($field), $val, 'LIKE']);
		return $this;
	}

	function oldest(){
		$this->orderBy([$this->getFullyQualifiedField($this->createdAt) => 'ASC']);
		return $this;
	}

	function latest(){
		$this->oldest();
		return $this;
	}

	function newest(){
		$this->orderBy([$this->getFullyQualifiedField($this->createdAt) => 'DESC']);
		return $this;
	}
	
	function _having(array $conditions = null, $group_op = 'AND', $conjunction = null)
	{	
		if (Arrays::isAssocc($conditions)){
            $conditions = Arrays::nonAssoc($conditions);
        }

		if ((count($conditions) == 3 || count($conditions) == 2) && !is_array($conditions[1]))
			$conditions = [$conditions];

		// dd($conditions, 'CONDITIONS');

		$_having = [];
		foreach ((array) $conditions as $cond)
		{	
			if (Arrays::isAssocc($cond)){
				$cond[0] = Arrays::arrayKeyFirst($cond);
				$cond[1] = $cond[$cond[0]];
			}
			
			if (in_array($cond[0], $this->getAttr())){
				$dom = $this->getFullyQualifiedField($cond[0]);
			} else {
				$dom = $cond[0];
			}			

			$op = $cond[2] ?? '=';	
			
			$_having[] = "$dom $op ?";
			$this->h_vars[] = $dom;
			$this->h_vals[] = $cond[1];
		}

		////////////////////////////////////////////
		// group
		$ws_str = implode(" $conjunction ", $_having);
		
		if (count($conditions)>1 && !empty($ws_str))
			$ws_str = "($ws_str)";
		
		$this->having_group_op[] = $group_op;	

		$this->having[] = ' ' .$ws_str;
		////////////////////////////////////////////

		// dd($this->having, 'HAVING:');
		// dd($this->h_vars, 'VARS');
		// dd($this->h_vals, 'VALUES');

		return $this;
	}

	function havingRaw(string $q, array $vals = null, $conjunction = 'AND'){
		if (substr_count($q, '?') != count($vals))
			throw new \InvalidArgumentException("Number of ? are not consitent with the number of passed values");
		
		$this->having_raw_q = $q;

		if ($vals != null)
			$this->having_raw_vals = $vals;


		////////////////////////////////////////////
		// group

		// No está implementado
		////////////////////////////////////////////

		// dd($this->having, 'HAVING:');
		// dd($this->h_vars, 'VARS');
		// dd($this->h_vals, 'VALUES');
			
		return $this;
	}

	function having(array $conditions, $conjunction = 'AND')
	{	
		if (Arrays::isAssocc($conditions)){
            $conditions = Arrays::nonAssoc($conditions);
        }

		if (!is_array($conditions[0])){
			if (Strings::contains('(', $conditions[0])){
				$op = $conditions[2] ?? '=';

				$q = "{$conditions[0]} {$op} ?";
				$v = $conditions[1];

				if ($this->strict_mode_having){
					throw new \Exception("Use havingRaw() instead for {$q}");
				}

				$this->havingRaw($q, [$v]);
				return $this;
			}
		} 
	
		$this->_having($conditions, 'AND', $conjunction);
		return $this;
	}

	/*
		No admite eventos. Depredicada.

		El uso de esta función deberá ser reemplazado por DB::select()
	*/
	static function query(string $raw_sql, $fetch_mode = \PDO::FETCH_ASSOC){
		$conn = DB::getConnection();

		$query = $conn->query($raw_sql);
		DB::setRawSql($raw_sql);

		if ($fetch_mode !== null){
			if (is_string($fetch_mode)){
				$fetch_mode = constant("\PDO::FETCH_{$fetch_mode}");
			}

			$query->setFetchMode($fetch_mode);
		}

		$output = $query->fetchAll();

		return $output;
	}

	/**
	 * update
	 * It admits partial updates
	 *
	 * @param  array $data
	 *
	 * @return mixed
	 * 
	 */
	function update(array $data, $set_updated_at = true)
	{
		if ($this->conn == null)
			throw new SqlException('No conection');
			
		if (empty($data)){
			throw new SqlException('There is no data to update');
		}

		if (!Arrays::isAssocc($data)){
			throw new SqlException('Array of data should be associative');
		}

		$this->ignoreFieldsNotPresentInSchema($data);


		$this->data = $data;

		switch ($this->current_operation){
			case 'restore':
				$this->onRestoring($data);
				break;
			case 'delete':
				$this->onDeleting($data);
			default:
				$this->onUpdating($data);
		}		

		$data = $this->applyInputMutator($data, 'UPDATE');
		$vars = array_keys($data);
		$vals = array_values($data);

		if(!empty($this->fillable) && is_array($this->fillable)){
			foreach($vars as $var){
				if (!in_array($var,$this->fillable))
					throw new SqlException("Update: $var is not fillable");
			}
		}

		// Validación
		if (!empty($this->validator)){
			$validado = $this->validator->validate($data, $this->getRules());
			if ($validado !== true){
				throw new InvalidValidationException(json_encode(
					$this->validator->getErrors()
				));
			} 
		}
	
		$set = '';
		foreach($vars as $ix => $var){
			$set .= " $var = ?, ";
		}
		$set =trim(substr($set, 0, strlen($set)-2));

		if ($set_updated_at && $this->inSchema([$this->updatedAt])){
			if (isset($this->config)){
				$d = new \DateTime('', new \DateTimeZone($this->config['DateTimeZone'])); // *
			} else {
				$d = new \DateTime();
			}
						
			$at = $d->format('Y-m-d G:i:s');

			$set .= ", {$this->updatedAt} = '$at'";
		}

		if (!empty($this->where)){
			$where = implode(' AND ', $this->where);
		} else {
			$where = '';
		}

		if (trim($where) == ''){
			throw new SqlException("WHERE can not be empty in UPDATE statement");
		}

		$q = "UPDATE ". DB::quote($this->from()) .
				" SET $set WHERE " . $where;		

		// dd($q, 'Update statement');

		/*
			JSON no puede ser un string vacio ('')
		*/
		foreach($vals as $ix => $val){	
			if (isset($this->schema['attr_type_detail'][$vars[$ix]]) && in_array($this->schema['attr_type_detail'][$vars[$ix]], ['JSON'])){
				if ($vals[$ix] == ''){
					$vals[$ix] = null;
				} 
			}
		}
		
		$vals = array_merge($vals, $this->w_vals);
		$vars = array_merge($vars, $this->w_vars);		

		///////////////[ BUG FIXES ]/////////////////

		$_vals = [];
		$reps  = 0;
		foreach($vals as $ix => $val)
		{				
			if($val === NULL){
				$q = Strings::replaceNth('?', 'NULL', $q, $ix+1-$reps);
				$reps++;

			/*
				Corrección para operaciones entre enteros y floats en PGSQL
			*/
			} elseif(DB::driver() == DB::PGSQL && is_float($val)){ 
				$q = Strings::replaceNth('?', 'CAST(? AS DOUBLE PRECISION)', $q, $ix+1-$reps);
				$reps++;
				$_vals[] = $val;
			} else {
				$_vals[] = $val;
			}
		}

		$vals = $_vals;

		///////////////////////////////////////////

		if ($this->semicolon_ending){
			$q .= ';';
		}

		// dd($vals, 'vals');
		// dd($q, 'q');

		$st = $this->conn->prepare($q);

		foreach($vals as $ix => $val){		
			if(is_array($val)){
				if (isset($this->schema['attr_types'][$vars[$ix]]) && !$this->schema['attr_types'][$vars[$ix]] == 'STR'){
					throw new \InvalidArgumentException("Param '{[$vars[$ix]}' is not expected to be an string. Given '$val'");
				} else {
					$val = json_encode($val);
					$type = \PDO::PARAM_STR;
				}	
			} else {
				if(is_null($val)){
					$type = \PDO::PARAM_NULL;
				}elseif(isset($vars[$ix]) && isset($this->schema['attr_types'][$vars[$ix]])){
					$const = $this->schema['attr_types'][$vars[$ix]];
					$type = constant("PDO::PARAM_{$const}");
				}elseif(is_int($val))
					$type = \PDO::PARAM_INT;
				elseif(is_bool($val))
					$type = \PDO::PARAM_BOOL;
				elseif(is_string($val))
					$type = \PDO::PARAM_STR;	
			}
			
			$st->bindValue($ix+1, $val, $type);
		}

		$this->last_bindings = $vals;
		$this->last_pre_compiled_query = $q;
		$this->last_operation = ($this->current_operation !== null) ? $this->current_operation : 'update';
	 
		if (!$this->exec){
			return 0;
		}

		if($st->execute()) {
			$count = $st->rowCount();

			switch ($this->current_operation){
				case 'restore':
					$this->onRestored($data, $count);
					break;
				case 'delete':
					$this->onDeleted($data, $count);
					break;
				default:
					$this->onUpdated($data, $count);
			}	
		
		} else 
			$count = false;
			
		return $count;
	}

	function updateOrFail(array $data, $set_updated_at = true)
	{
		if (!$this->exists()){
			throw new \Exception("Resource does not exist");
		}                     

		return $this->update($data, $set_updated_at);
	}

	function touch(){
		$this->fill([$this->updatedAt()]);
		return $this->update([$this->updatedAt() => at()]);
	}

	function setSoftDelete(bool $status) {
		if (!$this->inSchema([$this->deletedAt])){
			if ($status){
				throw new SqlException("There is no $this->deletedAt for table '".$this->from()."' in the attr_types");
			}
		} 
		
		$this->soft_delete = $status;
		return $this;
	}

	/**
	 * delete
	 *
	 * @param  array $data (aditional fields in case of soft-delete)
	 * @return mixed
	 */
	function delete(bool $soft_delete = true, array $data = [])
	{
		if ($this->conn == null)
			throw new SqlException('No conection');

		// Validación
		if (!empty($this->validator)){
			$validado = $this->validator->validate(array_combine($this->w_vars, $this->w_vals), $this->getRules());

			if ($validado !== true){
				throw new InvalidValidationException(json_encode(
					$this->validator->getErrors()
				));
			} 
		}

		if ($this->soft_delete && $soft_delete){
			$at = at();

			$to_fill = [];
			if (!empty($data)){
				$to_fill = array_keys($data);
			}
			$to_fill[] = $this->deletedAt;

			$data =  array_merge($data, [$this->deletedAt => $at]);

			$this->fill($to_fill);
			
			$this->current_operation = 'delete';
			$ret = $this->update($data, false);
			$this->last_operation    = 'delete';

			return $ret;
		}		

		$this->onDeleting($data);

		$where = '';	
		if (!empty($this->where)){
			$where = implode(' AND ', $this->where);
		}		
	
		$where = trim($where);
		
		if (empty($where) && empty($this->where_raw_q)){
			throw new \Exception("DELETE statement requieres WHERE condition");
		}

		if (!empty($this->where_raw_q)){
			if (!empty($where)){
				$where = $this->where_raw_q . " AND $where";
			} else {
				$where = $this->where_raw_q;
			}			
		}

		$q = "DELETE FROM ". DB::quote($this->from()) . " WHERE " . $where;
		
		if ($this->semicolon_ending){
			$q .= ';';
		}

		$st = $this->bind($q);
	 
		$this->last_bindings = $this->getBindings();
		$this->last_pre_compiled_query = $q;
		$this->last_operation = 'delete';

		if($this->exec && $st->execute()) {
			$count = $st->rowCount();
			$this->onDeleted($data, $count);
		} else 
			$count = false;	
		
		return $count;	
	}

	function forceDelete(){
		$this->delete(false);
	}

	protected function checkUndeletePreconditions() : bool
	{
		if (!$this->soft_delete){
			throw new \Exception("Undelete is not available");
		}	

		$where = '';	
		if (!empty($this->where)){
			$where = implode(' AND ', $this->where);
		}		
	
		$where = trim($where);
		
		if (empty($where) && empty($this->where_raw_q)){
			throw new \Exception("Lacks WHERE condition");
		}

		return true;
	}

	// debe remover cualquier condición que involucre a $this->deletedAt en el WHERE !!!!
	function deleted($state = true){
		$this->show_deleted = $state;
		return $this;
	}

	// alias de deleted()
	function withTrashed(){
		return $this->deleted(true);
	}

	function onlyTrashed(){
		$this->deleted();
		$this->whereNotNull($this->deletedAt());
		return $this;
	}

	/*
		Devuelve si la row fue borrada
	*/
	function trashed() : bool
	{
		$this->checkUndeletePreconditions();
		$this->onlyTrashed();
		return $this->exists();
	}

	// alias
	function is_trashed() : bool
	{
		return $this->trashed();
	}


	/*
		Si el undelete está disponible intenta restaurar sin chequear si la row fue previamente borrada
	*/
	function undelete()
	{
		$this->current_operation = 'restore';
		$this->checkUndeletePreconditions();
		
		if (isset($this->config)){
			$d = new \DateTime('', new \DateTimeZone($this->config['DateTimeZone']));
		} else {
			$d = new \DateTime();
		}
		
		$at = at();

		$this->fill([$this->deletedAt]);

		$ret = $this->update([
			$this->deletedAt() => NULL
		], false);

		$this->current_operation = null;
		return $ret;
	}	

	// alias for delete()
	function restore(){
		return $this->undelete();
	}

	function truncate(){
		DB::truncate($this->table_name);
		return $this;
	}

	/*
		Si un campo es enviado al Modelo pero realmente no existe en el schema
		entonces se debe ignorar para evitar generar un error innecesario.
	*/
	protected function ignoreFieldsNotPresentInSchema(array &$data){
		if (empty($this->schema)){
			return;
		}

		foreach ($data as $key => $dato){
			if (!in_array($key, $this->getFillables())){
				unset($data[$key]);
			}
		}
	}

	/*
		@return mixed false | integer 

		Si la data es un array de arrays, intenta un INSERT MULTIPLE
	*/
	function create(array $data, $ignore_duplicates = false)
	{	
		$this->current_operation = 'create';

		if ($this->conn == null)
			throw new SqlException('No connection');

		if (!Arrays::isAssocc($data)){
			foreach ($data as $dato){
				if (is_array($dato)){					
					$last_id = $this->create($dato, $ignore_duplicates);
				} else {
					throw new \InvalidArgumentException('Array of data should be associative');
				}
			}
		}
		
		// control de recursión para INSERT múltiple
		if (isset($data[0]) && is_array($data[0])){
			return $last_id ?? null;
		}

		$this->ignoreFieldsNotPresentInSchema($data);

		$this->data = $data;	
		
		$data = $this->applyInputMutator($data, 'CREATE');
		$vars = array_keys($data);
		$vals = array_values($data);

		// Event hook
		$this->onCreating($data);

		if ($this->inSchema([$this->createdAt]) && !isset($data[$this->createdAt])){
			$this->fill([$this->createdAt]);

			$at = datetime();
			$data[$this->createdAt] = $at;

			$vars = array_keys($data);
			$vals = array_values($data);
		}

		// dd($this->fillable, 'FILLABLE');
		// dd($this->not_fillable, 'NOT FILLABLE');

		// Validación
		if (!empty($this->validator))
		{			
			$validado = $this->validator->validate($data, $this->getRules(), $this->fillable, $this->not_fillable);
			if ($validado !== true){
				dd($this->validator->getErrors());

				throw new InvalidValidationException(json_encode(
					$this->validator->getErrors()
				));
			} 
		}

		$symbols  = array_map(function(?string $e = null){
			if ($e === null){
				$e = '';
			}

			return ':'.$e;
		}, $vars);

		$q_marks  = array_map(function(?string $e = null){
			if ($e === null){
				$e = '';
			}
			
			return "'$e'";
		}, $vals);

		/*
			BLOB, TEXT, GEOMETRY or JSON columns can't have a default value
		*/
		foreach($vals as $ix => $val){	
			if (isset($this->schema['attr_type_detail'][$vars[$ix]]) && in_array($this->schema['attr_type_detail'][$vars[$ix]], ['JSON', 'TEXT', 'BLOB', 'GEOMETRY'])){
				if ($vals[$ix] == ''){
					$vals[$ix] = null;
				} 
			}
		}

		if (DB::driver() == DB::MYSQL || DB::isMariaDB()) {
			$str_vars = implode(', ', array_map(function ($var) {
				return "`$var`";
			}, $vars));
		} else {
			$str_vars = implode(', ',$vars);
		}

		$str_vals     = implode(', ',$symbols);

		$str_qmarks   = implode(', ',$q_marks);

		$this->insert_vars = $vars;

		$q                       = "INSERT INTO " . DB::quote($this->from()) . " ($str_vars) VALUES ($str_vals)";
		$this->last_compiled_sql = "INSERT INTO " . DB::quote($this->from()) . " ($str_vars) VALUES ($str_qmarks)";

		if ($this->semicolon_ending){
			$q .= ';';
		}

		$st = $this->conn->prepare($q);
	
		foreach($vals as $ix => $val){	
			if(is_array($val)){
				if (isset($this->schema['attr_types'][$vars[$ix]]) && !$this->schema['attr_types'][$vars[$ix]] == 'STR'){
					throw new \InvalidArgumentException("Param '{[$vars[$ix]}' is not expected to be an string. Given '$val'");
				} else {
					$vals[$ix] = json_encode($val);
					$type = \PDO::PARAM_STR;
				}			
			} else {
				if(is_null($val)){
					$type = \PDO::PARAM_NULL;
				}elseif(isset($vars[$ix]) && $this->schema != NULL && isset($this->schema['attr_types'][$vars[$ix]])){
					$const = $this->schema['attr_types'][$vars[$ix]];
					$type = constant("PDO::PARAM_{$const}");
				}elseif(is_int($val))
					$type = \PDO::PARAM_INT;
				elseif(is_bool($val))
					$type = \PDO::PARAM_BOOL;
				elseif(is_string($val))
					$type = \PDO::PARAM_STR;
			}
			
			// d($type, "TYPE");	
			// d([$vals[$ix], $symbols[$ix], $type]);

			$st->bindParam($symbols[$ix], $vals[$ix], $type);
		}

		$this->last_bindings = $vals;
		$this->last_pre_compiled_query = $q;
		$this->last_operation = 'create';

		if (!$this->exec){
			$this->logSQL();

			// Ejecuto igual el hook a fines de poder ver la query con dd()
			$this->onCreated($data, null);
			return NULL;
		}	


		try {
			$result = $st->execute();
		} catch (\PDOException $e){
			$this->logSQL();
			
			if (!$ignore_duplicates && !Strings::contains('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry', $e->getMessage())){
				throw new \PDOException(Config::get()['debug'] ? $e->getMessage() : "Integrity constraint violation");
			} 
		}
	
		$this->current_operation = null;
	
		if (!isset($result)){
			return;
		}

		if ($result){
			// sin schema no hay forma de saber la PRI Key. Intento con 'id' 
			if ($this->schema != null && $this->schema['id_name'] != null){
				$id_name = $this->schema['id_name'];
			} else {
				$id_name = 'id';
			}
			
			if (isset($data[$id_name])){
				$this->last_inserted_id =	$data[$id_name];
			} else {
				$this->last_inserted_id = $this->conn->lastInsertId();
			}

			$this->onCreated($data, $this->last_inserted_id);
		}else {
			$this->last_inserted_id = false;
		}

		return $this->last_inserted_id;
	}
	

	function createOrIgnore(array $data){
		$this->create($data, true);
	}

	function insert(array $data, bool $ignore_duplicates = false){
		if (!Arrays::isAssocc($data)){
			if (is_array($data[0]))
			{	
				DB::beginTransaction();
			
				try {
					$ret = $this->create($data, $ignore_duplicates);
					DB::commit();
				} catch (\Exception $e){
					//
					// Si el modo NO es DEBUG => NO! incluir la sentencia SQL  (revisar en todos lados)
					//

					$val_str = implode(',', $this->getLastBindingParamters());
					
					DB::rollback();

					if (Config::get()['debug']){
						$msg = "Error inserting data from ". $this->from() . ' - ' .$e->getMessage() . '- SQL: '. $this->getLog();
					} else {
						$msg = 'Error inserting data';
					}

					throw new \Exception($msg);
				}
				
				return $ret; 
			} else {
				throw new \InvalidArgumentException('Array of data should be associative');
			}
		} else {
			DB::beginTransaction();

			try {
				$ret = $this->create($data, $ignore_duplicates);
				DB::commit();

				return $ret;
			} catch (\Exception $e){
				DB::rollback();

				if (Config::get()['debug']){
					$msg = "Error inserting data from ". $this->from() . ' - ' .$e->getMessage() . '- SQL: '. $this->getLog();
				} else {
					$msg = 'Error inserting data';
				}

				throw new \Exception($msg);
			}
		}
	}

	function insertOrIgnore(array $data){
		$this->insert($data, true);
	}

	/*
		Insert múltiple en un solo STATEMENT - Por corregir ***
		
		Quizas pueda re-hacerse teniendo como premisa la sintaxis:

        INSERT INTO tbl_name VALUES (1,2,3), (4,5,6), (7,8,9)

		En cualquier caso los comodines deberian estar siempre en las mismas posiciones:

		Ej:

        INSERT INTO tbl_name VALUES (1,?,?), (4,?,?), (7,?,?)

	*/
	// function insertMultiple(array $data_arr, bool $hooks = true, bool $mutators = true)
	// {
	// 	if ($this->conn == null)
	// 		throw new SqlException('No connection');

	// 	$vals = [];		
	// 	$vars = array_keys($data_arr[0]);

	// 	$has_created_at =  $this->inSchema([$this->createdAt]) && !isset($vars[$this->createdAt]);
	// 	if ($has_created_at){
	// 		$at = datetime();
	// 	}
		
	// 	foreach ($data_arr as $ix => $data){
	// 		$this->data = $data;	
			
	// 		$data_arr[$ix] = $mutators ? $this->applyInputMutator($data, 'CREATE') : $data;
			
	// 		if (empty($vars)){
	// 			$vars = array_keys($data);
	// 		} else {
	// 			$vars = array_unique(array_merge($vars, array_keys($data)));
	// 		}

	// 		$vals[$ix] = array_values($data_arr[$ix]);

	// 		// Validación
	// 		if (!empty($this->validator)){
	// 			if(!empty($this->fillable) && is_array($this->fillable)){
	// 				foreach($vars as $var){
	// 					if (!in_array($var,$this->fillable))
	// 						throw new \InvalidArgumentException("`{$this->table_name}`.`$var` is no fillable");
	// 				}
	// 			}

	// 			$validado = $this->validator->validate($data, $this->getRules());
	// 			if ($validado !== true){
	// 				throw new InvalidValidationException(json_encode(
					// 	$this->validator->getErrors()
					// ));
	// 			} 
	// 		}

	// 		// Event hook
	// 		if ($hooks){
	// 			$this->onCreating($data);

	// 			// Hook onCreating() can change $data
	// 			$vars = array_keys($data);
	// 			$vals = array_values($data);
	// 		}

	// 		if ($has_created_at){
	// 			$vals[$ix][] = $at;
	// 		}
	// 	}		
	
	// 	if ($has_created_at){
	// 		$vars[] = $this->createdAt;
	// 	}

	// 	$cnt = count($data_arr);
	// 	for ($i=0; $i<$cnt; $i++){
	// 		$sr = [];
	// 		foreach ($vars as $ix => $var){
	// 			$key = ":{$var}_{$i}_k_{$ix}";
	// 			$symbols[] = $key;
	// 			$sr[] = $key;
	// 		}
	// 		$str_vals[] = '('. implode(', ',$sr) . ')';
	// 	}

	// 	if (!isset($str_vars)){
	// 		$str_vars = implode(',', $vars);
	// 	}

	// 	/*
	// 		INSERT INTO `product_tags` (`id_tag`, `name`, `comment`, `product_id`) 
			
	// 		VALUES 
	// 		(NULL, 'N99', 'C99', '137'), 
	// 		(NULL, 'N100', 'C100', '138')
	// 	*/	

	// 	$q = "INSERT INTO " . $this->from() . " ($str_vars) VALUES ". implode(',', $str_vals);
	// 	$st = $this->conn->prepare($q);
		
	// 	for ($i=0; $i<$cnt; $i++){
	// 		$cnt_vals = count($vals[$i]);
	// 		for ($ix=0; $ix<$cnt_vals; $ix++){	
	// 			$val = $vals[$i][$ix];	

	// 			if(is_null($val)){
	// 				$type = \PDO::PARAM_NULL;
	// 			}elseif(isset($vars[$ix]) && $this->schema != NULL && isset($this->schema['attr_types'][$vars[$ix]])){
	// 				$const = $this->schema['attr_types'][$vars[$ix]];
	// 				$type = constant("PDO::PARAM_{$const}");
	// 			}elseif(is_int($val))
	// 				$type = \PDO::PARAM_INT;
	// 			elseif(is_bool($val))
	// 				$type = \PDO::PARAM_BOOL;
	// 			elseif(is_string($val))
	// 				$type = \PDO::PARAM_STR;	

	// 			$param = ":{$vars[$ix]}_{$i}_k_{$ix}";
	// 			$variable =  $val;
			
	// 			dd([$param,  $variable, $type]);
	// 			$st->bindParam($param, $variable, $type);
	// 		}
	// 	}

	// 	//dd($q);

	// 	$this->last_bindings = $vals;
	// 	$this->last_pre_compiled_query = $q;
	// 	$this->last_operation = 'create';

	// 	if (!$this->exec){
	// 		// Ejecuto igual el hook a fines de poder ver la query con dd()

	// 		if ($hooks){
	// 			$this->onCreated($data, null);
	// 		}
			
	// 		return NULL;
	// 	}	

	// 	$result = $st->execute();
		
	// 	if (!isset($result)){
	// 		return;
	// 	}

	// 	/*
	// 		If you insert multiple rows using a single INSERT statement, LAST_INSERT_ID() returns the value generated 
	// 		for the first inserted row only. 
	// 		The reason for this is to make it possible to reproduce easily the same INSERT statement against some other server.
	// 	*/

	// 	if ($result){
	// 		// sin schema no hay forma de saber la PRI Key. Intento con 'id' 
	// 		$id_name = ($this->schema != NULL) ? $this->schema['id_name'] : 'id';		

	// 		if (isset($data[$id_name])){
	// 			$this->last_inserted_id =	$data[$id_name];
	// 		} else {
	// 			$this->last_inserted_id = $this->conn->lastInsertId();
	// 		}

	// 		if ($hooks){
	// 			$this->onCreated($data, $this->last_inserted_id);
	// 		}
	// 	}else {
	// 		$this->last_inserted_id = false;	
	// 	}

	// 	return $this->last_inserted_id;		
	// }
		

	function getInsertVals(){
		return $this->insert_vars;
	}
	
	
	/*
		 to be called inside onUpdating() event hook

		 el problema es que necesito ejecutar el mismo WHERE que el UPDATE en un GET para seleccionar el mismo registro y tener contra que comparar.	

		 https://stackoverflow.com/questions/45702409/laravel-check-if-updateorcreate-performed-update/49350664#49350664
		 https://stackoverflow.com/questions/48793257/laravel-check-with-observer-if-column-was-changed-on-update/48793801
	*/	 

	function isDirty($fields = null) 
	{
		if ($fields == null){
			$fields = $this->attributes;
		}

		if (!is_array($fields)){
			$fields = [$fields];
		}

		// to be updated
		$keys = array_keys($this->data);

		if (!$this->inSchema($fields)){
			throw new \Exception("A field was not found in table {$this->table_name}");
		}
		
		$old_vals = $this->first($fields);
		foreach ($fields as $field){	
			if (!in_array($field, $keys)){
				continue;
			}

			$new_val = $this->data[$field];
			
			if ($new_val != $old_vals[$field]){
				return true;
			}	
		}

		return false;
	}


	/*
		Even hooks -podrían estar definidos en clase abstracta o interfaz-
	*/

	protected function boot() { }

	protected function onReading() { }
	protected function onRead(int $count) { }

	protected function onCreating(Array &$data) {	}
	protected function onCreated(Array &$data, $last_inserted_id) { }

	protected function onUpdating(Array &$data) { }
	protected function onUpdated(Array &$data, ?int $count) { }

	protected function onDeleting(Array &$data) { }
	protected function onDeleted(Array &$data, ?int $count) { }

	protected function onRestoring(Array &$data) { }
	protected function onRestored(Array &$data, ?int $count) { }

	protected function init() { }


	function getSchema(){
		return $this->schema;
	}

	function hasSchema(){
		return !empty($this->schema);
	}

	/*
		'''Reflection'''
	*/
	
	/**
	 * inSchema
	 *
	 * @param  array $props
	 *
	 * @return bool
	 */
	function inSchema(array $props){
		// debería chequear que la tabla exista

		if (empty($props))
			throw new \InvalidArgumentException("Attributes not found!");

		foreach ($props as $prop)
			if (!in_array($prop, $this->attributes)){
				return false; 
			}	
		
		return true;
	}

	/**
	 * getMissing
	 *
	 * @param  array $fields
	 *
	 * @return array
	 */
	function getMissing(array $fields){
		$diff =  array_diff($this->attributes, array_keys($fields));
		return array_diff($diff, $this->schema['nullable']);
	}
	
	/**
	 * Get attr_types 
	 */ 
	function getAttr()
	{
		return $this->attributes;
	}

	function getIdName(){
		return $this->schema['id_name'] ?? null; // *
	}

	// alias for getIdName()
	function id(){
		return $this->getIdName();
	}

	function getNotHidden(){
		return array_diff($this->attributes, $this->hidden);
	}

	function isNullable(string $field){
		return in_array($field, $this->schema['nullable']);
	}

	function isFillable(string $field){
		return in_array($field, $this->fillable) && !in_array($field, $this->not_fillable);
	}

	function getFillables(){
		return $this->fillable;
	}

	function getNotFillables(){
		return $this->not_fillable;
	}

	function setNullables(Array $arr){
		$this->schema['nullable'] = $arr;
	}

	function addNullables(Array $arr){
		$this->schema['nullable'] = array_merge($this->schema['nullable'], $arr);
	}

	function removeNullables(Array $arr){
		$this->schema['nullable'] = array_diff($this->schema['nullable'], $arr);
	}

	function getNullables(){
		return $this->schema['nullable'];
	}

	function getNotNullables(){
		return array_diff($this->attributes, $this->schema['nullable']);
	}

	function getUniques(){
		return $this->schema['uniques'];
	}

	function getRules(){
		return $this->schema['rules'] ?? NULL;
	}

	function getRule(string $name){
		return $this->schema['rules'][$name] ?? NULL;
	}

	function getFieldOrder(){
		return $this->field_order;
	}

	/**
	 * Set the value of conn
	 *
	 * @return  self
	 */ 
	function connect()
	{
		$this->conn = DB::getConnection();
		return $this;
	}

	function setConn($conn)
	{
		$this->conn = $conn;
		return $this;
	}

	function getConn(){
		return $this->conn;
	}

	/*	
		Adds prefix to raw statements / queries

		it's a partial implementation
	*/
	static function addPrefix(string $st, $tb_prefix = null)
	{
		$tb_prefix = $tb_prefix ?? tb_prefix() ?? null;

		if (empty($tb_prefix)){
			return $st;
		}

		// Para evitar agregarlo dos veces
		if (Strings::contains($tb_prefix, $st)){
			return $st;
		}

		$tb        = Strings::match($st, "/REFERENCES[ ]+`?([^\b^`^ ]+)`?/i");
		$tb_quoted = preg_quote($tb, '/');

		if (!empty($tb)){
			$st = preg_replace("/REFERENCES[ ]+`$tb_quoted`/i", "REFERENCES `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/REFERENCES[ ]+$tb_quoted/i", "REFERENCES $tb_prefix{$tb}", $st);
		}

		$tb = Strings::match($st, "/CREATE[ ]+TABLE(?: IF NOT EXISTS)?[ ]+`?([^\b^`^ ]+)`?/i");

		if (!empty($tb)){
			$st = preg_replace("/CREATE TABLE IF NOT EXISTS?[ ]+`$tb`/i", "CREATE TABLE IF NOT EXISTS `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/CREATE TABLE[ ]+`$tb`/i", "CREATE TABLE `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/CREATE TABLE IF NOT EXISTS?[ ]+$tb/i", "CREATE TABLE IF NOT EXISTS $tb_prefix{$tb}", $st);
			$st = preg_replace("/CREATE TABLE[ ]+$tb/i", "CREATE TABLE $tb_prefix{$tb}", $st);

			return $st;
		}

		$tb = Strings::match($st, "/UPDATE[ ]+`?([^\b^`^ ]+)`?/i");

		if (!empty($tb)){
			$st = preg_replace("/UPDATE[ ]+`$tb`/i", "UPDATE `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/UPDATE[ ]+$tb/i", "UPDATE $tb_prefix{$tb}", $st);

			return $st;
		}

		$tb = Strings::match($st, "/DELETE[ ]+FROM[ ]+`?([^\b^`^ ]+)`?/i");

		if (!empty($tb)){
			$st = preg_replace("/DELETE[ ]+FROM[ ]+`$tb`/i", "DELETE FROM `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/DELETE[ ]+FROM[ ]+$tb/i", "DELETE FROM $tb_prefix{$tb}", $st);

			return $st;
		}

		$tb = Strings::match($st, "/ALTER[ ]+TABLE[ ]+`?([^\b^`^ ]+)`?/i");

		if (!empty($tb)){
			$st = preg_replace("/ALTER[ ]+TABLE[ ]+`$tb`/i", "ALTER TABLE `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/ALTER[ ]+TABLE[ ]+$tb/i", "ALTER TABLE $tb_prefix{$tb}", $st);

			return $st;
		}

		$tb = Strings::match($st, "/INSERT[ ]+INTO[ ]+`?([^\b^`^ ]+)`?/i");

		if (!empty($tb)){
			$st = preg_replace("/INSERT[ ]+INTO[ ]+`$tb`/i", "INSERT INTO `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/INSERT[ ]+INTO[ ]+$tb/i", "INSERT INTO $tb_prefix{$tb}", $st);

			return $st;
		}

		if (Strings::match($st, "/(SELECT)[ ]/i")){
			$tb = Strings::match($st, "/FROM[ ]+`?([^\b^`^ ]+)`?/i");

			if (!Strings::startsWith('information_schema.tables', $tb) && !empty($tb)){
				$st = preg_replace("/FROM[ ]+`$tb`/i", "FROM `$tb_prefix{$tb}`", $st);
				$st = preg_replace("/FROM[ ]+$tb/i", "FROM $tb_prefix{$tb}", $st);
			}
		}

		/*
			JOIN es complejo porque el ON puede incluir nombres de tablas 
			o de alias y en este ultimo caso no tendria sentido agregar prefijo

			SELECT Orders.OrderID, Customers.CustomerName
			FROM Orders
			INNER JOIN Customers ON Orders.CustomerID = Customers.CustomerID;
		*/
		$tb = Strings::match($st, "/JOIN[ ]+`?([^\b^`^ ]+)`?/i");

		if (!empty($tb)){
			$st = preg_replace("/JOIN[ ]+`$tb`/i", "JOIN `$tb_prefix{$tb}`", $st);
			$st = preg_replace("/JOIN[ ]+$tb/i", "JOIN $tb_prefix{$tb}", $st);

			// Aca podria ver de agregar prefijo en la parte del "ON" en JOINs
		}

		return $st;		
	}

}