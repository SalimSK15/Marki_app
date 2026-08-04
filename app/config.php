<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'app' => [
        'name' => markiEnv('MARKI_APP_NAME', 'MARKI'),
        'env' => markiEnv('MARKI_APP_ENV', 'local'),
        'debug' => markiEnvBool('MARKI_APP_DEBUG', true),
        'timezone' => markiEnv('MARKI_APP_TIMEZONE', 'Africa/Algiers'),
        'base_path' => markiEnv(
            'MARKI_APP_BASE_PATH',
            '/Marki_app/Partie_medecin/public'
        ),
        'origin' => markiEnv('MARKI_APP_ORIGIN', ''),
        'app_key' => markiEnv(
            'MARKI_APP_KEY',
            ''
        ),
    ],

    'security' => [
        'force_https' => markiEnvBool('MARKI_FORCE_HTTPS', false),
        'trust_proxy_headers' => markiEnvBool(
            'MARKI_TRUST_PROXY_HEADERS',
            false
        ),
        'allowed_hosts' => array_values(array_filter(array_map(
            static fn(string $host): string => strtolower(trim($host)),
            explode(',', markiEnv('MARKI_ALLOWED_HOSTS', '') ?? '')
        ))),
        'max_request_bytes' => markiEnvInt(
            'MARKI_MAX_REQUEST_BYTES',
            1048576
        ),
    ],

    'db' => [
        'host' => markiEnv('MARKI_DB_HOST', '127.0.0.1'),
        'port' => markiEnvInt('MARKI_DB_PORT', 3307),
        'dbname' => markiEnv('MARKI_DB_NAME', 'markii_db'),
        'charset' => markiEnv('MARKI_DB_CHARSET', 'utf8mb4'),
        'username' => markiEnv('MARKI_DB_USERNAME', ''),
        'password' => markiEnvRaw('MARKI_DB_PASSWORD', ''),
    ],

    'qr' => [
        'hmac_secret' => markiEnv(
            'MARKI_QR_HMAC_SECRET',
            ''
        ),
        // Pour un test mobile local : http://192.168.x.x
        'public_origin' => markiEnv('MARKI_QR_PUBLIC_ORIGIN', ''),
        'rate_limit_attempts' => markiEnvInt(
            'MARKI_QR_RATE_LIMIT_ATTEMPTS',
            20
        ),
        'rate_limit_minutes' => markiEnvInt(
            'MARKI_QR_RATE_LIMIT_MINUTES',
            15
        ),
    ],

    'auth' => [
        'session_name' => markiEnv('MARKI_SESSION_NAME', 'marki_session'),
        'idle_timeout_seconds' => markiEnvInt(
            'MARKI_IDLE_TIMEOUT_SECONDS',
            43200
        ),
        'remember_days' => markiEnvInt('MARKI_REMEMBER_DAYS', 30),
        'max_failed_attempts' => markiEnvInt(
            'MARKI_MAX_FAILED_ATTEMPTS',
            5
        ),
        'lock_minutes' => markiEnvInt('MARKI_LOCK_MINUTES', 15),
        'password_min_length' => markiEnvInt(
            'MARKI_PASSWORD_MIN_LENGTH',
            10
        ),
    ],

    'platform' => [
        'allowed_ips' => array_values(array_filter(array_map(
            static fn(string $ip): string => trim($ip),
            explode(',', markiEnv('MARKI_PLATFORM_ALLOWED_IPS', '') ?? '')
        ))),
        'invitation_expiry_hours' => markiEnvInt(
            'MARKI_INVITATION_EXPIRY_HOURS',
            72
        ),
        'remember_days' => markiEnvInt(
            'MARKI_PLATFORM_REMEMBER_DAYS',
            30
        ),
        'max_failed_attempts' => markiEnvInt(
            'MARKI_PLATFORM_MAX_FAILED_ATTEMPTS',
            5
        ),
        'lock_minutes' => markiEnvInt(
            'MARKI_PLATFORM_LOCK_MINUTES',
            15
        ),
        'idle_timeout_seconds' => markiEnvInt(
            'MARKI_PLATFORM_IDLE_TIMEOUT_SECONDS',
            14400
        ),
    ],
];
