<?php
declare(strict_types=1);
require_once __DIR__ . '/../core/AdminSessionSecurity.php';
start_admin_php_session();
delete_legacy_admin_session_cookie();
require_once __DIR__ . '/../config/bootstrap.php';
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
if (!config_ready()) {
    header('Location: ../install/');
    exit;
}

const ADMIN_SESSION_IDLE_TIMEOUT = 3600;
const ADMIN_SESSION_ABSOLUTE_TIMEOUT = 43200;
const ADMIN_REMEMBER_TOKEN_LIFETIME = 2592000;
const ADMIN_TWO_FACTOR_TIMEOUT = 600;

function remember_cookie_name(): string {
    return 'cr_admin_remember';
}

function remembered_identifier_cookie_name(): string {
    return 'cr_admin_login_identifier';
}

function remember_cookie_options(int $expires): array {
    return [
        'expires'=>$expires,
        'path'=>'/',
        'secure'=>admin_request_uses_https(),
        'httponly'=>true,
        'samesite'=>'Lax',
    ];
}

function clear_remember_cookie(bool $revokeDatabase=true): void {
    $name=remember_cookie_name();
    if($revokeDatabase&&!empty($_COOKIE[$name])) {
        $parts=explode(':',(string)$_COOKIE[$name],2);
        if(count($parts)===2) {
            try {
                db()->prepare('DELETE FROM admin_remember_tokens WHERE selector=?')->execute([$parts[0]]);
            } catch(Throwable $e) {
            }
        }
    }
    if(!headers_sent())setcookie($name,'',remember_cookie_options(time()-42000));
    unset($_COOKIE[$name]);
}

function remembered_login_identifier(): string {
    $identifier=trim((string)($_COOKIE[remembered_identifier_cookie_name()]??''));
    if($identifier===''||strlen($identifier)>180||preg_match('/[\x00-\x1F\x7F]/',$identifier))return '';
    return $identifier;
}

function remember_login_identifier(string $identifier): void {
    $identifier=trim($identifier);
    if($identifier===''||strlen($identifier)>180)return;
    $expires=time()+ADMIN_REMEMBER_TOKEN_LIFETIME;
    setcookie(remembered_identifier_cookie_name(),$identifier,remember_cookie_options($expires));
    $_COOKIE[remembered_identifier_cookie_name()]=$identifier;
}

function clear_remembered_login_identifier(): void {
    if(!headers_sent())setcookie(remembered_identifier_cookie_name(),'',remember_cookie_options(time()-42000));
    unset($_COOKIE[remembered_identifier_cookie_name()]);
}

function establish_admin_session(int $adminId,string $username,int $authenticatedAt,?int $rememberExpiresAt=null): void {
    $_SESSION=[];
    session_regenerate_id(true);
    $now=time();
    $_SESSION['cr_admin_id']=$adminId;
    $_SESSION['cr_admin_user']=$username;
    $_SESSION['cr_admin_authenticated_at']=$authenticatedAt;
    $_SESSION['cr_admin_last_activity']=$now;
    $_SESSION['cr_admin_rotated_at']=$now;
    if($rememberExpiresAt!==null)$_SESSION['cr_admin_remember_expires_at']=$rememberExpiresAt;
    else unset($_SESSION['cr_admin_remember_expires_at']);
}

function create_remember_token(int $adminId): void {
    try {
        $selector=bin2hex(random_bytes(9));
        $validator=bin2hex(random_bytes(32));
        $hash=hash('sha256',$validator);
        $expires=time()+ADMIN_REMEMBER_TOKEN_LIFETIME;
        db()->prepare('DELETE FROM admin_remember_tokens WHERE admin_id=? OR expires_at<NOW()')->execute([$adminId]);
        db()->prepare('INSERT INTO admin_remember_tokens(admin_id,selector,token_hash,expires_at) VALUES(?,?,?,?)')->execute([$adminId,$selector,$hash,date('Y-m-d H:i:s',$expires)]);
        $value=$selector.':'.$validator;
        setcookie(remember_cookie_name(),$value,remember_cookie_options($expires));
        $_COOKIE[remember_cookie_name()]=$value;
        $_SESSION['cr_admin_remember_expires_at']=$expires;
    } catch(Throwable $e) {
    }
}

