<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/support.php';

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
        'can_manage_clinic' => (bool) (
            $context['capabilities']['settings.manage_clinic'] ?? false
        ),
        'can_manage_doctor' => (bool) (
            $context['capabilities']['settings.manage_doctor'] ?? false
        ),
        'can_manage_team' => (bool) (
            $context['capabilities']['team.manage'] ?? false
        ),
    ];

    echo json_encode([
        'ok' => true,
        'data' => $settings,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    markiJsonException(
        'settings_get',
        $exception,
        $config,
        'Impossible de charger les paramètres.',
        [
            'user_id' => $context['user_id'] ?? null,
            'clinic_id' => $context['clinic_id'] ?? null,
            'doctor_id' => $context['doctor_id'] ?? null,
        ]
    );
}
