<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : modifier le statut d'un patient dans la liste du jour
|--------------------------------------------------------------------------
| Actions prises en charge :
|
| - waiting  : remettre un patient absent à la fin de la file
| - no_show  : marquer le patient absent
| - canceled : annuler son inscription
| - done     : terminer le patient, enregistrer la visite et l'audit
|--------------------------------------------------------------------------
*/

$context = require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__
    . '/../../app/repositories/QueueRepository.php';

require_once __DIR__
    . '/../../app/repositories/QueueEntryRepository.php';

/*
|--------------------------------------------------------------------------
| POST uniquement
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    /*
    |----------------------------------------------------------------------
    | Contexte du cabinet et du médecin
    |----------------------------------------------------------------------
    | Pour le moment, ces deux valeurs viennent encore du contexte de dev.
    | Elles seront remplacées par le futur module de connexion/permissions.
    |----------------------------------------------------------------------
    */
    // $userId = resolveCurrentUserId($config, $clinicId);
    $clinicId = $context['clinic_id'];
    $doctorId = $context['doctor_id'];
    $userId = $context['user_id'];
    $today = $context['today'];

    /*
    |----------------------------------------------------------------------
    | Lire JSON ou formulaire classique
    |----------------------------------------------------------------------
    */
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

    $allowedStatuses = [
        'waiting',
        'done',
        'no_show',
        'canceled',
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Statut invalide.',
            'errors' => [
                'status' => 'Statut invalide.',
            ],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |----------------------------------------------------------------------
    | Charger la liste du jour
    |----------------------------------------------------------------------
    */
    $queueRepository = new QueueRepository();

    $todayQueue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    /*
    |----------------------------------------------------------------------
    | La liste doit être active
    |----------------------------------------------------------------------
    | Fermer les nouvelles inscriptions ne bloque pas les actions sur les
    | patients déjà présents. Une pause ou une clôture les bloque.
    |----------------------------------------------------------------------
    */
    if (($todayQueue['day_status'] ?? 'active') !== 'active') {
        http_response_code(409);

        $message = ($todayQueue['day_status'] ?? '') === 'paused'
            ? 'La liste est en pause. Reprenez-la avant de modifier un statut.'
            : 'La journée est clôturée. Les patients sont en lecture seule.';

        echo json_encode([
            'ok' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |----------------------------------------------------------------------
    | Charger et vérifier l'inscription
    |----------------------------------------------------------------------
    */
    $queueEntryRepository = new QueueEntryRepository();

    $entry = $queueEntryRepository->findById(
        $entryId,
        $clinicId
    );

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
            'message' =>
                'Cette entrée ne fait pas partie de la liste du jour.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $visit = null;

    /*
    |----------------------------------------------------------------------
    | Patient terminé
    |----------------------------------------------------------------------
    | Le repository réalise dans une seule transaction :
    | - la mise à jour de queue_entries ;
    | - la création ou la fin de visits ;
    | - les colonnes d'audit ;
    | - l'événement VISIT_COMPLETED.
    |----------------------------------------------------------------------
    */
    if ($status === 'done') {
        $result = $queueEntryRepository->markDoneAndCreateVisit(
            $entryId,
            $clinicId,
            $doctorId,
            $userId
        );

        $updatedEntry = $result['entry'];
        $visit = $result['visit'];
        $message =
            'Patient marqué comme terminé et visite enregistrée.';
    } else {
        $updatedEntry = $queueEntryRepository->updateStatus(
            $entryId,
            $clinicId,
            $status,
            $userId,
            $cancellationReason
        );

        $message = match ($status) {
            'waiting' =>
                'Le patient a été remis à la fin de la file.',
            'no_show' =>
                'Le patient a été marqué absent.',
            'canceled' =>
                'L’inscription du patient a été annulée.',
            default =>
                'Statut mis à jour avec succès.',
        };
    }

    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => [
            'queue' => $todayQueue,
            'entry' => $updatedEntry,
            'visit' => $visit,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (AuthorizationException $exception) {
    http_response_code(403);

    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
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

    $response = [
        'ok' => false,
        'message' =>
            'Impossible de mettre à jour le statut du patient.',
    ];

    if ($debug) {
        $response['error'] = $exception->getMessage();
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );
}
