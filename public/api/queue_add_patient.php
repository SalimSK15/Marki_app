<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : ajouter un patient à la liste du jour
|--------------------------------------------------------------------------
| Règles :
| - nom obligatoire
| - téléphone obligatoire
| - date facultative
| - même nom + même téléphone = patient existant
| - même patient déjà waiting = refus
| - même nom + téléphone différent = autorisé
| - nom différent + même téléphone = confirmation familiale
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

    $source = trim(
        (string) ($input['source'] ?? 'secretary')
    );

    $allowSharedPhone = filter_var(
        $input['allow_shared_phone'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );

    /*
    |--------------------------------------------------------------------------
    | Validations
    |--------------------------------------------------------------------------
    */
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

    if (
        !in_array(
            $source,
            ['secretary', 'doctor', 'qr', 'link'],
            true
        )
    ) {
        $source = 'secretary';
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

    /*
    |--------------------------------------------------------------------------
    | Chercher une fiche ayant exactement le même nom et téléphone
    |--------------------------------------------------------------------------
    */
    $exactPatient =
        $patientRepository->findByPhoneAndName(
            $clinicId,
            $phone,
            $fullName
        );

    if ($exactPatient !== null) {
        /*
        |----------------------------------------------------------------------
        | Même nom + même téléphone = même patient
        |----------------------------------------------------------------------
        */
        $patient = $exactPatient;

        $existingWaitingEntry =
            $queueEntryRepository
            ->findWaitingByQueueAndPatientId(
                (int) $queue['id'],
                (int) $patient['id']
            );

        if ($existingWaitingEntry !== null) {
            http_response_code(409);

            echo json_encode([
                'ok' => false,
                'error_code' =>
                'PATIENT_ALREADY_WAITING',
                'message' =>
                'Ce patient est déjà en attente dans la liste du jour.',
                'data' => [
                    'existing_entry' =>
                    $existingWaitingEntry,
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    } else {
        /*
        |----------------------------------------------------------------------
        | Vérifier si le téléphone est utilisé par un autre nom
        |----------------------------------------------------------------------
        */
        $phoneOwner =
            $patientRepository->findByPhone(
                $clinicId,
                $phone
            );

        if (
            $phoneOwner !== null
            && !$allowSharedPhone
        ) {
            http_response_code(409);

            echo json_encode([
                'ok' => false,
                'error_code' =>
                'PHONE_SHARED_CONFIRMATION_REQUIRED',
                'message' => sprintf(
                    'Ce numéro est déjà utilisé par « %s ». Confirmez uniquement s’il s’agit d’un numéro familial partagé.',
                    $phoneOwner['full_name']
                ),
                'data' => [
                    'existing_patient' => [
                        'id' =>
                        (int) $phoneOwner['id'],
                        'full_name' =>
                        $phoneOwner['full_name'],
                        'phone' =>
                        $phoneOwner['phone'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        /*
        |----------------------------------------------------------------------
        | Téléphone libre ou partage familial confirmé
        |----------------------------------------------------------------------
        */
        $patient =
            $patientRepository->create(
                $clinicId,
                $fullName,
                $phone,
                $birthDate
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Ajouter le patient dans la liste
    |--------------------------------------------------------------------------
    */
    $createdEntry =
        $queueEntryRepository->create(
            (int) $queue['id'],
            $clinicId,
            (int) $patient['id'],
            $patient['full_name'],
            $patient['phone'],
            $patient['birth_date'] ?? null,
            $source,
            $userId
        );

    echo json_encode([
        'ok' => true,
        'message' =>
        'Patient ajouté à la liste du jour.',
        'data' => [
            'patient' => $patient,
            'queue' => $queue,
            'entry' => $createdEntry,
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
        'Impossible d’ajouter le patient.',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}