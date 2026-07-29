<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Repository des entrées de la liste du jour
|--------------------------------------------------------------------------
| Gère les patients inscrits dans une queue :
| - lecture FIFO
| - compteurs
| - ajout
| - modification administrative
| - changement de statut
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/PatientDataNormalizer.php';

class QueueEntryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /*
    |--------------------------------------------------------------------------
    | Convertir une ligne SQL en tableau typé
    |--------------------------------------------------------------------------
    */
    private function mapEntryRow(array $row, ?int $fallbackNumber = null): array
    {
        $arrivalAt = !empty($row['last_rejoined_at'])
            ? (string) $row['last_rejoined_at']
            : (string) $row['created_at'];
        return [
            'id' => (int) $row['id'],
            'queue_id' => (int) $row['queue_id'],
            'clinic_id' => (int) $row['clinic_id'],
            'patient_id' => $row['patient_id'] !== null
                ? (int) $row['patient_id']
                : null,
            'number' => $row['position_number'] !== null
                ? (int) $row['position_number']
                : $fallbackNumber,
            'display_name' => $row['display_name'],
            'phone' => PatientDataNormalizer::formatPhoneForDisplay(
                $row['phone'] !== null
                    ? (string) $row['phone']
                    : null
            ),
            'birth_date' => $row['birth_date'],
            'source' => $row['source'],
            'status' => $row['status'],
            'status_before_completion' =>
            $row['status_before_completion'],
            'canceled_by_completion' =>
            (bool) $row['canceled_by_completion'],
            'patient_notes' => $row['patient_notes'] ?? null,
            'recent_visits' => [],
            'time' => date('H:i', strtotime($arrivalAt)),
            'arrival_at' => $arrivalAt,
            'position_number' => $row['position_number'] !== null
                ? (int) $row['position_number']
                : null,
            'created_at' => $row['created_at'],
            'called_at' => $row['called_at'],
            'done_at' => $row['done_at'],
            'canceled_at' => $row['canceled_at'],
            'cancellation_reason' => $row['cancellation_reason'],
            'no_show_at' => $row['no_show_at'],
            'last_rejoined_at' => $row['last_rejoined_at'],
            'created_by_user_id' => $row['created_by_user_id'] !== null
                ? (int) $row['created_by_user_id']
                : null,
            'updated_by_user_id' => $row['updated_by_user_id'] !== null
                ? (int) $row['updated_by_user_id']
                : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Colonnes SELECT réutilisées
    |--------------------------------------------------------------------------
    */
    private function entrySelectColumns(): string
    {
        return "
            qe.id,
            qe.queue_id,
            qe.clinic_id,
            qe.patient_id,
            qe.display_name,
            qe.phone,
            qe.birth_date,
            qe.source,
            qe.status,
            qe.status_before_completion,
            qe.canceled_by_completion,
            qe.position_number,
            qe.created_at,
            qe.called_at,
            qe.done_at,
            qe.canceled_at,
            qe.cancellation_reason,
            qe.no_show_at,
            qe.last_rejoined_at,
            qe.created_by_user_id,
            qe.updated_by_user_id,
            p.notes_non_medical AS patient_notes
        ";
    }

    /*
    |--------------------------------------------------------------------------
    | Récupérer toutes les entrées dans l'ordre FIFO
    |--------------------------------------------------------------------------
    */
    public function findByQueueId(int $queueId): array
    {
        $sql = "
            SELECT {$this->entrySelectColumns()}
            FROM queue_entries qe
            LEFT JOIN patients p
              ON p.id = qe.patient_id
             AND p.clinic_id = qe.clinic_id
            WHERE qe.queue_id = :queue_id
            ORDER BY
                CASE
                    WHEN qe.position_number IS NULL THEN 1
                    ELSE 0
                END,
                qe.position_number ASC,
                qe.created_at ASC,
                qe.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
        ]);

        $rows = $stmt->fetchAll();
        $entries = [];

        foreach ($rows as $index => $row) {
            $entries[] = $this->mapEntryRow($row, $index + 1);
        }

        return $this->attachRecentVisits($entries);
    }

    /*
    |--------------------------------------------------------------------------
    | Ajouter les trois dernières visites à chaque patient
    |--------------------------------------------------------------------------
    | Une seule requête est exécutée pour tous les patients affichés.
    | Les patients sans visite conservent un tableau vide.
    |--------------------------------------------------------------------------
    */
    private function attachRecentVisits(array $entries): array
    {
        $patientIds = [];

        foreach ($entries as $entry) {
            if ($entry['patient_id'] !== null) {
                $patientIds[] = (int) $entry['patient_id'];
            }
        }

        $patientIds = array_values(array_unique($patientIds));

        if ($patientIds === []) {
            return $entries;
        }

        $placeholders = implode(
            ', ',
            array_fill(0, count($patientIds), '?')
        );

        $sql = "
            SELECT
                v.id,
                v.patient_id,
                v.started_at,
                v.ended_at,
                v.status,
                v.created_at
            FROM visits v
            WHERE v.patient_id IN ($placeholders)
            ORDER BY
                v.patient_id ASC,
                COALESCE(v.ended_at, v.started_at, v.created_at) DESC,
                v.id DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($patientIds);

        $visitsByPatient = [];

        foreach ($stmt->fetchAll() as $row) {
            $patientId = (int) $row['patient_id'];

            if (count($visitsByPatient[$patientId] ?? []) >= 3) {
                continue;
            }

            $visitsByPatient[$patientId][] = [
                'id' => (int) $row['id'],
                'status' => $row['status'],
                'visit_at' => $row['ended_at']
                    ?? $row['started_at']
                    ?? $row['created_at'],
            ];
        }

        foreach ($entries as &$entry) {
            $patientId = $entry['patient_id'];

            $entry['recent_visits'] = $patientId !== null
                ? ($visitsByPatient[(int) $patientId] ?? [])
                : [];
        }

        unset($entry);

        return $entries;
    }

    /*
    |--------------------------------------------------------------------------
    | Compter les entrées par statut
    |--------------------------------------------------------------------------
    */
    public function countByStatus(int $queueId): array
    {
        $sql = "
            SELECT
                qe.status,
                COUNT(*) AS total
            FROM queue_entries qe
            WHERE qe.queue_id = :queue_id
            GROUP BY qe.status
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
        ]);

        $rows = $stmt->fetchAll();

        $counts = [
            'waiting' => 0,
            'called' => 0,
            'absent' => 0,
            'done' => 0,
            'canceled' => 0,
            'total' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $total = (int) $row['total'];

            $counts['total'] += $total;

            if ($status === 'waiting') {
                $counts['waiting'] = $total;
            } elseif ($status === 'called') {
                $counts['called'] = $total;
            } elseif ($status === 'no_show') {
                $counts['absent'] = $total;
            } elseif ($status === 'done') {
                $counts['done'] = $total;
            } elseif ($status === 'canceled') {
                $counts['canceled'] = $total;
            }
        }

        return $counts;
    }

    /*
    |--------------------------------------------------------------------------
    | Récupérer une entrée précise
    |--------------------------------------------------------------------------
    */
    public function findById(int $entryId, int $clinicId): ?array
    {
        $sql = "
            SELECT {$this->entrySelectColumns()}
            FROM queue_entries qe
            LEFT JOIN patients p
              ON p.id = qe.patient_id
             AND p.clinic_id = qe.clinic_id
            WHERE qe.id = :id
              AND qe.clinic_id = :clinic_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $entryId,
            ':clinic_id' => $clinicId,
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $entries = $this->attachRecentVisits([
            $this->mapEntryRow($row),
        ]);

        return $entries[0] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Récupérer la prochaine position disponible
    |--------------------------------------------------------------------------
    */
    private function getNextPositionNumber(int $queueId): int
    {
        $sql = "
            SELECT COALESCE(MAX(qe.position_number), 0) + 1 AS next_position
            FROM queue_entries qe
            WHERE qe.queue_id = :queue_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
        ]);

        $row = $stmt->fetch();

        return (int) ($row['next_position'] ?? 1);
    }
    /*
    |--------------------------------------------------------------------------
    | Déterminer le N° d'arrivée d'un patient absent qui revient
    |--------------------------------------------------------------------------
    | Règles :
    |
    | - s'il était déjà le dernier patient actif, il conserve son numéro ;
    | - si d'autres patients actifs se trouvent après lui, il reçoit
    |   le prochain N° d'arrivée disponible ;
    | - les patients terminés, annulés ou absents ne sont pas considérés
    |   comme étant encore actifs dans la file.
    |--------------------------------------------------------------------------
    */
    private function getReturnPositionNumber(int $queueId, int $entryId, ?int $currentPosition
    ): int {
        /*
        |--------------------------------------------------------------------------
        | Ancienne inscription sans numéro
        |--------------------------------------------------------------------------
        */
        if ($currentPosition === null) {
            return $this->getNextPositionNumber(
                $queueId
            );
        }

        $sql = "
            SELECT EXISTS (
                SELECT 1
                FROM queue_entries qe
                WHERE qe.queue_id = :queue_id
                AND qe.id <> :entry_id
                AND qe.status IN (
                    'waiting',
                    'called'
                )
                AND qe.position_number
                    > :current_position
            ) AS has_active_patient_after
        ";

        $stmt =
            $this->pdo->prepare($sql);

        $stmt->execute([
            ':queue_id' => $queueId,
            ':entry_id' => $entryId,
            ':current_position' =>
                $currentPosition,
        ]);

        $row = $stmt->fetch();

        $hasActivePatientAfter =
            (int) (
                $row['has_active_patient_after']
                ?? 0
            ) === 1;

        /*
        |--------------------------------------------------------------------------
        | Aucun patient actif ne se trouve après lui
        |--------------------------------------------------------------------------
        */
        if (!$hasActivePatientAfter) {
            return $currentPosition;
        }

        /*
        |--------------------------------------------------------------------------
        | Il doit réellement repartir à la fin
        |--------------------------------------------------------------------------
        */
        return $this->getNextPositionNumber(
            $queueId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mettre à jour le statut d'une entrée
    |--------------------------------------------------------------------------
    | Transitions V1 :
    | - waiting/called -> done | no_show | canceled
    | - no_show        -> waiting, à la fin de la file
    | - done/canceled  -> états finaux
    |--------------------------------------------------------------------------
    */
    public function updateStatus(
        int $entryId,
        int $clinicId,
        string $newStatus,
        int $updatedByUserId,
        ?string $cancellationReason = null
    ): array {
        $allowedStatuses = [
            'waiting',
            'done',
            'no_show',
            'canceled',
        ];

        $allowedCancellationReasons = [
            'patient_request',
            'registration_error',
            'doctor_unavailable',
            'end_of_day',
            'other',
        ];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Statut invalide.');
        }

        $existingEntry = $this->findById($entryId, $clinicId);

        if ($existingEntry === null) {
            throw new RuntimeException('Entrée introuvable.');
        }

        $currentStatus = $existingEntry['status'];

        if ($currentStatus === $newStatus) {
            return $existingEntry;
        }

        if ($currentStatus === 'done') {
            throw new InvalidArgumentException(
                'Un patient terminé ne peut plus changer de statut.'
            );
        }

        if ($currentStatus === 'canceled') {
            throw new InvalidArgumentException(
                'Une inscription annulée ne peut pas être réactivée dans la V1.'
            );
        }

        if (
            $newStatus === 'waiting'
            && $currentStatus !== 'no_show'
        ) {
            throw new InvalidArgumentException(
                'Seul un patient absent peut être remis en attente.'
            );
        }

        if (
            in_array($newStatus, ['done', 'no_show', 'canceled'], true)
            && !in_array($currentStatus, ['waiting', 'called'], true)
        ) {
            throw new InvalidArgumentException(
                'Cette transition de statut n’est pas autorisée.'
            );
        }

        if ($newStatus === 'canceled') {
            $cancellationReason = $cancellationReason ?: 'other';

            if (!in_array($cancellationReason, $allowedCancellationReasons, true)) {
                throw new InvalidArgumentException(
                    'Raison d’annulation invalide.'
                );
            }
        } else {
            $cancellationReason = null;
        }

        $positionNumber = $existingEntry['position_number'];

        $calledAt = $existingEntry['called_at'];
        $doneAt = null;
        $noShowAt = $existingEntry['no_show_at'];
        $canceledAt = null;

        $lastRejoinedAt = $existingEntry['last_rejoined_at'];

        if ($newStatus === 'done') {
            $doneAt = date('Y-m-d H:i:s');
        } elseif ($newStatus === 'no_show') {
            $noShowAt = date('Y-m-d H:i:s');
        } elseif ($newStatus === 'canceled') {
            $canceledAt = date('Y-m-d H:i:s');
        } elseif ($newStatus === 'waiting') {
            /*
            |--------------------------------------------------------------------------
            | Réintégrer un patient absent
            |--------------------------------------------------------------------------
            | - conserver son numéro s'il était déjà le dernier ;
            | - sinon lui attribuer le prochain N° d'arrivée ;
            | - enregistrer l'heure réelle de son retour ;
            | - supprimer une éventuelle heure d'appel.
            |--------------------------------------------------------------------------
            */
            $positionNumber = $this->getReturnPositionNumber(
                (int) $existingEntry['queue_id'],
                $entryId,
                $existingEntry['position_number']
            );
            $calledAt = null;
            $lastRejoinedAt = date('Y-m-d H:i:s');
        }

        $sql = "
            UPDATE queue_entries
            SET
                status = :status,
                position_number = :position_number,
                called_at = :called_at,
                done_at = :done_at,
                no_show_at = :no_show_at,
                last_rejoined_at = :last_rejoined_at,
                canceled_at = :canceled_at,
                cancellation_reason = :cancellation_reason,
                updated_by_user_id = :updated_by_user_id
            WHERE id = :id
                AND clinic_id = :clinic_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':status' => $newStatus,
            ':position_number' => $positionNumber,
            ':called_at' => $calledAt,
            ':done_at' => $doneAt,
            ':no_show_at' => $noShowAt,
            ':last_rejoined_at' => $lastRejoinedAt,
            ':canceled_at' => $canceledAt,
            ':cancellation_reason' => $cancellationReason,
            ':updated_by_user_id' => $updatedByUserId,
            ':id' => $entryId,
            ':clinic_id' => $clinicId,
        ]);

        $updatedEntry = $this->findById($entryId, $clinicId);

        if ($updatedEntry === null) {
            throw new RuntimeException(
                'Impossible de récupérer l’entrée après mise à jour.'
            );
        }

        return $updatedEntry;
    }

    /*
    |--------------------------------------------------------------------------
    | Chercher le même patient déjà en attente dans la queue
    |--------------------------------------------------------------------------
    */
    public function findWaitingByQueueAndPatientId(
        int $queueId,
        int $patientId
    ): ?array {
        $sql = "
            SELECT {$this->entrySelectColumns()}
            FROM queue_entries qe
            LEFT JOIN patients p
              ON p.id = qe.patient_id
             AND p.clinic_id = qe.clinic_id
            WHERE qe.queue_id = :queue_id
              AND qe.patient_id = :patient_id
              AND qe.status IN ('waiting', 'called')
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':patient_id' => $patientId,
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $entries = $this->attachRecentVisits([
            $this->mapEntryRow($row),
        ]);

        return $entries[0] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Créer une nouvelle entrée dans la liste
    |--------------------------------------------------------------------------
    */
    public function create(
        int $queueId,
        int $clinicId,
        ?int $patientId,
        string $displayName,
        ?string $phone,
        ?string $birthDate,
        string $source,
        int $createdByUserId
    ): array {
        $displayName = PatientDataNormalizer::normalizeName($displayName);
        $phone = PatientDataNormalizer::normalizePhone($phone ?? '');
        $birthDate = $birthDate !== null ? trim($birthDate) : null;
        $source = trim($source);

        if ($displayName === '') {
            throw new InvalidArgumentException(
                'Le nom complet est obligatoire.'
            );
        }

        if ($phone === '') {
            throw new InvalidArgumentException(
                'Le numéro de téléphone est obligatoire.'
            );
        }

        if (!PatientDataNormalizer::isValidPhone($phone)) {
            throw new InvalidArgumentException(
                PatientDataNormalizer::phoneValidationMessage()
            );
        }

        if ($birthDate === '') {
            $birthDate = null;
        }

        if (!in_array($source, ['secretary', 'doctor', 'qr', 'link'], true)) {
            $source = 'secretary';
        }

        $positionNumber = $this->getNextPositionNumber($queueId);

        $sql = "
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

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':clinic_id' => $clinicId,
            ':patient_id' => $patientId,
            ':display_name' => $displayName,
            ':phone' => $phone,
            ':birth_date' => $birthDate,
            ':source' => $source,
            ':position_number' => $positionNumber,
            ':created_by_user_id' => $createdByUserId,
            ':updated_by_user_id' => $createdByUserId,
        ]);

        $entryId = (int) $this->pdo->lastInsertId();
        $createdEntry = $this->findById($entryId, $clinicId);

        if ($createdEntry === null) {
            throw new RuntimeException(
                'Impossible de récupérer l’entrée après création.'
            );
        }

        return $createdEntry;
    }

    /*
    |--------------------------------------------------------------------------
    | Corriger l'identité affichée dans la liste
    |--------------------------------------------------------------------------
    */
    public function updatePatientIdentity(
        int $entryId,
        int $clinicId,
        string $displayName,
        ?string $phone,
        ?string $birthDate,
        int $updatedByUserId
    ): array {
        $displayName = PatientDataNormalizer::normalizeName($displayName);
        $phone = PatientDataNormalizer::normalizePhone($phone ?? '');
        $birthDate = $birthDate !== null ? trim($birthDate) : null;

        if ($displayName === '') {
            throw new InvalidArgumentException(
                'Le nom complet est obligatoire.'
            );
        }

        if ($phone === '') {
            throw new InvalidArgumentException(
                'Le numéro de téléphone est obligatoire.'
            );
        }

        if (!PatientDataNormalizer::isValidPhone($phone)) {
            throw new InvalidArgumentException(
                PatientDataNormalizer::phoneValidationMessage()
            );
        }

        if ($birthDate === '') {
            $birthDate = null;
        }

        $sql = "
            UPDATE queue_entries
            SET
                display_name = :display_name,
                phone = :phone,
                birth_date = :birth_date,
                updated_by_user_id = :updated_by_user_id
            WHERE id = :id
              AND clinic_id = :clinic_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':display_name' => $displayName,
            ':phone' => $phone,
            ':birth_date' => $birthDate,
            ':updated_by_user_id' => $updatedByUserId,
            ':id' => $entryId,
            ':clinic_id' => $clinicId,
        ]);

        $updatedEntry = $this->findById($entryId, $clinicId);

        if ($updatedEntry === null) {
            throw new RuntimeException(
                'Impossible de récupérer l’entrée après mise à jour.'
            );
        }

        return $updatedEntry;
    }
    /*
    |--------------------------------------------------------------------------
    | Enregistrer une action dans le journal d'activité
    |--------------------------------------------------------------------------
    | Cette méthode utilise la même connexion PDO que la transaction en cours.
    | L'action est donc annulée automatiquement si la visite échoue.
    |--------------------------------------------------------------------------
    */
    private function createActivityLog(
        int $clinicId,
        int $actorUserId,
        string $action,
        string $entityType,
        int $entityId,
        array $metadata
    ): void {
        $sql = "
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
                :action,
                :entity_type,
                :entity_id,
                :metadata_json,
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':actor_user_id' => $actorUserId,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':metadata_json' => json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Marquer un patient comme terminé et enregistrer sa visite
    |--------------------------------------------------------------------------
    | Cette opération est entièrement transactionnelle :
    |
    | 1. verrouiller l'inscription ;
    | 2. passer queue_entries.status à "done" ;
    | 3. enregistrer l'utilisateur ayant réalisé l'action ;
    | 4. créer ou terminer la visite ;
    | 5. enregistrer VISIT_COMPLETED dans activity_logs ;
    | 6. valider toutes les opérations ensemble.
    |
    | Dans la V1, aucune heure de début n'est inventée :
    | started_at et started_by_user_id restent NULL si aucune valeur réelle
    | n'existait déjà.
    |--------------------------------------------------------------------------
    */
    public function markDoneAndCreateVisit(
        int $entryId,
        int $clinicId,
        int $doctorId,
        int $updatedByUserId
    ): array {
        $this->pdo->beginTransaction();

        try {
            /*
            |----------------------------------------------------------------------
            | Verrouiller l'inscription
            |----------------------------------------------------------------------
            | Le verrou empêche deux clics simultanés de créer deux visites.
            |----------------------------------------------------------------------
            */
            $entrySql = "
                SELECT
                    qe.id,
                    qe.queue_id,
                    qe.clinic_id,
                    qe.patient_id,
                    qe.status,
                    qe.done_at
                FROM queue_entries qe
                WHERE qe.id = :entry_id
                  AND qe.clinic_id = :clinic_id
                LIMIT 1
                FOR UPDATE
            ";

            $entryStmt = $this->pdo->prepare($entrySql);
            $entryStmt->execute([
                ':entry_id' => $entryId,
                ':clinic_id' => $clinicId,
            ]);

            $entry = $entryStmt->fetch();

            if (!$entry) {
                throw new RuntimeException('Entrée introuvable.');
            }

            if (
                !in_array(
                    $entry['status'],
                    ['waiting', 'called', 'done'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Ce patient ne peut pas être marqué comme terminé.'
                );
            }

            $previousQueueStatus = (string) $entry['status'];
            $isNewCompletion = $previousQueueStatus !== 'done';
            $doneAt = $entry['done_at'] ?: date('Y-m-d H:i:s');

            /*
            |----------------------------------------------------------------------
            | Mettre à jour l'inscription dans la file
            |----------------------------------------------------------------------
            */
            if ($isNewCompletion) {
                $updateEntrySql = "
                    UPDATE queue_entries
                    SET
                        status = 'done',
                        done_at = :done_at,
                        no_show_at = NULL,
                        canceled_at = NULL,
                        cancellation_reason = NULL,
                        updated_by_user_id = :updated_by_user_id
                    WHERE id = :entry_id
                      AND clinic_id = :clinic_id
                    LIMIT 1
                ";

                $updateEntryStmt = $this->pdo->prepare($updateEntrySql);
                $updateEntryStmt->execute([
                    ':done_at' => $doneAt,
                    ':updated_by_user_id' => $updatedByUserId,
                    ':entry_id' => $entryId,
                    ':clinic_id' => $clinicId,
                ]);
            }

            /*
            |----------------------------------------------------------------------
            | Chercher une éventuelle visite déjà liée à l'inscription
            |----------------------------------------------------------------------
            */
            $visitSql = "
                SELECT
                    v.id,
                    v.status,
                    v.created_by_user_id,
                    v.completed_by_user_id
                FROM visits v
                WHERE v.queue_entry_id = :queue_entry_id
                LIMIT 1
                FOR UPDATE
            ";

            $visitStmt = $this->pdo->prepare($visitSql);
            $visitStmt->execute([
                ':queue_entry_id' => $entryId,
            ]);

            $visit = $visitStmt->fetch();

            if ($visit) {
                /*
                |------------------------------------------------------------------
                | Terminer une visite existante
                |------------------------------------------------------------------
                | COALESCE conserve l'auteur original si la valeur existe déjà.
                | Un deuxième clic ne remplace donc pas l'utilisateur ayant
                | réellement terminé la visite.
                |------------------------------------------------------------------
                */
                $updateVisitSql = "
                    UPDATE visits
                    SET
                        clinic_id = :clinic_id,
                        doctor_id = :doctor_id,
                        patient_id = :patient_id,
                        ended_at = COALESCE(ended_at, :ended_at),
                        status = 'done',
                        created_by_user_id = COALESCE(
                            created_by_user_id,
                            :created_by_user_id
                        ),
                        completed_by_user_id = COALESCE(
                            completed_by_user_id,
                            :completed_by_user_id
                        ),
                        updated_at = NOW()
                    WHERE id = :visit_id
                    LIMIT 1
                ";

                $updateVisitStmt = $this->pdo->prepare($updateVisitSql);
                $updateVisitStmt->execute([
                    ':clinic_id' => $clinicId,
                    ':doctor_id' => $doctorId,
                    ':patient_id' => $entry['patient_id'],
                    ':ended_at' => $doneAt,
                    ':created_by_user_id' => $updatedByUserId,
                    ':completed_by_user_id' => $updatedByUserId,
                    ':visit_id' => (int) $visit['id'],
                ]);

                $visitId = (int) $visit['id'];
            } else {
                /*
                |------------------------------------------------------------------
                | Créer directement une visite terminée pour la V1
                |------------------------------------------------------------------
                | La même personne crée et termine la visite au même clic.
                | started_at et started_by_user_id restent NULL.
                |------------------------------------------------------------------
                */
                $insertVisitSql = "
                    INSERT INTO visits (
                        clinic_id,
                        doctor_id,
                        patient_id,
                        queue_entry_id,
                        appointment_id,
                        started_at,
                        ended_at,
                        status,
                        created_by_user_id,
                        started_by_user_id,
                        completed_by_user_id,
                        canceled_by_user_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        :clinic_id,
                        :doctor_id,
                        :patient_id,
                        :queue_entry_id,
                        NULL,
                        NULL,
                        :ended_at,
                        'done',
                        :created_by_user_id,
                        NULL,
                        :completed_by_user_id,
                        NULL,
                        NOW(),
                        NOW()
                    )
                ";

                $insertVisitStmt = $this->pdo->prepare($insertVisitSql);
                $insertVisitStmt->execute([
                    ':clinic_id' => $clinicId,
                    ':doctor_id' => $doctorId,
                    ':patient_id' => $entry['patient_id'],
                    ':queue_entry_id' => $entryId,
                    ':ended_at' => $doneAt,
                    ':created_by_user_id' => $updatedByUserId,
                    ':completed_by_user_id' => $updatedByUserId,
                ]);

                $visitId = (int) $this->pdo->lastInsertId();
            }

            /*
            |----------------------------------------------------------------------
            | Journaliser seulement la première transition vers "done"
            |----------------------------------------------------------------------
            | Une répétition de la requête ne crée pas un deuxième événement.
            |----------------------------------------------------------------------
            */
            if ($isNewCompletion) {
                $this->createActivityLog(
                    $clinicId,
                    $updatedByUserId,
                    'VISIT_COMPLETED',
                    'visit',
                    $visitId,
                    [
                        'queue_id' => (int) $entry['queue_id'],
                        'queue_entry_id' => $entryId,
                        'patient_id' => $entry['patient_id'] !== null
                            ? (int) $entry['patient_id']
                            : null,
                        'doctor_id' => $doctorId,
                        'previous_queue_status' => $previousQueueStatus,
                        'new_queue_status' => 'done',
                    ]
                );
            }

            /*
            |----------------------------------------------------------------------
            | Relire la visite complète
            |----------------------------------------------------------------------
            */
            $createdVisitSql = "
                SELECT
                    id,
                    clinic_id,
                    doctor_id,
                    patient_id,
                    queue_entry_id,
                    appointment_id,
                    started_at,
                    ended_at,
                    status,
                    created_by_user_id,
                    started_by_user_id,
                    completed_by_user_id,
                    canceled_by_user_id,
                    created_at,
                    updated_at
                FROM visits
                WHERE id = :visit_id
                LIMIT 1
            ";

            $createdVisitStmt = $this->pdo->prepare($createdVisitSql);
            $createdVisitStmt->execute([
                ':visit_id' => $visitId,
            ]);

            $createdVisit = $createdVisitStmt->fetch();

            if (!$createdVisit) {
                throw new RuntimeException(
                    'Impossible de récupérer la visite enregistrée.'
                );
            }

            $updatedEntry = $this->findById($entryId, $clinicId);

            if ($updatedEntry === null) {
                throw new RuntimeException(
                    'Impossible de récupérer l’inscription terminée.'
                );
            }

            $this->pdo->commit();

            return [
                'entry' => $updatedEntry,
                'visit' => [
                    'id' => (int) $createdVisit['id'],
                    'clinic_id' => (int) $createdVisit['clinic_id'],
                    'doctor_id' => (int) $createdVisit['doctor_id'],
                    'patient_id' => $createdVisit['patient_id'] !== null
                        ? (int) $createdVisit['patient_id']
                        : null,
                    'queue_entry_id' => $createdVisit['queue_entry_id'] !== null
                        ? (int) $createdVisit['queue_entry_id']
                        : null,
                    'appointment_id' => $createdVisit['appointment_id'] !== null
                        ? (int) $createdVisit['appointment_id']
                        : null,
                    'started_at' => $createdVisit['started_at'],
                    'ended_at' => $createdVisit['ended_at'],
                    'status' => $createdVisit['status'],
                    'created_by_user_id' =>
                        $createdVisit['created_by_user_id'] !== null
                            ? (int) $createdVisit['created_by_user_id']
                            : null,
                    'started_by_user_id' =>
                        $createdVisit['started_by_user_id'] !== null
                            ? (int) $createdVisit['started_by_user_id']
                            : null,
                    'completed_by_user_id' =>
                        $createdVisit['completed_by_user_id'] !== null
                            ? (int) $createdVisit['completed_by_user_id']
                            : null,
                    'canceled_by_user_id' =>
                        $createdVisit['canceled_by_user_id'] !== null
                            ? (int) $createdVisit['canceled_by_user_id']
                            : null,
                    'created_at' => $createdVisit['created_at'],
                    'updated_at' => $createdVisit['updated_at'],
                ],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
