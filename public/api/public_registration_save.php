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
        echo json_encode([
            'ok' => false,
            'message' => 'Méthode non autorisée.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];

    $birthDateRequired = filter_var(
        $input['birth_date_required'] ?? false,
        FILTER_VALIDATE_BOOL
    );
    $sessionDuration = (int) (
        $input['public_session_duration_minutes'] ?? 720
    );
    $maxRegistrationsRaw = trim(
        (string) ($input['max_public_registrations_per_day'] ?? '')
    );
    $maxRegistrations = $maxRegistrationsRaw === ''
        ? null
        : (int) $maxRegistrationsRaw;

    $errors = [];
    if ($sessionDuration < 30 || $sessionDuration > 10080) {
        $errors['public_session_duration_minutes'] =
            'La durée doit être comprise entre 30 minutes et 7 jours.';
    }
    if (
        $maxRegistrations !== null
        && ($maxRegistrations < 1 || $maxRegistrations > 1000)
    ) {
        $errors['max_public_registrations_per_day'] =
            'La limite doit être comprise entre 1 et 1000.';
    }

    $allowedMessageCodes = [
        'day_not_open',
        'registration_open',
        'registration_closed',
        'queue_paused',
        'day_completed',
        'qr_disabled',
        'outside_schedule',
        'registration_success',
    ];
    $messagesInput = is_array($input['messages'] ?? null)
        ? $input['messages']
        : [];
    $messages = [];

    foreach ($allowedMessageCodes as $code) {
        if (!array_key_exists($code, $messagesInput)) {
            continue;
        }

        $text = trim((string) $messagesInput[$code]);
        if ($text === '' || mb_strlen($text, 'UTF-8') > 1000) {
            $errors['messages.' . $code] =
                'Le message doit contenir entre 1 et 1000 caractères.';
            continue;
        }
        $messages[$code] = $text;
    }

    if ($errors !== []) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Vérifiez les paramètres du formulaire.',
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $repository = new PublicRegistrationRepository();
    $repository->ensureDoctorConfiguration(
        (int) $context['clinic_id'],
        (int) $context['doctor_id']
    );
    $repository->updateConfiguration(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        [
            'birth_date_required' => $birthDateRequired,
            'public_session_duration_minutes' => $sessionDuration,
            'max_public_registrations_per_day' => $maxRegistrations,
        ],
        $messages
    );
    $repository->logActivity(
        (int) $context['clinic_id'],
        (int) $context['user_id'],
        'PUBLIC_REGISTRATION_SETTINGS_UPDATED',
        'doctor',
        (int) $context['doctor_id'],
        [
            'birth_date_required' => $birthDateRequired,
            'session_duration_minutes' => $sessionDuration,
            'max_public_registrations_per_day' => $maxRegistrations,
        ]
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Les paramètres de l’inscription publique ont été enregistrés.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible d’enregistrer les paramètres du QR.',
        'error' => !empty($context['config']['app']['debug'])
            ? $exception->getMessage()
            : null,
    ], JSON_UNESCAPED_UNICODE);
}
