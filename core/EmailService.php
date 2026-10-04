<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/emailTemplates.php';
require_once __DIR__.'/EmailValidator.php';

class EmailService {
    private const GRAPH_ATTACHMENT_LIMIT = 2500000;
    private const SMTP_ATTACHMENT_LIMIT = 18000000;

    public function configByType(int $type):?array {
        $st=db()->prepare('SELECT * FROM correo WHERE correo_tipo_id=? AND estado=1 ORDER BY correo_id DESC LIMIT 1');
        $st->execute([$type]);
        return $st->fetch()?:null;
    }

    public function configById(int $id):?array {
        $st=db()->prepare('SELECT * FROM correo WHERE correo_id=? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch()?:null;
    }

    public function sendByType(
        int $type,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array $bcc=[],
        array $attachments=[]
    ):array {
        $cfg=$this->configByType($type);
        if(!$cfg)return ['success'=>false,'message'=>'No active email configuration for this type.'];
        return $this->send($cfg,$to,$subject,$html,$replyTo,$bcc,$attachments);
    }

    public function sendWithFallback(
        array $types,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array $bcc=[],
        array $attachments=[]
    ):array {
        foreach($types as $type) {
            $cfg=$this->configByType((int)$type);
            if($cfg) {
                $result=$this->send($cfg,$to,$subject,$html,$replyTo,$bcc,$attachments);
                if($result['success'])return $result;
                if(($result['error_code']??'')==='invalid_recipients')return $result;
                $last=$result;
            }
        }
        return $last??['success'=>false,'message'=>'No active email configuration is available.'];
    }

    public function test(int $id,string $to=''):array {
        $cfg=$this->configById($id);
        if(!$cfg)return ['success'=>false,'message'=>'Email configuration not found.'];
        $dest=$to!==''?$to:$cfg['correo'];
        return $this->send($cfg,$dest,'Castro\'s Ready email test',EmailTemplates::test($cfg['metodo_envio'],settings()));
    }

    public function send(
        array $cfg,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array $bcc=[],
        array $attachments=[]
    ):array {
        $rejected=[];
        $primaryValidation=EmailValidator::validate($to);
        $to=$primaryValidation['valid']?(string)$primaryValidation['email']:'';
        if(!$primaryValidation['valid']) {
            $rejected[]=$this->rejectedRecipient($primaryValidation,'to');
        }

        $bcc=$this->normalizeEmails($bcc,$to,$rejected);

        if($to===''&&$bcc===[]) {
            return [
                'success'=>false,
                'message'=>'No valid email recipients are available. The message was not sent.',
                'error_code'=>'invalid_recipients',
                'rejected_recipients'=>$rejected,
                'valid_recipient_count'=>0,
            ];
        }

        $replyTo=trim($replyTo);
        if($replyTo!=='') {
            $replyValidation=EmailValidator::validate($replyTo);
            if($replyValidation['valid']) {
                $replyTo=(string)$replyValidation['email'];
            } else {
                $rejected[]=$this->rejectedRecipient($replyValidation,'reply_to');
                $replyTo='';
            }
        }

        $result=strtoupper((string)$cfg['metodo_envio'])==='GRAPH'
            ?$this->graph($cfg,$to,$subject,$html,$replyTo,$bcc,$attachments)
            :$this->smtp($cfg,$to,$subject,$html,$replyTo,$bcc,$attachments);

        $result['valid_recipient_count']=($to!==''?1:0)+count($bcc);
        if($rejected!==[]) {
            $result['rejected_recipients']=$rejected;
            $result['message'].=' '.count($rejected).' invalid recipient(s) skipped.';
        }

        return $result;
    }

    private function normalizeEmails(
        array $emails,
        string $primary='',
        array &$rejected=[]
    ):array {
        $normalized=[];
        foreach($emails as $email) {
            $validation=EmailValidator::validate((string)$email);
            if(!$validation['valid']) {
                $rejected[]=$this->rejectedRecipient($validation,'bcc');
                continue;
            }

            $normalizedEmail=(string)$validation['email'];
            if($primary!==''&&strcasecmp($normalizedEmail,$primary)===0)continue;
            $normalized[strtolower($normalizedEmail)]=$normalizedEmail;
        }
        return array_values($normalized);
    }

    private function rejectedRecipient(array $validation,string $role):array {
        $rejected=[
            'email'=>(string)($validation['email']??''),
            'role'=>$role,
            'reason_code'=>(string)($validation['reason_code']??'invalid'),
            'reason'=>(string)($validation['reason']??'Invalid email recipient.'),
            'suggestion'=>(string)($validation['suggestion']??''),
        ];

        $this->logRejectedRecipient($rejected);
        return $rejected;
    }

    private function logRejectedRecipient(array $rejected):void {
        static $logged=[];
        $key=hash('sha256',json_encode($rejected,JSON_UNESCAPED_UNICODE));
        if(isset($logged[$key]))return;
        $logged[$key]=true;

        log_activity(
            'email_recipient_rejected',
            'Rejected invalid email recipient: '.($rejected['email']?:'[empty]').' ('.$rejected['reason'].')',
            $rejected
        );
    }

    private function normalizeAttachments(array $attachments,int $maxTotalBytes):array {
        $safe=[];
        $total=0;
        $uploadRoot=realpath(UPLOAD_DIR);
        if($uploadRoot===false)return [];

        foreach(array_slice($attachments,0,8) as $attachment) {
            if(!is_array($attachment))continue;
            $path=(string)($attachment['path']??'');
            if($path==='')continue;
            $real=realpath($path);
            if($real===false||!is_file($real)||!str_starts_with($real,$uploadRoot.DIRECTORY_SEPARATOR))continue;
            $size=(int)filesize($real);
            if($size<1||($total+$size)>$maxTotalBytes)continue;

            $name=basename(trim((string)($attachment['name']??basename($real))));
            if($name==='')$name=basename($real);
            $name=preg_replace('/[\r\n\x00-\x1F\x7F]+/u',' ',$name)?:'attachment';
            $mime=trim((string)($attachment['mime']??''));
            if(!preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i',$mime))$mime='application/octet-stream';

            $safe[]=['path'=>$real,'name'=>$name,'mime'=>$mime,'size'=>$size];
            $total+=$size;
        }
        return $safe;
    }

    private function graph(
        array $c,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array $bcc=[],
        array $attachments=[]
    ):array {
        if(!function_exists('curl_init'))return ['success'=>false,'message'=>'cURL is not enabled on this server.'];
        $tenant=trim((string)$c['tenant_id']);
        $client=trim((string)$c['client_id']);
        $secret=secret_decrypt($c['client_secret']??'');
        $from=trim((string)($c['graph_user']?:$c['correo']));
        if(!$tenant||!$client||!$secret||!filter_var($from,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Graph credentials are incomplete.'];

        $ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>30,
            CURLOPT_POSTFIELDS=>http_build_query([
                'client_id'=>$client,
                'scope'=>'https://graph.microsoft.com/.default',
                'client_secret'=>$secret,
                'grant_type'=>'client_credentials'
            ]),
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']
        ]);
        $raw=curl_exec($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $err=curl_error($ch);
        curl_close($ch);
        $json=json_decode((string)$raw,true);
        if($code<200||$code>=300||empty($json['access_token']))return ['success'=>false,'message'=>'Graph token error: '.($err?:('HTTP '.$code))];

        $message=[
            'subject'=>$subject,
            'body'=>['contentType'=>'HTML','content'=>$html]
        ];
        if($to!=='')$message['toRecipients']=[['emailAddress'=>['address'=>$to]]];
        if($replyTo!=='')$message['replyTo']=[['emailAddress'=>['address'=>$replyTo]]];
        if($bcc)$message['bccRecipients']=array_map(static fn(string $email)=>['emailAddress'=>['address'=>$email]],$bcc);

        $safeAttachments=$this->normalizeAttachments($attachments,self::GRAPH_ATTACHMENT_LIMIT);
        if($safeAttachments) {
            $message['attachments']=[];
            foreach($safeAttachments as $attachment) {
                $content=@file_get_contents($attachment['path']);
                if($content===false)continue;
                $message['attachments'][]=[
                    '@odata.type'=>'#microsoft.graph.fileAttachment',
                    'name'=>$attachment['name'],
                    'contentType'=>$attachment['mime'],
                    'contentBytes'=>base64_encode($content)
                ];
            }
            if(!$message['attachments'])unset($message['attachments']);
        }

        $payload=['message'=>$message,'saveToSentItems'=>(int)($c['save_to_sent_items']??1)===1];
        $ch=curl_init('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail');
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>45,
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$json['access_token'],'Content-Type: application/json']
        ]);
        $resp=curl_exec($ch);
        $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $err=curl_error($ch);
        curl_close($ch);

        return $code===202
            ?['success'=>true,'message'=>'Email sent with Microsoft Graph.']
            :['success'=>false,'message'=>'Graph send error: '.($err?:('HTTP '.$code.' '.$resp))];
    }

