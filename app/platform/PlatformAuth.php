<?php

declare(strict_types=1);

require_once __DIR__ . '/PlatformAdminRepository.php';

final class PlatformAuthException extends RuntimeException {}

final class PlatformAuth
{
    private const REMEMBER_COOKIE = 'marki_platform_remember';

    public static function attemptLogin(
        array $config,
        string $email,
        string $password,
        bool $remember
    ): array {
        Auth::start($config);

        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '' || $password === '') {
            throw new PlatformAuthException('Courriel ou mot de passe incorrect.');
        }

        $repository = new PlatformAdminRepository();
        $admin = $repository->findByEmail($email);

        if (!$admin || (string) $admin['status'] !== 'active') {
            throw new PlatformAuthException('Courriel ou mot de passe incorrect.');
        }

        if (
            !empty($admin['locked_until'])
            && strtotime((string) $admin['locked_until']) > time()
        ) {
            $remainingSeconds = max(
                60,
                strtotime((string) $admin['locked_until']) - time()
            );
            $remainingMinutes = (int) ceil($remainingSeconds / 60);

            throw new PlatformAuthException(
                sprintf(
                    'Trop de tentatives. Réessayez dans %d minute(s).',
                    $remainingMinutes
                )
            );
        }

        if (!password_verify($password, (string) $admin['password_hash'])) {
            $failure = $repository->recordFailedLogin(
                (int) $admin['id'],
                (int) ($admin['failed_login_attempts'] ?? 0),
                (int) ($config['platform']['max_failed_attempts'] ?? 5),
                (int) ($config['platform']['lock_minutes'] ?? 15)
            );

            $repository->log(
                (int) $admin['id'],
                'PLATFORM_LOGIN_FAILED',
                ['locked' => !empty($failure['locked_until'])],
                self::clientIpHash($config)
            );

            if (!empty($failure['locked_until'])) {
                throw new PlatformAuthException(
                    sprintf(
                        'Trop de tentatives. Le compte est verrouillé pendant %d minute(s).',
                        (int) ($config['platform']['lock_minutes'] ?? 15)
                    )
                );
            }

            throw new PlatformAuthException('Courriel ou mot de passe incorrect.');
        }

        $repository->recordSuccessfulLogin((int) $admin['id']);
        session_regenerate_id(true);
        self::establishSession($admin);

        if ($remember) {
            self::createRememberCookie(
                $config,
                $repository,
                (int) $admin['id']
            );
        } else {
            self::clearRememberCookie($config, $repository);
        }

        $repository->log(
            (int) $admin['id'],
            'PLATFORM_LOGIN_SUCCEEDED',
            ['remembered_device' => $remember],
            self::clientIpHash($config)
        );

