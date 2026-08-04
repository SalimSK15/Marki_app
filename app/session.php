<?php

declare(strict_types=1);

function startMarkiSession(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $basePath = rtrim((string) ($config['app']['base_path'] ?? '/'), '/');
    $cookiePath = $basePath !== '' ? $basePath . '/' : '/';
    $isHttps = function_exists('markiRequestIsHttps')
        ? markiRequestIsHttps($config)
        : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    session_cache_limiter('nocache');

    session_name((string) ($config['auth']['session_name'] ?? 'marki_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}
