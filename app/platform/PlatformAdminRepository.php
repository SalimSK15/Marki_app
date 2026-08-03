<?php

declare(strict_types=1);

final class PlatformAdminRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM platform_admins WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => mb_strtolower(trim($email), 'UTF-8')]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function findActiveById(int $adminId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, email, full_name, status, last_login_at, password_changed_at
             FROM platform_admins
             WHERE id = :id AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':id' => $adminId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function recordFailedLogin(
        int $adminId,
        int $currentAttempts,
        int $maxAttempts,
        int $lockMinutes
    ): array {
        $attempts = $currentAttempts + 1;
        $lockedUntil = null;

        if ($attempts >= max(1, $maxAttempts)) {
            $lockedUntil = (new DateTimeImmutable('now'))
                ->modify('+' . max(1, $lockMinutes) . ' minutes')
                ->format('Y-m-d H:i:s');
            $attempts = 0;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE platform_admins
             SET failed_login_attempts = :attempts,
                 locked_until = :locked_until,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':attempts' => $attempts,
            ':locked_until' => $lockedUntil,
            ':id' => $adminId,
        ]);

        return [
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ];
    }

    public function recordSuccessfulLogin(int $adminId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE platform_admins
             SET failed_login_attempts = 0,
                 locked_until = NULL,
                 last_login_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $adminId]);
    }

    public function createRememberSession(
        int $adminId,
        string $selector,
        string $validatorHash,
        string $expiresAt,
        ?string $ipHash,
        ?string $userAgentHash
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_admin_sessions (
                platform_admin_id,
                selector,
                validator_hash,
                expires_at,
                ip_hash,
                user_agent_hash,
                created_at
             ) VALUES (
                :admin_id,
                :selector,
                :validator_hash,
                :expires_at,
                :ip_hash,
                :user_agent_hash,
                NOW()
             )'
        );
        $stmt->execute([
            ':admin_id' => $adminId,
            ':selector' => $selector,
            ':validator_hash' => $validatorHash,
            ':expires_at' => $expiresAt,
            ':ip_hash' => $ipHash,
            ':user_agent_hash' => $userAgentHash,
        ]);
    }

    public function findRememberSession(string $selector): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                s.id AS session_id,
                s.platform_admin_id,
                s.validator_hash,
                s.expires_at,
                s.revoked_at,
                a.email,
                a.full_name,
                a.status,
                a.password_changed_at
             FROM platform_admin_sessions s
             INNER JOIN platform_admins a ON a.id = s.platform_admin_id
             WHERE s.selector = :selector
             LIMIT 1"
        );
        $stmt->execute([':selector' => $selector]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function touchRememberSession(int $sessionId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE platform_admin_sessions
             SET last_used_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $sessionId]);
    }

    public function revokeRememberSession(string $selector): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE platform_admin_sessions
             SET revoked_at = NOW()
             WHERE selector = :selector AND revoked_at IS NULL'
        );
        $stmt->execute([':selector' => $selector]);
    }

    public function deleteExpiredSessions(): void
    {
        $this->pdo->exec(
            'DELETE FROM platform_admin_sessions
             WHERE expires_at < NOW()
                OR revoked_at IS NOT NULL'
        );
    }

    public function upsertAdmin(
        string $email,
        string $passwordHash,
        string $fullName
    ): int {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $fullName = trim($fullName);

        $stmt = $this->pdo->prepare(
            "INSERT INTO platform_admins (
                email,
                password_hash,
                full_name,
                status,
                failed_login_attempts,
                locked_until,
                password_changed_at,
                created_at,
                updated_at
             ) VALUES (
                :email,
                :password_hash,
                :full_name,
                'active',
                0,
                NULL,
                NOW(),
                NOW(),
                NOW()
             )
             ON DUPLICATE KEY UPDATE
                password_hash = VALUES(password_hash),
                full_name = VALUES(full_name),
                status = 'active',
                failed_login_attempts = 0,
                locked_until = NULL,
                password_changed_at = NOW(),
                updated_at = NOW()"
        );
        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':full_name' => $fullName,
        ]);

        $admin = $this->findByEmail($email);
        if (!$admin) {
            throw new RuntimeException('Impossible de créer le compte administrateur MARKI.');
        }

        return (int) $admin['id'];
    }

    public function log(
        ?int $adminId,
        string $action,
        array $metadata = [],
        ?string $ipHash = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_admin_activity_logs (
                platform_admin_id,
                action,
                metadata_json,
                ip_hash,
                created_at
             ) VALUES (
                :admin_id,
                :action,
                :metadata,
                :ip_hash,
                NOW()
             )'
        );
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':metadata' => $metadata === []
                ? null
                : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip_hash' => $ipHash,
        ]);
    }
}
