<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

namespace boctulus\TutorNewCourses\core\libs;

class Strings 
{	
	const UPPERCASE_FILTER = 'up';
	const LOWERCASE_FILTER = 'lo';
	const UCFIRST_FILTER   = 'uc';
	const UCWORDS_FILTER   = 'uw';
	const SNAKECASE_FILTER = 'sn';
	const CAMELCASE_FILTER = 'cm';

	static $regex = [
		'URL'	=> "/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",
		// ...
	];

	/*
		Aplica un filtro de tipo case

		La idea es "normalizar" la forma de aplicar cambios de CASE
	*/
	static function case($filter, string $str){
		switch ($filter){
			case static::UPPERCASE_FILTER :
				$str = strtoupper($str);
				break;
			case static::LOWERCASE_FILTER :
				$str = strtolower($str);
				break;
			case static::UCFIRST_FILTER :
				$str = ucfirst($str);
				break;
			case static::UCWORDS_FILTER :
				$str = ucfirst($str);
				break;
			case static::CAMELCASE_FILTER :
				$str = static::snakeToCamel($str);
				break;
			case static::SNAKECASE_FILTER :
				$str = static::toSnakeCase($str);
				break;
			default:
				throw new \InvalidArgumentException("Invalid filter type");
		}

		return $str;
	}

	static function replaceNonAllowedChars($input, $allowedCharsRegex = 'a-z0-9-', $replace = '-', $case_sensitive = false) {
        // Añade la bandera 'i' para hacer la expresión regular insensible a mayúsculas y minúsculas si $case_sensitive es false
        $modifiers = $case_sensitive ? '' : 'i';

        return preg_replace('/[^'. $allowedCharsRegex . ']/' . $modifiers, $replace, $input);
    }

	/*
		Elimina caracteres especiales
	*/	
	static function cleanString(string $str) {
		return static::replaceNonAllowedChars($str, 'a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ-', '');
	}

	/*
		Elimina caracteres duplicados
	*/
	static function replaceDupes($str, $haystack) {
		 // Reemplaza repetidamente hasta que no haya más repeticiones sucesivas
		 while (strpos($str, $haystack . $haystack) !== false) {
			 $str = str_replace($haystack . $haystack, $haystack, $str);
		 }
 
		 return $str;
    }

	static function removeMultipleSpaces($str){
		return static::replaceDupes($str, ' ');
	}

	/*
		Extrae la parte numerica de una cadena que contenga una cantidad
		y la castea a un float
	*/
	static function parseFloat(string $num, $thousand_sep = null, $decimal_sep = null){
		if ($thousand_sep != null){
			static::replace($thousand_sep, '', $num);
		}

		preg_match('![0-9'.$decimal_sep.']+!', $num, $matches);

		$result = $matches[0] ?? false;

		if ($result !== false && $decimal_sep !== null && $decimal_sep !== '.'){
			static::replace($decimal_sep, '.', $result);
		}

		return $result;
	}

	// alias -- depredicar?
	static function parseCurrency(string $num, $thousand_sep = null, $decimal_sep = null){
		return static::parseFloat($num, $thousand_sep, $decimal_sep);
	}

	static function parseFloatOrFail(string $num, $thousand_sep = null, $decimal_sep = null){
		if (static::parseFloat($num, $thousand_sep, $decimal_sep) === false){
			throw new \Exception("String '$num' is not a Float");
		}
	}

	/*
		Interpreta un string como un número entero con la posibilidad de que contenga separador de miles
	*/
	static function parseInt(string $num, string $thousand_sep = '.'){
		$num = trim($num);

		if (!preg_match('/(^[-0-9][0-9.]*$)/', $num, $matches)){
			return false;
		}

		if (!static::contains($thousand_sep, $num)){
			return (int) $num;
		}

		$pa = explode($thousand_sep, $num);
		
		$ct = count($pa);
		for ($i=1; $i<$ct; $i++){
			if (strlen($pa[$i]) != 3){
				return false;
			}
		}

		return (int) implode('', $pa);
	}

	static function parseIntOrFail(string $num, string $thousand_sep = '.'){
		if (static::parseInt($num, $thousand_sep) === false){
			throw new \Exception("String '$num' is not an Integer");
		}
	}

	/*
		null	=> null|Exception
		false	=> 0|Exception
		"{int}" => int|false
	*/
	static function toInt($num = null, bool $accept_null = true, bool $accept_false = true){
		if (is_int($num)){
			return $num;
		}

		if ($num === null){
			if ($accept_null){
				return null;
			} else {
				throw new \InvalidArgumentException("Numberic string can not be null");
			}			
		}
	
		if ($num === false){
			if ($accept_false){
				return 0;
			} else {
				throw new \InvalidArgumentException("Boolean can not be false");
			}			
		}

		if (!is_numeric($num)){
			return false;
		}

		return (int) $num;
	}

	/*
		null	=> null|Exception
		false	=> 0|Exception
		"{int}" => int|Exception
	*/
	static function toIntOrFail($num, bool $accept_null = true, bool $accept_false = true){
		if (is_int($num)){
			return $num;
		}

		if ($num === null){
			if ($accept_null){
				return null;
			} else {
				throw new \InvalidArgumentException("Numberic string can not be null");
			}			
		}
	
		if ($num === false){
			if ($accept_false){
				return 0;
			} else {
				throw new \InvalidArgumentException("Boolean can not be false");
			}			
		}

		if (!is_numeric($num)){
			throw new \Exception("Conversion for '$num' failed");
		}

		return (int) $num;
	}

	/*
		int|string|null => Exception
	*/
	static function fromInt($num = null, bool $accept_null = true){		
		if ($num === null){
			if ($accept_null){
				return null;
			} else {
				throw new \InvalidArgumentException("Int can not be null");
			}			
		}

		$num = (string) $num;
	
		if ((!is_numeric($num) && !static::match($num, '/([0-9]+)/')) || (static::contains('.', $num))){
			throw new \Exception("Invalid integer for '$num'");
		}

		return $num;
	}

	/*
		Intenta hacer un casting de un string numerico a float 

		Evita castear null o false a 0.0
	*/
	static function toFloat($num = null){
		if (is_float($num)){
			return $num;
		}

		if ($num === null){
			return null;
		}
	
		if ($num === false){
			return false;
		}

		if (!is_numeric($num)){
			return false;
		}

		return (float) $num;
	}

	static function toFloatOrFail($num){
		if (is_float($num)){
			return $num;
		}

		if ($num === null){
			throw new \InvalidArgumentException("'$num' can not be null");
		}
	
		if ($num === false){
			throw new \InvalidArgumentException("'$num' can not be false");
		}

		if (!is_numeric($num)){
			throw new \Exception("Conversion for '$num' failed");
		}

		return (float) $num;
	}


