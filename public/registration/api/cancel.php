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
    $result = $service->cancel(
        trim((string) ($input['session'] ?? '')),
        $public['config']
    );

    echo json_encode([
        'ok' => true,
        'message' => $result['message'],
        'data' => ['status' => $result['status']],
    ], JSON_UNESCAPED_UNICODE);
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
        'message' => 'Impossible d’annuler votre inscription.',
        'error' => !empty($public['config']['app']['debug'])
            ? $exception->getMessage()
            : null,
    ], JSON_UNESCAPED_UNICODE);
}
