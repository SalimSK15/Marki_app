<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';

    echo json_encode([
        'ok' => true,
        'data' => [
            'user' => $context['user'],
            'clinic' => $context['clinic'],
            'doctor' => $context['doctor'],
            'doctors' => $context['doctors'],
            'roles' => $context['roles'],
            'role_label' => $context['role_label'],
            'access_level' => $context['access_level'],
            'capabilities' => $context['capabilities'],
            'timezone' => $context['timezone'],
            'csrf_token' => $context['csrf_token'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible de charger la session.');
}