	/*
		float|string|null => Exception
	*/
	static function fromFloat($num = null, bool $accept_null = true){
		if ($num === null){
			if ($accept_null){
				return null;
			} else {
				throw new \InvalidArgumentException("Float can not be null");
			}			
		}

		$num = (string) $num;
	
		if (!is_numeric($num) && !static::match($num, '/([0-9\.]+)/')){
			throw new \Exception("Invalid float for '$num'");
		}

		return $num;
	}

	static function formatNumber($x, string $locale = "it-IT"){
		$nf = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
	
		if ($x > 1000000){
			$nf->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
		} else {
			$nf->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
		}
	
		return $nf->format($x); 
	}	

	/*
		Reemplazo para la función nativa explode()
	*/
	static function explode(string $str, string $separator = ','){
		return Arrays::trimArray(explode($separator, rtrim(trim($str), $separator)));
	}

	/*
		Cada línea la convierte en un <p>párrafo</p>
	*/
	static function paragraph(string $str){
		return '<p>' . implode('</p><p>', array_filter(explode("\n", $str))) . '</p>';
	}

	// N-ésimo segmento luego de hacer un explode por $separator
	static function segment(string $string, string $separator,  int $position){
		$array = explode($separator, $string);

		if (isset($array[$position])){
			return $array[$position];
		}

		return false;
	}

	// N-ésimo segmento luego de hacer un explode por $separator
	static function segmentOrFail(string $string, string $separator,  int $position){
		$array = explode($separator, $string);

		if (count($array) === 1 && $position > 0){
			throw new \Exception("There is no segments after explode '$string'");
		}

		if (!isset($array[$position])){
			throw new \Exception("There is no segment in position $position after explode '$string'");
		}

		return $array[$position];
	}

	/*
		Separator es expresion regular
	*/
	static function segmentByRegEx(string $string, string $separator, int $position) {
		$array = preg_split( $separator , $string);
	
		if (isset($array[$position])) {
			return $array[$position];
		}
	
		return false;
	}

	static function segmentOrFailByRegEx(string $string, string $separator, int $position) {
		$ret = static::segmentByRegEx($string, $separator, $position);

		if ($ret === false){
			throw new \Exception("There is no segment in position $position after exploding '$string'");
		}

		return $ret;
	}	

	// String antes de la N-ésima ocurrencia del substring
	static function before(string $string, string $substr, $occurrence = 1){
		$parts = explode($substr, $string, $occurrence +1);

		return $parts[$occurrence -1];
	}

	// String antes de la primera ocurrencia del substring
	static function first(string $string, string $substr){
		$parts = explode($substr, $string, 2);

		return $parts[0];
	}

	// String después de la N-esima ocurrencia del substring
	static function after(string $string, string $substr, int $occurence = 1){
		$parts = explode($substr, $string, $occurence+1);

		if (!isset($parts[$occurence])){
			return false;
		}

		return implode($substr, array_slice($parts, $occurence));
	}

	static function beforeIfContains(string $str, string $substr, int $occurence = 1){
		if (!static::contains($substr, $str)){
			return $str;
		} else {
			return static::before($str, $substr, $occurence);
		}
	}
	
	static function afterIfContains(string $str, string $substr, int $occurence = 1){
		if (!static::contains($substr, $str)){
			return $str;
		} else {
			return static::after($str, $substr, $occurence);
		}
	}

	// String después de la primera ocurrencia del substring
	static function afterOrFail(string $string, string $substr){
		$parts = explode($substr, $string, 2);

		if (!isset($parts[1])){
			throw new \Exception("There is nothing after '$substr' for '$string'");
		}

		return $parts[1];
	}

	// String después de la última ocurrencia del substring (que podría ser empty)
	static function last(string $str, string $substr){
		$parts = explode($substr, $str);

		return $parts[count($parts)-1];
	}

	// String antes de la última ocurrencia del substring
	static function beforeLast(string $string, string $substr){
		$parts = explode($substr, $string);

		return implode($substr, array_slice($parts, 0, count($parts)-1));
	}

	/*
		Ensayar para todos los casos incluida la no ocurrencia
	*/
	static function untilLast(string $string, string $substr){
		$parts = explode($substr, $string);

		return implode($substr, array_slice($parts, 0, count($parts)-1)) . $substr;
	}

	// Segment before the last one
	static function beforeLastSegment(string $string, string $substr){
		return static::last(static::beforeLast($string, $substr), $substr);
	}

	static function lastSegment(string $string, string $delimeter){
		$segments = explode($delimeter, $string);

		if (count($segments) === 1){
			return null;
		}

        return $segments[count($segments)-1];
	}

	static function lastSegmentOrFail(string $string, string $delimeter){
		$ret = static::lastSegment($string, $delimeter);

		if ($ret === null){
			throw new \Exception("Substring '$delimeter' not found in '$string'");
		}

		return $ret;
	}

	static function trim($dato = null, bool $even_null = true, bool $auto_cast_numbers = true){
		if ($dato === null){
			if (!$even_null){
				throw new \InvalidArgumentException("Dato can not be null");
			}

			return '';
		}

		if ($auto_cast_numbers){
			if (is_int($dato) || is_float($dato) || is_double($dato)){
				$dato = (string) $dato;
			}
		}

		return trim($dato);
	}

	/*
		Auto-detecta retorno de carro en un string
	*/
	static function carriageReturn(string $str){
		$qty_rn = substr_count($str, "\r\n");
		$qty_r  = substr_count($str, "\r");
		$qty_n  = substr_count($str, "\n");

		// Priorizar \r\n sobre \r y \n
		if ($qty_rn > $qty_r && $qty_rn > $qty_n) {
			return "\r\n";
		}

		// En caso de empate, priorizar \r sobre \n
		if ($qty_r > $qty_n) {
			return "\r";
		}

		// Si no hay \r ni \r\n, devolver \n
		return "\n";
	}

	// alias
	static function newLine(string $str){
		return static::carriageReturn($str);
	}
	
	/*
		Convierte string en array de lineas (rows)
		usando retorno de carro en autodeteccion

		@param string $str 
		@param bool $trim
		@param bool $empty_lines
	*/
	static function lines(string $str, bool $trim = false, bool $empty_lines = true, $carry_ret = null){
		if (empty($str)){
			return [];
		}

		$cr = $carry_ret ?? static::carriageReturn($str);

		if (empty($cr)){
			return [ $str ];
		}

		$lines = explode($cr, $str);

		if ($empty_lines) {
			// Mantener solo líneas no vacías
			$lines = array_filter($lines, function($line) {
				return trim($line) !== '';
			});
		} else {
			// Eliminar líneas vacías
			$lines = array_filter($lines, 'strlen');
		}

		if ($trim) {
			// Aplicar trim a todas las líneas si es necesario
			$lines = array_map('trim', $lines);
		}

		return $lines;
	}

