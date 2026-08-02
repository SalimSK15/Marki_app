<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/public_registration/PublicRegistrationRepository.php';

    Auth::requireCapability($context, 'settings.manage_doctor');

    $repository = new PublicRegistrationRepository();
    $overview = $repository->adminOverview(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $context['config'],
        (string) $context['today']
    );

    $link = $overview['link'];
    $publicUrl = PublicRegistrationSecurity::absolutePublicUrl(
        $context['config'],
        (string) $link['public_id'],
        (string) $link['token']
    );

    echo json_encode([
        'ok' => true,
        'data' => [
            'link' => [
                'id' => (int) $link['id'],
                'public_id' => (string) $link['public_id'],
                'public_url' => $publicUrl,
                'is_active' => (bool) $link['is_active'],
                'token_version' => (int) $link['token_version'],
                'activated_at' => $link['activated_at'],
                'deactivated_at' => $link['deactivated_at'],
                'last_scanned_at' => $link['last_scanned_at'],
                'last_regenerated_at' => $link['revoked_at'],
            ],
            'settings' => $overview['settings'],
            'messages' => $overview['messages'],
            'metrics' => $overview['metrics'],
            'doctor' => [
                'id' => (int) $context['doctor_id'],
                'name' => (string) $context['doctor']['display_name'],
                'specialty' => $context['doctor']['specialty'],
            ],
            'clinic' => [
                'id' => (int) $context['clinic_id'],
                'name' => (string) $context['clinic']['name'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de charger la configuration du QR.',
        'error' => !empty($context['config']['app']['debug'])
            ? $exception->getMessage()
            : null,
    ], JSON_UNESCAPED_UNICODE);
}
