<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$public = require __DIR__ . '/../../../app/public_bootstrap.php';
require_once __DIR__ . '/../../../app/public_registration/PublicRegistrationService.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'message' => 'Méthode non autorisée.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new PublicRegistrationService();
    $data = $service->status(
        trim((string) ($_GET['session'] ?? '')),
        $public['config']
    );

    echo json_encode([
        'ok' => true,
        'data' => $data,
        'csrf_token' => $public['csrf_token'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PublicRegistrationException $exception) {
    http_response_code($exception->httpStatus());
    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
        'error_code' => $exception->errorCode(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de charger le suivi de votre inscription.',
        'error' => !empty($public['config']['app']['debug'])
            ? $exception->getMessage()
            : null,
    ], JSON_UNESCAPED_UNICODE);
}
