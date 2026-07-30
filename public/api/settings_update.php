<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/repositories/SettingsRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : $_POST;

    $settings = (new SettingsRepository())->update(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $input,
        (bool) ($context['capabilities']['settings.manage_clinic'] ?? false),
        (bool) ($context['capabilities']['settings.manage_doctor'] ?? false)
    );

    $settings['permissions'] = [
        'can_manage_clinic' => (bool) ($context['capabilities']['settings.manage_clinic'] ?? false),
        'can_manage_doctor' => (bool) ($context['capabilities']['settings.manage_doctor'] ?? false),
        'can_manage_team' => (bool) ($context['capabilities']['team.manage'] ?? false),
    ];

    echo json_encode([
        'ok' => true,
        'message' => 'Paramètres enregistrés avec succès.',
        'data' => $settings,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    Auth::jsonError(422, $exception->getMessage());
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible d’enregistrer les paramètres.');
}
