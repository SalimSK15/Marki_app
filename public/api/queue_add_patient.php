<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : queue_add_patient.php
|--------------------------------------------------------------------------
| Rôle :
| - valider les données envoyées
| - normaliser le nom
| - retrouver ou créer le patient
| - gérer les cas métier de doublon
| - ajouter l'entrée dans la liste du jour
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

/*
|--------------------------------------------------------------------------
| Normaliser un nom de personne
|--------------------------------------------------------------------------
*/
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

    $fullName = normalizePersonName((string) ($input['full_name'] ?? $input['display_name'] ?? ''));
    $phone = isset($input['phone']) ? trim((string) $input['phone']) : null;
    $birthDate = isset($input['birth_date']) ? trim((string) $input['birth_date']) : null;
    $source = isset($input['source']) ? trim((string) $input['source']) : 'secretary';

    /*
    |--------------------------------------------------------------------------
    | Permettre une confirmation explicite côté front plus tard
    |--------------------------------------------------------------------------
    | Exemple d'usage futur :
    | - téléphone déjà utilisé par une autre personne
    | - la secrétaire confirme manuellement
    |--------------------------------------------------------------------------
    */
    $allowSharedPhone = (bool) ($input['allow_shared_phone'] ?? false);

    if ($fullName === '') {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Le nom complet est obligatoire.',
            'errors' => [
                'full_name' => 'Le nom complet est obligatoire.',
            ],
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
                'errors' => [
                    'birth_date' => 'Format attendu : YYYY-MM-DD.',
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    } else {
        $birthDate = null;
    }

    $patientRepository = new PatientRepository();
    $queueRepository = new QueueRepository();
    $queueEntryRepository = new QueueEntryRepository();

    /*
    |--------------------------------------------------------------------------
    | Récupérer ou créer la liste du jour
    |--------------------------------------------------------------------------
    */
    $queue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    /*
    |--------------------------------------------------------------------------
    | Bloquer si la liste est fermée
    |--------------------------------------------------------------------------
    */
    if (($queue['status'] ?? '') !== 'open') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'message' => 'La liste du jour est fermée. Impossible d’ajouter un nouveau patient.',
            'data' => [
                'queue' => $queue,
            ],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CAS 1 : téléphone présent
    |--------------------------------------------------------------------------
    | On regarde d'abord s'il existe déjà un patient avec ce numéro.
    |--------------------------------------------------------------------------
    */
    $existingPatientByPhone = null;

    if ($phone !== null) {
        $existingPatientByPhone = $patientRepository->findByPhone($clinicId, $phone);
    }

    if ($existingPatientByPhone !== null) {
        /*
        |--------------------------------------------------------------
        | Même téléphone + nom différent
        |--------------------------------------------------------------
        | On ne bloque pas définitivement.
        | On demande une confirmation explicite future côté front.
        */
        if (!$patientRepository->sameNormalizedName($existingPatientByPhone['full_name'], $fullName)) {
            if (!$allowSharedPhone) {
                http_response_code(409);

                echo json_encode([
                    'ok' => false,
                    'message' => 'Ce numéro de téléphone est déjà utilisé par un autre patient. Si c’est normal (parent/enfant, famille), confirme l’ajout.',
                    'error_code' => 'PHONE_ALREADY_USED_BY_ANOTHER_PATIENT',
                    'data' => [
                        'existing_patient' => [
                            'id' => (int) $existingPatientByPhone['id'],
                            'full_name' => $existingPatientByPhone['full_name'],
                            'phone' => $existingPatientByPhone['phone'],
                            'birth_date' => $existingPatientByPhone['birth_date'],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE);

                exit;
            }

            /*
            |----------------------------------------------------------
            | Si confirmé, on crée un nouveau patient malgré le téléphone partagé
            |----------------------------------------------------------
            */
            $patient = $patientRepository->create(
                $clinicId,
                $fullName,
                $phone,
                $birthDate
            );
        } else {
            /*
            |----------------------------------------------------------
            | Même téléphone + même nom => on considère même patient
            |----------------------------------------------------------
            */
            $patient = $existingPatientByPhone;
        }
    } else {
        /*
        |--------------------------------------------------------------
        | CAS 2 : pas de téléphone déjà trouvé
        |--------------------------------------------------------------
        | On essaie la logique standard de rapprochement
        */
        $existingPatient = $patientRepository->findExisting(
            $clinicId,
            $phone,
            $fullName,
            $birthDate
        );

        $patient = $existingPatient ?: $patientRepository->create(
            $clinicId,
            $fullName,
            $phone,
            $birthDate
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bloquer si le même patient est déjà en attente dans la liste du jour
    |--------------------------------------------------------------------------
    */
    if (!empty($patient['id'])) {
        $existingWaitingEntry = $queueEntryRepository->findWaitingByQueueAndPatientId(
            (int) $queue['id'],
            (int) $patient['id']
        );

        if ($existingWaitingEntry !== null) {
            http_response_code(409);

            echo json_encode([
                'ok' => false,
                'message' => 'Ce patient est déjà inscrit aujourd’hui avec ce numéro de téléphone.',
                'data' => [
                    'patient' => $patient,
                    'queue' => $queue,
                    'existing_entry' => $existingWaitingEntry,
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CAS 3 : pas de téléphone
    |--------------------------------------------------------------------------
    | Si aucun téléphone n'est fourni, on bloque au moins le même nom
    | déjà en attente aujourd'hui.
    |--------------------------------------------------------------------------
    */
    if ($phone === null) {
        $existingSameNameEntry = $queueEntryRepository->findWaitingByQueueAndDisplayName(
            (int) $queue['id'],
            $fullName
        );

        if ($existingSameNameEntry !== null) {
            http_response_code(409);

            echo json_encode([
                'ok' => false,
                'message' => 'Un patient avec le même nom est déjà présent aujourd’hui. Ajoute un numéro ou une date de naissance pour mieux distinguer les patients.',
                'error_code' => 'SAME_NAME_ALREADY_WAITING_TODAY',
                'data' => [
                    'queue' => $queue,
                    'existing_entry' => $existingSameNameEntry,
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Créer l'entrée dans la queue du jour
    |--------------------------------------------------------------------------
    */
    $createdEntry = $queueEntryRepository->create(
        (int) $queue['id'],
        $clinicId,
        (int) $patient['id'],
        $patient['full_name'],
        $patient['phone'] ?? null,
        $patient['birth_date'] ?? null,
        $source !== '' ? $source : 'secretary',
        $userId
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Patient ajouté à la liste du jour avec succès.',
        'data' => [
            'patient' => $patient,
            'queue' => $queue,
            'entry' => $createdEntry,
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
        'message' => 'Impossible d’ajouter le patient à la liste du jour.',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}