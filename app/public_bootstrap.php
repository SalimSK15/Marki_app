<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

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
];
