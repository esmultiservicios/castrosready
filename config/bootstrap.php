<?php
declare(strict_types=1);

const ROOT_DIR = __DIR__ . '/..';
const UPLOAD_DIR = ROOT_DIR . '/uploads';

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $configFile = __DIR__ . '/database.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Database is not configured. Copy config/database.example.php to config/database.php and enter your MySQL credentials.');
    }
    $cfg = require $configFile;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['dbname'], $cfg['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function site_content(): array {
    $rows = db()->query('SELECT content_key, content_value FROM site_content')->fetchAll();
    $out = [];
    foreach ($rows as $row) $out[$row['content_key']] = $row['content_value'];
    return $out;
}

function settings(): array {
    $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $out = [];
    foreach ($rows as $row) $out[$row['setting_key']] = $row['setting_value'];
    return $out;
}

function upload_image(array $file, string $subdir, string $prefix, int $maxMb = 8): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException("Image exceeds {$maxMb} MB.");
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    $dir = UPLOAD_DIR . '/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Could not create upload directory.');
    $name = preg_replace('/[^a-z0-9_-]+/i', '-', $prefix) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('Could not save uploaded image.');
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}
