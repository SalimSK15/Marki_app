<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';

try {
    db()->query('SELECT 1');

    echo json_encode([
        'ok' => true,
        'message' => 'Service disponible.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(503);

    $payload = [
        'ok' => false,
        'message' => 'Service temporairement indisponible.',
    ];

    if ((bool) ($config['app']['debug'] ?? false)) {
        $payload['error'] = $exception->getMessage();
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}
