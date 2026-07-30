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
            INNER JOIN doctor_profiles d ON d.clinic_id = c.id
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
        array $input,
        bool $canManageClinic,
        bool $canManageDoctor
    ): array {
        $current = $this->get($clinicId, $doctorId);

        if (!$canManageClinic && !$canManageDoctor) {
            throw new InvalidArgumentException(
                'Vous n’avez pas la permission de modifier ces paramètres.'
            );
        }

        $clinicName = $canManageClinic
            ? trim((string) ($input['clinic_name'] ?? ''))
            : (string) $current['clinic']['name'];
        $clinicType = $canManageClinic
            ? trim((string) ($input['clinic_type'] ?? 'solo'))
            : (string) $current['clinic']['type'];
        $clinicAddress = $canManageClinic
            ? trim((string) ($input['clinic_address'] ?? ''))
            : (string) ($current['clinic']['address'] ?? '');
        $clinicCity = $canManageClinic
            ? trim((string) ($input['clinic_city'] ?? ''))
            : (string) ($current['clinic']['city'] ?? '');
        $clinicWilaya = $canManageClinic
            ? trim((string) ($input['clinic_wilaya'] ?? ''))
            : (string) ($current['clinic']['wilaya'] ?? '');
        $clinicPhone = $canManageClinic
            ? trim((string) ($input['clinic_phone'] ?? ''))
            : (string) ($current['clinic']['phone'] ?? '');
        $clinicTimezone = $canManageClinic
            ? trim((string) ($input['clinic_timezone'] ?? 'Africa/Algiers'))
            : (string) $current['clinic']['timezone'];

        $doctorDisplayName = $canManageDoctor
            ? trim((string) ($input['doctor_display_name'] ?? ''))
            : (string) $current['doctor']['display_name'];
        $doctorSpecialty = $canManageDoctor
            ? trim((string) ($input['doctor_specialty'] ?? ''))
            : (string) ($current['doctor']['specialty'] ?? '');
        $doctorLicenseNumber = $canManageDoctor
            ? trim((string) ($input['doctor_license_number'] ?? ''))
            : (string) ($current['doctor']['license_number'] ?? '');
        $doctorAddress = $canManageDoctor
            ? trim((string) ($input['doctor_address'] ?? ''))
            : (string) ($current['doctor']['address'] ?? '');

        if ($clinicName === '') {
            throw new InvalidArgumentException('Le nom de la structure est obligatoire.');
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
            throw new InvalidArgumentException('Le téléphone de la structure est trop long.');
        }

        $clinicAddress = $clinicAddress !== '' ? $clinicAddress : null;
        $clinicCity = $clinicCity !== '' ? $clinicCity : null;
        $clinicWilaya = $clinicWilaya !== '' ? $clinicWilaya : null;
        $clinicPhone = $clinicPhone !== '' ? $clinicPhone : null;
        $doctorSpecialty = $doctorSpecialty !== '' ? $doctorSpecialty : null;
        $doctorLicenseNumber = $doctorLicenseNumber !== '' ? $doctorLicenseNumber : null;
        $doctorAddress = $doctorAddress !== '' ? $doctorAddress : null;

        $this->pdo->beginTransaction();

        try {
            if ($canManageClinic) {
                $clinicStmt = $this->pdo->prepare(
                    "UPDATE clinics
                     SET name = :clinic_name,
                         type = :clinic_type,
                         address = :clinic_address,
                         city = :clinic_city,
                         wilaya = :clinic_wilaya,
                         phone = :clinic_phone,
                         timezone = :clinic_timezone,
                         updated_at = NOW()
                     WHERE id = :clinic_id
                     LIMIT 1"
                );
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
            }

            if ($canManageDoctor) {
                $doctorStmt = $this->pdo->prepare(
                    "UPDATE doctor_profiles
                     SET display_name = :display_name,
                         specialty = :specialty,
                         license_number = :license_number,
                         address = :address,
                         updated_at = NOW()
                     WHERE id = :doctor_id
                       AND clinic_id = :clinic_id
                     LIMIT 1"
                );
                $doctorStmt->execute([
                    ':display_name' => $doctorDisplayName,
                    ':specialty' => $doctorSpecialty,
                    ':license_number' => $doctorLicenseNumber,
                    ':address' => $doctorAddress,
                    ':doctor_id' => $doctorId,
                    ':clinic_id' => $clinicId,
                ]);

                $userStmt = $this->pdo->prepare(
                    "UPDATE users
                     SET full_name = :full_name,
                         updated_at = NOW()
                     WHERE id = :user_id
                       AND clinic_id = :clinic_id
                     LIMIT 1"
                );
                $userStmt->execute([
                    ':full_name' => $doctorDisplayName,
                    ':user_id' => (int) $current['doctor']['user_id'],
                    ':clinic_id' => $clinicId,
                ]);
            }

            $metadata = json_encode([
                'doctor_id' => $doctorId,
                'clinic_updated' => $canManageClinic,
                'doctor_updated' => $canManageDoctor,
                'previous_timezone' => $current['clinic']['timezone'],
                'new_timezone' => $clinicTimezone,
                'previous_clinic_name' => $current['clinic']['name'],
                'new_clinic_name' => $clinicName,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $logStmt = $this->pdo->prepare(
                "INSERT INTO activity_logs (
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
                    'SETTINGS_UPDATED',
                    'clinic',
                    :entity_id,
                    :metadata_json,
                    NOW()
                 )"
            );
            $logStmt->execute([
                ':clinic_id' => $clinicId,
                ':actor_user_id' => $actorUserId,
                ':entity_id' => $clinicId,
                ':metadata_json' => $metadata,
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
