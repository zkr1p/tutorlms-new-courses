<?php 

/*
	@author boctulus
*/

namespace boctulus\TutorNewCourses\core\libs;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once ABSPATH . 'wp-includes/PHPMailer/Exception.php';
require_once ABSPATH . 'wp-includes/PHPMailer/PHPMailer.php';
require_once ABSPATH . 'wp-includes/PHPMailer/SMTP.php';

/*
  Cambiar algunos métodos a de intancia a fin de poder usar métodos encadenados

  ->to(..)
  ->body(..)
  ->etc

  Ej:
    
    Mail::debug(4);
    //Mail::silentDebug();

    Mail::config([
        'ssl' => [
            'allow_self_signed' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
    ]]);


    $msg = "Cuerpo del mensaje";

    Mail::send(get_option('admin_email'), '[ Divorcio ] Tiene una nueva cita con un cliente', nl2br($msg));

    debug(Mail::errors(), 'Error');
    debug(Mail::status(), 'Status');

*/
class Mail extends MailBase
{
    protected static $mailer      = null;
    protected static $options     = [];

    // change mailer
    static function setMailer(string $name){
        static::$mailer = $name;
    }

    static function getMailer(){
        $config = Config::get();
        return static::$mailer ?? $config['email']['mailer_default'];
    }

    /*
        Overide options
    */
    static function config(Array $options){
        if (!isset($options['SMTPOptions'])){
            static::$options['SMTPOptions'] = $options;
        } else {
            static::$options = $options;
        }
    }

    static function silentDebug($level = null){
        global $config;

        $options = $config['email']['mailers'][ static::getMailer() ];

        if (isset($options['SMTPDebug']) && $options['SMTPDebug'] != 0){
            $default_debug_level = $options['SMTPDebug'];
        }

        $level = static::$debug_level ?? $level ?? $default_debug_level ?? 4;

        static::config([
            'SMTPDebug' => $level
        ]);

        static::$silent = true;
    }

    /*
        level 1 = client; will show you messages sent by the client
        level 2  = client and server; will add server messages, it’s the recommended setting.
        level 3 = client, server, and connection; will add information about the initial information, might be useful for discovering STARTTLS failures
        level 4 = low-level information. 
    */
    static function debug(int $level = 4){
        static::$debug_level = $level;
    }

    /*
        Usar una interfaz común para SMTP y correos via API

        Es preferible recibir $from y  $replyTo como arrays de la forma:
            
            [
                'name' => 'xxx',
                'email' => 'xxxxx@xxx.com'
            ]

        y $to como un array de arrays:

        [
            [
                'name' => 'xxx',
                'email' => 'xxxxx@xxx.com'
            ], 
            
            // ...
        ]

        Ver
        https://stackoverflow.com/questions/3149452/php-mailer-multiple-address
        https://stackoverflow.com/questions/24560328/phpmailer-altbody-is-not-working

        Gmail => habilitar:

        https://myaccount.google.com/lesssecureapps

        TODO

        - Hacer que parametros que son de tipo Array puedan ser Array|string

        send(
            Array|string|null $to, 
            $subject = '', 
            $body = '', 
            $attachments = null, 
            Array|string|null $from = null, 
            Array|string|null $cc = null, 
            Array|string|null $bcc = null, 
            Array|string|null $reply_to = null, 
            $alt_body = null
        )
    */
    static function send($to, $subject = '', $body = '', $attachments = null, $from = [], Array $cc = [], $bcc = [], $reply_to = [], $alt_body = null){
		$config = Config::get();

        $body = trim($body);

        if (!Strings::startsWith('<html>', $body)){
            $body = "<html><body>$body</body></html>";
        }

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

        // if (empty($reply_to)){
        //     $reply_to = $from;
        // }

        $mailer = static::getMailer();

		$mail = new PHPMailer();
        $mail->isSMTP();

        $options = array_merge($config['email']['mailers'][$mailer], static::$options);

        if (static::$debug_level !== null){
            $options['SMTPDebug'] = static::$debug_level;
        }

        foreach ($options as $k => $prop){
			$mail->{$k} = $prop;
        }	

        if (!empty($reply_to)){
            $mail->addReplyTo($reply_to['email'], $reply_to['name'] ?? '');
        }
        

        $from['email'] = $from['email'] ?? $config['email']['from']['address'] ?? $config['email']['mailers'][$mailer]['Username'];
        $from['mame']  = $from['mame']  ?? $config['email']['from']['name'];

        if (!empty($from)){
            $mail->setFrom($from['email'], $from['name'] ?? '');
        }
        
        foreach ($to as $_to){
            $mail->addAddress($_to['email'], $_to['name'] ?? '');
        }

        $mail->Subject = $subject;
		$mail->msgHTML($body); 
		
		if (!is_null($alt_body)){
            $mail->AltBody = $alt_body;
        }
		
        if (!empty($attachments)){
            if (!is_array($attachments)){
                $attachments = [ $attachments ];
            }

            foreach($attachments as $att){
                $mail->addAttachment($att);    
            }
        }

        if (!empty($cc)){            
            foreach($cc as $_cc){
                $mail->addCC($_cc['email'], $_cc['name'] ?? '');
            }
        }

        if (!empty($bcc)){            
            foreach($bcc as $_bcc){
                $mail->addBCC($_bcc['email'], $_bcc['name'] ?? '');
            }
        }

        if (static::$silent){
            ob_start();
        }
		
        if (!$mail->send())
        {	
            static::$errors = $mail->ErrorInfo;

            if (static::$silent){
                Logger::dump(static::$errors, 'dump.txt', true);
            }

            $ret = static::$errors;
        }else{
            if (static::$silent){
                Logger::dump(true, 'dump.txt', true);
            }

            static::$errors = null;
            $ret =  true;
        }        
                 
        if (static::$silent){
            $content = ob_get_contents();
            ob_end_clean();
        }

        return $ret;
	}
    
}