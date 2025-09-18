<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Strings;

class PHPLexicalAnalyzer
{
    /*
        Devuelve todos los nombres de funciones no-anonimas 

        Retorna algo como...

        Ej:

        Array
        (
            [0] => getFunctionNames
            [1] => getClassName
            [2] => getClassNameByFileName
        )
    */
    static function getFunctionNames(string $file_str){
        return Strings::matchAll($file_str, '/function[\s]+([a-z][0-9a-z_]+)/i');
    }

    /*
		Parse php class from file

		Actualizada 29/11/2023
	*/
	static function getClassName(string $file_str, bool $fully_qualified = true){
		$pre_append = '';
			
		if ($fully_qualified){
			$namespace = Strings::match($file_str, '/namespace[\s]{1,}([^;]+)/');
			$namespace = !empty($namespace) ? trim($namespace) : '';

			if (!empty($namespace)){
				$pre_append = "$namespace\\";
			}
		}	

		$before_bkt = Strings::matchOrFail($file_str, '/class[\s]+([^{]+)/i');
		$class_name = Strings::segmentOrFailByRegEx($before_bkt, '/\s+/',0);
		$class_name = $pre_append . $class_name;

		return $class_name;
	}

	/*
		Parse php class given the filename
	*/
	static function getClassNameByFileName(string $filename, bool $fully_qualified = true){
		$file = file_get_contents($filename);
		return self::getClassName($file, $fully_qualified);
	}


}
