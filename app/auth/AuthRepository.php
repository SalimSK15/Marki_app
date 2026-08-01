<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/PatientDataNormalizer.php';

final class AuthRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function findClinicBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug, timezone, status
             FROM clinics
             WHERE slug = :slug
             LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findUserForLogin(int $clinicId, string $identifier): ?array
    {
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $normalizedPhone = $isEmail
            ? null
            : PatientDataNormalizer::normalizePhone($identifier);

        $sql = $isEmail
            ? "SELECT * FROM users
               WHERE clinic_id = :clinic_id
                 AND LOWER(email) = :identifier
               LIMIT 1"
            : "SELECT * FROM users
               WHERE clinic_id = :clinic_id
                 AND phone = :identifier
               LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':identifier' => $isEmail
                ? mb_strtolower(trim($identifier), 'UTF-8')
                : $normalizedPhone,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findActiveUserContext(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id,
                u.clinic_id,
                u.email,
                u.phone,
                u.full_name,
                u.status,
                u.must_change_password,
                u.password_changed_at,
                c.name AS clinic_name,
                c.slug AS clinic_slug,
                c.timezone,
                c.status AS clinic_status
             FROM users u
             INNER JOIN clinics c ON c.id = u.clinic_id
             WHERE u.id = :user_id
               AND u.status = 'active'
               AND c.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function rolesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.code
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.id"
        );
        $stmt->execute([':user_id' => $userId]);

        return array_values(array_map(
            static fn(array $row): string => (string) $row['code'],
            $stmt->fetchAll()
        ));
    }

    public function accessibleDoctors(
        int $userId,
        int $clinicId,
        array $roles
    ): array {
        if (in_array('clinic_admin', $roles, true)) {
            $stmt = $this->pdo->prepare(
                "SELECT id, user_id, display_name, specialty
                 FROM doctor_profiles
                 WHERE clinic_id = :clinic_id
                   AND is_active = 1
                 ORDER BY display_name"
            );
            $stmt->execute([':clinic_id' => $clinicId]);
        } elseif (in_array('doctor', $roles, true)) {
            $stmt = $this->pdo->prepare(
                "SELECT id, user_id, display_name, specialty
                 FROM doctor_profiles
                 WHERE clinic_id = :clinic_id
                   AND user_id = :user_id
                   AND is_active = 1
                 ORDER BY display_name"
            );
            $stmt->execute([
                ':clinic_id' => $clinicId,
                ':user_id' => $userId,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT
                    d.id,
                    d.user_id,
                    d.display_name,
                    d.specialty,
                    sda.access_level
                 FROM staff_profiles sp
                 INNER JOIN staff_doctor_access sda
                    ON sda.staff_profile_id = sp.id
                 INNER JOIN doctor_profiles d
                    ON d.id = sda.doctor_id
                 WHERE sp.user_id = :user_id
                   AND sp.clinic_id = :clinic_id
                   AND d.is_active = 1
                 ORDER BY d.display_name"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':clinic_id' => $clinicId,
            ]);
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'display_name' => (string) $row['display_name'],
                'specialty' => $row['specialty'],
                'access_level' => $row['access_level'] ?? 'full',
            ];
        }, $stmt->fetchAll());
    }

    public function accessLevelForDoctor(
        int $userId,
        int $doctorId,
        array $roles
    ): ?string {
        if (
            in_array('clinic_admin', $roles, true)
            || in_array('doctor', $roles, true)
        ) {
            return 'full';
        }

        $stmt = $this->pdo->prepare(
            "SELECT sda.access_level
             FROM staff_profiles sp
             INNER JOIN staff_doctor_access sda
                ON sda.staff_profile_id = sp.id
             WHERE sp.user_id = :user_id
               AND sda.doctor_id = :doctor_id
             LIMIT 1"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':doctor_id' => $doctorId,
        ]);
        $row = $stmt->fetch();

        return $row ? (string) $row['access_level'] : null;
    }

    public function recordFailedLogin(
        int $userId,
        int $currentAttempts,
        int $maxAttempts,
        int $lockMinutes
    ): array {
        $newAttempts = $currentAttempts + 1;
        $lockedUntil = $newAttempts >= $maxAttempts
            ? date('Y-m-d H:i:s', time() + ($lockMinutes * 60))
            : null;

        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET failed_login_attempts = :attempts,
                 locked_until = :locked_until,
                 updated_at = NOW()
             WHERE id = :user_id
             LIMIT 1"
        );
        $stmt->execute([
            ':attempts' => $newAttempts,
            ':locked_until' => $lockedUntil,
            ':user_id' => $userId,
        ]);

        return [
            'attempts' => $newAttempts,
            'locked_until' => $lockedUntil,
        ];
    }

    public function recordSuccessfulLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET failed_login_attempts = 0,
                 locked_until = NULL,
                 last_login_at = NOW(),
                 updated_at = NOW()
             WHERE id = :user_id
             LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
    }

    public function createPersistentSession(
        int $userId,
        int $clinicId,
        int $doctorId,
        string $selector,
        string $validatorHash,
        ?string $userAgentHash,
        ?string $ipHash,
        string $expiresAt
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_sessions (
                user_id,
                clinic_id,
                selected_doctor_id,
                selector,
                validator_hash,
                user_agent_hash,
                ip_hash,
                expires_at,
                last_used_at,
                created_at
             ) VALUES (
                :user_id,
                :clinic_id,
                :doctor_id,
                :selector,
                :validator_hash,
                :user_agent_hash,
                :ip_hash,
                :expires_at,
                NOW(),
                NOW()
             )"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':selector' => $selector,
            ':validator_hash' => $validatorHash,
            ':user_agent_hash' => $userAgentHash,
            ':ip_hash' => $ipHash,
            ':expires_at' => $expiresAt,
        ]);
    }

    public function findPersistentSession(string $selector): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM user_sessions
             WHERE selector = :selector
               AND revoked_at IS NULL
               AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([':selector' => $selector]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function rotatePersistentSession(
        int $sessionId,
        string $validatorHash,
        int $doctorId
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions
             SET validator_hash = :validator_hash,
                 selected_doctor_id = :doctor_id,
                 last_used_at = NOW()
             WHERE id = :session_id
             LIMIT 1"
        );
        $stmt->execute([
            ':validator_hash' => $validatorHash,
            ':doctor_id' => $doctorId,
            ':session_id' => $sessionId,
        ]);
    }

    public function updatePersistentSelectedDoctor(
        string $selector,
        int $doctorId
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions
             SET selected_doctor_id = :doctor_id,
                 last_used_at = NOW()
             WHERE selector = :selector
               AND revoked_at IS NULL"
        );
        $stmt->execute([
            ':doctor_id' => $doctorId,
            ':selector' => $selector,
        ]);
    }

    public function revokePersistentSession(string $selector): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions
             SET revoked_at = NOW()
             WHERE selector = :selector
               AND revoked_at IS NULL"
        );
        $stmt->execute([':selector' => $selector]);
    }

    public function revokeAllUserSessions(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions
             SET revoked_at = NOW()
             WHERE user_id = :user_id
               AND revoked_at IS NULL"
        );
        $stmt->execute([':user_id' => $userId]);
    }

    public function log(
        int $clinicId,
        ?int $actorUserId,
        string $action,
        string $entityType,
        int $entityId,
        array $metadata = []
    ): void {
        $metadataJson = $metadata === []
            ? null
            : json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

        $stmt = $this->pdo->prepare(
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
                :action,
                :entity_type,
                :entity_id,
                :metadata_json,
                NOW()
             )"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':actor_user_id' => $actorUserId,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':metadata_json' => $metadataJson,
        ]);
    }

    public function createPasswordResetToken(
        int $userId,
        int $clinicId,
        string $selector,
        string $tokenHash,
        string $expiresAt
    ): void {
        $this->pdo->prepare(
            "UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE user_id = :user_id
               AND used_at IS NULL"
        )->execute([':user_id' => $userId]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO password_reset_tokens (
                user_id,
                clinic_id,
                selector,
                token_hash,
                expires_at,
                created_at
             ) VALUES (
                :user_id,
                :clinic_id,
                :selector,
                :token_hash,
                :expires_at,
                NOW()
             )"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':clinic_id' => $clinicId,
            ':selector' => $selector,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);
    }

    public function findPasswordResetToken(string $selector): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT prt.*, u.email, u.full_name, u.status
             FROM password_reset_tokens prt
             INNER JOIN users u ON u.id = prt.user_id
             WHERE prt.selector = :selector
               AND prt.used_at IS NULL
               AND prt.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([':selector' => $selector]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function consumePasswordResetToken(
        int $tokenId,
        int $userId,
        string $passwordHash
    ): void {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE users
                 SET password_hash = :password_hash,
                     must_change_password = 0,
                     password_changed_at = NOW(),
                     failed_login_attempts = 0,
                     locked_until = NULL,
                     updated_at = NOW()
                 WHERE id = :user_id
                 LIMIT 1"
            );
            $stmt->execute([
                ':password_hash' => $passwordHash,
                ':user_id' => $userId,
            ]);

            $this->pdo->prepare(
                "UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE id = :token_id
                 LIMIT 1"
            )->execute([':token_id' => $tokenId]);

            $this->pdo->prepare(
                "UPDATE user_sessions
                 SET revoked_at = NOW()
                 WHERE user_id = :user_id
                   AND revoked_at IS NULL"
            )->execute([':user_id' => $userId]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updatePassword(
        int $userId,
        string $passwordHash,
        string $changedAt
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET password_hash = :password_hash,
                 must_change_password = 0,
                 password_changed_at = :changed_at,
                 failed_login_attempts = 0,
                 locked_until = NULL,
                 updated_at = :updated_at
             WHERE id = :user_id
             LIMIT 1"
        );
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':changed_at' => $changedAt,
            ':updated_at' => $changedAt,
            ':user_id' => $userId,
        ]);
    }

    public function passwordHashForUser(int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT password_hash FROM users WHERE id = :user_id LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ? (string) $row['password_hash'] : null;
    }
}
