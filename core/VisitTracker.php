<?php
declare(strict_types=1);

final class VisitTracker
{
    private const COOKIE_NAME = 'cr_public_visitor';
    private const COOKIE_LIFETIME = 31536000;

    public static function record(PDO $pdo): void
    {
        if (PHP_SAPI === 'cli' || self::isBot() || self::isAssetRequest()) return;

        $visitorId = self::visitorId();
        if ($visitorId === '') return;

        $visitorHash = hash('sha256', $visitorId);
        $entryPath = substr((string) ($_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
        $referrer = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 700);

        try {
            $statement = $pdo->prepare(
                'INSERT INTO website_visits
                    (visitor_hash, visit_date, first_seen_at, last_seen_at, page_views, entry_path, referrer)
                 VALUES (?, CURDATE(), NOW(), NOW(), 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    last_seen_at=NOW(),
                    page_views=page_views+1'
            );
            $statement->execute([$visitorHash, $entryPath, $referrer]);
        } catch (Throwable $ignored) {
            // Analytics must never interrupt the public website if the update SQL is still pending.
        }
    }

    private static function visitorId(): string
    {
        $existing = trim((string) ($_COOKIE[self::COOKIE_NAME] ?? ''));
        if (preg_match('/^[a-f0-9]{40}$/', $existing)) return $existing;

        try {
            $value = bin2hex(random_bytes(20));
        } catch (Throwable $ignored) {
            return '';
        }

        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $value, [
                'expires' => time() + self::COOKIE_LIFETIME,
                'path' => '/',
                'secure' => self::usesHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[self::COOKIE_NAME] = $value;
        return $value;
    }

    private static function usesHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private static function isBot(): bool
    {
        $userAgent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent === '') return true;
        return (bool) preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|headless|lighthouse/', $userAgent);
    }

    private static function isAssetRequest(): bool
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        return (bool) preg_match('/\.(?:css|js|jpe?g|png|gif|webp|svg|ico|woff2?|mp4|webm)$/i', $path);
    }
}
