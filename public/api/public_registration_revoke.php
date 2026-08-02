<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/public_registration/PublicRegistrationRepository.php';

    Auth::requireCapability($context, 'settings.manage_doctor');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Méthode non autorisée.']);
        exit;
    }

    $repository = new PublicRegistrationRepository();
    $repository->ensureDoctorConfiguration(
        (int) $context['clinic_id'],
        (int) $context['doctor_id']
    );
    $current = $repository->ensurePublicLink(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $context['config']
    );
    $updated = $repository->revokeLink(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $context['config']
    );
    $repository->logActivity(
        (int) $context['clinic_id'],
        (int) $context['user_id'],
        'PUBLIC_LINK_REGENERATED',
        'public_link',
        (int) $current['id'],
        [
            'doctor_id' => (int) $context['doctor_id'],
            'previous_token_version' => (int) $current['token_version'],
            'new_token_version' => (int) $updated['token_version'],
        ]
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Un nouveau QR sécurisé a été généré. L’ancien QR ne fonctionne plus.',
        'data' => [
            'public_url' => PublicRegistrationSecurity::absolutePublicUrl(
                $context['config'],
                (string) $updated['public_id'],
                (string) $updated['token']
            ),
            'token_version' => (int) $updated['token_version'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de régénérer le QR.',
        'error' => !empty($context['config']['app']['debug'])
            ? $exception->getMessage()
            : null,
    ], JSON_UNESCAPED_UNICODE);
}