	/*
		Remueve del forma eficiente un substring del inicio de una cadena

		- Precaucion: remueve cualquier caracter, no solo espacios, tabs, etc

		- Podria limitarse tambien a los primeros n-caracteres tambien
	*/
	static function lTrim(string $substr, ?string $str = null){
		if (empty($str)){
			return '';
		}

		$len_sb = strlen($substr);
		$len_ss = strlen($str);

		if ($len_sb > $len_ss){
			return $str;
		}

		if (substr($str, 0, $len_sb) == $substr){
			return substr($str, $len_sb);
		}

		return $str;
	} 

	static function rTrim(string $needle, string $haystack)
    {
        if (substr($haystack, -strlen($needle)) === $needle){
			return substr($haystack, 0, - strlen($needle));
		}
		return $haystack;
    }

	// alias
	static function removeBeginning($substr, string $string){
		return static::lTrim($substr, $string);
	}

	// alias
	static function removeEnding(string $substr, string $string){
		return static::rTrim($substr, $string);
	}

	/*
		Ubica la primera ocurrencia de $substr en $string 
		y se come retornos de carro, espacios,... 
		... hasta que se encuentra con algo distinto a derecha
	*/
	static function trimAfter(string $substr, string $string, int $offset = 0, ?string $chars = " \t\r\n\0\x0B", int $extra_cr = 0){
		if ($string === ''){
			return $string;
		}
		
		$pos = strpos($string, $substr, $offset);

		if ($pos === false){
			return $string;
		}

		$pos += strlen($substr);

		$l = static::left($string,  $pos);
		$r = static::right($string, $pos);

		if ($chars === null){
			$chars = " \t\r\n\0\x0B";
		}

		return $l . str_repeat("\r\n", $extra_cr) . ltrim($r, $chars);
	}

	static function trimEmptyLinesAfter(string $substr, string $string, int $offset = 0, ?string $chars = " \t\r\n\0\x0B", int $extra_cr = 0){
		if ($string === ''){
			return $string;
		}
		
		$pos = strpos($string, $substr, $offset);

		if ($pos === false){
			return $string;
		}

		$pos += strlen($substr);

		$l = static::left($string,  $pos);
		$r = static::right($string, $pos);

		if ($chars === null){
			$chars = " \t\r\n\0\x0B";
		}
		
		// toca auto-detectar tipo de retorno de carro 
		$cr = static::carriageReturn($string);

		$lines = explode($cr, $r);

		foreach ($lines as $ix => $line){
			if (empty(trim($line, $chars))){
				unset($lines[$ix]);
			} else {
				break;
			}
		}

		return $l . str_repeat($cr, $extra_cr) . implode($cr, $lines);
	}

	static function trimEmptyLinesBefore(string $substr, string $string, int $offset = 0, ?string $chars = " \t\r\n\0\x0B", int $extra_cr = 0){
		if ($string === ''){
			return $string;
		}
		
		$pos = strpos($string, $substr, $offset);

		if ($pos === false){
			return $string;
		}

		$l = static::left($string,  $pos);
		$r = static::right($string, $pos);

		if ($chars === null){
			$chars = " \t\r\n\0\x0B";
		}
		
		// toca auto-detectar tipo de retorno de carro 
		$cr = static::carriageReturn($string);

		$lines = explode($cr, $l);

		$lines = array_reverse($lines);

		foreach ($lines as $ix => $line){
			if (empty(trim($line, $chars))){
				unset($lines[$ix]);
			} else {
				break;
			}
		}

		$lines = array_reverse($lines);

		return  implode($cr, $lines) . str_repeat($cr, $extra_cr) . $r;
	}


	/*
		Remueve el sustring entre $startingWith y $endingWith
	*/
	static function removeSubstring(string $startingWith, string $endingWith, string $string){
		if (empty($string)){
			return $string;
		}

		$ini = strpos($string, $startingWith);

		if ($ini === false){
			return $string;
		}

		$end = strpos($string, $endingWith, $ini);

		if ($end === false){
			return $string;
		}

		return substr($string, 0, $ini) . substr($string, $end + strlen($endingWith));
	}

	/*
		Apply tabs to some string

		En vez de PHP_EOL, deberias usar static::carriageReturn($str)
	*/
	static function tabulate(string $str, int $tabs, ?int $first = null, ?int $last = null){
		$lines = explode(PHP_EOL, $str);

		$cnt = count($lines);
        foreach($lines as $ix => $line){
			if ($first !== null && $ix == 0){
				if ($first > 0){
					$lines[$ix] = str_repeat("\t", $first) . $line;
				}  else {
					$lines[$ix] = substr($line, abs($first));
				}
				continue;
			} 

			if ($last !== null && $ix == $cnt-1){
				if ($last < 0){
					$lines[$ix] = substr($line, abs($last));
				}  else {
					$lines[$ix] = str_repeat("\t", $last) . $line;
				}

				continue;
			}

			if ($tabs < 0){
				$lines[$ix] = substr($line, abs($tabs));
			}  else {
				$lines[$ix] = str_repeat("\t",$tabs) . $line;
			}
        }

        $str = implode(PHP_EOL, $lines);

		return $str;
	}


	/*
		Returns $s1 - $s2
	*/
	static function substract(string $s1, string $s2){
		$s2_len = strlen($s2);
		$s1_len = strlen($s1);

		if ($s2_len > $s1_len){
			return;
		}

		if (!self::startsWith($s2, $s1)){
			return;
		}

		return substr($s1, $s2_len);
	}

	// alias
	static function diff(string $s1, string $s2){
		return static::substract($s1, $s2);
	}

	static function trimArray(Array $strings){
		return array_map('trim', $strings);
	}

	/*
		Elimina espacios, tabs etc del comienzo de cada linea
	*/
	static function trimMultiline(string $str){
		$cr    = static::carriageReturn($str);

		$lines = explode($cr, $str);
		$arr   = static::trimArray($lines);

		return implode($cr, $arr);
	}

	static function trimFromLastOcurrence(string $substr, string $str){
		$pos = strrpos($str, $substr);

		if ($pos === false){
			return $str;
		}

		return substr($str, 0, $pos);
	}

