<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

/*
	@author boctulus
*/

class FileCache extends Cache
{
    static function getCachePath(string $key) : string {
        static $path;

        if (isset($path[$key])){
            return $path[$key];
        }

        $filename = sha1($key);

        $path[$key] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.cache';
        return $path[$key];
    }

    /*
        Logica para saber si un archivo usado como cache ha expirado
    */
    static function expiredFile(string $path, ?int $expiration_time = null) : bool {
        $exists = file_exists($path);

        if (!$exists){
            return true;
        }

        if ($expiration_time !== null){
            $updated_at = filemtime($path);

            if (static::expired($updated_at, $expiration_time)){
                // Cache has expired, delete the file
                unlink($path);
            
                return true;
            }
        } 

        $content = file_get_contents($path);
        $data    = unserialize($content);

        if ($data['expires_at'] < time()) {
            // Cache has expired, delete the file
            unlink($path);

            return true;
        }

        return false;
    }

    static function put($key, $value, $minutes)
    {
        $path = static::getCachePath($key);
        $expiresAt = time() + ($minutes * 60);
        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
        $content = serialize($data);

        if (file_put_contents($path, $content) !== false) {
            return true;
        }

        return false;
    }

    static function get($key, $default = null)
    {
        $path = static::getCachePath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $content = file_get_contents($path);
        $data    = unserialize($content);

        if ($data['expires_at'] < time()) {
            // Cache has expired, delete the file
            unlink($path);
            return $default;
        }

        return $data['value'];
    }

    static function forget($key)
    {
        $path = static::getCachePath($key);

        if (!file_exists($path)) {
            return;
        }
        
        unlink($path);
    }
}
