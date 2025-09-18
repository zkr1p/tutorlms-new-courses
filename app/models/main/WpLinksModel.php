<?php

namespace boctulus\TutorNewCourses\models\main;

use boctulus\TutorNewCourses\models\MyModel;
use boctulus\TutorNewCourses\schemas\main\WpLinksSchema;

class WpLinksModel extends MyModel
{
	protected $hidden       = [];
	protected $not_fillable = [];

	protected $field_names  = [];
	protected $formatters    = [];

    function __construct(bool $connect = false){
        parent::__construct($connect, WpLinksSchema::class);
	}	
}

