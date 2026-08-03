<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$context = null;

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/repositories/SettingsRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    $settings = (new SettingsRepository())->get(
        (int) $context['clinic_id'],
        (int) $context['doctor_id']
    );

    $settings['permissions'] = [
        'can_manage_clinic' => (bool) ($context['capabilities']['settings.manage_clinic'] ?? false),
        'can_manage_doctor' => (bool) ($context['capabilities']['settings.manage_doctor'] ?? false),
        'can_manage_team' => (bool) ($context['capabilities']['team.manage'] ?? false),
    ];

    echo json_encode([
        'ok' => true,
        'data' => $settings,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[MARKI settings_get] ' . $exception->getMessage());

    $debug = (bool) ($context['config']['app']['debug'] ?? false);
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de charger les paramètres.',
        'error' => $debug ? $exception->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE);
}