	/*
		Returns false if fails

		@param 	string|array 	$pattern
		@param	string|array	$result_position

		@return string|boolean

		Ej:

		$namespace = static::match($file_str, '/namespace[ ]{1,}([^;]+)/');
		$table     = static::match($raw_sql, '/insert[ ]+(ignore[ ]+)?into[ ]+[`]?([a-z_]+[a-z0-9]?)[`]? /i', 2);

		Si $pattern es un array, busca coindicencias con cada patron 

		Nota: esta funcion deberia ser revisda (!)
		
		Tener en cuenta las dependencias de esta funcion.
	*/
	static function match(string $str, $pattern, $result_position = null){
		if (is_array($pattern)){
			if (is_null($result_position)){
				$result_position = 1;
				$is_pos_array    = false;
			} else {
				$is_pos_array = is_array($result_position);

				if ($is_pos_array){
					if (count($result_position) != count($pattern)){
						throw new \InvalidArgumentException("Number of patterns should be the same as result positions");
					}
				} 
			}

			foreach ($pattern as $ix => $p){
				if (preg_match($p, $str, $matches)){
					if (is_array($result_position)){
						$pos = $result_position[$ix];
					} else {
						$pos = $result_position;
					}

					if (isset($matches[$pos])){
						return $matches[$pos];
					}
				}
			}
		} else {
			if (is_null($result_position)){
				$result_position = 1;
			}			

			if (preg_match($pattern, $str, $matches)){
				if (!isset($matches[$result_position])){
					return false;
				}

				return $matches[$result_position];
			}
		}

		return false;
	}

	/*
		Ej:

		$id = static::matchOrFail($filename, '/installation-file-([0-9]+)\.zip$/');
	*/
	static function matchOrFail(string $str, string $pattern, $flags = 0, $offset = 0) { 
		if (preg_match($pattern, $str, $matches, $flags, $offset)){			
			return $matches[1];
		}

		throw new \Exception("String $str does not match with $pattern");
	}

	/*
		Ej:

		static::matchAll($str, static::$regex['URL']);

		Antes si fallaba devolvia false, ahora []
	*/
	static function matchAll(string $str, string $pattern, $flags = 0, $offset = 0) { 
		if (preg_match_all($pattern, $str, $matches, $flags, $offset)){			
			return $matches[1];
		}

		return [];
	}

	static function ifMatch(string $str, $pattern, callable $fn_success, callable $fn_fail = NULL){
		if (preg_match($pattern, $str, $matches)){
			return call_user_func($fn_success, $matches);
		} else if (is_callable($fn_fail)){
			return call_user_func($fn_fail, $matches);
		} else {
			return $matches;
		}
	}

	/*
        Tipo "preg_match()" destructivo

		Va extrayendo substrings que cumplen con un patron mutando la cadena pasada por referencia.
		
		Aplica solo la primera ocurrencia *
		
		En caso de entregarse un callback, se aplica sobre la salida.
	*/
	
    static function slice(string &$str, string $pattern, callable $output_fn = NULL) {
		if (!preg_match('|\((.*)\)|', $pattern)){
			throw new \Exception("Invalid regex expression '$pattern'. It should contains a (group)");
		}

        $ret = null;
        if (preg_match($pattern,$str,$matches)){
            $str = self::replaceFirst($matches[1], '', $str);
            $ret = $matches[1];
        }

        if ($output_fn != NULL){
            $ret = call_user_func($output_fn, $ret);
        }
     
     	return $ret;   
	}


    /*
        preg_match destructivo

        Similar a slice() pero aplica a todas las ocurrencias y no acepta callback.
     */
    static function sliceAll(string &$str, string $pattern) {
        if (preg_match($pattern,$str,$matches)){
            $str = self::replaceFirst($matches[1], '', $str);
            
            return array_slice($matches, 1);
        }
    }

	static function getParamRegex(string $param_name, ?string $arg_expr = '[a-z0-9A-Z_-]+'){
		$equals = !is_null($arg_expr) ? '[=|:]' : '';		
		return '/^--'.$param_name. $equals . '('.$arg_expr.')$/';
	}

	/*
		$param_name can be string | Array
	*/
	static function matchParam(string $str, $param_name, ?string $arg_expr = '[a-z0-9A-Z_-]+'){

		if (is_array($param_name)){
			$patt = [];
			foreach ($param_name as $p){
				$patt[] = static::getParamRegex($p, $arg_expr);
			}	
		} else {
			$patt =	static::getParamRegex($param_name, $arg_expr);
		}

		$res = static::match($str, $patt, 1);

		if ($arg_expr === null){
			return ($res !== false); 
		}

		return $res;		
	}

	/*
		Wrap target with delimeter(s)
	*/
	static function enclose($target, string $delimeter = "'", $delimeter2 = null){
		if (empty($delimeter2)){
			$delimeter2 = $delimeter;
		}

		if (is_array($target)) {
			return array_map(function($e) use ($delimeter, $delimeter2){
				return "{$delimeter}$e{$delimeter2}";
			}, $target);
		} else {
			return "{$delimeter}$target{$delimeter2}";
		}
	}
	
	// alias de enclose()
	static function wrap($target, string $delimeter = "'", $delimeter2 = null){
		return static::enclose($target, $delimeter, $delimeter2);
	}

	static function backticks($target){
		return static::enclose($target, '`');
	}

	static function toSnakeCase(string $str) : string {
        // Se convierte todo a minúsculas
        $str = strtolower($str);
        
        // Se elimina cualquier caracter no-alfanumérico excepto espacios
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);
        
        // Se reemplazan espacios múltiples por uno solo
        $str = preg_replace('/\s+/', ' ', $str);
        
        // Se reemplazan espacios por "_"
        $str = str_replace(' ', '_', $str);
        
