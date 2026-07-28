<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/repositories/SettingsRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'message' => 'Méthode non autorisée.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $repository = new SettingsRepository();
    $settings = $repository->get(
        (int) $context['clinic_id'],
        (int) $context['doctor_id']
    );

    echo json_encode([
        'ok' => true,
        'data' => $settings,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de charger les paramètres.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
