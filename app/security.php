<?php

declare(strict_types=1);

/**
 * Couche de securite HTTP commune a toutes les pages et API MARKI.
 */
function markiSecurityBootstrap(array $config): string
{
    static $nonce = null;

    if (is_string($nonce)) {
        return $nonce;
    }

    $nonce = base64_encode(random_bytes(18));

    if (PHP_SAPI === 'cli' || headers_sent()) {
        return $nonce;
    }

    markiAssertProductionConfiguration($config);
    markiValidateRequestHost($config);
    markiEnforceHttps($config);
    markiRejectOversizedRequest($config);

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "media-src 'none'",
        "worker-src 'none'",
        "manifest-src 'self'",
    ];

    if ((bool) ($config['security']['force_https'] ?? false)) {
        $csp[] = 'upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    header('Content-Security-Policy: ' . implode('; ', $csp));

    return $nonce;
}

function markiCspNonce(): string
{
    return (string) ($GLOBALS['marki_csp_nonce'] ?? '');
}

function markiRequestIsHttps(array $config): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if (in_array($https, ['on', '1'], true)) {
        return true;
    }

    if (!(bool) ($config['security']['trust_proxy_headers'] ?? false)) {
        return false;
    }

    $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

    return $forwarded === 'https';
}

function markiApplicationOrigin(array $config): string
{
    $configured = rtrim((string) ($config['app']['origin'] ?? ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    $qrOrigin = rtrim((string) ($config['qr']['public_origin'] ?? ''), '/');
    if ($qrOrigin !== '') {
        return $qrOrigin;
    }

    $scheme = markiRequestIsHttps($config) ? 'https' : 'http';
    $host = markiNormalizedRequestHost();

    return $scheme . '://' . $host;
}

function markiAbsoluteUrl(array $config, string $path): string
{
    $origin = markiApplicationOrigin($config);
    $normalizedPath = trim($path);

    return $normalizedPath === ''
        ? $origin
        : $origin . '/' . ltrim($normalizedPath, '/');
}

function markiClientIp(): string
{
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) ?: 'unknown';
}

function markiEnforcePlatformIpAllowlist(array $config): void
{
    $allowed = $config['platform']['allowed_ips'] ?? [];
    if (!is_array($allowed) || $allowed === []) {
        return;
    }

    if (!in_array(markiClientIp(), $allowed, true)) {
        markiSecurityAbort(404, 'Ressource introuvable.');
    }
}

function markiNormalizedRequestHost(): string
{
    $raw = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = parse_url('http://' . $raw, PHP_URL_HOST);

    return is_string($host) && $host !== '' ? strtolower($host) : 'invalid-host';
}

function markiValidateRequestHost(array $config): void
{
    $allowed = $config['security']['allowed_hosts'] ?? [];
    if (!is_array($allowed) || $allowed === []) {
        return;
    }

    $host = markiNormalizedRequestHost();
    if (!in_array($host, $allowed, true)) {
        markiSecurityAbort(400, 'Requete invalide.');
    }
}

function markiEnforceHttps(array $config): void
{
    if (!(bool) ($config['security']['force_https'] ?? false) || markiRequestIsHttps($config)) {
        return;
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        markiSecurityAbort(400, 'HTTPS est obligatoire.');
    }

    $origin = rtrim((string) ($config['app']['origin'] ?? ''), '/');
    if ($origin === '' || !str_starts_with($origin, 'https://')) {
        markiSecurityAbort(503, 'Configuration HTTPS incomplete.');
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $origin . $uri, true, 308);
    exit;
}

function markiRejectOversizedRequest(array $config): void
{
    $limit = max(1024, (int) ($config['security']['max_request_bytes'] ?? 1048576));
    $length = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);

    if (is_int($length) && $length > $limit) {
        markiSecurityAbort(413, 'Requete trop volumineuse.');
    }
}

function markiAssertProductionConfiguration(array $config): void
{
    if ((string) ($config['app']['env'] ?? 'local') !== 'production') {
        return;
    }

    $origin = (string) ($config['app']['origin'] ?? '');
    $appKey = (string) ($config['app']['app_key'] ?? '');
    $qrSecret = (string) ($config['qr']['hmac_secret'] ?? '');

    if ((bool) ($config['app']['debug'] ?? true)) {
        markiSecurityAbort(503, 'Configuration de production invalide.');
    }

    $originScheme = strtolower((string) parse_url($origin, PHP_URL_SCHEME));
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $originPath = (string) parse_url($origin, PHP_URL_PATH);

    if (
        $originScheme !== 'https'
        || $originHost === ''
        || !in_array($originPath, ['', '/'], true)
        || strlen($appKey) < 32
        || strlen($qrSecret) < 32
    ) {
        markiSecurityAbort(503, 'Configuration de production incomplete.');
    }

    if (($config['security']['allowed_hosts'] ?? []) === []) {
        markiSecurityAbort(503, 'Aucun domaine de production autorise.');
    }

    if (!in_array($originHost, $config['security']['allowed_hosts'], true)) {
        markiSecurityAbort(503, 'Le domaine principal n est pas autorise.');
    }
}

function markiSecurityAbort(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
