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

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../../app/config.php';

require_once __DIR__ . '/../../app/repositories/QueueRepository.php';
require_once __DIR__ . '/../../app/repositories/QueueEntryRepository.php';

try {
    $clinicId = (int) $config['dev_context']['clinic_id'];
    $doctorId = (int) $config['dev_context']['doctor_id'];
    $userId = (int) $config['dev_context']['user_id'];
    $today = date('Y-m-d');

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
