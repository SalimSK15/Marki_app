<?php

declare(strict_types=1);

final class PublicRegistrationSecurity
{
    public static function generatePublicId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function tokenFor(
        string $publicId,
        int $tokenVersion,
        array $config
    ): string {
        $payload = strtolower(trim($publicId)) . ':' . $tokenVersion;
        $binary = hash_hmac(
            'sha256',
            $payload,
            self::hmacSecret($config),
            true
        );

        return rtrim(
            strtr(base64_encode($binary), '+/', '-_'),
            '='
        );
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function validateToken(
        array $publicLink,
        string $providedToken,
        array $config
    ): bool {
        $publicId = (string) ($publicLink['public_id'] ?? '');
        $tokenVersion = (int) ($publicLink['token_version'] ?? 0);
        $storedHash = (string) ($publicLink['token_hash'] ?? '');

        if (
            $publicId === ''
            || $tokenVersion <= 0
            || $storedHash === ''
            || $providedToken === ''
        ) {
            return false;
        }

        $expectedToken = self::tokenFor(
            $publicId,
            $tokenVersion,
            $config
        );

        return hash_equals($expectedToken, $providedToken)
            && hash_equals(
                $storedHash,
                self::tokenHash($providedToken)
            );
    }

    public static function publicPath(
        array $config,
        string $publicId,
        string $token
    ): string {
        $basePath = rtrim(
            (string) ($config['app']['base_path'] ?? ''),
            '/'
        );

        return $basePath
            . '/registration/?link='
            . rawurlencode($publicId)
            . '&token='
            . rawurlencode($token);
    }

    public static function absolutePublicUrl(
        array $config,
        string $publicId,
        string $token
    ): string {
        $configuredOrigin = rtrim(
            (string) ($config['qr']['public_origin'] ?? ''),
            '/'
        );

        if ($configuredOrigin !== '') {
            return $configuredOrigin
                . self::publicPath($config, $publicId, $token);
        }

        $scheme = self::requestIsHttps() ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        return $scheme
            . '://'
            . $host
            . self::publicPath($config, $publicId, $token);
    }

    public static function clientIpHash(array $config): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $secret = (string) (
            $config['app']['app_key']
            ?? self::hmacSecret($config)
        );

        return hash_hmac('sha256', $ip, $secret);
    }

    public static function userAgent(): string
    {
        return mb_substr(
            trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            0,
            255,
            'UTF-8'
        );
    }

    public static function maskedPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (preg_match('/^213([567]\d{8})$/', $digits, $match) === 1) {
            $local = '0' . $match[1];

            return substr($local, 0, 4)
                . ' ** ** '
                . substr($local, -2);
        }

        if (preg_match('/^0[567]\d{8}$/', $digits) === 1) {
            return substr($digits, 0, 4)
                . ' ** ** '
                . substr($digits, -2);
        }

        return '';
    }

    private static function hmacSecret(array $config): string
    {
        $environmentSecret = getenv('MARKI_QR_HMAC_SECRET');
        $configuredSecret = (string) (
            $config['qr']['hmac_secret']
            ?? $config['app']['app_key']
            ?? ''
        );
        $secret = is_string($environmentSecret)
            && trim($environmentSecret) !== ''
                ? trim($environmentSecret)
                : trim($configuredSecret);

        if (strlen($secret) < 32) {
            throw new RuntimeException(
                'Le secret HMAC du QR doit contenir au moins 32 caractères.'
            );
        }

        return $secret;
    }

    private static function requestIsHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $forwardedProto = strtolower(
            trim(
                explode(
                    ',',
                    (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
                )[0]
            )
        );

        return in_array($https, ['on', '1'], true)
            || $forwardedProto === 'https';
    }
}
