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

    $input = json_decode((string) file_get_contents('php://input'), true);
    $active = filter_var(
        is_array($input) ? ($input['active'] ?? false) : false,
        FILTER_VALIDATE_BOOL
    );

    $repository = new PublicRegistrationRepository();
    $repository->ensureDoctorConfiguration(
        (int) $context['clinic_id'],
        (int) $context['doctor_id']
    );
    $link = $repository->ensurePublicLink(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $context['config']
    );
    $updated = $repository->setLinkActive(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $active
    );
    $repository->logActivity(
        (int) $context['clinic_id'],
        (int) $context['user_id'],
        $active ? 'PUBLIC_LINK_ACTIVATED' : 'PUBLIC_LINK_DEACTIVATED',
        'public_link',
        (int) $link['id'],
        ['doctor_id' => (int) $context['doctor_id']]
    );

    echo json_encode([
        'ok' => true,
        'message' => $active
            ? 'L’inscription publique est activée.'
            : 'L’inscription publique est désactivée.',
        'data' => [
            'is_active' => (bool) $updated['is_active'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de modifier l’état du QR.',
        'error' => !empty($context['config']['app']['debug'])
            ? $exception->getMessage()
            : null,
    ], JSON_UNESCAPED_UNICODE);
}
