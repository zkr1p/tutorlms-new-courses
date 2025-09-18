<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\MailBase;

/*
    wp_mail() wrapper

    Notas:

    - De momento usa un solo $to, un solo $reply_to, etc

    - Mucho codigo podria ser movido a MailBase si se re-factorizara
*/
class MailWP extends MailBase
{
       static function send($to, $subject = '', $body = '', $attachments = null, $from = [], Array $cc = [], $bcc = [], $reply_to = [], $alt_body = null)
       {
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

        if (!is_array($reply_to)){
            $tmp = $reply_to;
            $reply_to  = [];
            $reply_to[]['email'] = $tmp;
        } else {
            if (Arrays::is_assoc($reply_to)){
                $reply_to = [ $reply_to ];
            }
        }

        if (!is_array($from)){
            $tmp = $from;
            $from = [];
            $from['email'] = $tmp;
        } 

        /*
            Sanitization
        */

        if (!empty($to)){
            foreach ($to as $ix => $_to){
                $to[$ix]['email'] = sanitize_email($to[$ix]['email']);
            }
        }

        if (!empty($from)){
            foreach ($from as $ix => $_f){
                $from[$ix]['email'] = sanitize_email($from[$ix]['email']);
            }
        }

        if (!empty($reply_to)){
            foreach ($reply_to as $ix => $_to){
                $reply_to[$ix]['email'] = sanitize_email($reply_to[$ix]['email']);
            }
        }

        if (!empty($subject)){
            $subject  = sanitize_text_field($subject);
        }
        
        if (!empty($message)){
            $message  = wp_kses_post($message);
        }

        $headers = array(
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=utf-8",   
        );

        if  (!empty($from)){
            $headers[] = "From: $from[0]['email']";
        }

        if  (!empty($reply_to)){
            $headers[] = "Reply-To: $reply_to[0]['email']";
        }

        // paths to attachments
        if ( strpos( $attachments, ',' ) !== false ) {
            $attachments = explode( ',', $attachments );
        }
       
        $res = wp_mail($to[0]['email'], $subject, $body, $headers, $attachments);
        
        return $res;
	}
    
}