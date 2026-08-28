<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/bootstrap.php';

function is_logged_in(): bool { return !empty($_SESSION['cr_admin_id']); }
function require_login(): void { if (!is_logged_in()) { header('Location: login.php'); exit; } }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Invalid session token.'); } }
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type','message'); }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function admin_count(): int { return (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(); }
