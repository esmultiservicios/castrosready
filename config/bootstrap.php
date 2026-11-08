<?php
declare(strict_types=1);
const ROOT_DIR = __DIR__ . '/..';
const UPLOAD_DIR = ROOT_DIR . '/uploads';

function versioned_asset(string $url, string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $fullPath = ROOT_DIR . '/' . $relativePath;
    if (!is_file($fullPath)) return $url;

    static $versions = [];
    if (!isset($versions[$fullPath])) {
        $hash = @sha1_file($fullPath);
        $versions[$fullPath] = $hash !== false
            ? substr($hash, 0, 12)
            : (string) (@filemtime($fullPath) ?: 1);
    }

    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . 'v=' . rawurlencode($versions[$fullPath]);
}
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function config_ready(): bool {
    return is_file(__DIR__ . '/database.php');
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $configFile = __DIR__ . '/database.php';
    if (!is_file($configFile)) throw new RuntimeException('Database is not configured. Open /install/ to start the setup wizard.');
    $cfg = require $configFile;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['dbname'], $cfg['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ]);
    return $pdo;
}
function site_content(): array {
    $rows=db()->query('SELECT content_key,content_value FROM site_content')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['content_key']]=$r['content_value'];
    return $o;
}
function settings(): array {
    $rows=db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['setting_key']]=$r['setting_value'];
    return $o;
}
function setting(string $key, string $default=''): string {
    static $cache=null;
    if($cache===null)$cache=settings();
    return isset($cache[$key])?(string)$cache[$key]:$default;
}
function save_setting(string $key, string $value): void {
    $st=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $st->execute([$key,$value]);
}
function server_runtime_requirements(): array {
    return [
        [
            'key'=>'php_version',
            'name'=>'PHP 8.0 or newer',
            'available'=>version_compare(PHP_VERSION,'8.0.0','>='),
            'purpose'=>'Required by the CMS language features.',
            'install'=>'Select PHP 8.2 or PHP 8.3 in MultiPHP Manager.',
            'required'=>true,
        ],
        [
            'key'=>'pdo',
            'name'=>'PDO',
            'available'=>extension_loaded('pdo')&&class_exists('PDO'),
            'purpose'=>'Provides secure database connections.',
            'install'=>'Search for pdo in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'pdo_mysql',
            'name'=>'PDO MySQL',
            'available'=>extension_loaded('pdo_mysql'),
            'purpose'=>'Connects the CMS to its MySQL or MariaDB database.',
            'install'=>'Search for pdo_mysql in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'openssl',
            'name'=>'OpenSSL',
            'available'=>extension_loaded('openssl')&&function_exists('openssl_encrypt'),
            'purpose'=>'Encrypts stored email credentials and enables secure SMTP connections.',
            'install'=>'Search for openssl in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'curl',
            'name'=>'cURL',
            'available'=>extension_loaded('curl')&&function_exists('curl_init'),
            'purpose'=>'Sends email through Microsoft Graph and connects optional validation services.',
            'install'=>'Search for curl in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'dns',
            'name'=>'DNS resolver',
            'available'=>function_exists('dns_get_record'),
            'purpose'=>'Checks whether public form email domains have valid MX records.',
            'install'=>'Ask the hosting provider to enable the PHP dns_get_record function.',
            'required'=>true,
        ],
        [
            'key'=>'fileinfo',
            'name'=>'Upload type validation',
            'available'=>(extension_loaded('fileinfo')&&class_exists('finfo')&&defined('FILEINFO_MIME_TYPE'))||function_exists('file_get_contents'),
            'purpose'=>'Validates uploaded images, videos and documents using Fileinfo or secure file signatures.',
            'install'=>'Enable file_get_contents or install fileinfo in WHM → EasyApache 4 → PHP Extensions.',
            'detail'=>(extension_loaded('fileinfo')&&class_exists('finfo')&&defined('FILEINFO_MIME_TYPE'))
                ? 'Native PHP Fileinfo is active.'
                : 'Secure signature fallback is active; Fileinfo remains optional.',
            'required'=>true,
        ],
        [
            'key'=>'zip',
            'name'=>'ZIP / ZipArchive',
            'available'=>extension_loaded('zip')&&class_exists('ZipArchive'),
            'purpose'=>'Creates and restores complete CMS backups.',
            'install'=>'Search for zip in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'json',
            'name'=>'JSON',
            'available'=>extension_loaded('json')&&function_exists('json_encode')&&function_exists('json_decode'),
            'purpose'=>'Processes form responses, backups and CMS metadata.',
            'install'=>'Search for json in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'session',
            'name'=>'Session',
            'available'=>extension_loaded('session')&&function_exists('session_start'),
            'purpose'=>'Maintains secure administrator sessions.',
            'install'=>'Search for session in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'filter',
            'name'=>'Filter',
            'available'=>extension_loaded('filter')&&function_exists('filter_var'),
            'purpose'=>'Validates email addresses and submitted information.',
            'install'=>'Search for filter in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
        [
            'key'=>'hash',
            'name'=>'Hash',
            'available'=>extension_loaded('hash')&&function_exists('hash_hmac'),
            'purpose'=>'Protects sessions, recovery tokens and two-factor authentication.',
            'install'=>'Search for hash in WHM → EasyApache 4 → PHP Extensions.',
            'required'=>true,
        ],
    ];
}
function fileinfo_available(): bool {
    return extension_loaded('fileinfo')&&class_exists('finfo')&&defined('FILEINFO_MIME_TYPE');
}
function upload_signature_mime_type(string $path): string {
    if(!is_file($path)||!is_readable($path)||!function_exists('file_get_contents'))return '';
    $head=@file_get_contents($path,false,null,0,64);
    if(!is_string($head)||$head==='')return '';
    if(str_starts_with($head,"\xFF\xD8\xFF"))return 'image/jpeg';
    if(str_starts_with($head,"\x89PNG\x0D\x0A\x1A\x0A"))return 'image/png';
    if(strlen($head)>=12&&substr($head,0,4)==='RIFF'&&substr($head,8,4)==='WEBP')return 'image/webp';
    if(str_starts_with($head,"\x1A\x45\xDF\xA3"))return 'video/webm';
    if(strlen($head)>=12&&substr($head,4,4)==='ftyp') {
        return substr($head,8,4)==='qt  '?'video/quicktime':'video/mp4';
    }
    if(str_starts_with($head,'%PDF-'))return 'application/pdf';
    return '';
}
function detect_file_mime_type(string $path): string {
    $allowed=['image/jpeg','image/png','image/webp','video/mp4','video/webm','video/quicktime','application/pdf'];
    $signature=upload_signature_mime_type($path);
    if($signature!=='')return $signature;
    if(fileinfo_available()) {
        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=(string)($finfo->file($path)?:'');
        if(in_array($mime,$allowed,true))return $mime;
    }
    throw new RuntimeException('The real file type could not be verified. Use a valid JPG, PNG, WEBP, MP4, WEBM, MOV or PDF file.');
}
function upload_error_message(array $file,string $label='File'): string {
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    return match($error) {
        UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>"{$label} exceeds the upload limit configured on this server.",
        UPLOAD_ERR_PARTIAL=>"{$label} was only partially uploaded. Please try again.",
        UPLOAD_ERR_NO_FILE=>"Choose a {$label} to upload.",
        UPLOAD_ERR_NO_TMP_DIR=>"The server upload folder is unavailable.",
        UPLOAD_ERR_CANT_WRITE=>"The server could not write the uploaded {$label}.",
        UPLOAD_ERR_EXTENSION=>"A server extension stopped the {$label} upload.",
        default=>"{$label} upload failed.",
    };
}
function php_ini_size_bytes(string $value): int {
    $value=trim($value);
    if($value===''||$value==='-1')return PHP_INT_MAX;
    $unit=strtolower(substr($value,-1));
    $number=(float)$value;
    return (int)round($number*match($unit){'g'=>1024**3,'m'=>1024**2,'k'=>1024,default=>1});
}
function effective_upload_limit_mb(int $applicationLimitMb=250): int {
    $limits=[$applicationLimitMb*1024*1024];
    foreach(['upload_max_filesize','post_max_size'] as $key) {
        $bytes=php_ini_size_bytes((string)ini_get($key));
        if($bytes>0&&$bytes<PHP_INT_MAX)$limits[]=$bytes;
    }
    return max(1,(int)floor(min($limits)/1024/1024));
}
function upload_image(array $file, string $subdir, string $prefix, int $maxMb = 8): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException(upload_error_message($file,'image'));
    if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException("Image exceeds {$maxMb} MB.");
    $mime=detect_file_mime_type((string)$file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg',
    'image/png'=>'png',
    'image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    $dir=UPLOAD_DIR.'/'.trim($subdir,'/');
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Could not create upload directory.');
    $name=preg_replace('/[^a-z0-9_-]+/i','-',$prefix).'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    $target=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Could not save uploaded image.');
    return 'uploads/'.trim($subdir,'/').'/'.$name;
}
function upload_media_file(array $file, string $subdir='media', string $prefix='media', int $maxMb = 60): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException(upload_error_message($file,'media file'));
    if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException("Media file exceeds {$maxMb} MB.");
    $mime=detect_file_mime_type((string)$file['tmp_name']);
    $allowed=[ 'image/jpeg'=>'jpg',
    'image/png'=>'png',
    'image/webp'=>'webp',
    'video/mp4'=>'mp4',
    'video/webm'=>'webm',
    'video/quicktime'=>'mov',
    'application/pdf'=>'pdf' ];
    if(!isset($allowed[$mime])) throw new RuntimeException('Allowed media: JPG, PNG, WEBP, MP4, WEBM, MOV and PDF.');
    $dir=UPLOAD_DIR.'/'.trim($subdir,'/');
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Could not create upload directory.');
    $name=preg_replace('/[^a-z0-9_-]+/i','-',$prefix).'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    $target=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Could not save uploaded media.');
    return 'uploads/'.trim($subdir,'/').'/'.$name;
}
function normalized_files(string $field): array {
    if(empty($_FILES[$field])) return [];
    $src=$_FILES[$field];
    $out=[];
    if(!is_array($src['name'])) return [$src];
    foreach($src['name'] as $i=>$name) {
        if(($src['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
        $out[]=['name'=>$name,
        'type'=>$src['type'][$i]??'',
        'tmp_name'=>$src['tmp_name'][$i]??'',
        'error'=>$src['error'][$i]??UPLOAD_ERR_NO_FILE,
        'size'=>$src['size'][$i]??0];
    }
    return $out;
}
function app_key(): string {
    $file=__DIR__.'/app.key';
    if(!is_file($file)) {
        $key=random_bytes(32);
        if(@file_put_contents($file,base64_encode($key),LOCK_EX)===false) throw new RuntimeException('Unable to create config/app.key. Check write permissions.');
        @chmod($file,0600);
        return $key;
    }
    $raw=base64_decode(trim((string)file_get_contents($file)),true);
    if($raw===false||strlen($raw)<32) throw new RuntimeException('Invalid application encryption key.');
    return substr($raw,0,32);
}
function secret_encrypt(?string $value): string {
    $value=trim((string)$value);
    if($value==='')return '';
    $iv=random_bytes(12);
    $tag='';
    $cipher=openssl_encrypt($value,'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false)throw new RuntimeException('Unable to encrypt secret.');
    return 'v1:'.base64_encode($iv.$tag.$cipher);
}
function secret_decrypt(?string $value): string {
    $value=(string)$value;
    if($value===''||!str_starts_with($value,'v1:'))return $value;
    $raw=base64_decode(substr($value,3),true);
    if($raw===false||strlen($raw)<29)return '';
    $iv=substr($raw,0,12);
    $tag=substr($raw,12,16);
    $cipher=substr($raw,28);
    $plain=openssl_decrypt($cipher,'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,$iv,$tag);
    return $plain===false?'':$plain;
}
function validate_email_configuration(array $config): void {
    $method=strtoupper(trim((string)($config['metodo_envio']??'SMTP')));
    $sender=trim((string)($config['correo']??''));
    if(!filter_var($sender,FILTER_VALIDATE_EMAIL))throw new RuntimeException('The sender email is not valid.');
    if($method==='GRAPH') {
        $graphUser=trim((string)($config['graph_user']??$sender));
        if(trim((string)($config['tenant_id']??''))===''||trim((string)($config['client_id']??''))===''||secret_decrypt($config['client_secret']??'')===''||!filter_var($graphUser,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Microsoft Graph configuration is incomplete.');
        return;
    }
    $port=(int)($config['port']??0);
    $secure=strtolower(trim((string)($config['smtp_secure']??'')));
    if(trim((string)($config['server']??''))===''||secret_decrypt($config['password']??'')===''||$port<1||$port>65535||!in_array($secure,['tls','ssl'],true))throw new RuntimeException('SMTP configuration is incomplete.');
}
function draft_content(): array {
    $rows=db()->query('SELECT content_key,content_value FROM content_drafts')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['content_key']]=$r['content_value'];
    return $o;
}
function site_sections(): array {
    try {
        $rows=db()->query('SELECT section_key,label,sort_order,active FROM site_sections ORDER BY sort_order,section_key')->fetchAll();
    } catch(Throwable $e) {
        return [];
    }
    $o=[];
    foreach($rows as $r)$o[$r['section_key']]=$r;
    return $o;
}
function section_enabled(string $key): bool {
    static $s=null;
    if($s===null)$s=site_sections();
    return !isset($s[$key])||(int)$s[$key]['active']===1;
}
function section_order(string $key,int $default=999): int {
    static $s=null;
    if($s===null)$s=site_sections();
    return isset($s[$key])?(int)$s[$key]['sort_order']:$default;
}
function gallery_default_image(string $title): string {
    static $images=[
        'Before & After'=>'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1100&q=85',
        'Interior Painting'=>'https://images.unsplash.com/photo-1562259949-e8e7689d7828?auto=format&fit=crop&w=900&q=85',
        'Exterior Painting'=>'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85',
        'Remodeling'=>'https://images.unsplash.com/photo-1581858726788-75bc0f6a952d?auto=format&fit=crop&w=900&q=85',
        'Flooring'=>'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85',
        'Drywall Repair'=>'https://images.unsplash.com/photo-1590725121839-892b458a74fe?auto=format&fit=crop&w=900&q=85',
        'Decks'=>'https://images.unsplash.com/photo-1590725121839-892b458a74fe?auto=format&fit=crop&w=900&q=85',
        'Concrete'=>'https://images.unsplash.com/photo-1590725121839-892b458a74fe?auto=format&fit=crop&w=900&q=85',
    ];
    return $images[trim($title)]??'';
}
function log_activity(string $type,string $description,array $metadata=[]): void {
    try {
        $adminId=!empty($_SESSION['cr_admin_id'])?(int)$_SESSION['cr_admin_id']:null;
        $st=db()->prepare('INSERT INTO activity_log(admin_id,action_type,description,metadata_json) VALUES(?,?,?,?)');
        $st->execute([$adminId,$type,$description,$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE):null]);
    } catch(Throwable $e) {
    }
}
function admin_notify(string $type,string $title,string $message,string $url=''): void {
    try {
        $st=db()->prepare('INSERT INTO admin_notifications(notification_type,title,message,action_url) VALUES(?,?,?,?)');
        $st->execute([$type,$title,$message,$url?:null]);
    } catch(Throwable $e) {
    }
}
function media_add(string $path,string $title='',int $sortOrder=0): void {
    try {
        $full=ROOT_DIR.'/'.$path;
        $mime=is_file($full)?detect_file_mime_type($full):'';
        $size=is_file($full)?filesize($full):0;
        $adminId=!empty($_SESSION['cr_admin_id'])?(int)$_SESSION['cr_admin_id']:null;
        try {
            $st=db()->prepare('INSERT INTO media_library(title,file_path,mime_type,file_size,sort_order,uploaded_by) VALUES(?,?,?,?,?,?)');
            $st->execute([$title,$path,$mime,(int)$size,$sortOrder,$adminId]);
        } catch(Throwable $schemaFallback) {
            $st=db()->prepare('INSERT INTO media_library(title,file_path,mime_type,file_size,uploaded_by) VALUES(?,?,?,?,?)');
            $st->execute([$title,$path,$mime,(int)$size,$adminId]);
        }
    } catch(Throwable $e) {
    }
}
