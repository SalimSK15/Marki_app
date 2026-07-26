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
| - done     : terminer le patient ET enregistrer sa visite
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header(
    'Content-Type: application/json; charset=utf-8'
);

$config =
    require __DIR__ . '/../../app/config.php';

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
        'message' =>
        'Méthode non autorisée. Utilisez POST.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    /*
    |--------------------------------------------------------------------------
    | Contexte temporaire de développement
    |--------------------------------------------------------------------------
    */
    $clinicId =
        (int) $config['dev_context']['clinic_id'];

    $doctorId =
        (int) $config['dev_context']['doctor_id'];

    $userId =
        (int) $config['dev_context']['user_id'];

    $today = date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | Lire JSON ou formulaire classique
    |--------------------------------------------------------------------------
    */
    $rawInput =
        file_get_contents('php://input');

    $jsonInput =
        json_decode($rawInput, true);

    $input = is_array($jsonInput)
        ? $jsonInput
        : $_POST;

    $entryId =
        (int) ($input['entry_id'] ?? 0);

    $status =
        trim((string) ($input['status'] ?? ''));

    $cancellationReason =
        isset($input['cancellation_reason'])
        ? trim(
            (string) $input['cancellation_reason']
        )
        : null;

    /*
    |--------------------------------------------------------------------------
    | Validation de l'identifiant
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Validation du statut demandé
    |--------------------------------------------------------------------------
    */
    $allowedStatuses = [
        'waiting',
        'done',
        'no_show',
        'canceled',
    ];

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
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
    |--------------------------------------------------------------------------
    | Charger la liste du jour
    |--------------------------------------------------------------------------
    */
    $queueRepository =
        new QueueRepository();

    $todayQueue =
        $queueRepository->getOrCreateTodayQueue(
            $clinicId,
            $doctorId,
            $userId,
            $today
        );

    /*
    |--------------------------------------------------------------------------
    | Les changements de statut nécessitent une liste active
    |--------------------------------------------------------------------------
    | Fermer les inscriptions ne bloque pas ces actions.
    | En revanche, une liste en pause ou clôturée les bloque.
    |--------------------------------------------------------------------------
    */
    if (
        ($todayQueue['day_status'] ?? 'active')
        !== 'active'
    ) {
        http_response_code(409);

        $message =
            ($todayQueue['day_status'] ?? '')
            === 'paused'
            ? 'La liste est en pause. Reprenez-la avant de modifier un statut.'
            : 'La journée est clôturée. Les patients sont en lecture seule.';

        echo json_encode([
            'ok' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Charger l'inscription
    |--------------------------------------------------------------------------
    */
    $queueEntryRepository =
        new QueueEntryRepository();

    $entry =
        $queueEntryRepository->findById(
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

    /*
    |--------------------------------------------------------------------------
    | Vérifier que le patient appartient à la liste du jour
    |--------------------------------------------------------------------------
    */
    if (
        (int) $entry['queue_id']
        !== (int) $todayQueue['id']
    ) {
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
    |--------------------------------------------------------------------------
    | Cas spécial : patient terminé
    |--------------------------------------------------------------------------
    | Le repository met à jour l'inscription et crée la visite dans
    | une seule transaction.
    |--------------------------------------------------------------------------
    */
    if ($status === 'done') {
        $result =
            $queueEntryRepository
            ->markDoneAndCreateVisit(
                $entryId,
                $clinicId,
                $doctorId,
                $userId
            );

        $updatedEntry =
            $result['entry'];

        $visit =
            $result['visit'];

        $message =
            'Patient marqué comme terminé et visite enregistrée.';
    } else {
        /*
        |--------------------------------------------------------------------------
        | Autres statuts
        |--------------------------------------------------------------------------
        */
        $updatedEntry =
            $queueEntryRepository->updateStatus(
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

    /*
    |--------------------------------------------------------------------------
    | Réponse JSON
    |--------------------------------------------------------------------------
    */
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => [
            'queue' => $todayQueue,
            'entry' => $updatedEntry,
            'visit' => $visit,
        ],
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
        'message' =>
        'Impossible de mettre à jour le statut du patient.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}