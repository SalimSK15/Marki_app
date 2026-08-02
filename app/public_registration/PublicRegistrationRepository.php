<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/PublicRegistrationSecurity.php';

final class PublicRegistrationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function ensureDoctorConfiguration(
        int $clinicId,
        int $doctorId
    ): void {
        $settingsSql = "
            INSERT IGNORE INTO doctor_public_registration_settings (
                clinic_id,
                doctor_id,
                public_registration_enabled,
                guest_registration_enabled,
                phone_required,
                birth_date_required,
                privacy_consent_required,
                automatic_schedule_enabled,
                public_sessions_enabled,
                public_session_duration_minutes,
                queue_notifications_enabled,
                created_at,
                updated_at
            ) VALUES (
                :clinic_id,
                :doctor_id,
                1,
                1,
                1,
                0,
                1,
                0,
                1,
                720,
                0,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($settingsSql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);

        $defaults = $this->defaultMessages();
        $messageSql = "
            INSERT IGNORE INTO doctor_public_registration_messages (
                clinic_id,
                doctor_id,
                message_code,
                message_text,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :clinic_id,
                :doctor_id,
                :message_code,
                :message_text,
                1,
                NOW(),
                NOW()
            )
        ";
        $messageStmt = $this->pdo->prepare($messageSql);

        foreach ($defaults as $code => $text) {
            $messageStmt->execute([
                ':clinic_id' => $clinicId,
                ':doctor_id' => $doctorId,
                ':message_code' => $code,
                ':message_text' => $text,
            ]);
        }
    }

    public function ensurePublicLink(
        int $clinicId,
        int $doctorId,
        int $actorUserId,
        array $config
    ): array {
        $this->pdo->beginTransaction();

        try {
            $link = $this->findLinkForDoctorForUpdate(
                $clinicId,
                $doctorId
            );

            if ($link === null) {
                $publicId = PublicRegistrationSecurity::generatePublicId();
                $tokenVersion = 1;
                $token = PublicRegistrationSecurity::tokenFor(
                    $publicId,
                    $tokenVersion,
                    $config
                );

                $insertSql = "
                    INSERT INTO public_links (
                        public_id,
                        clinic_id,
                        doctor_id,
                        type,
                        token_hash,
                        token_version,
                        is_active,
                        activated_at,
                        created_by_user_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        :public_id,
                        :clinic_id,
                        :doctor_id,
                        'qr',
                        :token_hash,
                        :token_version,
                        1,
                        NOW(),
                        :created_by_user_id,
                        NOW(),
                        NOW()
                    )
                ";

                $stmt = $this->pdo->prepare($insertSql);
                $stmt->execute([
                    ':public_id' => $publicId,
                    ':clinic_id' => $clinicId,
                    ':doctor_id' => $doctorId,
                    ':token_hash' => PublicRegistrationSecurity::tokenHash(
                        $token
                    ),
                    ':token_version' => $tokenVersion,
                    ':created_by_user_id' => $actorUserId,
                ]);

                $link = $this->findLinkForDoctorForUpdate(
                    $clinicId,
                    $doctorId
                );
            }

            if ($link === null) {
                throw new RuntimeException(
                    'Impossible de préparer le lien public du médecin.'
                );
            }

            $token = PublicRegistrationSecurity::tokenFor(
                (string) $link['public_id'],
                (int) $link['token_version'],
                $config
            );
            $expectedHash = PublicRegistrationSecurity::tokenHash($token);

            if (!hash_equals((string) $link['token_hash'], $expectedHash)) {
                $syncSql = "
                    UPDATE public_links
                    SET
                        token_hash = :token_hash,
                        revoked_at = NULL,
                        revoked_by_user_id = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                    LIMIT 1
                ";
                $syncStmt = $this->pdo->prepare($syncSql);
                $syncStmt->execute([
                    ':token_hash' => $expectedHash,
                    ':id' => (int) $link['id'],
                ]);
                $link['token_hash'] = $expectedHash;
                $link['revoked_at'] = null;
                $link['revoked_by_user_id'] = null;
            }

            $this->pdo->commit();

            $link['token'] = $token;

            return $link;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function adminOverview(
        int $clinicId,
        int $doctorId,
        int $actorUserId,
        array $config,
        string $today
    ): array {
        $this->ensureDoctorConfiguration($clinicId, $doctorId);
        $link = $this->ensurePublicLink(
            $clinicId,
            $doctorId,
            $actorUserId,
            $config
        );
        $settings = $this->settingsForDoctor($clinicId, $doctorId);
        $messages = $this->messagesForDoctor($clinicId, $doctorId);
        $metrics = $this->metricsForLink((int) $link['id'], $today);

        return [
            'link' => $link,
            'settings' => $settings,
            'messages' => $messages,
            'metrics' => $metrics,
        ];
    }

    public function settingsForDoctor(
        int $clinicId,
        int $doctorId
    ): array {
        $sql = "
            SELECT
                id,
                clinic_id,
                doctor_id,
                public_registration_enabled,
                guest_registration_enabled,
                phone_required,
                birth_date_required,
                privacy_consent_required,
                automatic_schedule_enabled,
                public_sessions_enabled,
                public_session_duration_minutes,
                queue_notifications_enabled,
                max_public_registrations_per_day,
                created_at,
                updated_at
            FROM doctor_public_registration_settings
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException(
                'Les paramètres d’inscription publique sont introuvables.'
            );
        }

        return [
            'id' => (int) $row['id'],
            'public_registration_enabled' =>
                (bool) $row['public_registration_enabled'],
            'guest_registration_enabled' =>
                (bool) $row['guest_registration_enabled'],
            'phone_required' => (bool) $row['phone_required'],
            'birth_date_required' =>
                (bool) $row['birth_date_required'],
            'privacy_consent_required' =>
                (bool) $row['privacy_consent_required'],
            'automatic_schedule_enabled' =>
                (bool) $row['automatic_schedule_enabled'],
            'public_sessions_enabled' =>
                (bool) $row['public_sessions_enabled'],
            'public_session_duration_minutes' =>
                (int) $row['public_session_duration_minutes'],
            'queue_notifications_enabled' =>
                (bool) $row['queue_notifications_enabled'],
            'max_public_registrations_per_day' =>
                $row['max_public_registrations_per_day'] !== null
                    ? (int) $row['max_public_registrations_per_day']
                    : null,
            'updated_at' => $row['updated_at'],
        ];
    }

    public function messagesForDoctor(
        int $clinicId,
        int $doctorId
    ): array {
        $sql = "
            SELECT
                message_code,
                message_text
            FROM doctor_public_registration_messages
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
              AND is_active = 1
            ORDER BY id ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);

        $messages = $this->defaultMessages();
        foreach ($stmt->fetchAll() as $row) {
            $messages[(string) $row['message_code']] =
                (string) $row['message_text'];
        }

        return $messages;
    }

    public function updateConfiguration(
        int $clinicId,
        int $doctorId,
        array $settings,
        array $messages
    ): void {
        $this->pdo->beginTransaction();

        try {
            $settingsSql = "
                UPDATE doctor_public_registration_settings
                SET
                    birth_date_required = :birth_date_required,
                    privacy_consent_required = :privacy_consent_required,
                    public_sessions_enabled = :public_sessions_enabled,
                    public_session_duration_minutes = :session_duration,
                    max_public_registrations_per_day = :max_registrations,
                    updated_at = NOW()
                WHERE clinic_id = :clinic_id
                  AND doctor_id = :doctor_id
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($settingsSql);
            $stmt->execute([
                ':birth_date_required' =>
                    !empty($settings['birth_date_required']) ? 1 : 0,
                ':privacy_consent_required' => 1,
                ':public_sessions_enabled' => 1,
                ':session_duration' =>
                    (int) $settings['public_session_duration_minutes'],
                ':max_registrations' =>
                    $settings['max_public_registrations_per_day'],
                ':clinic_id' => $clinicId,
                ':doctor_id' => $doctorId,
            ]);

            $allowedCodes = [
                'day_not_open',
                'registration_open',
                'registration_closed',
                'queue_paused',
                'day_completed',
                'qr_disabled',
                'outside_schedule',
                'registration_success',
            ];
            $messageSql = "
                UPDATE doctor_public_registration_messages
                SET
                    message_text = :message_text,
                    updated_at = NOW()
                WHERE clinic_id = :clinic_id
                  AND doctor_id = :doctor_id
                  AND message_code = :message_code
                LIMIT 1
            ";
            $messageStmt = $this->pdo->prepare($messageSql);

            foreach ($allowedCodes as $code) {
                if (!array_key_exists($code, $messages)) {
                    continue;
                }

                $messageStmt->execute([
                    ':message_text' => $messages[$code],
                    ':clinic_id' => $clinicId,
                    ':doctor_id' => $doctorId,
                    ':message_code' => $code,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function setLinkActive(
        int $clinicId,
        int $doctorId,
        int $actorUserId,
        bool $active
    ): array {
        $this->pdo->beginTransaction();

        try {
            $link = $this->findLinkForDoctorForUpdate(
                $clinicId,
                $doctorId
            );

            if ($link === null) {
                throw new RuntimeException('Lien public introuvable.');
            }

            $linkSql = "
                UPDATE public_links
                SET
                    is_active = :is_active,
                    activated_at = CASE
                        WHEN :is_active_again = 1 THEN COALESCE(activated_at, NOW())
                        ELSE activated_at
                    END,
                    deactivated_at = CASE
                        WHEN :is_inactive = 1 THEN NOW()
                        ELSE NULL
                    END,
                    deactivated_by_user_id = CASE
                        WHEN :is_inactive_again = 1 THEN :actor_user_id
                        ELSE NULL
                    END,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($linkSql);
            $stmt->execute([
                ':is_active' => $active ? 1 : 0,
                ':is_active_again' => $active ? 1 : 0,
                ':is_inactive' => $active ? 0 : 1,
                ':is_inactive_again' => $active ? 0 : 1,
                ':actor_user_id' => $actorUserId,
                ':id' => (int) $link['id'],
            ]);

            $settingsSql = "
                UPDATE doctor_public_registration_settings
                SET
                    public_registration_enabled = :enabled,
                    updated_at = NOW()
                WHERE clinic_id = :clinic_id
                  AND doctor_id = :doctor_id
                LIMIT 1
            ";
            $settingsStmt = $this->pdo->prepare($settingsSql);
            $settingsStmt->execute([
                ':enabled' => $active ? 1 : 0,
                ':clinic_id' => $clinicId,
                ':doctor_id' => $doctorId,
            ]);

            $this->pdo->commit();

            return $this->findLinkForDoctor($clinicId, $doctorId)
                ?? throw new RuntimeException('Lien public introuvable.');
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function revokeLink(
        int $clinicId,
        int $doctorId,
        int $actorUserId,
        array $config
    ): array {
        $this->pdo->beginTransaction();

        try {
            $link = $this->findLinkForDoctorForUpdate(
                $clinicId,
                $doctorId
            );

            if ($link === null) {
                throw new RuntimeException('Lien public introuvable.');
            }

            $newVersion = (int) $link['token_version'] + 1;
            $newToken = PublicRegistrationSecurity::tokenFor(
                (string) $link['public_id'],
                $newVersion,
                $config
            );

            $sql = "
                UPDATE public_links
                SET
                    token_version = :token_version,
                    token_hash = :token_hash,
                    revoked_at = NOW(),
                    revoked_by_user_id = :actor_user_id,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':token_version' => $newVersion,
                ':token_hash' => PublicRegistrationSecurity::tokenHash(
                    $newToken
                ),
                ':actor_user_id' => $actorUserId,
                ':id' => (int) $link['id'],
            ]);

            $this->pdo->commit();

            $updated = $this->findLinkForDoctor($clinicId, $doctorId);
            if ($updated === null) {
                throw new RuntimeException(
                    'Impossible de relire le nouveau lien.'
                );
            }
            $updated['token'] = $newToken;

            return $updated;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function findLinkForDoctor(
        int $clinicId,
        int $doctorId
    ): ?array {
        $sql = "
            SELECT
                id,
                public_id,
                clinic_id,
                doctor_id,
                type,
                token_hash,
                token_version,
                is_active,
                activated_at,
                created_by_user_id,
                created_at,
                updated_at,
                deactivated_at,
                deactivated_by_user_id,
                last_scanned_at,
                revoked_at,
                revoked_by_user_id
            FROM public_links
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
              AND type = 'qr'
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findValidatedLinkRecord(
        string $publicId
    ): ?array {
        $sql = "
            SELECT
                pl.id,
                pl.public_id,
                pl.clinic_id,
                pl.doctor_id,
                pl.type,
                pl.token_hash,
                pl.token_version,
                pl.is_active,
                pl.activated_at,
                pl.last_scanned_at,
                pl.revoked_at,
                c.name AS clinic_name,
                c.type AS clinic_type,
                c.address AS clinic_address,
                c.city AS clinic_city,
                c.wilaya AS clinic_wilaya,
                c.phone AS clinic_phone,
                c.timezone AS clinic_timezone,
                c.status AS clinic_status,
                dp.display_name AS doctor_name,
                dp.specialty AS doctor_specialty,
                dp.is_active AS doctor_is_active
            FROM public_links pl
            INNER JOIN clinics c
                ON c.id = pl.clinic_id
            INNER JOIN doctor_profiles dp
                ON dp.id = pl.doctor_id
               AND dp.clinic_id = pl.clinic_id
            WHERE pl.public_id = :public_id
              AND pl.type = 'qr'
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':public_id' => strtolower(trim($publicId)),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function logPublicEvent(
        int $publicLinkId,
        string $eventType,
        ?string $resultCode,
        string $ipHash,
        string $userAgent,
        ?int $queueId = null,
        ?int $queueEntryId = null,
        ?array $metadata = null
    ): void {
        $sql = "
            INSERT INTO public_link_events (
                public_link_id,
                queue_id,
                queue_entry_id,
                event_type,
                result_code,
                metadata_json,
                ip_hash,
                user_agent,
                created_at
            ) VALUES (
                :public_link_id,
                :queue_id,
                :queue_entry_id,
                :event_type,
                :result_code,
                :metadata_json,
                :ip_hash,
                :user_agent,
                NOW()
            )
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':public_link_id' => $publicLinkId,
            ':queue_id' => $queueId,
            ':queue_entry_id' => $queueEntryId,
            ':event_type' => mb_substr($eventType, 0, 50, 'UTF-8'),
            ':result_code' => $resultCode !== null
                ? mb_substr($resultCode, 0, 50, 'UTF-8')
                : null,
            ':metadata_json' => $metadata !== null
                ? json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : null,
            ':ip_hash' => $ipHash,
            ':user_agent' => $userAgent,
        ]);
    }

    public function logActivity(
        int $clinicId,
        int $actorUserId,
        string $action,
        string $entityType,
        int $entityId,
        ?array $metadata = null
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
            ':metadata_json' => $metadata !== null
                ? json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : null,
        ]);
    }

    public function rateLimitCount(
        int $publicLinkId,
        string $ipHash,
        int $minutes
    ): int {
        $minutes = max(1, min(120, $minutes));
        $threshold = date(
            'Y-m-d H:i:s',
            time() - ($minutes * 60)
        );
        $sql = "
            SELECT COUNT(*) AS total
            FROM public_link_events
            WHERE public_link_id = :public_link_id
              AND ip_hash = :ip_hash
              AND event_type = 'registration_attempt'
              AND created_at >= :threshold
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':public_link_id' => $publicLinkId,
            ':ip_hash' => $ipHash,
            ':threshold' => $threshold,
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function touchLastScanned(int $publicLinkId): void
    {
        $sql = "
            UPDATE public_links
            SET
                last_scanned_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $publicLinkId]);
    }

    private function findLinkForDoctorForUpdate(
        int $clinicId,
        int $doctorId
    ): ?array {
        $sql = "
            SELECT
                id,
                public_id,
                clinic_id,
                doctor_id,
                type,
                token_hash,
                token_version,
                is_active,
                activated_at,
                created_by_user_id,
                created_at,
                updated_at,
                deactivated_at,
                deactivated_by_user_id,
                last_scanned_at,
                revoked_at,
                revoked_by_user_id
            FROM public_links
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
              AND type = 'qr'
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function metricsForLink(
        int $publicLinkId,
        string $today
    ): array {
        $sql = "
            SELECT
                SUM(event_type = 'scan') AS scans_today,
                SUM(event_type = 'registered'
                    AND result_code = 'success') AS registrations_today,
                MAX(created_at) AS last_event_at
            FROM public_link_events
            WHERE public_link_id = :public_link_id
              AND created_at >= :today_start
              AND created_at < DATE_ADD(:today_start_end, INTERVAL 1 DAY)
        ";
        $stmt = $this->pdo->prepare($sql);
        $todayStart = $today . ' 00:00:00';
        $stmt->execute([
            ':public_link_id' => $publicLinkId,
            ':today_start' => $todayStart,
            ':today_start_end' => $todayStart,
        ]);
        $row = $stmt->fetch() ?: [];

        return [
            'scans_today' => (int) ($row['scans_today'] ?? 0),
            'registrations_today' =>
                (int) ($row['registrations_today'] ?? 0),
            'last_event_at' => $row['last_event_at'] ?? null,
        ];
    }

    private function defaultMessages(): array
    {
        return [
            'day_not_open' =>
                'La liste du jour n’est pas encore ouverte. Veuillez revenir plus tard ou contacter le cabinet.',
            'registration_open' =>
                'Les inscriptions sont ouvertes. Vous pouvez rejoindre la liste d’attente.',
            'registration_closed' =>
                'Les nouvelles inscriptions sont temporairement fermées. Veuillez revenir plus tard ou contacter le cabinet.',
            'queue_paused' =>
                'La prise en charge est temporairement en pause. Les nouvelles inscriptions ne sont pas disponibles pour le moment.',
            'day_completed' =>
                'La liste d’attente est terminée pour aujourd’hui. Veuillez revenir lors de la prochaine journée d’ouverture.',
            'qr_disabled' =>
                'L’inscription par QR code est temporairement indisponible pour ce médecin.',
            'outside_schedule' =>
                'Les inscriptions en ligne ne sont pas disponibles à cette heure.',
            'registration_success' =>
                'Votre inscription à la liste d’attente a bien été enregistrée.',
        ];
    }
}
