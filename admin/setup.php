<?php
require __DIR__.'/bootstrap.php';
if (admin_count()>0) { header('Location: login.php'); exit; }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $u=trim((string)($_POST['username']??'')); $p=(string)($_POST['password']??''); $p2=(string)($_POST['password2']??'');
    if(strlen($u)<4) $error='Username must have at least 4 characters.';
    elseif(strlen($p)<10) $error='Password must have at least 10 characters.';
    elseif($p!==$p2) $error='Passwords do not match.';
    else { $st=db()->prepare('INSERT INTO admin_users(username,password_hash) VALUES(?,?)'); $st->execute([$u,password_hash($p,PASSWORD_DEFAULT)]); header('Location: login.php?setup=1'); exit; }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Setup</title><link rel="stylesheet" href="admin.css"></head><body><main class="auth-wrap"><div class="auth-card"><img src="../assets/logo.jpg" alt=""><h1>Create administrator</h1><p>First-time setup for Castro's Ready.</p><?php if(isset($_GET['reset'])):?><div class="alert success">Administrator access was reset successfully. Create the client's new administrator account below.</div><?php endif;?><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="new-password"></label><label>Repeat password<input type="password" name="password2" required autocomplete="new-password"></label><button type="submit">Create administrator</button></form></div></main></body></html>
