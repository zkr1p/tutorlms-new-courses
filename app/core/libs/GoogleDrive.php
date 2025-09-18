<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Url;
use boctulus\TutorNewCourses\core\libs\XML;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\FileCache;

/*
    Wrapper sobre el SDK de Google Drive

    @author Pablo Bozzolo < boctulus >
*/
class GoogleDrive
{
	protected $client;
    protected $api_key;

    function __construct($api_key = null) { 
        $this->api_key = $api_key;
    }

    /*
        Google API client

        Podria trabajar tambien on OAuth
    */
	protected function __getClient($api_key = null){
		$cfg = Config::get();

        $google_console_api_key = $this->api_key ?? $api_key ?? $cfg['google_console_api_key'];

        $client = new \Google\Client();

        // Disable SSL check in local
        if ($cfg['ssl_cert'] === false || env('APP_ENV') == 'local' || env('APP_ENV') == 'debug' || env('DEGUG') == 'true'){  
            $guzzleClient = new \GuzzleHttp\Client(["curl" => [
                CURLOPT_SSL_VERIFYPEER => false
            ]]);        
        }

        $client->setHttpClient($guzzleClient);

        $client->setApplicationName($cfg['app_name']);
        $client->setDeveloperKey($google_console_api_key);

		$this->client = $client;
	}

    protected function __getDriveService()
    {
        $this->__getClient();

        $class = 'Google_Service_Drive';
        return new $class($this->client);
    }

    protected function getId(string $link_or_id){
        if (Strings::startsWith('https://', $link_or_id)){
            $id = Url::getQueryParam($link_or_id, 'id');
        } else {
            $id = $link_or_id;
        }

        return $id;
    }

    /*
        Obtiene info (con los permisos correctos) sobre un "drive" o "folder" 
        o sea... primero hace un "list" de archivos

        Ej:
    
        $googleDrive->getInfo('1oUqLiey81m0keXAo1ZtOsGYfd5c1VTeT')

        o

        $googleDrive->getInfo('1oUqLiey81m0keXAo1ZtOsGYfd5c1VTeT', null, 'createdTime', 'modifiedTime')

        o

        googleDrive->getInfo('1oUqLiey81m0keXAo1ZtOsGYfd5c1VTeT', null,  'createdTime, modifiedTime')

        o incluyendo paginacion

        $googleDrive->getInfo('1oUqLiey81m0keXAo1ZtOsGYfd5c1VTeT', [
            'pageSize' => 10
        ], 'id, name, createdTime, modifiedTime');


        Para saber que atributos ('createdTime', 'modifiedTime', etc) se pueden solicitar, ver la lista:

        https://developers.google.com/resources/api-libraries/documentation/drive/v3/php/latest/class-Google_Service_Drive_DriveFile.html
    */
    function getFolderInfo(?string $folder_id = null, ?array $options = null, ...$file_fields)
    {
        $service = $this->__getDriveService();

        $query = "trashed = false";
        if ($folder_id) {
            // Add the folder ID to the query
            $query .= " and '{$folder_id}' in parents";
        }

        $file_fields_str = '';
        if (!empty($file_fields)){
            $file_fields_str = implode(',', $file_fields); 
        }  

        $_options = [
            'q' => $query,
            'fields' => "files($file_fields_str)",
        ];

        /*
            Podria incluir offset ("page"?),... y hacer un merge entre $_options y $options
        */
        if (isset($options['pageSize'])){
            $_options['pageSize'] =  $options['pageSize'];
            $_options['fields']   = 'nextPageToken, '. $_options['fields'];
        }

        $files = $service->files->listFiles($_options);

        if (is_array($file_fields) && count($file_fields) == 1 && Strings::contains(',', $file_fields[0])){
            $file_fields = explode(',',$file_fields[0]);
            $file_fields = array_map('trim', $file_fields);
        }

        $ret = [];
        foreach ($files->getFiles() as $file) {
            $row = [];

            foreach ($file_fields as $field){
                $getter = "get". ucfirst($field);
                $row[$field] = $file->{$getter}();
            }

            $ret[] = $row;
        }

        return $ret;
    }

    /*
        Obtiene INFO sobre un ARCHIVO en particular

        $googleDrive  = new GoogleDrive();
        $modifiedTime = $googleDrive->getInfo($id, 'modifiedTime')['modifiedTime'];

        Nota:

        Solo usar $format si todos los campos pasados detro de $fields son de tipo timestamp
        porque se aplica $format a todos los campos (por diseño)

    */
    function getInfo(string $link_or_id, $fields, ?string $format = null): array
    {       
        $service = $this->__getDriveService();
        $id      = $this->getId($link_or_id);

        if (is_array($fields)){
            $fields_str = implode(',', $fields);
        } else {
            $fields_str = $fields;
            $fields     = explode(',', $fields);
        }

        // Retrieve the file metadata based on the link or ID
        $file = $service->files->get($id, [
            'fields' => $fields_str
        ]);

        $ret = [];    

        foreach ($fields as $field){
            $getter = "get". ucfirst($field);
            $ret[$field] = $file->{$getter}();

            // Se aplica a cada campo !
            if (!empty($format)){
                $ret[$field] = date($format, strtotime($ret[$field]));
            }
        };
    
        return $ret;
    }

