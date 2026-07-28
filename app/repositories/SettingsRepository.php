<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class SettingsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function get(int $clinicId, int $doctorId): array
    {
        $sql = "
            SELECT
                c.id AS clinic_id,
                c.name AS clinic_name,
                c.type AS clinic_type,
                c.address AS clinic_address,
                c.city AS clinic_city,
                c.wilaya AS clinic_wilaya,
                c.phone AS clinic_phone,
                c.timezone AS clinic_timezone,
                c.status AS clinic_status,
                d.id AS doctor_id,
                d.user_id AS doctor_user_id,
                d.display_name AS doctor_display_name,
                d.specialty AS doctor_specialty,
                d.license_number AS doctor_license_number,
                d.address AS doctor_address,
                d.is_active AS doctor_is_active
            FROM clinics c
            INNER JOIN doctor_profiles d
                ON d.clinic_id = c.id
            WHERE c.id = :clinic_id
              AND d.id = :doctor_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Paramètres introuvables.');
        }

        return [
            'clinic' => [
                'id' => (int) $row['clinic_id'],
                'name' => $row['clinic_name'],
                'type' => $row['clinic_type'],
                'address' => $row['clinic_address'],
                'city' => $row['clinic_city'],
                'wilaya' => $row['clinic_wilaya'],
                'phone' => $row['clinic_phone'],
                'timezone' => $row['clinic_timezone'],
                'status' => $row['clinic_status'],
            ],
            'doctor' => [
                'id' => (int) $row['doctor_id'],
                'user_id' => (int) $row['doctor_user_id'],
                'display_name' => $row['doctor_display_name'],
                'specialty' => $row['doctor_specialty'],
                'license_number' => $row['doctor_license_number'],
                'address' => $row['doctor_address'],
                'is_active' => (bool) $row['doctor_is_active'],
            ],
        ];
    }

    public function update(
        int $clinicId,
        int $doctorId,
        int $actorUserId,
        array $input
    ): array {
        $current = $this->get($clinicId, $doctorId);

        $clinicName = trim((string) ($input['clinic_name'] ?? ''));
        $clinicType = trim((string) ($input['clinic_type'] ?? 'solo'));
        $clinicAddress = trim((string) ($input['clinic_address'] ?? ''));
        $clinicCity = trim((string) ($input['clinic_city'] ?? ''));
        $clinicWilaya = trim((string) ($input['clinic_wilaya'] ?? ''));
        $clinicPhone = trim((string) ($input['clinic_phone'] ?? ''));
        $clinicTimezone = trim((string) ($input['clinic_timezone'] ?? 'Africa/Algiers'));

        $doctorDisplayName = trim((string) ($input['doctor_display_name'] ?? ''));
        $doctorSpecialty = trim((string) ($input['doctor_specialty'] ?? ''));
        $doctorLicenseNumber = trim((string) ($input['doctor_license_number'] ?? ''));
        $doctorAddress = trim((string) ($input['doctor_address'] ?? ''));

        if ($clinicName === '') {
            throw new InvalidArgumentException('Le nom du cabinet est obligatoire.');
        }

        if (!in_array($clinicType, ['solo', 'clinic', 'hospital_simple'], true)) {
            throw new InvalidArgumentException('Type de structure invalide.');
        }

        if (!in_array($clinicTimezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Fuseau horaire invalide.');
        }

        if ($doctorDisplayName === '') {
            throw new InvalidArgumentException('Le nom du médecin est obligatoire.');
        }

        if (mb_strlen($clinicPhone) > 30) {
            throw new InvalidArgumentException('Le téléphone du cabinet est trop long.');
        }

        $clinicAddress = $clinicAddress !== '' ? $clinicAddress : null;
        $clinicCity = $clinicCity !== '' ? $clinicCity : null;
        $clinicWilaya = $clinicWilaya !== '' ? $clinicWilaya : null;
        $clinicPhone = $clinicPhone !== '' ? $clinicPhone : null;
        $doctorSpecialty = $doctorSpecialty !== '' ? $doctorSpecialty : null;
        $doctorLicenseNumber = $doctorLicenseNumber !== ''
            ? $doctorLicenseNumber
            : null;
        $doctorAddress = $doctorAddress !== '' ? $doctorAddress : null;

        $this->pdo->beginTransaction();

        try {
            $clinicSql = "
                UPDATE clinics
                SET
                    name = :clinic_name,
                    type = :clinic_type,
                    address = :clinic_address,
                    city = :clinic_city,
                    wilaya = :clinic_wilaya,
                    phone = :clinic_phone,
                    timezone = :clinic_timezone,
                    updated_at = NOW()
                WHERE id = :clinic_id
                LIMIT 1
            ";

            $clinicStmt = $this->pdo->prepare($clinicSql);
            $clinicStmt->execute([
                ':clinic_name' => $clinicName,
                ':clinic_type' => $clinicType,
                ':clinic_address' => $clinicAddress,
                ':clinic_city' => $clinicCity,
                ':clinic_wilaya' => $clinicWilaya,
                ':clinic_phone' => $clinicPhone,
                ':clinic_timezone' => $clinicTimezone,
                ':clinic_id' => $clinicId,
            ]);

            $doctorSql = "
                UPDATE doctor_profiles
                SET
                    display_name = :doctor_display_name,
                    specialty = :doctor_specialty,
                    license_number = :doctor_license_number,
                    address = :doctor_address,
                    updated_at = NOW()
                WHERE id = :doctor_id
                  AND clinic_id = :doctor_clinic_id
                LIMIT 1
            ";

            $doctorStmt = $this->pdo->prepare($doctorSql);
            $doctorStmt->execute([
                ':doctor_display_name' => $doctorDisplayName,
                ':doctor_specialty' => $doctorSpecialty,
                ':doctor_license_number' => $doctorLicenseNumber,
                ':doctor_address' => $doctorAddress,
                ':doctor_id' => $doctorId,
                ':doctor_clinic_id' => $clinicId,
            ]);

            $userSql = "
                UPDATE users
                SET
                    full_name = :user_full_name,
                    updated_at = NOW()
                WHERE id = :doctor_user_id
                  AND clinic_id = :user_clinic_id
                LIMIT 1
            ";

            $userStmt = $this->pdo->prepare($userSql);
            $userStmt->execute([
                ':user_full_name' => $doctorDisplayName,
                ':doctor_user_id' => $current['doctor']['user_id'],
                ':user_clinic_id' => $clinicId,
            ]);

            $metadata = json_encode([
                'doctor_id' => $doctorId,
                'previous_timezone' => $current['clinic']['timezone'],
                'new_timezone' => $clinicTimezone,
                'previous_clinic_name' => $current['clinic']['name'],
                'new_clinic_name' => $clinicName,
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
                    :log_clinic_id,
                    :log_actor_user_id,
                    'SETTINGS_UPDATED',
                    'clinic',
                    :log_entity_id,
                    :log_metadata_json,
                    NOW()
                )
            ";

            $logStmt = $this->pdo->prepare($logSql);
            $logStmt->execute([
                ':log_clinic_id' => $clinicId,
                ':log_actor_user_id' => $actorUserId,
                ':log_entity_id' => $clinicId,
                ':log_metadata_json' => $metadata,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        configureClinicTimezone($this->pdo, $clinicId, $clinicTimezone);

        return $this->get($clinicId, $doctorId);
    }
}
