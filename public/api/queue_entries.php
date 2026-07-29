<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : récupérer la Liste du jour
|--------------------------------------------------------------------------
| Retourne :
| - la queue avec registration_status et day_status
| - les patients dans l'ordre FIFO
| - les compteurs
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');

$context = require __DIR__ . '/../../app/bootstrap.php';

require_once __DIR__ . '/../../app/repositories/QueueRepository.php';
require_once __DIR__ . '/../../app/repositories/QueueEntryRepository.php';

try {

    $clinicId = $context['clinic_id'];
    $doctorId = $context['doctor_id'];
    $userId = $context['user_id'];
    $today = $context['today'];

    $queueRepository = new QueueRepository();
    $queue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    $queueEntryRepository = new QueueEntryRepository();
    $entries = $queueEntryRepository->findByQueueId((int) $queue['id']);
    $counts = $queueEntryRepository->countByStatus((int) $queue['id']);

    echo json_encode([
        'ok' => true,
        'message' => 'Entrées de la liste du jour récupérées avec succès.',
        'data' => [
            'queue' => $queue,
            'entries' => $entries,
            'counts' => $counts,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de récupérer les entrées de la liste du jour.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
