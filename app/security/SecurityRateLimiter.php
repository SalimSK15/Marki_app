<?php

declare(strict_types=1);

final class SecurityRateLimitException extends RuntimeException
{
}

final class SecurityRateLimiter
{
    public static function consume(
        array $config,
        string $scope,
        string $subject,
        int $maximum,
        int $windowSeconds
    ): void {
        $maximum = max(1, $maximum);
        $windowSeconds = max(60, $windowSeconds);
        $secret = (string) ($config['app']['app_key'] ?? '');

        if (strlen($secret) < 32) {
            if ((string) ($config['app']['env'] ?? 'local') === 'production') {
                throw new RuntimeException('Cle de limitation de debit invalide.');
            }
            $secret = 'marki-local-rate-limit-key-not-for-production';
        }

        $key = hash_hmac(
            'sha256',
            $scope . '|' . mb_strtolower(trim($subject), 'UTF-8'),
            $secret
        );
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expires = $now->modify('+' . $windowSeconds . ' seconds');

        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO security_rate_limits (
                bucket_key, scope, attempt_count, window_started_at, expires_at
             ) VALUES (
                :bucket_key, :scope, 1, :now_at, :expires_at
             )
             ON DUPLICATE KEY UPDATE
                attempt_count = IF(expires_at <= :reset_at, 1, attempt_count + 1),
                window_started_at = IF(expires_at <= :reset_at_2, :new_start_at, window_started_at),
                expires_at = IF(expires_at <= :reset_at_3, :new_expires_at, expires_at)'
        );
        $stmt->execute([
            ':bucket_key' => $key,
            ':scope' => mb_substr($scope, 0, 50, 'UTF-8'),
            ':now_at' => $now->format('Y-m-d H:i:s'),
            ':expires_at' => $expires->format('Y-m-d H:i:s'),
            ':reset_at' => $now->format('Y-m-d H:i:s'),
            ':reset_at_2' => $now->format('Y-m-d H:i:s'),
            ':new_start_at' => $now->format('Y-m-d H:i:s'),
            ':reset_at_3' => $now->format('Y-m-d H:i:s'),
            ':new_expires_at' => $expires->format('Y-m-d H:i:s'),
        ]);

        $select = $pdo->prepare(
            'SELECT attempt_count, expires_at
             FROM security_rate_limits
             WHERE bucket_key = :bucket_key
             LIMIT 1'
        );
        $select->execute([':bucket_key' => $key]);
        $bucket = $select->fetch();

        if ($bucket && (int) $bucket['attempt_count'] > $maximum) {
            throw new SecurityRateLimitException(
                'Trop de tentatives. Patientez quelques minutes avant de reessayer.'
            );
        }

        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM security_rate_limits WHERE expires_at < UTC_TIMESTAMP()');
        }
    }
}