        return self::publicAdmin($admin);
    }

    public static function current(array $config): ?array
    {
        Auth::start($config);

        if (empty($_SESSION['platform_admin_id'])) {
            self::restoreFromRememberCookie($config);
        }

        $adminId = (int) ($_SESSION['platform_admin_id'] ?? 0);
        if ($adminId <= 0) {
            return null;
        }

        $idleTimeout = (int) (
            $config['platform']['idle_timeout_seconds']
            ?? $config['auth']['idle_timeout_seconds']
            ?? 14400
        );
        $lastActivity = (int) ($_SESSION['platform_last_activity_at'] ?? 0);

        if (
            $lastActivity > 0
            && $idleTimeout > 0
            && (time() - $lastActivity) > $idleTimeout
        ) {
            self::logout($config);
            return null;
        }

        $repository = new PlatformAdminRepository();
        $admin = $repository->findActiveById($adminId);

        if (!$admin) {
            self::logout($config);
            return null;
        }

        $sessionPasswordChangedAt = (string) (
            $_SESSION['platform_password_changed_at'] ?? ''
        );
        $databasePasswordChangedAt = (string) (
            $admin['password_changed_at'] ?? ''
        );

        if (
            $sessionPasswordChangedAt !== ''
            && $databasePasswordChangedAt !== ''
            && $sessionPasswordChangedAt !== $databasePasswordChangedAt
        ) {
            self::logout($config);
            return null;
        }

        $_SESSION['platform_last_activity_at'] = time();
        $_SESSION['platform_password_changed_at'] = $databasePasswordChangedAt;

        return self::publicAdmin($admin);
    }

    public static function logout(array $config): void
    {
        Auth::start($config);

        $adminId = isset($_SESSION['platform_admin_id'])
            ? (int) $_SESSION['platform_admin_id']
            : null;

        try {
            $repository = new PlatformAdminRepository();
            self::clearRememberCookie($config, $repository);
            $repository->log(
                $adminId,
                'PLATFORM_LOGOUT',
                [],
                self::clientIpHash($config)
            );
        } catch (Throwable $exception) {
            self::expireCookie($config);
        }

        unset(
            $_SESSION['platform_admin_id'],
            $_SESSION['platform_admin_email'],
            $_SESSION['platform_admin_full_name'],
            $_SESSION['platform_password_changed_at'],
            $_SESSION['platform_last_activity_at']
        );

        session_regenerate_id(true);
    }

    private static function establishSession(array $admin): void
    {
        $_SESSION['platform_admin_id'] = (int) $admin['id'];
        $_SESSION['platform_admin_email'] = (string) $admin['email'];
        $_SESSION['platform_admin_full_name'] = (string) $admin['full_name'];
        $_SESSION['platform_password_changed_at'] = (string) (
            $admin['password_changed_at'] ?? ''
        );
        $_SESSION['platform_last_activity_at'] = time();
    }

    private static function createRememberCookie(
        array $config,
        PlatformAdminRepository $repository,
        int $adminId
    ): void {
        self::clearRememberCookie($config, $repository);

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $validatorHash = hash('sha256', $validator);
        $days = max(1, (int) ($config['platform']['remember_days'] ?? 30));
        $expiresAt = (new DateTimeImmutable('now'))
            ->modify('+' . $days . ' days');

        $repository->createRememberSession(
            $adminId,
            $selector,
            $validatorHash,
            $expiresAt->format('Y-m-d H:i:s'),
            self::clientIpHash($config),
            self::userAgentHash($config)
        );

        self::setCookie(
            $config,
            $selector . '.' . $validator,
            $expiresAt->getTimestamp()
        );
    }

    private static function restoreFromRememberCookie(array $config): void
    {
        $cookie = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        [$selector, $validator] = self::parseRememberCookie($cookie);

        if ($selector === '' || $validator === '') {
            self::expireCookie($config);
            return;
        }

        try {
            $repository = new PlatformAdminRepository();
            $repository->deleteExpiredSessions();
            $session = $repository->findRememberSession($selector);

            if (
                !$session
                || !empty($session['revoked_at'])
                || strtotime((string) $session['expires_at']) <= time()
                || (string) $session['status'] !== 'active'
                || !hash_equals(
                    (string) $session['validator_hash'],
                    hash('sha256', $validator)
                )
            ) {
                self::clearRememberCookie($config, $repository);
                return;
            }

            $admin = $repository->findActiveById(
                (int) $session['platform_admin_id']
            );

            if (!$admin) {
                self::clearRememberCookie($config, $repository);
                return;
            }

            session_regenerate_id(true);
            self::establishSession($admin);
            $repository->touchRememberSession((int) $session['session_id']);
        } catch (Throwable $exception) {
            self::expireCookie($config);
        }
    }

    private static function clearRememberCookie(
        array $config,
        PlatformAdminRepository $repository
    ): void {
        $cookie = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        [$selector] = self::parseRememberCookie($cookie);

        if ($selector !== '') {
            $repository->revokeRememberSession($selector);
        }

        self::expireCookie($config);
    }

    private static function parseRememberCookie(string $cookie): array
    {
        $parts = explode('.', $cookie, 2);
        $selector = $parts[0] ?? '';
        $validator = $parts[1] ?? '';

        if (
            !preg_match('/^[a-f0-9]{24}$/', $selector)
            || !preg_match('/^[a-f0-9]{64}$/', $validator)
        ) {
            return ['', ''];
        }

        return [$selector, $validator];
    }

    private static function setCookie(
        array $config,
        string $value,
        int $expires
    ): void {
        $basePath = rtrim((string) ($config['app']['base_path'] ?? ''), '/');
        $path = $basePath !== '' ? $basePath . '/' : '/';
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires' => $expires,
            'path' => $path,
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if ($expires > time()) {
            $_COOKIE[self::REMEMBER_COOKIE] = $value;
        } else {
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }

    private static function expireCookie(array $config): void
    {
        self::setCookie($config, '', time() - 3600);
    }

    private static function publicAdmin(array $admin): array
    {
        return [
            'id' => (int) $admin['id'],
            'email' => (string) $admin['email'],
            'full_name' => (string) $admin['full_name'],
            'last_login_at' => $admin['last_login_at'] ?? null,
        ];
    }

    private static function clientIpHash(array $config): ?string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            $ip,
            (string) ($config['app']['app_key'] ?? 'marki-local')
        );
    }

    private static function userAgentHash(array $config): ?string
    {
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            $userAgent,
            (string) ($config['app']['app_key'] ?? 'marki-local')
        );
    }
}