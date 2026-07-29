<?php

declare(strict_types=1);

function startMarkiSession(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $basePath = rtrim((string) ($config['app']['base_path'] ?? '/'), '/');
    $cookiePath = $basePath !== '' ? $basePath . '/' : '/';
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_name((string) ($config['auth']['session_name'] ?? 'marki_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
