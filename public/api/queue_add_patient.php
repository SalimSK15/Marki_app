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
| - même patient déjà waiting/called = refus
| - même nom + téléphone différent = autorisé
| - nom différent + même téléphone = confirmation familiale
| - ajout interdit si les inscriptions sont fermées
| - ajout interdit si la liste est en pause ou clôturée
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');

$context = require __DIR__ . '/../../app/bootstrap.php';

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

    $clinicId = $context['clinic_id'];
    $doctorId = $context['doctor_id'];
    $userId = $context['user_id'];
    $today = $context['today'];

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);

    $input = is_array($jsonInput)
        ? $jsonInput
        : $_POST;

    $fullName = PatientDataNormalizer::normalizeName(
        (string) ($input['full_name'] ?? '')
    );

    $phone = PatientDataNormalizer::normalizePhone(
        (string) ($input['phone'] ?? '')
    );

    $birthDate = isset($input['birth_date'])
        ? trim((string) $input['birth_date'])
        : null;

    $source = trim((string) ($input['source'] ?? 'secretary'));

    $allowSharedPhone = filter_var(
        $input['allow_shared_phone'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );

    if ($fullName === '') {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Le nom complet est obligatoire.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($phone === '') {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => 'Le numéro de téléphone est obligatoire.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if (!PatientDataNormalizer::isValidPhone($phone)) {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => PatientDataNormalizer::phoneValidationMessage(),
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($birthDate !== null && $birthDate !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);

        if (!$date || $date->format('Y-m-d') !== $birthDate) {
            http_response_code(422);

            echo json_encode([
                'ok' => false,
                'message' => 'La date de naissance est invalide.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    } else {
        $birthDate = null;
    }

    if (!in_array($source, ['secretary', 'doctor', 'qr', 'link'], true)) {
        $source = 'secretary';
    }

    $queueRepository = new QueueRepository();
    $queueEntryRepository = new QueueEntryRepository();
    $patientRepository = new PatientRepository();

    $queue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    if ($queue['day_status'] === 'completed') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'error_code' => 'DAY_COMPLETED',
            'message' => 'La journée est clôturée. Aucune nouvelle inscription n’est possible.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($queue['day_status'] === 'paused') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'error_code' => 'DAY_PAUSED',
            'message' => 'La liste est temporairement en pause. Les nouvelles inscriptions sont désactivées.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($queue['registration_status'] !== 'open') {
        http_response_code(409);

        echo json_encode([
            'ok' => false,
            'error_code' => 'REGISTRATIONS_CLOSED',
            'message' => 'Les inscriptions sont fermées pour aujourd’hui.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $exactPatient = $patientRepository->findByPhoneAndName(
        $clinicId,
        $phone,
        $fullName
    );

    if ($exactPatient !== null) {
        $patient = $exactPatient;

        $existingWaitingEntry = $queueEntryRepository
            ->findWaitingByQueueAndPatientId(
                (int) $queue['id'],
                (int) $patient['id']
            );

        if ($existingWaitingEntry !== null) {
            http_response_code(409);

            echo json_encode([
                'ok' => false,
                'error_code' => 'PATIENT_ALREADY_WAITING',
                'message' => 'Ce patient est déjà en attente dans la liste du jour.',
                'data' => [
                    'existing_entry' => $existingWaitingEntry,
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    } else {
        $phoneOwner = $patientRepository->findByPhone(
            $clinicId,
            $phone
        );

        if ($phoneOwner !== null && !$allowSharedPhone) {
            http_response_code(409);

            echo json_encode([
                'ok' => false,
                'error_code' => 'PHONE_SHARED_CONFIRMATION_REQUIRED',
                'message' => sprintf(
                    'Ce numéro est déjà utilisé par « %s ». Confirmez uniquement s’il s’agit d’un numéro familial partagé.',
                    $phoneOwner['full_name']
                ),
                'data' => [
                    'existing_patient' => [
                        'id' => (int) $phoneOwner['id'],
                        'full_name' => $phoneOwner['full_name'],
                        'phone' => $phoneOwner['phone'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $patient = $patientRepository->create(
            $clinicId,
            $fullName,
            $phone,
            $birthDate
        );
    }

    $createdEntry = $queueEntryRepository->create(
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
        'message' => 'Patient ajouté à la liste du jour.',
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
        'message' => 'Impossible d’ajouter le patient.',
    ], JSON_UNESCAPED_UNICODE);
}
