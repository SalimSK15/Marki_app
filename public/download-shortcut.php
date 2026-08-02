<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$config = $public['config'];

function shortcutAbsoluteBaseUrl(array $config): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $basePath = rtrim((string) ($config['app']['base_path'] ?? ''), '/');

    return $scheme . '://' . $host . $basePath;
}

$type = trim((string) ($_GET['type'] ?? 'clinic'));
$baseUrl = shortcutAbsoluteBaseUrl($config);
$filename = 'MARKI.url';

if ($type === 'platform') {
    $targetUrl = $baseUrl . '/platform-invitations.php';
    $filename = 'MARKI-Administration.url';
} else {
    require_once __DIR__ . '/../app/auth/AuthRepository.php';

    $clinicSlug = trim((string) ($_GET['clinic'] ?? ''));
    $clinic = $clinicSlug !== ''
        ? (new AuthRepository())->findClinicBySlug($clinicSlug)
        : null;

    if ($clinic === null || ($clinic['status'] ?? '') !== 'active') {
        http_response_code(404);
        echo 'Structure introuvable.';
        exit;
    }

    $targetUrl = $baseUrl
        . '/login.php?clinic='
        . rawurlencode($clinicSlug);

    $safeName = preg_replace(
        '/[^a-zA-Z0-9_-]+/',
        '-',
        (string) ($clinic['name'] ?? 'Clinique')
    ) ?: 'Clinique';
    $filename = 'MARKI-' . trim($safeName, '-') . '.url';
}

header('Content-Type: application/internet-shortcut; charset=windows-1252');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo "[InternetShortcut]\r\n";
echo 'URL=' . $targetUrl . "\r\n";
echo 'IconFile=' . $baseUrl . '/assets/icons/marki-app.ico' . "\r\n";
echo "IconIndex=0\r\n";
