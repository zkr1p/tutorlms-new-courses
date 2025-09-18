<?php

namespace boctulus\TutorNewCourses\core\interfaces;

interface IMail {
    
    static function send(Array $to, $subject = '', $body = '', $attachments = null, Array $from = [], Array $cc = [], Array $bcc = [], Array $reply_to = [], $alt_body = null);

}