function try_remember_login(): void {
    if(!empty($_SESSION['cr_admin_id'])||empty($_COOKIE[remember_cookie_name()]))return;
    $parts=explode(':',(string)$_COOKIE[remember_cookie_name()],2);
    if(count($parts)!==2) {
        clear_remember_cookie();
        return;
    }
    [$selector,
    $validator]=$parts;
    if(!preg_match('/^[a-f0-9]{18}$/',$selector)||!preg_match('/^[a-f0-9]{64}$/',$validator)) {
        clear_remember_cookie();
        return;
    }
    try {
        $st=db()->prepare('SELECT t.id,t.admin_id,t.token_hash,t.expires_at,t.created_at,u.username FROM admin_remember_tokens t JOIN admin_users u ON u.id=t.admin_id AND u.active=1 WHERE t.selector=? AND t.expires_at>NOW() LIMIT 1');
        $st->execute([$selector]);
        $row=$st->fetch();
        if(!$row||!hash_equals((string)$row['token_hash'],hash('sha256',$validator))) {
            clear_remember_cookie();
            return;
        }
        $authenticatedAt=strtotime((string)$row['created_at'])?:0;
        $rememberExpiresAt=strtotime((string)$row['expires_at'])?:0;
        if($authenticatedAt<=0||$rememberExpiresAt<=time()||(time()-$authenticatedAt)>=ADMIN_SESSION_ABSOLUTE_TIMEOUT) {
            clear_remember_cookie();
            return;
        }

        $newSelector=bin2hex(random_bytes(9));
        $newValidator=bin2hex(random_bytes(32));
        db()->prepare('UPDATE admin_remember_tokens SET selector=?,token_hash=? WHERE id=?')->execute([$newSelector,hash('sha256',$newValidator),(int)$row['id']]);
        $newCookieValue=$newSelector.':'.$newValidator;
        setcookie(remember_cookie_name(),$newCookieValue,remember_cookie_options($rememberExpiresAt));
        $_COOKIE[remember_cookie_name()]=$newCookieValue;
        establish_admin_session((int)$row['admin_id'],(string)$row['username'],$authenticatedAt,$rememberExpiresAt);
    } catch(Throwable $e) {
        clear_remember_cookie(false);
    }
}
function request_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR']??''),0,64);
}
function request_user_agent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);
}
function session_fingerprint(): string {
    return hash('sha256',session_id());
}

function auth_request_expects_json(): bool {
    $requestedWith=strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''));
    $accept=strtolower((string)($_SERVER['HTTP_ACCEPT']??''));
    $fetchDestination=strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST']??''));

    return $requestedWith==='xmlhttprequest'
        || str_contains($accept,'application/json')
        || $fetchDestination==='empty';
}

function revoke_current_admin_session(): void {
    if(session_status()!==PHP_SESSION_ACTIVE||session_id()==='')return;
    try {
        db()->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE session_hash=? AND revoked_at IS NULL')->execute([session_fingerprint()]);
    } catch(Throwable $e) {
    }
}

function terminate_admin_authentication(bool $revokeRemember=true,bool $restartSession=false): void {
    $adminId=(int)($_SESSION['cr_admin_id']??0);
    revoke_current_admin_session();

    if($revokeRemember&&$adminId>0) {
        try {
            db()->prepare('DELETE FROM admin_remember_tokens WHERE admin_id=?')->execute([$adminId]);
        } catch(Throwable $e) {
        }
    }

    clear_remember_cookie($revokeRemember&&$adminId===0);
    $_SESSION=[];
    delete_admin_session_cookie();
    if(session_status()===PHP_SESSION_ACTIVE)session_destroy();

    if($restartSession)start_admin_php_session();
}

function respond_to_authentication_failure(string $reason='expired'): void {
    terminate_admin_authentication(true,false);

    if(auth_request_expects_json()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'=>false,
            'error'=>'authentication_required',
            'reason'=>$reason,
            'message'=>'Your administrator session expired for security. Please sign in again.',
            'login_url'=>'login.php?expired=1',
        ],JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Location: login.php?'.($reason==='revoked'?'revoked=1':'expired=1'));
    exit;
}

