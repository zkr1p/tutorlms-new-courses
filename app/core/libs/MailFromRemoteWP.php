<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\MailBase;

class MailFromRemoteWP extends MailBase
{
    protected static $url;
    
    static function setRemote($url){
        static::$url = $url;
    }

    /*
        API email like this

        POST /api/wp_mail/send

        {
            "to": "boctulus@gmail.com",
            "subject": "Otra prueba 2",
            "body": "Cuerpo del mensaje 2"
        }
    */
    static function send($to, $subject = '', $body = '', $attachments = null, $from = [], Array $cc = [], $bcc = [], $reply_to = [], $alt_body = null){
		if (empty(static::$url)){
            throw new \Exception("Set remote WP url first");
        }

        $body = trim($body); 

        if (empty($subject)){
            throw new \Exception("Subject is required");
        }

        if (empty($body) && empty($alt_body)){
            throw new \Exception("Body or alt_body is required");
        }

        if (!is_array($to)){
            $tmp = $to;
            $to  = [];
            $to[]['email'] = $tmp;
        } else {
            if (Arrays::is_assoc($to)){
                $to = [ $to ];
            }
        }

        if (!is_array($cc)){
            $tmp = $cc;
            $cc  = [];
            $cc[]['email'] = $tmp;
        } else {
            if (Arrays::is_assoc($cc)){
                $cc = [ $cc ];
            }
        }

        if (!is_array($bcc)){
            $tmp = $bcc;
            $bcc  = [];
            $bcc[]['email'] = $tmp;
        } else {
            if (Arrays::is_assoc($bcc)){
                $bcc = [ $bcc ];
            }
        }

        if (!is_array($from)){
            $tmp = $from;
            $from = [];
            $from['email'] = $tmp;
        } 

        $data = [
            'to'      => $to[0]['email'],
            'subject' => $subject,
            'body'    => $body
        ];
        
        $res = consume_api(static::$url, 'POST', $data, [
            'User-Agent' => 'PostmanRuntime/7.34.0'
        ]);
        
        return $res;
	}
    
}