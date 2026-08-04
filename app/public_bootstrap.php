<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$debug = (bool) ($config['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/Auth.php';

Auth::start($config);

return [
    'config' => $config,
    'csrf_token' => Auth::csrfToken(),
    'csp_nonce' => markiCspNonce(),
];