function sync_admin_session(): void {
    if(empty($_SESSION['cr_admin_id'])||session_status()!==PHP_SESSION_ACTIVE)return;

    $now=time();
    $authenticatedAt=(int)($_SESSION['cr_admin_authenticated_at']??0);
    $lastActivity=(int)($_SESSION['cr_admin_last_activity']??0);

    if($authenticatedAt<=0||$lastActivity<=0)respond_to_authentication_failure('expired');
    if(($now-$lastActivity)>=ADMIN_SESSION_IDLE_TIMEOUT)respond_to_authentication_failure('idle');
    if(($now-$authenticatedAt)>=ADMIN_SESSION_ABSOLUTE_TIMEOUT)respond_to_authentication_failure('absolute');

    try {
        $hash=session_fingerprint();
        $st=db()->prepare('SELECT admin_id,revoked_at,(last_seen_at < DATE_SUB(NOW(),INTERVAL 60 MINUTE)) AS idle_expired FROM admin_sessions WHERE session_hash=? LIMIT 1');
        $st->execute([$hash]);
        $persisted=$st->fetch();
        if($persisted) {
            if(!empty($persisted['revoked_at'])||(int)$persisted['admin_id']!==(int)$_SESSION['cr_admin_id']) {
                respond_to_authentication_failure('revoked');
            }
            if((int)($persisted['idle_expired']??0)===1) {
                respond_to_authentication_failure('idle');
            }
        }
        db()->prepare('INSERT INTO admin_sessions(admin_id,session_hash,ip_address,user_agent,last_seen_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE admin_id=VALUES(admin_id),ip_address=VALUES(ip_address),user_agent=VALUES(user_agent),last_seen_at=NOW()')->execute([(int)$_SESSION['cr_admin_id'],$hash,request_ip(),request_user_agent()]);
    } catch(Throwable $e) {
    }
    $_SESSION['cr_admin_last_activity']=$now;
}
function record_login_event(?int $adminId,string $username,bool $success): void {
    try {
        db()->prepare('INSERT INTO admin_login_events(admin_id,username_attempt,success,ip_address,user_agent) VALUES(?,?,?,?,?)')->execute([$adminId,$username,$success?1:0,request_ip(),request_user_agent()]);
    } catch(Throwable $e) {
    }
}
function is_logged_in(): bool {
    return !empty($_SESSION['cr_admin_id']);
}
function require_login(): void {
    if(!is_logged_in()) {
        if(auth_request_expects_json()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'=>false,
                'error'=>'authentication_required',
                'message'=>'Authentication is required.',
                'login_url'=>'login.php',
            ],JSON_UNESCAPED_SLASHES);
            exit;
        }
        header('Location: login.php');
        exit;
    }
    try {
        $st=db()->prepare('SELECT active FROM admin_users WHERE id=?');
        $st->execute([(int)$_SESSION['cr_admin_id']]);
        if((int)$st->fetchColumn()!==1) {
            terminate_admin_authentication(true,false);
            header('Location: login.php?disabled=1');
            exit;
        }
    } catch(Throwable $e) {
    }
}
function csrf_token(): string {
    if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if(!hash_equals($_SESSION['csrf']??'',(string)($_POST['csrf']??''))) {
        http_response_code(419);
        exit('Invalid session token.');
    }
}
function flash(string $type,string $message):void {
    $_SESSION['flash']=compact('type','message');
}
function take_flash():?array {
    $f=$_SESSION['flash']??null;
    unset($_SESSION['flash']);
    return $f;
}
function admin_count():int {
    return (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
}
function current_admin():?array {
    if(!is_logged_in())return null;
    $st=db()->prepare('SELECT u.id,u.username,u.full_name,u.email,u.avatar_path,u.role_id,u.active,u.last_login_at,u.two_factor_enabled,u.created_at,r.role_key,r.role_name FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.id=?');
    $st->execute([(int)$_SESSION['cr_admin_id']]);
    return $st->fetch()?:null;
}
function user_can(string $permission,?int $adminId=null): bool {
    $adminId=$adminId??(int)($_SESSION['cr_admin_id']??0);
    if(!$adminId)return false;
    try {
        $st=db()->prepare('SELECT r.role_key FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1');
        $st->execute([$adminId]);
        $role=(string)$st->fetchColumn();
        if($role==='owner')return true;
        $st=db()->prepare('SELECT COUNT(*) FROM admin_users u JOIN admin_role_permissions rp ON rp.role_id=u.role_id JOIN admin_permissions p ON p.id=rp.permission_id WHERE u.id=? AND u.active=1 AND p.permission_key=?');
        $st->execute([$adminId,$permission]);
        return (int)$st->fetchColumn()>0;
    } catch(Throwable $e) {
        return false;
    }
}
function require_permission(string $permission): void {
    require_login();
    if(!user_can($permission)) {
        http_response_code(403);
        $pageTitle='Access denied';
        $active='';
        require __DIR__.'/_header.php';
        echo '<div class="empty-state"><strong>Access denied</strong><p>Your role does not have permission to use this area.</p><a class="button" href="dashboard.php">Back to dashboard</a></div>';
        require __DIR__.'/_footer.php';
        exit;
    }
}
function role_is_owner(?array $admin=null): bool {
    $admin=$admin??current_admin();
    return ($admin['role_key']??'')==='owner';
}
function unread_notification_count(?int $adminId=null): int {
    $adminId=$adminId??(int)($_SESSION['cr_admin_id']??0);
    if(!$adminId)return 0;
    try {
        $st=db()->prepare('SELECT COUNT(*) FROM admin_notifications n LEFT JOIN admin_notification_reads r ON r.notification_id=n.id AND r.admin_id=? WHERE r.notification_id IS NULL');
        $st->execute([$adminId]);
        return (int)$st->fetchColumn();
    } catch(Throwable $e) {
        return 0;
    }
}
function recent_notifications(int $limit=6,?int $adminId=null): array {
    $adminId=$adminId??(int)($_SESSION['cr_admin_id']??0);
    try {
        $st=db()->prepare('SELECT n.*,CASE WHEN r.notification_id IS NULL THEN 0 ELSE 1 END AS read_by_me FROM admin_notifications n LEFT JOIN admin_notification_reads r ON r.notification_id=n.id AND r.admin_id=? ORDER BY n.id DESC LIMIT '.max(1,min(20,$limit)));
        $st->execute([$adminId]);
        return $st->fetchAll();
    } catch(Throwable $e) {
        return [];
    }
}
function mark_notification_read(int $notificationId,?int $adminId=null): void {
    $adminId=$adminId??(int)($_SESSION['cr_admin_id']??0);
    if(!$adminId||!$notificationId)return;
    try {
        db()->prepare('INSERT IGNORE INTO admin_notification_reads(notification_id,admin_id) VALUES(?,?)')->execute([$notificationId,$adminId]);
    } catch(Throwable $e) {
    }
}
function mark_all_notifications_read(?int $adminId=null): void {
    $adminId=$adminId??(int)($_SESSION['cr_admin_id']??0);
    if(!$adminId)return;
    try {
        db()->prepare('INSERT IGNORE INTO admin_notification_reads(notification_id,admin_id) SELECT id,? FROM admin_notifications')->execute([$adminId]);
    } catch(Throwable $e) {
    }
}
function fresh_login_requested(): bool {
    $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
    return $script==='login.php'
        &&($_SERVER['REQUEST_METHOD']??'GET')==='GET'
        &&($_GET['fresh']??'')==='1';
}

if(fresh_login_requested()) {
    terminate_admin_authentication(true,true);
} else {
    try_remember_login();
    sync_admin_session();
}
function base32_encode_raw(string $data): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits='';
    foreach(str_split($data) as $ch)$bits.=str_pad(decbin(ord($ch)),8,'0',STR_PAD_LEFT);
    $out='';
    foreach(str_split($bits,5) as $chunk) {
        if(strlen($chunk)<5)$chunk=str_pad($chunk,5,'0');
        $out.=$alphabet[bindec($chunk)];
    }
    return $out;
}
function base32_decode_raw(string $value): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $value=strtoupper(preg_replace('/[^A-Z2-7]/','',$value));
    $bits='';
    foreach(str_split($value) as $ch) {
        $pos=strpos($alphabet,$ch);
        if($pos===false)continue;
        $bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT);
    }
    $out='';
    foreach(str_split($bits,8) as $chunk) {
        if(strlen($chunk)<8)break;
        $out.=chr(bindec($chunk));
    }
    return $out;
}
function new_totp_secret(): string {
    return base32_encode_raw(random_bytes(20));
}
function totp_code(string $secret,?int $time=null): string {
    $time=$time??time();
    $counter=intdiv($time,30);
    $bin=pack('N*',0).pack('N*',$counter);
    $hash=hash_hmac('sha1',$bin,base32_decode_raw($secret),true);
    $offset=ord($hash[19])&0xf;
    $value=((ord($hash[$offset])&0x7f)<<24)|((ord($hash[$offset+1])&0xff)<<16)|((ord($hash[$offset+2])&0xff)<<8)|(ord($hash[$offset+3])&0xff);
    return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
}
function verify_totp(string $secret,string $code): bool {
    $code=preg_replace('/\D/','',$code);
    if(strlen($code)!==6)return false;
    for($w=-1;$w<=1;$w++)if(hash_equals(totp_code($secret,time()+$w*30),$code))return true;
    return false;
}
function icon(string $name): string {
    $icons=[ 'dashboard'=>'<svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6V11h-6v9Zm0-16v5h6V4h-6Z"/></svg>',
    'edit'=>'<svg viewBox="0 0 24 24"><path d="m4 16.5-.5 4 4-.5L19 8.5 15.5 5 4 16.5Zm12.6-12.6 1.2-1.2a1.4 1.4 0 0 1 2 0l1.5 1.5a1.4 1.4 0 0 1 0 2L20.1 7.4 16.6 3.9Z"/></svg>',
    'tools'=>'<svg viewBox="0 0 24 24"><path d="M21 5.5a5.5 5.5 0 0 1-7.3 5.2L6.3 18.1a2 2 0 1 1-2.8-2.8l7.4-7.4A5.5 5.5 0 0 1 18.5 2l-3.2 3.2 3.5 3.5L22 5.5h-1Z"/></svg>',
    'image'=>'<svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm1 13h14l-4.5-5.5-3.5 4-2.5-3L5 17Zm3-7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
    'pin'=>'<svg viewBox="0 0 24 24"><path d="M12 22s7-6.1 7-13A7 7 0 1 0 5 9c0 6.9 7 13 7 13Zm0-10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>',
    'bulb'=>'<svg viewBox="0 0 24 24"><path d="M9 21h6v-2H9v2Zm3-19a7 7 0 0 0-4.1 12.7c.7.5 1.1 1.2 1.1 2V17h6v-.3c0-.8.4-1.5 1.1-2A7 7 0 0 0 12 2Z"/></svg>',
    'mail'=>'<svg viewBox="0 0 24 24"><path d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm9 7 8-5H4l8 5Zm0 2.3L3 8.7V17h18V8.7l-9 5.6Z"/></svg>',
    'gear'=>'<svg viewBox="0 0 24 24"><path d="M19.4 13a7.9 7.9 0 0 0 0-2l2-1.5-2-3.4-2.4 1a8.1 8.1 0 0 0-1.7-1L15 3.5h-4l-.3 2.6a8.1 8.1 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.5a7.9 7.9 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8.1 8.1 0 0 0 1.7 1l.3 2.6h4l.3-2.6a8.1 8.1 0 0 0 1.7-1l2.4 1 2-3.4-2-1.5ZM13 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z"/></svg>',
    'api'=>'<svg viewBox="0 0 24 24"><path d="M8 3v4H6a3 3 0 0 0-3 3v4a3 3 0 0 0 3 3h2v4h2v-4h4v4h2v-4h2a3 3 0 0 0 3-3v-4a3 3 0 0 0-3-3h-2V3h-2v4h-4V3H8Zm-2 6h12a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1Z"/></svg>',
    'user'=>'<svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-9 9a9 9 0 0 1 18 0H3Z"/></svg>',
    'bell'=>'<svg viewBox="0 0 24 24"><path d="M12 22a2.7 2.7 0 0 0 2.6-2H9.4A2.7 2.7 0 0 0 12 22Zm8-5-2-2.3V10a6 6 0 0 0-5-5.9V3a1 1 0 0 0-2 0v1.1A6 6 0 0 0 6 10v4.7L4 17v1h16v-1Z"/></svg>',
    'users'=>'<svg viewBox="0 0 24 24"><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2 21a7 7 0 0 1 14 0H2Zm14.5 0c0-2.2-.7-4.2-2-5.8A6 6 0 0 1 22 21h-5.5Z"/></svg>',
    'shield'=>'<svg viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5.1 3.4 9.8 8 11 4.6-1.2 8-5.9 8-11V5l-8-3Zm0 4 4 1.5V11c0 3.2-1.9 6.4-4 7.5-2.1-1.1-4-4.3-4-7.5V7.5L12 6Z"/></svg>',
    'approval'=>'<svg viewBox="0 0 24 24"><path d="M4 3h11l5 5v13H4V3Zm10 2v4h4l-4-4Zm-4 12 7-7-1.4-1.4L10 14.2 7.4 11.6 6 13l4 4Z"/></svg>',
    'eye'=>'<svg viewBox="0 0 24 24"><path d="M12 5C6.5 5 2.3 9.2 1 12c1.3 2.8 5.5 7 11 7s9.7-4.2 11-7c-1.3-2.8-5.5-7-11-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
    ];
    return '<span class="ui-icon">'.($icons[$name]??$icons['gear']).'</span>';
}
