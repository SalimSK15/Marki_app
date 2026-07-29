<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : ouvrir / fermer les inscriptions
|--------------------------------------------------------------------------
| Important :
| - cette API ne ferme pas la journée
| - elle bloque seulement les nouvelles inscriptions
| - les patients déjà inscrits restent traitables
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');

$context = require __DIR__ . '/../../app/bootstrap.php';

require_once __DIR__ . '/../../app/repositories/QueueRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {

    $clinicId = $context['clinic_id'];
    $doctorId = $context['doctor_id'];
    $userId = $context['user_id'];
    $today = $context['today'];

    $queueRepository = new QueueRepository();
    $todayQueue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    $updatedQueue = $queueRepository->toggleRegistrationStatus(
        (int) $todayQueue['id'],
        $clinicId,
        $userId
    );

    $message = $updatedQueue['registration_status'] === 'open'
        ? 'Les inscriptions ont été rouvertes avec succès.'
        : 'Les inscriptions ont été fermées avec succès. Les patients déjà inscrits restent dans la liste.';

    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => [
            'queue' => $updatedQueue,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(409);

    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de modifier l’état des inscriptions.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