    private function smtp(
        array $c,
        string $to,
        string $subject,
        string $html,
        string $replyTo='',
        array $bcc=[],
        array $attachments=[]
    ):array {
        $host=trim((string)$c['server']);
        $port=(int)($c['port']?:587);
        $secure=strtolower(trim((string)$c['smtp_secure']));
        $user=trim((string)$c['correo']);
        $pass=secret_decrypt($c['password']??'');
        if(!$host||!filter_var($user,FILTER_VALIDATE_EMAIL)||$pass==='')return ['success'=>false,'message'=>'SMTP configuration is incomplete.'];

        $target=($secure==='ssl'?'ssl://':'tcp://').$host.':'.$port;
        $fp=@stream_socket_client($target,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
        if(!$fp)return ['success'=>false,'message'=>'SMTP connection failed: '.$errstr];
        stream_set_timeout($fp,20);

        try {
            $this->expect($fp,[220]);
            $ehloHost=preg_replace('/[^a-z0-9.-]/i','',(string)($_SERVER['HTTP_HOST']??'localhost'))?:'localhost';
            $this->cmd($fp,'EHLO '.$ehloHost,[250]);
            if($secure==='tls') {
                $this->cmd($fp,'STARTTLS',[220]);
                if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('Unable to enable TLS.');
                $this->cmd($fp,'EHLO '.$ehloHost,[250]);
            }
            $this->cmd($fp,'AUTH LOGIN',[334]);
            $this->cmd($fp,base64_encode($user),[334]);
            $this->cmd($fp,base64_encode($pass),[235]);
            $this->cmd($fp,'MAIL FROM:<'.$user.'>',[250]);
            if($to!=='')$this->cmd($fp,'RCPT TO:<'.$to.'>',[250,251]);
            foreach($bcc as $bccEmail)$this->cmd($fp,'RCPT TO:<'.$bccEmail.'>',[250,251]);
            $this->cmd($fp,'DATA',[354]);

            $boundary='cr_'.bin2hex(random_bytes(12));
            $headers=[
                'From: Castro\'s Ready <'.$user.'>',
                $to!==''?'To: <'.$to.'>':'To: undisclosed-recipients:;',
                'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="'.$boundary.'"'
            ];
            if($replyTo!=='')$headers[]='Reply-To: <'.$replyTo.'>';

            $parts=[];
            $parts[]='--'.$boundary."\r\n"
                .'Content-Type: text/html; charset=UTF-8'."\r\n"
                .'Content-Transfer-Encoding: base64'."\r\n\r\n"
                .chunk_split(base64_encode($html));

            foreach($this->normalizeAttachments($attachments,self::SMTP_ATTACHMENT_LIMIT) as $attachment) {
                $content=@file_get_contents($attachment['path']);
                if($content===false)continue;
                $asciiName=function_exists('iconv')?(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$attachment['name'])?:$attachment['name']):$attachment['name'];
                $safeAscii=preg_replace('/[^A-Za-z0-9._ -]+/','_',$asciiName);
                $safeAscii=trim((string)$safeAscii)?:'attachment';
                $parts[]='--'.$boundary."\r\n"
                    .'Content-Type: '.$attachment['mime'].'; name="'.addcslashes($safeAscii,'"\\').'"'."\r\n"
                    .'Content-Transfer-Encoding: base64'."\r\n"
                    .'Content-Disposition: attachment; filename="'.addcslashes($safeAscii,'"\\').'"; filename*=UTF-8\'\''.rawurlencode($attachment['name'])."\r\n\r\n"
                    .chunk_split(base64_encode($content));
            }
            $parts[]='--'.$boundary.'--'."\r\n";

            $body=implode("\r\n",$headers)."\r\n\r\n".implode("\r\n",$parts)."\r\n.";
            fwrite($fp,$body."\r\n");
            $this->expect($fp,[250]);
            $this->cmd($fp,'QUIT',[221]);
            fclose($fp);
            return ['success'=>true,'message'=>'Email sent with SMTP.'];
        } catch(Throwable $e) {
            @fwrite($fp,"QUIT\r\n");
            @fclose($fp);
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    private function cmd($fp,string $cmd,array $codes):void {
        fwrite($fp,$cmd."\r\n");
        $this->expect($fp,$codes);
    }

    private function expect($fp,array $codes):void {
        $resp='';
        while(($line=fgets($fp,515))!==false) {
            $resp.=$line;
            if(strlen($line)<4||$line[3]!=='-')break;
        }
        $code=(int)substr($resp,0,3);
        if(!in_array($code,$codes,true))throw new RuntimeException('SMTP error '.$code.': '.trim($resp));
    }
}
