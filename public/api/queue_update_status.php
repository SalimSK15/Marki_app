<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : mettre à jour le statut d'un patient dans la liste du jour
|--------------------------------------------------------------------------
| Statuts V1 :
| - waiting
| - done
| - no_show
| - canceled
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../../app/config.php';

require_once __DIR__ . '/../../app/repositories/QueueEntryRepository.php';
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
    $clinicId = (int) $config['dev_context']['clinic_id'];
    $doctorId = (int) $config['dev_context']['doctor_id'];
    $userId = (int) $config['dev_context']['user_id'];
    $today = date('Y-m-d');

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);

    $input = is_array($jsonInput)
        ? $jsonInput
        : $_POST;

    $entryId = (int) ($input['entry_id'] ?? 0);
    $status = trim((string) ($input['status'] ?? ''));
    $cancellationReason = isset($input['cancellation_reason'])
        ? trim((string) $input['cancellation_reason'])
        : null;

    if ($entryId <= 0) {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Entrée invalide.',
            'errors' => [
                'entry_id' => 'Entrée invalide.',
            ],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($status === '') {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Le statut est obligatoire.',
            'errors' => [
                'status' => 'Le statut est obligatoire.',
            ],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $queueRepository = new QueueRepository();
    $todayQueue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    if ($todayQueue['day_status'] === 'completed') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'message' => 'La journée est clôturée. La liste est en lecture seule.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($todayQueue['day_status'] === 'paused') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'message' => 'La liste est en pause. Reprenez-la avant de modifier le statut d’un patient.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $queueEntryRepository = new QueueEntryRepository();
    $entry = $queueEntryRepository->findById($entryId, $clinicId);

    if ($entry === null) {
        http_response_code(404);

        echo json_encode([
            'ok' => false,
            'message' => 'Entrée introuvable.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ((int) $entry['queue_id'] !== (int) $todayQueue['id']) {
        http_response_code(403);

        echo json_encode([
            'ok' => false,
            'message' => 'Cette entrée ne fait pas partie de la liste du jour.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $updatedEntry = $queueEntryRepository->updateStatus(
        $entryId,
        $clinicId,
        $status,
        $userId,
        $cancellationReason
    );

    $messages = [
        'waiting' => 'Le patient a été remis en attente à la fin de la file.',
        'done' => 'Le patient a été marqué comme terminé.',
        'no_show' => 'Le patient a été marqué comme absent.',
        'canceled' => 'L’inscription du patient a été annulée.',
    ];

    echo json_encode([
        'ok' => true,
        'message' => $messages[$status] ?? 'Statut mis à jour avec succès.',
        'data' => [
            'queue' => $todayQueue,
            'entry' => $updatedEntry,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de mettre à jour le statut.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
