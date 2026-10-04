<?php
declare(strict_types=1);

final class EmailValidator
{
    private const COMMON_DOMAIN_CORRECTIONS = [
        'gmail.con' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cmo' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'yahoo.con' => 'yahoo.com',
        'yaho.com' => 'yahoo.com',
        'icloud.con' => 'icloud.com',
    ];

    private const CLEARLY_FICTITIOUS_ADDRESSES = [
        'alguien@algo.com',
        'prueba@prueba.com',
        'test@test.com',
        'usuario@example.com',
        'correo@correo.com',
    ];

    private const RESERVED_EXAMPLE_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
        'example.edu',
        'example.invalid',
        'test.com',
        'prueba.com',
    ];

    private static array $validationCache = [];

    public static function validate(string $rawEmail): array
    {
        $email = trim($rawEmail);
        $cacheKey = strtolower($email);

        if (isset(self::$validationCache[$cacheKey])) {
            return self::$validationCache[$cacheKey];
        }

        $result = self::validateUncached($email);
        self::$validationCache[$cacheKey] = $result;

        return $result;
    }

    private static function validateUncached(string $email): array
    {
        if ($email === '') {
            return self::invalid($email, 'empty', 'The email address is empty.');
        }

        if (strlen($email) > 254) {
            return self::invalid($email, 'too_long', 'The email address exceeds the maximum allowed length.');
        }

        if (preg_match('/[\s\x00-\x1F\x7F]/', $email)) {
            return self::invalid($email, 'invalid_characters', 'The email address contains spaces or invalid characters.');
        }

        $atPosition = strrpos($email, '@');
        if ($atPosition === false) {
            return self::invalid($email, 'invalid_format', 'The email address format is invalid.');
        }

        $localPart = substr($email, 0, $atPosition);
        $domain = strtolower(substr($email, $atPosition + 1));

        if ($localPart === '' || $domain === '' || strlen($localPart) > 64 || strlen($domain) > 253) {
            return self::invalid($email, 'invalid_format', 'The email address format is invalid.');
        }

        $normalizedEmail = $localPart . '@' . $domain;
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return self::invalid($normalizedEmail, 'invalid_format', 'The email address format is invalid.');
        }

        $suggestedDomain = self::COMMON_DOMAIN_CORRECTIONS[$domain] ?? '';
        if ($suggestedDomain !== '') {
            $suggestion = $localPart . '@' . $suggestedDomain;
            return self::invalid(
                $normalizedEmail,
                'common_domain_typo',
                'The email domain appears to contain a typing error.',
                $suggestion
            );
        }

        if (self::isClearlyFictitious($normalizedEmail, $localPart, $domain)) {
            return self::invalid(
                $normalizedEmail,
                'fictitious_address',
                'The email address appears to be fictitious or intended only as an example.'
            );
        }

        if (self::isDisposableDomain($domain)) {
            return self::invalid(
                $normalizedEmail,
                'disposable_domain',
                'Temporary or disposable email addresses are not accepted.'
            );
        }

        $dns = self::validateDomainDns($domain);
        if ($dns['valid'] === false) {
            return self::invalid(
                $normalizedEmail,
                (string) $dns['reason_code'],
                (string) $dns['reason']
            );
        }

        return [
            'valid' => true,
            'email' => $normalizedEmail,
            'reason_code' => '',
            'reason' => '',
            'suggestion' => '',
            'dns_status' => (string) $dns['dns_status'],
        ];
    }

    private static function isClearlyFictitious(
        string $email,
        string $localPart,
        string $domain
    ): bool {
        $lowerEmail = strtolower($email);
        if (in_array($lowerEmail, self::CLEARLY_FICTITIOUS_ADDRESSES, true)) {
            return true;
        }

        if (in_array($domain, self::RESERVED_EXAMPLE_DOMAINS, true)) {
            return true;
        }

        $domainName = strtolower((string) strtok($domain, '.'));
        $normalizedLocal = strtolower(trim($localPart, '.-_'));

        return $normalizedLocal !== ''
            && $normalizedLocal === $domainName
            && in_array($normalizedLocal, ['test', 'prueba', 'correo', 'usuario'], true);
    }

    private static function isDisposableDomain(string $domain): bool
    {
        static $disposableDomains = null;

        if ($disposableDomains === null) {
            $path = __DIR__ . '/../config/disposable-email-domains.php';
            $configuredDomains = is_file($path) ? require $path : [];
            $configuredDomains = is_array($configuredDomains) ? $configuredDomains : [];
            $disposableDomains = array_fill_keys(
                array_map(
                    static fn ($item): string => strtolower(trim((string) $item)),
                    $configuredDomains
                ),
                true
            );
            unset($disposableDomains['']);
        }

        if (isset($disposableDomains[$domain])) {
            return true;
        }

        foreach ($disposableDomains as $disposableDomain => $_unused) {
            if (str_ends_with($domain, '.' . $disposableDomain)) {
                return true;
            }
        }

        return false;
    }

    private static function validateDomainDns(string $domain): array
    {
        if (!function_exists('dns_get_record')) {
            return [
                'valid' => true,
                'reason_code' => '',
                'reason' => '',
                'dns_status' => 'not_available',
            ];
        }

        $mxRecords = self::dnsRecords($domain, DNS_MX);
        if ($mxRecords === false) {
            return self::dnsUnavailable();
        }

        if ($mxRecords !== []) {
            $validMxRecords = array_filter(
                $mxRecords,
                static function (array $record): bool {
                    $target = strtolower(trim((string) ($record['target'] ?? '')));
                    return $target !== '' && $target !== '.';
                }
            );

            if ($validMxRecords !== []) {
                return [
                    'valid' => true,
                    'reason_code' => '',
                    'reason' => '',
                    'dns_status' => 'mx',
                ];
            }

            return [
                'valid' => false,
                'reason_code' => 'null_mx',
                'reason' => 'The email domain explicitly indicates that it does not accept email.',
                'dns_status' => 'null_mx',
            ];
        }

        $aRecords = self::dnsRecords($domain, DNS_A);
        $aaaaRecords = defined('DNS_AAAA')
            ? self::dnsRecords($domain, DNS_AAAA)
            : [];

        if ($aRecords === false && $aaaaRecords === false) {
            return self::dnsUnavailable();
        }

        if (
            (is_array($aRecords) && $aRecords !== [])
            || (is_array($aaaaRecords) && $aaaaRecords !== [])
        ) {
            return [
                'valid' => true,
                'reason_code' => '',
                'reason' => '',
                'dns_status' => 'address_fallback',
            ];
        }

        return [
            'valid' => false,
            'reason_code' => 'domain_without_mail_dns',
            'reason' => 'The email domain has no MX, A or AAAA records and cannot receive email.',
            'dns_status' => 'missing',
        ];
    }

    private static function dnsRecords(string $domain, int $type): array|false
    {
        set_error_handler(static fn (): bool => true);
        try {
            return dns_get_record($domain, $type);
        } finally {
            restore_error_handler();
        }
    }

    private static function dnsUnavailable(): array
    {
        return [
            'valid' => true,
            'reason_code' => '',
            'reason' => '',
            'dns_status' => 'temporarily_unavailable',
        ];
    }

    private static function invalid(
        string $email,
        string $reasonCode,
        string $reason,
        string $suggestion = ''
    ): array {
        return [
            'valid' => false,
            'email' => $email,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'suggestion' => $suggestion,
            'dns_status' => '',
        ];
    }
}