    /*
        Obtiene "Update date" sobre un ARCHIVO en particular

        $googleDrive = new GoogleDrive();
        $updateDate  = $googleDrive->getUpdateDate($gd_link, 'd-m-Y');
    */
    function getUpdateDate(string $link_or_id, string $format = 'Y-m-d H:i:s')
    {
        $id = $this->getId($link_or_id);

        if (empty($id)){
            return null;
        }

        // Extract the modified time from the file metadata
        $modifiedTime = $this->getInfo($id, 'modifiedTime', $format)['modifiedTime'];

        return $modifiedTime;
    }

    /*
      Descarga un archivo de Google Drive
     
      @param string         $link_or_id     
      @param string         $destination        ruta de destino para guardar el archivo descargado
      @param bool           $throw              si se lanza Exception
      @param callable|null  $progress_id_cb     callback para nombrar archivos de progreso de descarga (por default se toma el ID del link de GDrive)

      @return bool  true si la descarga se realizó correctamente, false en caso contrario
      
      Ej:
      
        // "https://docs.google.com/uc?export=download&id=1yMrPb6j51mvXV2taGiSa57fcElpbApGR"
        $fileId      = '1yMrPb6j51mvXV2taGiSa57fcElpbApGR';
        $destination = ETC_PATH . 'file_2.zip';
     
        $result = (new GoogleDrive())
        ->download($fileId, $destination);

        // true
        dd($result, 'RESULT');

        
      Ej:

        $result      = (new GoogleDrive($g_key))
        ->download($link, $destination, true, $expiration_time, 0, function() use ($pid){
            return $pid;
        });
     */
    function download(string $link_or_id, string $destination, bool $throw = false, $expiration_time = null, $micro_seconds = null, $progress_id_cb = null): bool
    {       
        $chunkSize = 256; // Chunk size to read from the response stream

        $id = $this->getId($link_or_id);

        if ($expiration_time !== null){
            if (file_exists($destination)){
                if (FileCache::expired(filemtime($destination), $expiration_time)){
                    unlink($destination);
                } else {
                    // dd("Using cache for $id ...");
                    return true;
                }
            }
        }

        $progress_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.my_store';
        Files::mkDirOrFail($progress_path);

        if ($micro_seconds === null){
            $micro_seconds = rand(10, 45) * rand(900000, 1000000);
        }

        static $downloads_in_a_row;
        
        if ($downloads_in_a_row === null){
            $downloads_in_a_row = 0;
        }

        // dd("Downloading $id ...");

        $service = $this->__getDriveService();
        
        if (empty($id)){
            if ($throw){
                throw new \Exception("id is empty for '$link_or_id'");
            }

            return null;
        }

        Files::mkDestination($destination);
       
        try {
            $response = $service->files->get($id, ['alt' => 'media']);

            $fileHandle = fopen($destination, 'w');

            $totalSize = (int) $response->getBody()->getSize();
            $bytesDownloaded = 0;

            // dd($totalSize, 'Size');
    
            while (!$response->getBody()->eof()) {
                // Traigo chunk

                $chunk = $response->getBody()->read($chunkSize);
                fwrite($fileHandle, $chunk);
    
                $bytesDownloaded += strlen($chunk);
    
                // Calculate and display the download progress percentage
        
                $progress = round(($bytesDownloaded / $totalSize) * 100);

                // Almaceno % de progreso

                $path = $progress_path . DIRECTORY_SEPARATOR . (is_callable($progress_id_cb) ? $progress_id_cb($id) : $id);

                // dd($path);

                file_put_contents($path, $progress);
            }
            
            fclose($fileHandle);

            $downloads_in_a_row++;
            // dd($downloads_in_a_row, "Downloads in a row");

            // Cada 10 downloads, hago una pausa
            if ($downloads_in_a_row % 10 === 0){
                // dd("Taking a nap ...");

                sleep(60 * rand(12, 15));
                usleep(rand(500000, 1000000));
            } else {
                // Sino aplico la pausa especificada
                if (!empty($micro_seconds)){
                    usleep($micro_seconds);
                }
            }

            // Cada 10 downloads, hago una pausa cada 33 downloads
            if ($downloads_in_a_row % 33 === 0 && $downloads_in_a_row % 99 !== 0){
                StdOut::pprint("Taking a nap ...");

                sleep(60 * rand(12, 15));
                usleep(rand(500000, 1000000));
            } else {
                // Sino aplico la pausa especificada
                if (!empty($micro_seconds)){
                    usleep($micro_seconds);
                }
            }
          
            return true;
        } catch (\Exception $e) {  
            $err_msg = $e->getMessage();

            $fatal_error = Strings::containsAny([
                '<html>',
                'your computer or network may be sending automated queries',
                'support.google.com/websearch/answer/'
            ], $err_msg);    

            if ($fatal_error){
                $downloads_in_a_row = 0;
            }
            
            if ($throw || $fatal_error){
                if (Strings::contains('<html>', $err_msg)){
                    $err_msg = XML::HTML2Text($err_msg);
                }

                throw new \Exception($err_msg);
            }

            return false;
        }
    }

}



