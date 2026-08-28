<?php
require __DIR__.'/bootstrap.php';
require_login();

$pdo = db();
$error = '';

$st = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE id=? LIMIT 1');
$st->execute([(int)$_SESSION['cr_admin_id']]);
$currentAdmin = $st->fetch();
if (!$currentAdmin) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');

    if (!password_verify($currentPassword, $currentAdmin['password_hash'])) {
        $error = 'The current password is incorrect.';
    } elseif ($action === 'change_password') {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $newPassword2 = (string)($_POST['new_password2'] ?? '');
        if (strlen($newPassword) < 10) {
            $error = 'The new password must have at least 10 characters.';
        } elseif ($newPassword !== $newPassword2) {
            $error = 'The new passwords do not match.';
        } else {
            $up = $pdo->prepare('UPDATE admin_users SET password_hash=? WHERE id=?');
            $up->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$currentAdmin['id']]);
            session_regenerate_id(true);
            flash('success', 'Administrator password updated.');
            header('Location: account.php');
            exit;
        }
    } elseif ($action === 'reset_admin') {
        $confirmation = trim((string)($_POST['confirmation'] ?? ''));
        if ($confirmation !== 'RESET ADMIN') {
            $error = 'Type RESET ADMIN exactly to confirm the handoff reset.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->exec('DELETE FROM admin_users');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
            }
            session_destroy();
            header('Location: setup.php?reset=1');
            exit;
        }
    }
}

$pageTitle = 'Administrator Access';
$active = 'account';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
  <div><p class="eyebrow">ADMINISTRATOR ACCESS</p><h1>Account & handoff</h1><p class="muted">Manage the current password or prepare the CMS for a new owner without deleting website content.</p></div>
</div>

<?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<div class="settings-grid">
  <section class="panel">
    <div class="panel-heading"><div class="panel-icon">🔐</div><div><h2>Change password</h2><p>Update the password for <strong><?=h($currentAdmin['username'])?></strong>.</p></div></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="action" value="change_password">
      <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
      <label>New password<input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
      <label>Repeat new password<input type="password" name="new_password2" required minlength="10" autocomplete="new-password"></label>
      <button type="submit">Update password</button>
    </form>
  </section>

  <section class="panel danger-panel">
    <div class="panel-heading"><div class="panel-icon danger-icon">↻</div><div><h2>Reset administrator setup</h2><p>Use this when handing the website to the client.</p></div></div>
    <div class="handoff-note">
      <strong>What this reset does</strong>
      <ul>
        <li>Removes all administrator accounts.</li>
        <li>Ends your current admin session.</li>
        <li>Returns the CMS to the first-time administrator setup.</li>
        <li>Keeps all website text, services, gallery images, areas, tips and estimate requests.</li>
      </ul>
    </div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="action" value="reset_admin">
      <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
      <label>Confirmation phrase<input name="confirmation" required placeholder="RESET ADMIN" autocomplete="off"><small>Type <strong>RESET ADMIN</strong> exactly.</small></label>
      <button type="submit" class="button danger">Reset administrator access</button>
    </form>
  </section>
</div>
<?php require __DIR__.'/_footer.php';
