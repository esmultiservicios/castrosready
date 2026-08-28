<?php
require __DIR__.'/bootstrap.php';
if(admin_count()===0){header('Location: setup.php');exit;}
if(is_logged_in()){header('Location: dashboard.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $u=trim((string)($_POST['username']??'')); $p=(string)($_POST['password']??'');
 $st=db()->prepare('SELECT id,username,password_hash FROM admin_users WHERE username=? LIMIT 1');$st->execute([$u]);$row=$st->fetch();
 if($row && password_verify($p,$row['password_hash'])){session_regenerate_id(true);$_SESSION['cr_admin_id']=(int)$row['id'];$_SESSION['cr_admin_user']=$row['username'];header('Location: dashboard.php');exit;}
 $error='Invalid username or password.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title><link rel="stylesheet" href="admin.css"></head><body><main class="auth-wrap"><div class="auth-card"><img src="../assets/logo.jpg" alt=""><h1>Administrator</h1><p>Manage Castro's Ready website content.</p><?php if(isset($_GET['setup'])):?><div class="alert success">Administrator created. You can log in now.</div><?php endif;?><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button type="submit">Log in</button></form></div></main></body></html>
