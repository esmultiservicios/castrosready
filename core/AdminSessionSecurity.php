<?php
declare(strict_types=1);

const CASTROS_READY_ADMIN_SESSION_NAME = 'castros_ready_admin';

function admin_request_uses_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwardedProtocol = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

    return $forwardedProtocol === 'https';
}

function admin_session_cookie_options(int $expires = 0): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => admin_request_uses_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function configure_admin_php_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', admin_request_uses_https() ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '43200');

    session_name(CASTROS_READY_ADMIN_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => admin_request_uses_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function start_admin_php_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    configure_admin_php_session();
    session_start();
}

function delete_admin_session_cookie(): void
{
    if (headers_sent()) {
        return;
    }

    $options = admin_session_cookie_options(time() - 42000);
    setcookie(CASTROS_READY_ADMIN_SESSION_NAME, '', $options);
    unset($_COOKIE[CASTROS_READY_ADMIN_SESSION_NAME]);
}

function delete_legacy_admin_session_cookie(): void
{
    if (headers_sent() || empty($_COOKIE['PHPSESSID'])) {
        return;
    }

    $options = admin_session_cookie_options(time() - 42000);
    setcookie('PHPSESSID', '', $options);
    unset($_COOKIE['PHPSESSID']);
}
