<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : queue_update_patient.php
|--------------------------------------------------------------------------
| Rôle :
| - modifier une entrée de la liste du jour
| - corriger une erreur de saisie
| - mettre à jour aussi la fiche patient liée si patient_id existe
|--------------------------------------------------------------------------
|
| IMPORTANT :
| Cette version reste simple et cohérente avec la V1.
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../../app/config.php';

require_once __DIR__ . '/../../app/repositories/QueueRepository.php';
require_once __DIR__ . '/../../app/repositories/QueueEntryRepository.php';
require_once __DIR__ . '/../../app/repositories/PatientRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

function normalizePersonName(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

try {
    $clinicId = (int) $config['dev_context']['clinic_id'];
    $doctorId = (int) $config['dev_context']['doctor_id'];
    $userId   = (int) $config['dev_context']['user_id'];
    $today    = date('Y-m-d');

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);

    $input = is_array($jsonInput) && !empty($jsonInput)
        ? $jsonInput
        : $_POST;

    $entryId = (int) ($input['entry_id'] ?? 0);
    $fullName = normalizePersonName((string) ($input['full_name'] ?? ''));
    $phone = isset($input['phone']) ? trim((string) $input['phone']) : null;
    $birthDate = isset($input['birth_date']) ? trim((string) $input['birth_date']) : null;

    if ($entryId <= 0) {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Entrée invalide.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($fullName === '') {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Le nom complet est obligatoire.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($phone === '') {
        $phone = null;
    }

    if ($birthDate !== null && $birthDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $birthDate);
        $isValidBirthDate = $date && $date->format('Y-m-d') === $birthDate;

        if (!$isValidBirthDate) {
            http_response_code(422);

            echo json_encode([
                'ok' => false,
                'message' => 'La date de naissance est invalide. Format attendu : YYYY-MM-DD.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    } else {
        $birthDate = null;
    }

    $queueRepository = new QueueRepository();
    $queue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    if (($queue['status'] ?? '') !== 'open') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'message' => 'La liste du jour est fermée. Impossible de modifier une entrée.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $queueEntryRepository = new QueueEntryRepository();
    $entry = $queueEntryRepository->findById($entryId, $clinicId);

    if (!$entry) {
        http_response_code(404);

        echo json_encode([
            'ok' => false,
            'message' => 'Entrée introuvable.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ((int) $entry['queue_id'] !== (int) $queue['id']) {
        http_response_code(403);

        echo json_encode([
            'ok' => false,
            'message' => 'Cette entrée ne fait pas partie de la liste du jour.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier qu'on ne crée pas un doublon visible dans la même journée
    |--------------------------------------------------------------------------
    | Si on change le nom et qu'une autre entrée waiting a déjà ce même nom,
    | on bloque pour éviter la confusion.
    |--------------------------------------------------------------------------
    */
    $existingSameNameEntry = $queueEntryRepository->findWaitingByQueueAndDisplayName(
        (int) $queue['id'],
        $fullName
    );

    if ($existingSameNameEntry !== null && (int) $existingSameNameEntry['id'] !== $entryId) {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'message' => 'Une autre entrée du jour utilise déjà ce même nom. Vérifie avant de modifier.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Mise à jour de queue_entries
    |--------------------------------------------------------------------------
    */
    $updatedEntry = $queueEntryRepository->updatePatientIdentity(
        $entryId,
        $clinicId,
        $fullName,
        $phone,
        $birthDate,
        $userId
    );

    /*
    |--------------------------------------------------------------------------
    | Si l'entrée est liée à un patient réel, on met aussi à jour sa fiche
    |--------------------------------------------------------------------------
    */
    if (!empty($entry['patient_id'])) {
        $patientRepository = new PatientRepository();

        $patientRepository->updateIdentity(
            (int) $entry['patient_id'],
            $clinicId,
            $fullName,
            $phone,
            $birthDate
        );
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Entrée mise à jour avec succès.',
        'data' => [
            'queue' => $queue,
            'entry' => $updatedEntry,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de modifier le patient.',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}