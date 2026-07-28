<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/PatientDataNormalizer.php';

final class QueueHistoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function paginate(
        int $clinicId,
        int $doctorId,
        ?string $dateFrom,
        ?string $dateTo,
        string $dayStatus,
        int $page,
        int $perPage
    ): array {
        $page = max(1, $page);
        $perPage = in_array($perPage, [12, 24, 48], true)
            ? $perPage
            : 12;
        $offset = ($page - 1) * $perPage;

        $where = [
            'q.clinic_id = :clinic_id',
            'q.doctor_id = :doctor_id',
        ];
        $params = [
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ];

        if ($dateFrom !== null) {
            $where[] = 'q.queue_date >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $where[] = 'q.queue_date <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        if (in_array($dayStatus, ['active', 'paused', 'completed'], true)) {
            $where[] = 'q.day_status = :day_status';
            $params[':day_status'] = $dayStatus;
        }

        $whereSql = implode("\n AND ", $where);

        $countSql = "
            SELECT COUNT(*) AS total
            FROM queues q
            WHERE {$whereSql}
        ";

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $sql = "
            SELECT
                q.id,
                q.queue_date,
                q.registration_status,
                q.day_status,
                q.status,
                q.opened_at,
                q.closed_at,
                q.paused_at,
                q.resumed_at,
                q.completed_at,
                q.created_at,
                COALESCE(COUNT(qe.id), 0) AS total_entries,
                COALESCE(SUM(qe.status = 'waiting'), 0) AS waiting_count,
                COALESCE(SUM(qe.status = 'called'), 0) AS called_count,
                COALESCE(SUM(qe.status = 'done'), 0) AS done_count,
                COALESCE(SUM(qe.status = 'no_show'), 0) AS no_show_count,
                COALESCE(SUM(qe.status = 'canceled'), 0) AS canceled_count,
                completed_user.full_name AS completed_by_name,
                closed_user.full_name AS closed_by_name
            FROM queues q
            LEFT JOIN queue_entries qe
                ON qe.queue_id = q.id
            LEFT JOIN users completed_user
                ON completed_user.id = q.completed_by_user_id
            LEFT JOIN users closed_user
                ON closed_user.id = q.closed_by_user_id
            WHERE {$whereSql}
            GROUP BY
                q.id,
                q.queue_date,
                q.registration_status,
                q.day_status,
                q.status,
                q.opened_at,
                q.closed_at,
                q.paused_at,
                q.resumed_at,
                q.completed_at,
                q.created_at,
                completed_user.full_name,
                closed_user.full_name
            ORDER BY q.queue_date DESC, q.id DESC
            LIMIT :limit_rows OFFSET :offset_rows
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit_rows', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset_rows', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(
            static function (array $row): array {
                return [
                    'id' => (int) $row['id'],
                    'queue_date' => $row['queue_date'],
                    'registration_status' => $row['registration_status'],
                    'day_status' => $row['day_status'],
                    'status' => $row['status'],
                    'opened_at' => $row['opened_at'],
                    'closed_at' => $row['closed_at'],
                    'paused_at' => $row['paused_at'],
                    'resumed_at' => $row['resumed_at'],
                    'completed_at' => $row['completed_at'],
                    'created_at' => $row['created_at'],
                    'total_entries' => (int) $row['total_entries'],
                    'waiting_count' => (int) $row['waiting_count'],
                    'called_count' => (int) $row['called_count'],
                    'done_count' => (int) $row['done_count'],
                    'no_show_count' => (int) $row['no_show_count'],
                    'canceled_count' => (int) $row['canceled_count'],
                    'completed_by_name' => $row['completed_by_name'],
                    'closed_by_name' => $row['closed_by_name'],
                ];
            },
            $stmt->fetchAll()
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function findDetails(
        int $queueId,
        int $clinicId,
        int $doctorId
    ): array {
        $queueSql = "
            SELECT
                q.*,
                opened_user.full_name AS opened_by_name,
                closed_user.full_name AS closed_by_name,
                paused_user.full_name AS paused_by_name,
                completed_user.full_name AS completed_by_name
            FROM queues q
            LEFT JOIN users opened_user
                ON opened_user.id = q.opened_by_user_id
            LEFT JOIN users closed_user
                ON closed_user.id = q.closed_by_user_id
            LEFT JOIN users paused_user
                ON paused_user.id = q.paused_by_user_id
            LEFT JOIN users completed_user
                ON completed_user.id = q.completed_by_user_id
            WHERE q.id = :queue_id
              AND q.clinic_id = :clinic_id
              AND q.doctor_id = :doctor_id
            LIMIT 1
        ";

        $queueStmt = $this->pdo->prepare($queueSql);
        $queueStmt->execute([
            ':queue_id' => $queueId,
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);

        $queue = $queueStmt->fetch();

        if (!$queue) {
            throw new RuntimeException('Liste introuvable.');
        }

        $entriesSql = "
            SELECT
                qe.id,
                qe.patient_id,
                qe.display_name,
                qe.phone,
                qe.birth_date,
                qe.source,
                qe.status,
                qe.position_number,
                qe.created_at,
                qe.called_at,
                qe.done_at,
                qe.no_show_at,
                qe.canceled_at,
                qe.cancellation_reason,
                created_user.full_name AS created_by_name,
                updated_user.full_name AS updated_by_name
            FROM queue_entries qe
            LEFT JOIN users created_user
                ON created_user.id = qe.created_by_user_id
            LEFT JOIN users updated_user
                ON updated_user.id = qe.updated_by_user_id
            WHERE qe.queue_id = :queue_id
              AND qe.clinic_id = :clinic_id
            ORDER BY
                CASE WHEN qe.position_number IS NULL THEN 1 ELSE 0 END,
                qe.position_number ASC,
                qe.created_at ASC,
                qe.id ASC
        ";

        $entriesStmt = $this->pdo->prepare($entriesSql);
        $entriesStmt->execute([
            ':queue_id' => $queueId,
            ':clinic_id' => $clinicId,
        ]);

        $entries = array_map(
            static function (array $row): array {
                return [
                    'id' => (int) $row['id'],
                    'patient_id' => $row['patient_id'] !== null
                        ? (int) $row['patient_id']
                        : null,
                    'display_name' => $row['display_name'],
                    'phone' => PatientDataNormalizer::formatPhoneForDisplay(
                        $row['phone'] !== null ? (string) $row['phone'] : null
                    ),
                    'birth_date' => $row['birth_date'],
                    'source' => $row['source'],
                    'status' => $row['status'],
                    'position_number' => $row['position_number'] !== null
                        ? (int) $row['position_number']
                        : null,
                    'created_at' => $row['created_at'],
                    'called_at' => $row['called_at'],
                    'done_at' => $row['done_at'],
                    'no_show_at' => $row['no_show_at'],
                    'canceled_at' => $row['canceled_at'],
                    'cancellation_reason' => $row['cancellation_reason'],
                    'created_by_name' => $row['created_by_name'],
                    'updated_by_name' => $row['updated_by_name'],
                ];
            },
            $entriesStmt->fetchAll()
        );

        $queue['id'] = (int) $queue['id'];
        $queue['clinic_id'] = (int) $queue['clinic_id'];
        $queue['doctor_id'] = (int) $queue['doctor_id'];

        return [
            'queue' => $queue,
            'entries' => $entries,
        ];
    }
}
