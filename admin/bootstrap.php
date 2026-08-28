<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/bootstrap.php';
if (!config_ready()) { header('Location: ../install/'); exit; }

function remember_cookie_name(): string { return 'cr_admin_remember'; }
function clear_remember_cookie(): void {
    $name=remember_cookie_name();
    if(!empty($_COOKIE[$name])){
        $parts=explode(':',(string)$_COOKIE[$name],2);
        if(count($parts)===2){try{db()->prepare('DELETE FROM admin_remember_tokens WHERE selector=?')->execute([$parts[0]]);}catch(Throwable $e){}}
    }
    setcookie($name,'',[
        'expires'=>time()-3600,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax'
    ]);
    unset($_COOKIE[$name]);
}
function create_remember_token(int $adminId): void {
    try{
        $selector=bin2hex(random_bytes(9));$validator=bin2hex(random_bytes(32));$hash=hash('sha256',$validator);$expires=time()+60*60*24*30;
        db()->prepare('DELETE FROM admin_remember_tokens WHERE admin_id=? OR expires_at<NOW()')->execute([$adminId]);
        db()->prepare('INSERT INTO admin_remember_tokens(admin_id,selector,token_hash,expires_at) VALUES(?,?,?,?)')->execute([$adminId,$selector,$hash,date('Y-m-d H:i:s',$expires)]);
        $value=$selector.':'.$validator;
        setcookie(remember_cookie_name(),$value,['expires'=>$expires,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
        $_COOKIE[remember_cookie_name()]=$value;
    }catch(Throwable $e){}
}
function try_remember_login(): void {
    if(!empty($_SESSION['cr_admin_id'])||empty($_COOKIE[remember_cookie_name()]))return;
    $parts=explode(':',(string)$_COOKIE[remember_cookie_name()],2);if(count($parts)!==2){clear_remember_cookie();return;}
    [$selector,$validator]=$parts;if(!preg_match('/^[a-f0-9]{18}$/',$selector)||!preg_match('/^[a-f0-9]{64}$/',$validator)){clear_remember_cookie();return;}
    try{$st=db()->prepare('SELECT t.admin_id,t.token_hash,u.username FROM admin_remember_tokens t JOIN admin_users u ON u.id=t.admin_id WHERE t.selector=? AND t.expires_at>NOW() LIMIT 1');$st->execute([$selector]);$row=$st->fetch();if(!$row||!hash_equals((string)$row['token_hash'],hash('sha256',$validator))){clear_remember_cookie();return;}session_regenerate_id(true);$_SESSION['cr_admin_id']=(int)$row['admin_id'];$_SESSION['cr_admin_user']=$row['username'];}catch(Throwable $e){}
}

function is_logged_in(): bool { return !empty($_SESSION['cr_admin_id']); }
function require_login(): void { if (!is_logged_in()) { header('Location: login.php'); exit; } }
function csrf_token(): string { if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf']; }
function verify_csrf(): void { if(!hash_equals($_SESSION['csrf']??'',(string)($_POST['csrf']??''))){http_response_code(419);exit('Invalid session token.');} }
function flash(string $type,string $message):void{$_SESSION['flash']=compact('type','message');}
function take_flash():?array{$f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f;}
function admin_count():int{return (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();}
function current_admin():?array{if(!is_logged_in())return null;$st=db()->prepare('SELECT id,username,full_name,email,avatar_path,created_at FROM admin_users WHERE id=?');$st->execute([(int)$_SESSION['cr_admin_id']]);return $st->fetch()?:null;}
try_remember_login();
function icon(string $name): string {
 $icons=[
 'dashboard'=>'<svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6V11h-6v9Zm0-16v5h6V4h-6Z"/></svg>',
 'edit'=>'<svg viewBox="0 0 24 24"><path d="m4 16.5-.5 4 4-.5L19 8.5 15.5 5 4 16.5Zm12.6-12.6 1.2-1.2a1.4 1.4 0 0 1 2 0l1.5 1.5a1.4 1.4 0 0 1 0 2L20.1 7.4 16.6 3.9Z"/></svg>',
 'tools'=>'<svg viewBox="0 0 24 24"><path d="M21 5.5a5.5 5.5 0 0 1-7.3 5.2L6.3 18.1a2 2 0 1 1-2.8-2.8l7.4-7.4A5.5 5.5 0 0 1 18.5 2l-3.2 3.2 3.5 3.5L22 5.5h-1Z"/></svg>',
 'image'=>'<svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm1 13h14l-4.5-5.5-3.5 4-2.5-3L5 17Zm3-7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
 'pin'=>'<svg viewBox="0 0 24 24"><path d="M12 22s7-6.1 7-13A7 7 0 1 0 5 9c0 6.9 7 13 7 13Zm0-10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg>',
 'bulb'=>'<svg viewBox="0 0 24 24"><path d="M9 21h6v-2H9v2Zm3-19a7 7 0 0 0-4.1 12.7c.7.5 1.1 1.2 1.1 2V17h6v-.3c0-.8.4-1.5 1.1-2A7 7 0 0 0 12 2Z"/></svg>',
 'mail'=>'<svg viewBox="0 0 24 24"><path d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm9 7 8-5H4l8 5Zm0 2.3L3 8.7V17h18V8.7l-9 5.6Z"/></svg>',
 'gear'=>'<svg viewBox="0 0 24 24"><path d="M19.4 13a7.9 7.9 0 0 0 0-2l2-1.5-2-3.4-2.4 1a8.1 8.1 0 0 0-1.7-1L15 3.5h-4l-.3 2.6a8.1 8.1 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.5a7.9 7.9 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8.1 8.1 0 0 0 1.7 1l.3 2.6h4l.3-2.6a8.1 8.1 0 0 0 1.7-1l2.4 1 2-3.4-2-1.5ZM13 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z"/></svg>',
 'api'=>'<svg viewBox="0 0 24 24"><path d="M8 3v4H6a3 3 0 0 0-3 3v4a3 3 0 0 0 3 3h2v4h2v-4h4v4h2v-4h2a3 3 0 0 0 3-3v-4a3 3 0 0 0-3-3h-2V3h-2v4h-4V3H8Zm-2 6h12a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1Z"/></svg>',
 'user'=>'<svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-9 9a9 9 0 0 1 18 0H3Z"/></svg>',
 'eye'=>'<svg viewBox="0 0 24 24"><path d="M12 5C6.5 5 2.3 9.2 1 12c1.3 2.8 5.5 7 11 7s9.7-4.2 11-7c-1.3-2.8-5.5-7-11-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
 ];return '<span class="ui-icon">'.($icons[$name]??$icons['gear']).'</span>';
}
