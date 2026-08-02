<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$public = require __DIR__ . '/../../../app/public_bootstrap.php';
require_once __DIR__ . '/../../../app/public_registration/PublicRegistrationService.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'message' => 'Méthode non autorisée.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    Auth::validateCsrf();
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];

    $service = new PublicRegistrationService();
    $result = $service->register($input, $public['config']);

    $basePath = rtrim(
        (string) ($public['config']['app']['base_path'] ?? ''),
        '/'
    );

    echo json_encode([
        'ok' => true,
        'message' => $result['message'],
        'data' => [
            'already_registered' => $result['already_registered'],
            'entry' => $result['entry'],
            'status_path' => $basePath
                . '/registration/status.php?session='
                . rawurlencode((string) $result['session_token']),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PublicRegistrationException $exception) {
    http_response_code($exception->httpStatus());
    $payload = [
        'ok' => false,
        'message' => $exception->getMessage(),
        'error_code' => $exception->errorCode(),
        'errors' => $exception->errors(),
        'data' => $exception->data(),
    ];
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible d’enregistrer votre inscription.',
        'error' => !empty($public['config']['app']['debug'])
            ? $exception->getMessage()
            : null,
    ], JSON_UNESCAPED_UNICODE);
}
