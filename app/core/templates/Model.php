<?php

namespace boctulus\TutorNewCourses\models;

use boctulus\TutorNewCourses\models\MyModel;
### IMPORTS

class __NAME__ extends MyModel
{
	### TRAITS
	### PROPERTIES

	protected $hidden       = [];
	protected $not_fillable = [];

	protected $field_names  = [];
	protected $formatters    = [];

    function __construct(bool $connect = false){
        parent::__construct($connect, __SCHEMA_CLASS__::class);
	}	
}

