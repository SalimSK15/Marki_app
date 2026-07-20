<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : modifier une entrée patient
|--------------------------------------------------------------------------
| Règles :
| - correction simple autorisée
| - même nom + autre téléphone autorisé
| - copie exacte d’une autre fiche refusée
| - téléphone d’un autre patient : confirmation familiale
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../../app/config.php';

require_once __DIR__ . '/../../app/helpers/PatientDataNormalizer.php';
require_once __DIR__ . '/../../app/repositories/QueueRepository.php';
require_once __DIR__ . '/../../app/repositories/QueueEntryRepository.php';
require_once __DIR__ . '/../../app/repositories/PatientRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'Méthode non autorisée.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $clinicId =
        (int) $config['dev_context']['clinic_id'];

    $doctorId =
        (int) $config['dev_context']['doctor_id'];

    $userId =
        (int) $config['dev_context']['user_id'];

    $today = date('Y-m-d');

    $rawInput =
        file_get_contents('php://input');

    $jsonInput =
        json_decode($rawInput, true);

    $input = is_array($jsonInput)
        ? $jsonInput
        : $_POST;

    $entryId =
        (int) ($input['entry_id'] ?? 0);

    $fullName =
        PatientDataNormalizer::normalizeName(
            (string) ($input['full_name'] ?? '')
        );

    $phone =
        PatientDataNormalizer::normalizePhone(
            (string) ($input['phone'] ?? '')
        );

    $birthDate = isset($input['birth_date'])
        ? trim((string) $input['birth_date'])
        : null;

    $allowSharedPhone = filter_var(
        $input['allow_shared_phone'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );

    /*
    |--------------------------------------------------------------------------
    | Validations
    |--------------------------------------------------------------------------
    */
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
            'message' =>
            'Le nom complet est obligatoire.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($phone === '') {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' =>
            'Le numéro de téléphone est obligatoire.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if (
        !PatientDataNormalizer::isValidPhone($phone)
    ) {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' =>
            'Le numéro de téléphone doit contenir entre 8 et 15 chiffres.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($birthDate !== null && $birthDate !== '') {
        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $birthDate
        );

        if (
            !$date
            || $date->format('Y-m-d') !== $birthDate
        ) {
            http_response_code(422);

            echo json_encode([
                'ok' => false,
                'message' =>
                'La date de naissance est invalide.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    } else {
        $birthDate = null;
    }

    $queueRepository =
        new QueueRepository();

    $queueEntryRepository =
        new QueueEntryRepository();

    $patientRepository =
        new PatientRepository();

    $queue =
        $queueRepository->getOrCreateTodayQueue(
            $clinicId,
            $doctorId,
            $userId,
            $today
        );

    if (($queue['status'] ?? '') !== 'open') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'message' =>
            'La liste du jour est fermée.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

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

    if (
        (int) $entry['queue_id']
        !== (int) $queue['id']
    ) {
        http_response_code(403);

        echo json_encode([
            'ok' => false,
            'message' =>
            'Cette entrée ne fait pas partie de la liste du jour.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $currentPatientId =
        !empty($entry['patient_id'])
        ? (int) $entry['patient_id']
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Refuser une copie exacte d’une autre fiche patient
    |--------------------------------------------------------------------------
    */
    $duplicateIdentity =
        $patientRepository->findByPhoneAndName(
            $clinicId,
            $phone,
            $fullName,
            $currentPatientId > 0
                ? $currentPatientId
                : null
        );

    if ($duplicateIdentity !== null) {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'error_code' =>
            'PATIENT_IDENTITY_ALREADY_EXISTS',
            'message' =>
            'Une autre fiche patient possède déjà exactement ce nom et ce numéro.',
            'data' => [
                'existing_patient' => [
                    'id' =>
                    (int) $duplicateIdentity['id'],
                    'full_name' =>
                    $duplicateIdentity['full_name'],
                    'phone' =>
                    $duplicateIdentity['phone'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier si le téléphone a réellement changé
    |--------------------------------------------------------------------------
    | Modifier seulement le nom ne doit pas redemander une confirmation
    | pour un numéro familial déjà partagé.
    |--------------------------------------------------------------------------
    */
    $currentPhone =
        PatientDataNormalizer::normalizePhone(
            (string) ($entry['phone'] ?? '')
        );

    $phoneChanged =
        $currentPhone !== $phone;

    if ($phoneChanged) {
        $otherPhoneOwner =
            $patientRepository
            ->findByPhoneExcludingPatientId(
                $clinicId,
                $phone,
                $currentPatientId
            );

        if (
            $otherPhoneOwner !== null
            && !$allowSharedPhone
        ) {
            http_response_code(409);

            echo json_encode([
                'ok' => false,
                'error_code' =>
                'PHONE_SHARED_CONFIRMATION_REQUIRED',
                'message' => sprintf(
                    'Ce numéro est déjà utilisé par « %s ». Confirmez uniquement s’il s’agit d’un numéro familial partagé.',
                    $otherPhoneOwner['full_name']
                ),
                'data' => [
                    'existing_patient' => [
                        'id' =>
                        (int) $otherPhoneOwner['id'],
                        'full_name' =>
                        $otherPhoneOwner['full_name'],
                        'phone' =>
                        $otherPhoneOwner['phone'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Mettre à jour l’entrée affichée
    |--------------------------------------------------------------------------
    */
    $updatedEntry =
        $queueEntryRepository->updatePatientIdentity(
            $entryId,
            $clinicId,
            $fullName,
            $phone,
            $birthDate,
            $userId
        );

    /*
    |--------------------------------------------------------------------------
    | Mettre à jour la fiche patient durable
    |--------------------------------------------------------------------------
    */
    if ($currentPatientId > 0) {
        $patientRepository->updateIdentity(
            $currentPatientId,
            $clinicId,
            $fullName,
            $phone,
            $birthDate
        );
    }

    echo json_encode([
        'ok' => true,
        'message' =>
        'Patient mis à jour avec succès.',
        'data' => [
            'queue' => $queue,
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
        'message' =>
        'Impossible de modifier le patient.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}