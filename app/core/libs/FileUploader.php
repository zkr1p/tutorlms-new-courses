<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

/*
	Funciona con form-data

	- Si se excede el POST Content-Length (post_max_size) ...
	
		<b>Warning</b>:  POST Content-Length of .... bytes exceeds the limit of 33554432 bytes in <b>Unknown</b> on line <b>0</b><br />
	
	- Si el numero de archivos excede max_file_uploads ... ni llegan ... al script, solo el maximo (e.g. 20)
	
	- Si un archivo supera upload_max_filesize ... ese archivo se procesa con error=1, los demas se procesan... 
*/

/*
	Uso:

		$uploader = (new FileUploader('uploads'));
        //debug($uploader->doUpload('file_*')->getFileNames(),'file_*');
        debug($uploader->doUpload()->getFileNames(),'Cargados:');
        //debug($uploader->doUpload('other_file')->getFileNames(),'other_file:');
        //debug($uploader->doUpload()->getFileNames(),'Cargados:');
        
        //debug($uploader->doUpload('otro')->getFileNames(),'otro:');
        //debug($uploader->doUpload('some_file')->getFileNames(),'some_file:');
        
        if($uploader->getErrors()){
            debug($uploader->getErrors(),'Errors:');
        }
*/

use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Arrays;
use boctulus\TutorNewCourses\core\libs\Numbers;

class FileUploader
{
	protected $filenames  = [];
	protected $location = Constants::UPLOADS_PATH;
	protected $erroneous = [];
	protected $renamerFn = null;
	protected const WILDCARD = '*';
	
	
	function __construct(){
        if (!file_exists($this->location)){
            Files::mkDirOrFail($this->location);
        }
	}	

	// @param string path (sin / al final)
	function setLocation($path){
		$this->location = $path;

        if (!file_exists($this->location)){
            Files::mkDirOrFail($this->location);
        }

		return $this;
	}	

	function getLocation(){
		return $this->location;
	}
	
	/*
		Renamer
	*/
	function setFileHandler($fn, ...$params){
		$this->renamerFn = [$fn, $params];
		return $this;
	}
	
	/* 
		Retorna un array con el nombre original y el nombre con el que se almacenó
	*/
	function getFileNames(){
		return $this->filenames;
	}
	
	/**
	* Los archivos que presentaron error quedan aqui	
	*/
	function getErrors(){
		return $this->erroneous;
	}
		
	/**
	* 
	* Dependiendo del caso puede tener que llamarse con el NAME del INPUT TYPE='file'
	* y si hay varias declaraciones de archivos como arrays o algunos estan declarados
	* como arrays y otros no, será necesario seleccionarlos con su NAME en $input_name
	*/	
	function doUpload($input_name = NULL)
	{		
		if(empty($_FILES))
			return $this;
					
		$renamerFn = $this->renamerFn[0];
		$subfijo   = $this->renamerFn[1][0];	

		// reset	
		$this->filenames  = [];	
		$this->erroneous = [];
			
		Files::mkDirOrFail($this->location);
		Files::writableOrFail($this->location);
		
		$key_0 = Arrays::arrayKeyFirst($_FILES);
		$file0 = $_FILES[$key_0]; 
		$name = $input_name != NULL ? $input_name : $key_0;


		if(is_array($file0['error']) && isset($_FILES[$name]['error']) && is_array($_FILES[$name]['error'])){
			$i = 0; 
			foreach($_FILES[$name]['error'] as $key => $error)
			{			
				if ($error == UPLOAD_ERR_OK)
				{
					/*
					 $tmp_name  -> "C:\xampp\tmp\phpF864.tmp"
  					 basename($_FILES[$name]["name"][$key]) -> "hidden.jfif"
					*/
					
					$tmp_name = $_FILES[$name]["tmp_name"][$key];
					$filename = basename($_FILES[$name]["name"][$key]); 
					$new_filename = $renamerFn($subfijo) . '.' . pathinfo($_FILES[$name]["name"][$key], PATHINFO_EXTENSION);
					$this->filenames[$i] = [ 
						'ori_name'  => $filename, 
						'as_stored' => $new_filename 
					];
					move_uploaded_file($tmp_name, $this->location. DIRECTORY_SEPARATOR . $new_filename);
					$i++;				
				}else
					$this->erroneous[] = $_FILES[$name]['name'][$key];
			}
		
		}else{
			
			if($input_name != NULL && isset($_FILES[$input_name]['error'])){
				if ($_FILES[$input_name]['error'] == UPLOAD_ERR_OK)
				{
					$tmp_name = $_FILES[$input_name]['tmp_name'];
					$filename =  basename($_FILES[$input_name]['name']);
					$new_filename = $renamerFn($subfijo) . '.' . pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
					$this->filenames[] = [ 
						'ori_name'  => $filename, 
						'as_stored' => $new_filename 
					];
					move_uploaded_file($tmp_name, $this->location. DIRECTORY_SEPARATOR. $new_filename);		
				}else
					$this->erroneous[] = $_FILES[$input_name]['name'];
			}
			else
				if($input_name == NULL){
					foreach($_FILES as $_name => $file){
						if ($file['error'] == UPLOAD_ERR_OK)
						{
							$tmp_name = $file['tmp_name'];
							$filename =  basename($file['name']);
							$new_filename = $renamerFn($subfijo) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
							$this->filenames[] = [ 
								'ori_name'  => $filename, 
								'as_stored' => $new_filename
							 ];
							move_uploaded_file($tmp_name, $this->location. DIRECTORY_SEPARATOR. $new_filename);		
						}else
							$this->erroneous[] = $file['name'];
					}
				}else if ($input_name[strlen($input_name)-1] == self::WILDCARD){
					$starts_with = substr($input_name, 0, strlen($input_name)-1);
					
					foreach($_FILES as $_name => $file){
						if(substr($_name, 0, strlen($_name)-1) != $starts_with)
							continue;
						
						if ($file['error'] == UPLOAD_ERR_OK)
						{
							$tmp_name = $file['tmp_name'];
							$filename =  basename($file['name']);
							$new_filename = $renamerFn($subfijo) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
							$this->filenames[] = [ 
								'ori_name'  => $filename, 
								'as_stored' => $new_filename 
							];
							move_uploaded_file($tmp_name, $this->location. DIRECTORY_SEPARATOR. $new_filename);		
						}else
							$this->erroneous[] = $file['name'];
					}
				}
		
		}
		
		return $this;
    }	

	
	
}	