<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/helpers/PatientDataNormalizer.php';
    require_once __DIR__ . '/../../app/repositories/PatientDirectoryRepository.php';
    require_once __DIR__ . '/../../app/repositories/QueueRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'message' => 'Méthode non autorisée.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    $input = is_array($decoded) ? $decoded : $_POST;
    $patientId = (int) ($input['patient_id'] ?? 0);

    if ($patientId <= 0) {
        throw new InvalidArgumentException('Patient invalide.');
    }

    $clinicId = (int) $context['clinic_id'];
    $doctorId = (int) $context['doctor_id'];
    $userId = (int) $context['user_id'];
    $today = (string) $context['today'];
    $pdo = $context['pdo'];

    $patientRepository = new PatientDirectoryRepository();
    $patient = $patientRepository->findByIdForDoctor(
        $patientId,
        $clinicId,
        $doctorId
    );

    $queueRepository = new QueueRepository();
    $queue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    if (($queue['day_status'] ?? 'active') !== 'active') {
        throw new RuntimeException(
            ($queue['day_status'] ?? '') === 'paused'
                ? 'La liste est en pause.'
                : 'La journée est clôturée.'
        );
    }

    if (
        ($queue['registration_status'] ?? 'open') !== 'open'
        || ($queue['status'] ?? 'open') !== 'open'
    ) {
        throw new RuntimeException('Les inscriptions sont fermées.');
    }

    $phone = PatientDataNormalizer::normalizePhone(
        (string) ($patient['phone'] ?? '')
    );

    if ($phone === '' || !PatientDataNormalizer::isValidPhone($phone)) {
        throw new InvalidArgumentException(
            PatientDataNormalizer::phoneValidationMessage()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Déterminer la source réelle de l'ajout
    |--------------------------------------------------------------------------
    | Un médecin seul possède généralement clinic_admin + doctor.
    | Une secrétaire possède secretary.
    |--------------------------------------------------------------------------
    */
    $roleSql = "
        SELECT r.code
        FROM user_roles ur
        INNER JOIN roles r
            ON r.id = ur.role_id
        WHERE ur.user_id = :user_id
    ";
    $roleStmt = $pdo->prepare($roleSql);
    $roleStmt->execute([':user_id' => $userId]);
    $roleCodes = array_column($roleStmt->fetchAll(), 'code');
    $source = in_array('doctor', $roleCodes, true)
        && !in_array('secretary', $roleCodes, true)
        ? 'doctor'
        : 'secretary';

    $pdo->beginTransaction();

    try {
        /*
        |--------------------------------------------------------------------------
        | Verrouiller la liste pour attribuer un N° d'arrivée sans collision
        |--------------------------------------------------------------------------
        */
        $lockSql = "
            SELECT id
            FROM queues
            WHERE id = :queue_id
              AND clinic_id = :clinic_id
              AND doctor_id = :doctor_id
            LIMIT 1
            FOR UPDATE
        ";
        $lockStmt = $pdo->prepare($lockSql);
        $lockStmt->execute([
            ':queue_id' => (int) $queue['id'],
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);

        if (!$lockStmt->fetch()) {
            throw new RuntimeException('Liste du jour introuvable.');
        }

        $existingSql = "
            SELECT status
            FROM queue_entries
            WHERE queue_id = :queue_id
              AND clinic_id = :clinic_id
              AND patient_id = :patient_id
            ORDER BY id DESC
            LIMIT 1
        ";
        $existingStmt = $pdo->prepare($existingSql);
        $existingStmt->execute([
            ':queue_id' => (int) $queue['id'],
            ':clinic_id' => $clinicId,
            ':patient_id' => $patientId,
        ]);
        $existing = $existingStmt->fetch();

        if ($existing) {
            $message = match ($existing['status']) {
                'waiting', 'called' => 'Ce patient est déjà dans la liste active.',
                'no_show' => 'Ce patient est absent. Utilisez Réintégrer dans la Liste du jour.',
                'done' => 'Ce patient a déjà été terminé aujourd’hui.',
                'canceled' => 'Cette inscription a été annulée aujourd’hui.',
                default => 'Ce patient possède déjà une inscription aujourd’hui.',
            };
            throw new RuntimeException($message);
        }

        $positionSql = "
            SELECT COALESCE(MAX(position_number), 0) + 1 AS next_position
            FROM queue_entries
            WHERE queue_id = :queue_id
        ";
        $positionStmt = $pdo->prepare($positionSql);
        $positionStmt->execute([
            ':queue_id' => (int) $queue['id'],
        ]);
        $positionNumber = (int) (
            $positionStmt->fetch()['next_position'] ?? 1
        );

        $insertSql = "
            INSERT INTO queue_entries (
                queue_id,
                clinic_id,
                patient_id,
                display_name,
                phone,
                birth_date,
                source,
                status,
                position_number,
                created_by_user_id,
                updated_by_user_id,
                created_at
            ) VALUES (
                :queue_id,
                :clinic_id,
                :patient_id,
                :display_name,
                :phone,
                :birth_date,
                :source,
                'waiting',
                :position_number,
                :created_by_user_id,
                :updated_by_user_id,
                NOW()
            )
        ";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':queue_id' => (int) $queue['id'],
            ':clinic_id' => $clinicId,
            ':patient_id' => $patientId,
            ':display_name' => (string) $patient['full_name'],
            ':phone' => $phone,
            ':birth_date' => $patient['birth_date'] !== null
                ? (string) $patient['birth_date']
                : null,
            ':source' => $source,
            ':position_number' => $positionNumber,
            ':created_by_user_id' => $userId,
            ':updated_by_user_id' => $userId,
        ]);

        $entryId = (int) $pdo->lastInsertId();

        $metadata = json_encode([
            'patient_id' => $patientId,
            'queue_id' => (int) $queue['id'],
            'doctor_id' => $doctorId,
            'source' => 'patients_tab',
            'position_number' => $positionNumber,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $logSql = "
            INSERT INTO activity_logs (
                clinic_id,
                actor_user_id,
                action,
                entity_type,
                entity_id,
                metadata_json,
                created_at
            ) VALUES (
                :clinic_id,
                :actor_user_id,
                'QUEUE_ENTRY_ADDED',
                'queue_entry',
                :entity_id,
                :metadata_json,
                NOW()
            )
        ";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':clinic_id' => $clinicId,
            ':actor_user_id' => $userId,
            ':entity_id' => $entryId,
            ':metadata_json' => $metadata,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Patient ajouté à la Liste du jour.',
        'data' => [
            'entry' => [
                'id' => $entryId,
                'queue_id' => (int) $queue['id'],
                'patient_id' => $patientId,
                'display_name' => (string) $patient['full_name'],
                'phone' => PatientDataNormalizer::formatPhoneForDisplay($phone),
                'birth_date' => $patient['birth_date'],
                'source' => $source,
                'status' => 'waiting',
                'position_number' => $positionNumber,
            ],
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
        'message' => 'Impossible d’ajouter le patient à la liste.',
    ], JSON_UNESCAPED_UNICODE);
}
