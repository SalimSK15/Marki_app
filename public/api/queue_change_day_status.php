<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : gérer l'état opérationnel de la Liste du jour
|--------------------------------------------------------------------------
| Actions acceptées :
| - pause    : met la liste en pause et ferme les inscriptions
| - resume   : reprend la liste, inscriptions toujours fermées
| - complete : clôture la journée et annule les patients restants
| - reopen   : annule la clôture et restaure l'état précédent
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// $config = require __DIR__ . '/../../app/config.php';
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
    // $clinicId = (int) $config['dev_context']['clinic_id'];
    // $doctorId = (int) $config['dev_context']['doctor_id'];
    // $userId = (int) $config['dev_context']['user_id'];
    // $today = date('Y-m-d');

    $clinicId = $context['clinic_id'];
    $doctorId = $context['doctor_id'];
    $userId = $context['user_id'];
    $today = $context['today'];

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $input = is_array($jsonInput) ? $jsonInput : $_POST;

    $action = trim((string) ($input['action'] ?? ''));
    $cancellationReason = trim(
        (string) ($input['cancellation_reason'] ?? 'end_of_day')
    );

    if (!in_array($action, ['pause', 'resume', 'complete', 'reopen'], true)) {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Action de liste invalide.',
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

    if ($action === 'pause') {
        $updatedQueue = $queueRepository->pauseDay(
            (int) $todayQueue['id'],
            $clinicId,
            $userId
        );

        echo json_encode([
            'ok' => true,
            'message' => 'La liste est en pause. Les patients conservent leur position.',
            'data' => [
                'queue' => $updatedQueue,
                'canceled_count' => 0,
            ],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($action === 'resume') {
        $updatedQueue = $queueRepository->resumeDay(
            (int) $todayQueue['id'],
            $clinicId,
            $userId
        );

        echo json_encode([
            'ok' => true,
            'message' => 'La liste a repris. Les inscriptions restent fermées.',
            'data' => [
                'queue' => $updatedQueue,
                'canceled_count' => 0,
            ],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    if ($action === 'reopen') {
        $result = $queueRepository
            ->reopenCompletedDayAndRestoreEntries(
                (int) $todayQueue['id'],
                $clinicId,
                $userId
            );

        $restoredCount = (int) $result['restored_count'];

        $message = $restoredCount > 0
            ? sprintf(
                'Clôture annulée. %d inscription(s) ont été restaurée(s).',
                $restoredCount
            )
            : 'Clôture annulée. La liste a été restaurée.';

        echo json_encode([
            'ok' => true,
            'message' => $message,
            'data' => $result,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $result = $queueRepository->completeDayAndCancelWaiting(
        (int) $todayQueue['id'],
        $clinicId,
        $userId,
        $cancellationReason
    );

    $canceledCount = (int) $result['canceled_count'];

    $message = $canceledCount > 0
        ? sprintf(
            'Journée clôturée. %d inscription(s) restante(s) ont été annulée(s).',
            $canceledCount
        )
        : 'Journée clôturée avec succès.';

    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
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
        'message' => 'Impossible de modifier l’état de la liste.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
