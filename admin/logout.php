<?php
require __DIR__.'/bootstrap.php';
try{if(is_logged_in()){db()->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE session_hash=?')->execute([session_fingerprint()]);log_activity('logout','Administrator signed out');}}catch(Throwable $e){}
terminate_admin_authentication(true,false);
header('Location: login.php');exit;
