<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/repositories/SettingsRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'message' => 'Méthode non autorisée.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    $input = is_array($decoded) ? $decoded : $_POST;

    $repository = new SettingsRepository();
    $settings = $repository->update(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $input
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Paramètres enregistrés.',
        'data' => $settings,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible d’enregistrer les paramètres.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
