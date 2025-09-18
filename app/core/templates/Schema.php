<?php

namespace boctulus\TutorNewCourses\schemas;

use boctulus\TutorNewCourses\core\interfaces\ISchema;

### IMPORTS

class __NAME__ implements ISchema
{ 
	static function get(){
		return [
			'table_name'		=> __TABLE_NAME__,

			'id_name'			=> __ID__,

			'fields'			=> __FIELDS__,

			'attr_types'		=> __ATTR_TYPES__,

			'attr_type_detail'	=> __ATTR_TYPE_DETAIL__,

			'primary'			=> __PRIMARY__,

			'autoincrement' 	=> __AUTOINCREMENT__,

			'nullable'			=> __NULLABLES__,

			'required'			=> __REQUIRED__,

			'uniques'			=> __UNIQUES__,

			'rules' 			=> __RULES__,

			'fks' 				=> __FKS__,

			'relationships' => [
				__RELATIONS__
			],

			'expanded_relationships' => __EXPANDED_RELATIONS__,

			'relationships_from' => [
				__RELATIONS_FROM__
			],

			'expanded_relationships_from' => __EXPANDED_RELATIONS_FROM__
		];
	}	
}