        return $str;
    }
	
	/*
		CamelCase to snake_case
	*/
	static function camelToSnake(string $name, string $char = '_'){
		$len = strlen($name);

		if ($len== 0)
			return NULL;

		$conv = strtolower($name[0]);
		for ($i=1; $i<$len; $i++){
			$ord = ord($name[$i]);
			if ($ord >=65 && $ord <= 90){
				$conv .= $char . strtolower($name[$i]);		
			} else {
				$conv .= $name[$i];	
			}					
		}		
	
		if ($name[$len-1] == $char){
			$name = substr($name, 0, -1);
		}
	
		return $conv;
	}

	/*
		snake_case to CamelCase
	*/
	static function snakeToCamel(string $str, bool $force = false){
		if ($force || static::isAllCaps($str)){
			return $str;
		}

        return implode('',array_map('ucfirst',explode('_', $str)));
    }

	static function isAllCaps(string $str){
		return strtoupper($str) === $str;
	}

    static function startsWith(string $substr, ?string $text, bool $case_sensitive = true)
	{
		if (empty($text)){
			return;
		}

		if (!$case_sensitive){
			$text = strtolower($text);
			$substr = strtolower($substr);
		}

        $length = strlen($substr);
        return (substr($text, 0, $length) === $substr);
    }

    static function endsWith(string $substr, string $text, bool $case_sensitive = true)
	{
		if (!$case_sensitive){
			$text   = strtolower($text);
			$substr = strtolower($substr);
		}

        return substr($text, -strlen($substr))===$substr;
    }

	/*
		Acomodar al órden de parámetros de PHP 8 con str_contains() 

		y corregir dependencias claro.
	*/
	static function contains(string $substr, string $text, bool $case_sensitive = true)
	{
		if (!$case_sensitive){
			$text = strtolower($text);
			$substr = strtolower($substr);
		}

		return ($substr !== '' &&  mb_strpos($text, $substr) !== false);
	}

	static function containsAny(Array $substr, $text, $case_sensitive = true)
	{
		foreach ($substr as $s){
			if (self::contains($s, $text, $case_sensitive)){
				return true;
			}
		}
		return false;
	}


	/*	
		Verifica si la palabra está contenida en el texto.

		Works in Hebrew and any other unicode characters
		Thanks https://medium.com/@shiba1014/regex-word-boundaries-with-unicode-207794f6e7ed
		Thanks https://www.phpliveregex.com/
	*/
	static function containsWord(string $word, string $text, bool $case_sensitive = true) : bool {
		$mod = !$case_sensitive ? 'i' : '';
		
		if (preg_match('/(?<=[\s,.:;_"\']|^)' . $word . '(?=[\s,.:;_"\']|$)/'.$mod, $text)){
			return true;
		} 

		return false;
	}

	static function containsWordButNotStartsWith(string $word, string $text, bool $case_sensitive = true) : bool {
		return !static::startsWith($word, $text, $case_sensitive) && static::containsWord($word, $text, $case_sensitive);
	}

	/*
		Verifica si *todas* las palabras se hallan en el texto. 
	*/
	static function containsWords(Array $words, string $text, bool $case_sensitive = true) {
		$mod = !$case_sensitive ? 'i' : '';

		foreach($words as $word){
			if (!preg_match('/(?<=[\s,.:;"\']|^)' . $word . '(?=[\s,.:;"\']|$)/'.$mod, $text)){
				return false;
			} 
		}		
		return true;
	}

	/*
		Verifica si al menos una palabra es encontrada en el texto
	*/
	static function containsAnyWord(Array $words, string $text, bool $case_sensitive = true) {
		foreach($words as $word){
			if (self::containsWord($word, $text, $case_sensitive)){
				return true;
			} 
		}	
		return false;	
	}

	/*
		Extrae las primeras $count palabras de un texto
		
		https://stackoverflow.com/a/5956635/980631
	*/
	static function getUpToNWords($sentence, $count = 10) {
		preg_match("/(?:\w+(?:\W+|$)){0,$count}/", $sentence, $matches);
		return $matches[0];
	}

	/*
		Filtra por cantidad maxima de palabras y/o de caracteres

        En caso de que ambos parametros sean no-nulos, se entrega un string que tenga como maximo 
		esa cantidad de caracteres y como maximo esa cantidad de palabras (doble restriccion)
	*/
	static function getUpTo(string $sentence, $max_word_count = null, $max_char_len = null) {
		if ($max_word_count === null) {
            $max_word_count = PHP_INT_MAX;
        }

        $words = explode(' ', $sentence);

        if ($max_word_count !== null && $max_char_len !== null) {
            // Ambos parámetros son no nulos
            $trimmedSentence = '';
            $wordCount = 0;
            $charCount = 0;

            foreach ($words as $word) {
                $wordCount++;
                $wordLength = mb_strlen($word);

                if ($charCount + $wordLength > $max_char_len) {
                    break;
                }

                $charCount += $wordLength + 1; // +1 for space
                $trimmedSentence .= $word . ' ';

                if ($wordCount >= $max_word_count) {
                    break;
                }
            }

            $trimmedSentence = trim($trimmedSentence);
        } elseif ($max_word_count !== null) {
            // Solo se proporciona la cantidad máxima de palabras
            $trimmedSentence = self::getUpToNWords($sentence, $max_word_count);
        } elseif ($max_char_len !== null) {
            // Solo se proporciona la cantidad máxima de caracteres
            $trimmedSentence = mb_substr($sentence, 0, $max_char_len);
        } else {
            // Ningún parámetro proporcionado, devuelve la cadena original
            $trimmedSentence = $sentence;
        }

        return trim($trimmedSentence);
    }

	/*
		Recupera todas las palabras desde N caracteres por palabra un texto

		https://stackoverflow.com/a/10685513/980631
	*/
	static function getWordsPerLength(string $str, int $min_chars){
		preg_match_all('/([a-zA-Z]|\xC3[\x80-\x96\x98-\xB6\xB8-\xBF]|\xC5[\x92\x93\xA0\xA1\xB8\xBD\xBE]){'.$min_chars.',}/', $str, $match_arr);
		return $match_arr[0];
	}

	static function getWords(string $str){
		// Reemplazar múltiples espacios, tabuladores y signos de puntuación con un espacio
		$str = preg_replace('/\s+|[\p{P}]+/u', ' ', $str);

		// Convertir el texto a minúsculas
		$str = strtolower($str);
	
		// Dividir el texto en palabras
		$words = explode(' ', $str);
	
		// Eliminar palabras vacías o que solo contengan espacios
		$words = array_filter($words, function($palabra) {
			return trim($palabra) !== '';
		});
	
		// Devolver el array de palabras
		return $words;
	}

	// alias
	static function words(string $str){
		return static::getWords($str);
	}

	static function wordsCount(string $str, bool $unique = false){
		$words = static::getWords($str);
		
		if ($unique){
			$words = array_unique($words);
		}

		return count($words);
	}

	/*
		Revise el DOM y acorte los textos utilizando el metodo getUpToNWords() 
		listado mas abajo a $n_words palabras 

		No afecta codigo Javascript o CSS si lo hubiera.

		No trunca nada dentro de atributos como style, class, etc
	*/
	static function reduceText(string $html, int $n_words): string {
        // Cargar el HTML en un objeto DOMDocument
        $dom = XML::getDocument($html);

        // Obtener todos los nodos de texto
        $xpath = new \DOMXPath($dom);
		
        $textNodes = $xpath->query('//text()');

        // Acortar el texto de cada nodo
        foreach ($textNodes as $node) {
            $text            = $node->nodeValue;
            $words           = preg_split('/\s+/', $text);
            $shortenedWords  = array_slice($words, 0, $n_words);
            $shortenedText   = implode(' ', $shortenedWords);
            $node->nodeValue = $shortenedText;
        }

        // Obtener el HTML resultante
        $reducedHtml = $dom->saveHTML();

        return $reducedHtml;
    }

	static function equal(string $s1, string $s2, bool $case_sensitive = true){
		if ($case_sensitive === false){
			$s1 = strtolower($s1);
			$s2 = strtolower($s2);
		}
		
		return ($s1 === $s2);
	}

	static function replace($search, $replace, &$subject, $count = NULL, $case_sensitive = true)
	{
		if ($subject === null){
			return null;
		}

		if ($case_sensitive){
			$subject = str_replace($search, $replace, $subject, $count);
		} else {
			$subject = str_ireplace($search, $replace, $subject, $count);
		}		
	}

	/**
	* String replace nth occurrence
	* 
	* @author	filipkappa
	*/
	static function replaceNth(string $search, string $replace, string $subject, ?int $occurrence)
	{
		$search = preg_quote($search);
		return preg_replace("/^((?:(?:.*?$search){".--$occurrence."}.*?))$search/", "$1$replace", $subject);
	}
   
	/*
		Recibe un string y un substring a buscar y un arrray de reemplazos alternativos 
		y devuelve al azar el string con uno de los reemplazos

		Ej:

		$originalString     = "I just bought a pair of shoes from this website and I'm really satisfied!";
        $substringToReplace = "shoes";
        $replacementArray   = ["sneakers", "boots", "sandals", "slippers"];

        $modifiedString = static::replaceSubstringRandomly($originalString, $substringToReplace, $replacementArray);

        dd("Original String: $originalString");
        dd("Modified String: $modifiedString");
	*/
	static function replaceSubstringRandomly($originalString, $substringToReplace, $replacementArray) {
		// Buscar todas las ocurrencias del substring en el string original
		$positions = [];
		$startPos = 0;
	
		while (($pos = strpos($originalString, $substringToReplace, $startPos)) !== false) {
			$positions[] = $pos;
			$startPos = $pos + 1;
		}
	
		// Si no hay ocurrencias, devolver el string original sin cambios
		if (empty($positions)) {
			return $originalString;
		}
	
		// Elegir al azar una posición para reemplazar
		$randomPosition = $positions[array_rand($positions)];
	
		// Elegir al azar un reemplazo del array de reemplazos
		$randomReplacement = $replacementArray[array_rand($replacementArray)];
	
		// Realizar el reemplazo
		$modifiedString = substr_replace($originalString, $randomReplacement, $randomPosition, strlen($substringToReplace));
	
		return $modifiedString;
	}

	/*
		Atomiza string (divivirlo en caracteres constituyentes)
		Source: php.net
	*/
	static function stringTochars($s){
		return	preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
	}	
	
	/*
		str_replace() de solo la primera ocurrencia
	*/
	static function replaceFirst($pattern, $replace, $subject)
	{
		$pattern = '/'.preg_quote($pattern, '/').'/';
		return preg_replace($pattern, $replace, $subject, 1);
	}
	
	/*
		str_replace() de solo la ultima ocurrencia
	*/
	static function replaceLast($search, $replace, $subject)
	{
		$pos = strrpos($subject, $search);
	
		if($pos !== false)    
			$subject = substr_replace($subject, $replace, $pos, strlen($search));
		
		return $subject;
	}

	/*
		Hace el substr() desde el $ini hasta $fin
		
		@param string 
		@param int indice de inicio
		@param int indice final
		@return string el substr() de inicio a fin	
	*/
	static function middle(string $str, int $ini, int $end = 0) : string {
		if (($ini==0) and ($end==0)){
			return ($str[0]);
		}else{  
			if ($end==0){
				$end = strlen($str);
			} 	
			return substr ($str,$ini,$end-$ini+1);
		}  	
	}

	static function left(string $str, int $to_pos){
		if ($to_pos === 0){
			return '';
		}

		return substr($str, 0, $to_pos);         
	}

	static function right(string $str, int $from_pos){
		if ($from_pos === 0){
			return $str;
		}

		return substr($str, $from_pos);        
	}

	static function firstChar(string $str) : string {
		return substr($str, 0, 1);
	}

	static function lastChar(string $str) : string {
		return substr($str, -1);
	}

	static function exceptLastChar(string $str) : string {
		return substr($str, 0, -1);
	}

	// alias for exceptLastChar
	static function untilLastChar(string $str) : string {
		return substr($str, 0, -1);
	}

	/*
		https://stackoverflow.com/a/13212994/980631
	*/	
	static function randomString(int $length = 60, bool $include_spaces = true, ?string $base = null){

		if ($base == null){
			$base = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		}

		if ($include_spaces){
			$base .= str_repeat(' ', rand(0, 10));
		}

		return substr(	str_shuffle(str_repeat($x=$base, (int) ceil($length/strlen($x)) )),	1, $length);
	}

	static function randomHexaString(int $length){
		return static::randomString($length, false, '0123456789abcdef');
	}

    /**
	 * Scretet_key generator
	 *
	 * @return string
	 */
	static function secretKeyGenerator(){
		$arr=[];
		for ($i=0;$i<(512/7);$i++){
			$arr[] = chr(rand(32,38));
			$arr[] = chr(rand(40,47));
			$arr[] = chr(rand(58,64));
			$arr[] = chr(rand(65,90));
			$arr[] = chr(rand(91,96));
			$arr[] = chr(rand(97,122));	
			$arr[] = chr(rand(123,126));
		}	
    
        shuffle($arr);
		return substr(implode('', $arr),0,512);
	}

	// https://stackoverflow.com/a/4964352
	function toBase($num, $b=62) {
		$base='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$r = $num  % $b ;
		$res = $base[$r];
		$q = floor($num/$b);
		while ($q) {
		  $r = $q % $b;
		  $q =floor($q/$b);
		  $res = $base[$r].$res;
		}
		return $res;
	}
	
	/*
		Determina si un registro cumple o no con las condiciones expuestas

		Los operadores son practicamente los mismos que los de ApiController

	*/
	static function filter(Array $reg, Array $conditions)
	{
		/*
			Volver búsquedas insensitivas al case (sin implementar)
		*/	
		$case_sensitive = false; 

		$ok = true;
		foreach($conditions as $field => $cond)
		{
			if (!is_array($cond)){                
				if ($cond == 'null' && $reg[$field] === null){                   
					continue;
				}

				if (strpos($cond, ',') === false){
					if ($reg[$field] == $cond){
						continue;
					}
				} else {
					$vals = explode(',', $cond);
					if (in_array($reg[$field], $vals)){
						continue;
					}
				}  
				
				$ok = false;
		
			} else {
				// some operators
		
				foreach($cond as $op => $val)
				{

					if (strpos($val, ',') === false)
					{
						switch ($op) {
							case 'eq':
								if ($reg[$field] == $val){                                    
									continue 2;
								}
								break;
							case 'neq':
								if ($reg[$field] != $val){                
									continue 2;
								}
								break;	
							case 'gt':
								if ($reg[$field] > $val){                           
									continue 2;
								}
								break;	
							case 'lt':
								if ($reg[$field] < $val){                             
									continue 2;
								}
								break;
							case 'gteq':
								if ($reg[$field] >= $val){
									$ok = true;
									continue 2;
								}
								break;	
							case 'lteq':
								if ($reg[$field] <= $val){                     
									continue 2;
								}
								break;	
							case 'contains':
								if (static::contains($val, $reg[$field])){                           
									continue 2;
								}
								break;    
							case 'notContains':
								if (!static::contains($val, $reg[$field])){                  ;
									continue 2;
								}
								break; 
							case 'startsWith':
								if (static::startsWith($val, $reg[$field])){                           
									continue 2;
								}
								break; 
							case 'notStartsWith':
								if (!static::startsWith($val, $reg[$field])){               
									continue 2;
								}
								break; 
							case 'endsWith':             
								if (static::endsWith($val, $reg[$field])){                 
									continue 2;
								}
								break;      
							case 'notEndsWith':
								if (!static::endsWith($val, $reg[$field])){                           
									continue 2;
								}
								break;  
							case 'containsWord':
								if (static::containsWord($val, $reg[$field])){                           
									continue 2;
								}
								break;   
							case 'notContainsWord':
								if (!static::containsWord($val, $reg[$field])){                           
									continue 2;
								}
								break;  
						
							default:
								throw new \InvalidArgumentException("Operator '$op' is unknown", 1);
								break;
						}

					} else {
						// operadores con valores que deben ser interpretados como arrays
						$vals = explode(',', $val);

						switch ($op) {
							case 'between':
								if (count($vals)>2){
									throw new \InvalidArgumentException("Operator between accepts only two arguments");
								}

								if ($reg[$field] >= $vals[0] && $reg[$field] <= $vals[1]){
									continue 2;
								}
								break;
							case 'notBetween':
								if (count($vals)>2){
									throw new \InvalidArgumentException("Operator between accepts only two arguments");
								}

								if ($reg[$field] < $vals[0] || $reg[$field] > $vals[1]){
									continue 2;
								}
								break;
							case 'in':                            
								if (in_array($reg[$field], $vals)){
									continue 2;
								}
								break;
							case 'notIn':                            
								if (!in_array($reg[$field], $vals)){
									continue 2;
								}
								break; 
							case 'contains':
								if (static::containsAny($vals, $reg[$field])){                           
									continue 2;
								}
								break;   
							case 'notContains':
								if (!static::containsAny($vals, $reg[$field])){                           
									continue 2;
								}
								break;        
							case 'containsWord':
								if (static::containsAnyWord($vals, $reg[$field])){                           
									continue 2;
								}
								break;   
							case 'notContainsWord':
								if (!static::containsAnyWord($vals, $reg[$field])){                           
									continue 2;
								}
								break;     

							default:
								throw new \InvalidArgumentException("Operator '$op' is unknown", 1);
								break;    
						}

					}
					$ok = false;

				}
	
				
			}

			if (!$ok){
				break;
			}
		
		} 

		return $ok;
	}

	static function realPathNoCoercive($path = null){
		if ($path === null){
			return false;
		}

		$_path = realpath($path);

		return $_path === false ? $path : $_path;
	}

	/*
		Asumiendo que hay un solo tipo de slash como sucede en un path,
		devuelve si es '/' o '\\'
	*/
	static function getSlash(string $str) {
		if (strpos($str, '/') !== false) {
			return '/';
		} elseif (strpos($str, '\\') !== false) {
			return '\\';
		} else {
			return null; // No se encontró ningún tipo de slash en la cadena
		}
	}

	static function replaceSlashes(string $path) : string {
		return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
	}
    
	/*
		'Util para normalizar rutas de archivos o URLs y asegurarse de que no haya barras diagonales duplicadas
	*/
	static function removeUnnecessarySlashes(string $path) : string {
       	return preg_replace('#/+#','/',$path);
	}

	static function removeTrailingSlash(?string $path = null) : ?string {
		if (empty($path)){
			return $path;
		}

		$path = static::realPathNoCoercive($path);

		if (static::endsWith('\\', $path) || static::endsWith('/', $path)){
			return substr($path, 0, strlen($path)-1);
		}

		return $path;
	}

	// alias
	static function trimTrailingSlash($path = null){
		return static::removeTrailingSlash($path);
	}

	static function removeFirstSlash(?string $path = null) : ?string {
		if (empty($path)){
			return $path;
		}

		if (static::startsWith('\\', $path)){
			return substr($path, 1);
		}

		if (static::startsWith('/', $path)){
			return substr($path, 1);
		}

		return $path;
	}

	// alias
	static function trimFirstSlash($path = null){
		return static::removeFirstSlash($path);
	}

	static function addTrailingSlash(string $path) : string{
		$path = static::realPathNoCoercive($path);

		if (!static::endsWith('\\', $path) && !static::endsWith('/', $path)){
			return $path . '/';
		}

		return $path;		
	}

	/*
		Desentrelaza un string en dos.
	*/
	static function deinterlace(string $literal) : Array {
        $arr = str_split($literal);

        $str1 = '';
        for ($i=0; $i<strlen($literal); $i+=2){
            if ($i>strlen($literal)-1){
                break;
            }
            $str1 .= $arr[$i];
        }

        $str2 = '';
        for ($i=1; $i<strlen($literal); $i+=2){
            if ($i>strlen($literal)-1){
                break;
            }
            $str2 .= $arr[$i];
        }
        
        return [$str1, $str2];
    }

	/*
		Entrelaza (hace un merge) de un array de strings
	*/
    static function interlace(Array $str) : string {
        $ret = '';

        if (count($str) === 0){
            return '';
        } 

        if (count($str) === 1){
            return $str[0];
        } 

        $max_len = 0;
        $arr = [];
        foreach ($str as $ix => $s){
			$ls = strlen($s);
            if ($ls > $max_len){
                $max_len = $ls;
            }

            $arr[] = str_split($s);
        }

        for ($i=0; $i<$max_len; $i++){
            foreach ($arr as $a){
                if (isset($a[$i])){
                    $ret .= $a[$i];
                }
            }
        }
        
        return $ret;
    }

	static function fileExtension(string $filename){
		return Files::fileExtension($filename);
	}

	/*
		Chequeo rapido
	*/
	static function isJSON($val, bool $fast_check = false){
		if ($val === null){
			return false;
		}

		if (!is_string($val)){
			return false;
		}

		if ($val == ''){
			return false;
		}

		if (!$fast_check){
			if (json_decode($val) === null){
				return false;
			}
		}

		return true;
	}

	static function formatJSON(string $str)
	{
		if (static::startsWith('http', $str)){
			$str = consume_api($str);
		} else if (static::endsWith('.json', $str)){
			$str = file_get_contents($str);
		}

		$arr = json_decode($str, true);
		$str = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		return $str;
	}

	static function removeMultipleSpacesInLines(string $str) : string {	
		return preg_replace('/\n\s*\n/', "\n", $str);
	}	

	static function removeMultiLineComments(string $str) : string {	
		return preg_replace('!/\*.*?\*/!s', '', $str);	
	}

	static function wipeEmptyTags(string $input, $tag = null) : string {
		if ($tag === null) {
			// Eliminar cualquier etiqueta vacía
			$pattern = '/<[^\/>]*>\s*<\/[^>]*>/';
		} else {
			// Escapar caracteres especiales en el tag
			$tag = preg_quote($tag);
			
			// Crear el patrón de búsqueda con el tag
			$pattern = '/<' . $tag . '>\s*<\/' . $tag . '>/';
		}
		
		// Realizar el reemplazo
		$output = preg_replace($pattern, '', $input);
		
		return $output;
	}

	/*
		Hay una version analoga en la clase XML 

		La diferencia es que esta *no* remueve tags si poseen atributos (puede ser algo bueno o malo)
	*/
	static function removeHTMLTextModifiers(string $html, $tags = null): string {
		$tagsToRemove = ['b', 'i', 'u', 's', 'strong', 'em', 'sup', 'sub', 'mark', 'small'];
	
		if (is_array($tags) || is_string($tags)) {
			// Si se proporciona un array o una cadena de etiquetas, se utilizan en lugar de las predeterminadas
			$tagsToRemove = is_array($tags) ? $tags : [$tags];
		}
	
		$pattern = '/<\/?(' . implode('|', $tagsToRemove) . ')>/i';
		$page = preg_replace($pattern, '', $html);
	
		return $page;
	}

	static function removeSpaceBetweenTags(string $html): string {
        // Eliminar espacios, tabs, saltos de linea entre etiquetas HTML
        $pattern     = '/>(\s+)</';
        $replacement = '><';
        $html = preg_replace($pattern, $replacement, $html);

        return $html;
    }

	static function replaceHTMLentities(string $page): string {
        return html_entity_decode($page);
    }

	static function removeHTMLentities(string $html): string {
        return preg_replace("/&#?[a-z0-9]+;/i","", $html);
    }

	static function removeDataAttr(string $page): string {
        // Eliminar todas las ocurrencias de atributos data-* en HTML
        $pattern = '/\s+data-[a-zA-Z0-9-]+=[\'"][^\'"]*[\'"]/i';
        $page    = preg_replace($pattern, '', $page);

        return $page;
    }

	// php.net
	static function stripTags($text, $tags = null, $invert = FALSE)
	{
		if (!empty($tags)){
			preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
		
			$tags = array_unique($tags[1]);
		}
		   
		if(is_array($tags) AND count($tags) > 0) {
	  
		  if($invert == FALSE) {
			return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
		  }
	  
		  else {
			return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
		  }
		}
	  
		elseif($invert == FALSE) {
		  return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
		}
	  
		return $text;
	}

	/*
		String length in Kilo bytes
	*/
	static function getLengthInKB(string $str, bool $include_subfix = true){
		return ((string) round(strlen($str) / (1024))) . ($include_subfix ? ' KB' : '') ;
	}

	static function isSerialized($str){
		return (unserialize($str) !== false);
	}

	static function enumerateWithLetters(int $value, bool $starting_by_zero = true){
		return chr($value + 97 + ($starting_by_zero == false ? -1 : 0));
	}

	/**
	 * Converts accentuated characters (àéïöû etc.) 
	 * to their ASCII equivalent (aeiou etc.)
	 *
	 * @param  string $str
	 * @param  string $charset
	 * @return string
	 * 
	 * https://dev.to/bdelespierre/convert-accentuated-character-to-their-ascii-equivalent-in-php-3kf1
	 */
	static function accents2Ascii(string $str, string $charset = 'utf-8'): string
	{
		$str = htmlentities($str, ENT_NOQUOTES, $charset);

		$str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
		$str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
		$str = preg_replace('#&[^;]+;#', '', $str); // supprime les autres caractères

		return $str;
	}

	static function convertAccents($input, $encoding = 'UTF-8') {
		return mb_convert_encoding($input, 'ASCII', $encoding);
	}

	static function convertSlashesToHTML($str)
	{
        $str = str_replace("\r\n", '<br>', $str);
        $str = str_replace("\n", '<br>', $str);
        $str = str_replace("\r", '<br>', $str);

        return $str;
	}

	static function fixBOM($input) {
		return preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $input);;
	}	

	// Caso de uso: campos de un CSV
	static function sanitize($str, bool $replace_accents = true, bool $trim = false, $allowed = 'a-z0-9- ') 
	{
		// Si hay caracteres BOM entonces deben primero convertirse los acentos y recien entonces remover los BOM
		if ($replace_accents){
			$str = static::accents2Ascii($str);
			$str = static::fixBOM($str);
		}

		$str = str_replace('_', '-', $str);
		
		if (!empty($allowed)){
			$str = static::replaceNonAllowedChars($str, $allowed, '');
		}

		if ($trim){
			$str = static::trim($str);
		}
		
		return $str;
	}

	/*
		Genera un slug a partir de un string

		Ej:

		dd(Strings::slug('lo que EL viento se llevó de España 2022')); 
	*/
	static function slug(string $str)
	{
		$str = str_replace('/', '', $str);
		$str = static::sanitize($str, true, true);
		$str = mb_strtolower($str);
		$str = str_replace(' ', '-', $str);
		$str = static::replaceDupes($str, '-');
		
		return trim($str, '-');
	}

	static function firstNotEmpty($default_value = null, ...$args){
        foreach ($args as $val){
            if ($val !== null && $val !== ''){
                return $val;
            }
        }

        return $default_value;
    }

	/*
		Extrae emails de un string

		@param string $str
		@return array

		No los valida, es una funcion sencilla
	*/
	static function getEmails($str){
        return Strings::matchAll($str, '/([a-z0-9_\.]+@[a-z0-9_\.]+)/i');
    }


}



