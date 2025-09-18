<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (php_sapi_name() != "cli"){
	return; 
}

/////////////////////////////////////////////////////////////////////////////////

/*
	Repara namespaces --sin dependencias--

	return [
        'namespace' => "boctulus\TutorImpExp"  <-------- deberia poder cambiar esto tambien !!
	]
*/

$search  = 'boctulus\\TutorImpExp\\';
$replace = 'boctulus\\TutorNewCourses\\';


searchAndReplaceInFiles(__DIR__, '*.php', $search, $replace);


function recursiveGlob($pattern, $flags = 0) {
	$files = glob($pattern, $flags); 
	foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT|GLOB_BRACE) as $dir) {
		$files = array_merge($files, recursiveGlob($dir.'/'.basename($pattern), $flags));
	}

	foreach ($files as $ix => $f){
		if (realpath($f) == __FILE__){
			unset($files[$ix]);
			continue;
		}

		$files[$ix] = preg_replace('#/+#','/',$f);
	}	

	return $files;
}

function searchAndReplaceInFiles($directory, $filePattern, $searchString, $replaceString) {
	$files = recursiveGlob("$directory/$filePattern", GLOB_BRACE);
	$files[] = __DIR__ . '/com';

	foreach ($files as $file) {
		if (is_file($file)) {
			$content = file_get_contents($file);
			$updatedContent = str_replace($searchString, $replaceString, $content);

			if ($content !== $updatedContent) {
				file_put_contents($file, $updatedContent);
				print_r("Replaced in file: $file");
			}
		} elseif (is_dir($file)) {
			searchAndReplaceInFiles($file, $filePattern, $searchString, $replaceString);
		}
	}
}

