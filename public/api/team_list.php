<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$context = null;

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/auth/TeamRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    $data = (new TeamRepository())->index(
        (int) $context['clinic_id'],
        (int) $context['user_id']
    );

    echo json_encode([
        'ok' => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[MARKI team_list] ' . $exception->getMessage());

    $debug = (bool) ($context['config']['app']['debug'] ?? false);
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de charger l’équipe.',
        'error' => $debug ? $exception->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE);
}
