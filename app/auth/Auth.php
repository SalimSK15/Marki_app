<?php

declare(strict_types=1);

require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/AuthRepository.php';
require_once __DIR__ . '/../security/SecurityRateLimiter.php';

class AuthException extends RuntimeException
{
}

final class AuthRateLimitException extends AuthException
{
}

final class Auth
{
    private const REMEMBER_COOKIE = 'marki_remember';
    private const DUMMY_PASSWORD_HASH = '$2y$12$taYOGaBOlzZUE4bgzH8FLubMMFj2fv/kbumEcWcPyOEsDcbv3EgjC';

    public static function start(array $config): void
    {
        $GLOBALS['marki_csp_nonce'] = markiSecurityBootstrap($config);
        startMarkiSession($config);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    public static function validateCsrf(?string $token = null): void
    {
        $expected = (string) ($_SESSION['csrf_token'] ?? '');
        $provided = $token
            ?? (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')
            ?? '';

        if (
            $expected === ''
            || $provided === ''
            || !hash_equals($expected, $provided)
        ) {
            throw new AuthException('La session de sécurité a expiré. Rechargez la page.');
        }
    }

    public static function attemptLogin(
        array $config,
        string $clinicSlug,
        string $identifier,
        string $password,
        bool $remember
    ): array {
        self::start($config);

        $clinicSlug = mb_strtolower(trim($clinicSlug), 'UTF-8');
        $identifier = trim($identifier);

        self::throttlePublicAction(
            $config,
            'login_ip',
            markiClientIp(),
            30,
            900
        );
        self::throttlePublicAction(
            $config,
            'login_identity',
            markiClientIp() . '|' . $clinicSlug . '|' . $identifier,
            8,
            900
        );

        if ($clinicSlug === '' || $identifier === '' || $password === '') {
            throw new AuthException('Identifiant ou mot de passe incorrect.');
        }

        $repository = new AuthRepository();
        $clinic = $repository->findClinicBySlug($clinicSlug);

        if (!$clinic || $clinic['status'] !== 'active') {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            throw new AuthException('Identifiant ou mot de passe incorrect.');
        }

        configureClinicTimezone(
            db(),
            (int) $clinic['id'],
            (string) ($config['app']['timezone'] ?? 'UTC')
        );

        $user = $repository->findUserForLogin((int) $clinic['id'], $identifier);

        if (!$user || $user['status'] !== 'active') {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            throw new AuthException('Identifiant ou mot de passe incorrect.');
        }

        if (
            !empty($user['locked_until'])
            && strtotime((string) $user['locked_until']) > time()
        ) {
            $remainingSeconds = max(
                60,
                strtotime((string) $user['locked_until']) - time()
            );
            $remainingMinutes = (int) ceil($remainingSeconds / 60);

            throw new AuthException(
                sprintf(
                    'Trop de tentatives. Ce compte est verrouillé pendant encore %d minute(s).',
                    $remainingMinutes
                )
            );
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $failure = $repository->recordFailedLogin(
                (int) $user['id'],
                (int) ($user['failed_login_attempts'] ?? 0),
                (int) ($config['auth']['max_failed_attempts'] ?? 5),
                (int) ($config['auth']['lock_minutes'] ?? 15)
            );

            if (!empty($failure['locked_until'])) {
                throw new AuthException(
                    sprintf(
                        'Trop de tentatives. Ce compte est verrouillé pendant %d minute(s).',
                        (int) ($config['auth']['lock_minutes'] ?? 15)
                    )
                );
            }

            throw new AuthException('Identifiant ou mot de passe incorrect.');
        }

        $roles = $repository->rolesForUser((int) $user['id']);
        $doctors = $repository->accessibleDoctors(
            (int) $user['id'],
            (int) $user['clinic_id'],
            $roles
        );

        if ($doctors === []) {
            throw new AuthException(
                'Aucun médecin actif ne vous est attribué. Contactez l’administrateur.'
            );
        }

        $selectedDoctorId = (int) $doctors[0]['id'];

        $repository->recordSuccessfulLogin((int) $user['id']);
        session_regenerate_id(true);

        self::establishSession(
            $user,
            $roles,
            $selectedDoctorId
        );

        if ($remember) {
            self::createRememberCookie(
                $config,
                $repository,
                (int) $user['id'],
                (int) $user['clinic_id'],
                $selectedDoctorId
            );
        }

        $repository->log(
            (int) $user['clinic_id'],
            (int) $user['id'],
            'USER_LOGGED_IN',
            'user',
            (int) $user['id'],
            [
                'remembered_device' => $remember,
                'selected_doctor_id' => $selectedDoctorId,
                'ip_hash' => self::clientIpHash($config),
            ]
        );

        return [
            'user' => [
                'id' => (int) $user['id'],
                'must_change_password' =>
                    (bool) ($user['must_change_password'] ?? false),
            ],
            'clinic' => [
                'id' => (int) $clinic['id'],
                'slug' => (string) $clinic['slug'],
            ],
        ];
    }

    public static function context(array $config, bool $api = true): array
    {
        self::start($config);

        if (empty($_SESSION['user_id'])) {
            self::restoreFromRememberCookie($config);
        }

        if (empty($_SESSION['user_id'])) {
            self::denyUnauthenticated($config, $api);
        }

        $idleTimeout = (int) ($config['auth']['idle_timeout_seconds'] ?? 43200);
        $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);

        if (
            $lastActivity > 0
            && (time() - $lastActivity) > $idleTimeout
        ) {
            self::logout($config, false);
            self::denyUnauthenticated($config, $api, 'Votre session a expiré.');
        }

        $repository = new AuthRepository();
        $user = $repository->findActiveUserContext((int) $_SESSION['user_id']);

        if (!$user) {
            self::logout($config, false);
            self::denyUnauthenticated($config, $api);
        }

        $sessionPasswordChangedAt = (string) ($_SESSION['password_changed_at'] ?? '');
        $databasePasswordChangedAt = (string) ($user['password_changed_at'] ?? '');

        if (
            $sessionPasswordChangedAt !== ''
            && $databasePasswordChangedAt !== ''
            && $sessionPasswordChangedAt !== $databasePasswordChangedAt
        ) {
            self::logout($config, false);
            self::denyUnauthenticated(
                $config,
                $api,
                'Votre mot de passe a changé. Reconnectez-vous.'
            );
        }

        $_SESSION['password_changed_at'] = $databasePasswordChangedAt;

        $roles = $repository->rolesForUser((int) $user['id']);
        $doctors = $repository->accessibleDoctors(
            (int) $user['id'],
            (int) $user['clinic_id'],
            $roles
        );

        if ($doctors === []) {
            self::logout($config, false);
            self::denyForbidden(
                $config,
                $api,
                'Aucun médecin actif ne vous est attribué.'
            );
        }

        $selectedDoctorId = (int) ($_SESSION['selected_doctor_id'] ?? 0);
        $doctorIds = array_map(
            static fn(array $doctor): int => (int) $doctor['id'],
            $doctors
        );

        if (!in_array($selectedDoctorId, $doctorIds, true)) {
            $selectedDoctorId = (int) $doctors[0]['id'];
            $_SESSION['selected_doctor_id'] = $selectedDoctorId;
        }

        $selectedDoctor = null;
        foreach ($doctors as $doctor) {
            if ((int) $doctor['id'] === $selectedDoctorId) {
                $selectedDoctor = $doctor;
                break;
            }
        }

        if ($selectedDoctor === null) {
            throw new RuntimeException('Médecin sélectionné introuvable.');
        }

        $accessLevel = $repository->accessLevelForDoctor(
            (int) $user['id'],
            $selectedDoctorId,
            $roles
        );

        if ($accessLevel === null) {
            self::denyForbidden($config, $api, 'Accès au médecin refusé.');
        }

        $capabilities = self::buildCapabilities($roles, $accessLevel);
        $timezone = configureClinicTimezone(
            db(),
            (int) $user['clinic_id'],
            (string) ($config['app']['timezone'] ?? 'UTC')
        );
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone));

        $_SESSION['clinic_id'] = (int) $user['clinic_id'];
        $_SESSION['role_codes'] = $roles;
        $_SESSION['last_activity_at'] = time();
        $_SESSION['must_change_password'] = (bool) $user['must_change_password'];

        $context = [
            'config' => $config,
            'pdo' => db(),
            'user_id' => (int) $user['id'],
            'clinic_id' => (int) $user['clinic_id'],
            'doctor_id' => $selectedDoctorId,
            'selected_doctor_id' => $selectedDoctorId,
            'user' => [
                'id' => (int) $user['id'],
                'full_name' => (string) $user['full_name'],
                'email' => $user['email'],
                'phone' => PatientDataNormalizer::formatPhoneForDisplay(
                    $user['phone'] !== null ? (string) $user['phone'] : null
                ),
                'must_change_password' => (bool) $user['must_change_password'],
            ],
            'clinic' => [
                'id' => (int) $user['clinic_id'],
                'name' => (string) $user['clinic_name'],
                'slug' => (string) $user['clinic_slug'],
            ],
            'roles' => $roles,
            'role_label' => self::roleLabel($roles),
            'access_level' => $accessLevel,
            'capabilities' => $capabilities,
            'doctors' => $doctors,
            'doctor' => $selectedDoctor,
            'timezone' => $timezone,
            'today' => $now->format('Y-m-d'),
            'now' => $now->format('Y-m-d H:i:s'),
            'csrf_token' => self::csrfToken(),
            'csp_nonce' => markiCspNonce(),
        ];

        if ((bool) $user['must_change_password']) {
            $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $allowed = [
                'change-password.php',
                'auth_change_password.php',
                'auth_logout.php',
                'auth_context.php',
            ];

            if (!in_array($script, $allowed, true)) {
                if ($api) {
                    self::jsonError(
                        403,
                        'Vous devez modifier votre mot de passe avant de continuer.',
                        'PASSWORD_CHANGE_REQUIRED'
                    );
                }

                header(
                    'Location: '
                    . self::baseUrl($config)
                    . '/change-password.php'
                );
                exit;
            }
        }

        return $context;
    }

    public static function authorizeEndpoint(array $context): void
    {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $rules = [
            'queue_entries.php' => ['queue.view'],
            'queue_today.php' => ['queue.view'],
            'queue_add_patient.php' => ['queue.manage'],
            'queue_update_patient.php' => ['queue.manage'],
            'queue_update_status.php' => ['queue.manage'],
            'queue_toggle_status.php' => ['queue.manage'],
            'queue_change_day_status.php' => ['queue.manage'],
            'patients_index.php' => ['patients.view'],
            'patient_details.php' => ['patients.view'],
            'patient_update_profile.php' => ['patients.manage'],
            'patient_add_to_today.php' => ['patients.manage', 'queue.manage'],
            'queues_history.php' => ['lists.view'],
            'queue_history_details.php' => ['lists.view'],
            'settings_get.php' => ['settings.view'],
            'settings_update.php' => ['settings.manage_doctor'],
            'public_registration_get.php' => ['settings.manage_doctor'],
            'public_registration_save.php' => ['settings.manage_doctor'],
            'public_registration_toggle.php' => ['settings.manage_doctor'],
            'public_registration_revoke.php' => ['settings.manage_doctor'],
            'team_list.php' => ['team.manage'],
            'team_save.php' => ['team.manage'],
            'team_toggle_status.php' => ['team.manage'],
        ];

        foreach ($rules[$script] ?? [] as $capability) {
            self::requireCapability($context, $capability);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            self::validateCsrf();
        }
    }

    public static function requireCapability(
        array $context,
        string $capability
    ): void {
        if (!($context['capabilities'][$capability] ?? false)) {
            self::jsonError(403, 'Vous n’avez pas accès à cette fonctionnalité.');
        }
    }

    public static function selectDoctor(
        array $config,
        int $doctorId
    ): array {
        $context = self::context($config, true);
        $allowed = array_map(
            static fn(array $doctor): int => (int) $doctor['id'],
            $context['doctors']
        );

        if (!in_array($doctorId, $allowed, true)) {
            throw new AuthException('Médecin non autorisé.');
        }

        $previousDoctorId = (int) ($context['doctor_id'] ?? 0);

        $_SESSION['selected_doctor_id'] = $doctorId;
        $_SESSION['last_activity_at'] = time();

        $repository = new AuthRepository();
        $remember = self::parseRememberCookie();
        if ($remember !== null) {
            $repository->updatePersistentSelectedDoctor(
                $remember['selector'],
                $doctorId
            );
        }

        if ($previousDoctorId !== $doctorId) {
            $repository->log(
                (int) $context['clinic_id'],
                (int) $context['user_id'],
                'DOCTOR_CONTEXT_CHANGED',
                'doctor',
                $doctorId,
                [
                    'previous_doctor_id' => $previousDoctorId,
                    'selected_doctor_id' => $doctorId,
                ]
            );
        }

        return self::context($config, true);
    }

    public static function logout(array $config, bool $log = true): void
    {
        self::start($config);

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $clinicId = isset($_SESSION['clinic_id']) ? (int) $_SESSION['clinic_id'] : 0;
        $remember = self::parseRememberCookie();
        $repository = new AuthRepository();

        if ($remember !== null) {
            $repository->revokePersistentSession($remember['selector']);
        }

        if ($log && $userId > 0 && $clinicId > 0) {
            $repository->log(
                $clinicId,
                $userId,
                'USER_LOGGED_OUT',
                'user',
                $userId
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        self::clearRememberCookie($config);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function requestPasswordReset(
        array $config,
        string $clinicSlug,
        string $email
    ): void {
        self::start($config);

        $clinicSlug = mb_strtolower(trim($clinicSlug), 'UTF-8');
        $email = mb_strtolower(trim($email), 'UTF-8');

        self::throttlePublicAction(
            $config,
            'password_reset_ip',
            markiClientIp(),
            8,
            3600
        );
        self::throttlePublicAction(
            $config,
            'password_reset_identity',
            markiClientIp() . '|' . $clinicSlug . '|' . $email,
            4,
            3600
        );

        if ($clinicSlug === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $repository = new AuthRepository();
        $clinic = $repository->findClinicBySlug($clinicSlug);
        if (!$clinic || $clinic['status'] !== 'active') {
            return;
        }

        $user = $repository->findUserForLogin((int) $clinic['id'], $email);
        if (!$user || $user['status'] !== 'active' || empty($user['email'])) {
            return;
        }

        $selector = bin2hex(random_bytes(12));
        $token = bin2hex(random_bytes(32));
        $repository->createPasswordResetToken(
            (int) $user['id'],
            (int) $user['clinic_id'],
            $selector,
            hash('sha256', $token),
            date('Y-m-d H:i:s', time() + 1800)
        );

        $link = markiAbsoluteUrl($config, self::baseUrl($config))
            . '/reset-password.php?selector='
            . rawurlencode($selector)
            . '&token='
            . rawurlencode($token);

        self::deliverResetLink($config, (string) $user['email'], $link);
    }

    public static function resetPassword(
        array $config,
        string $selector,
        string $token,
        string $newPassword
    ): void {
        self::throttlePublicAction(
            $config,
            'password_reset_submit',
            markiClientIp(),
            10,
            900
        );
        self::assertPasswordStrength($config, $newPassword);

        $repository = new AuthRepository();
        $reset = $repository->findPasswordResetToken($selector);

        if (
            !$reset
            || !hash_equals(
                (string) $reset['token_hash'],
                hash('sha256', $token)
            )
            || $reset['status'] !== 'active'
        ) {
            throw new AuthException('Le lien de réinitialisation est invalide ou expiré.');
        }

        $repository->consumePasswordResetToken(
            (int) $reset['id'],
            (int) $reset['user_id'],
            password_hash($newPassword, PASSWORD_DEFAULT)
        );
    }

    public static function changePassword(
        array $config,
        array $context,
        string $currentPassword,
        string $newPassword
    ): void {
        self::assertPasswordStrength($config, $newPassword);
        $repository = new AuthRepository();
        $currentHash = $repository->passwordHashForUser((int) $context['user_id']);

        if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
            throw new AuthException('Le mot de passe actuel est incorrect.');
        }

        if (password_verify($newPassword, $currentHash)) {
            throw new AuthException('Le nouveau mot de passe doit être différent.');
        }

        $changedAt = date('Y-m-d H:i:s');

        $repository->updatePassword(
            (int) $context['user_id'],
            password_hash($newPassword, PASSWORD_DEFAULT),
            $changedAt
        );
        $repository->revokeAllUserSessions((int) $context['user_id']);
        $repository->log(
            (int) $context['clinic_id'],
            (int) $context['user_id'],
            'PASSWORD_CHANGED',
            'user',
            (int) $context['user_id']
        );

        $_SESSION['must_change_password'] = false;
        $_SESSION['password_changed_at'] = $changedAt;
        session_regenerate_id(true);
    }

    public static function baseUrl(array $config): string
    {
        return rtrim((string) ($config['app']['base_path'] ?? ''), '/');
    }

    public static function jsonError(
        int $status,
        string $message,
        ?string $code = null
    ): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $payload = [
            'ok' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $payload['error_code'] = $code;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function establishSession(
        array $user,
        array $roles,
        int $selectedDoctorId
    ): void {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['clinic_id'] = (int) $user['clinic_id'];
        $_SESSION['role_codes'] = $roles;
        $_SESSION['selected_doctor_id'] = $selectedDoctorId;
        $_SESSION['authenticated_at'] = time();
        $_SESSION['last_activity_at'] = time();
        $_SESSION['must_change_password'] = (bool) ($user['must_change_password'] ?? false);
        $_SESSION['password_changed_at'] = (string) ($user['password_changed_at'] ?? '');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    private static function createRememberCookie(
        array $config,
        AuthRepository $repository,
        int $userId,
        int $clinicId,
        int $doctorId
    ): void {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $days = (int) ($config['auth']['remember_days'] ?? 30);
        $expires = time() + ($days * 86400);

        $repository->createPersistentSession(
            $userId,
            $clinicId,
            $doctorId,
            $selector,
            hash('sha256', $validator),
            self::userAgentHash($config),
            self::clientIpHash($config),
            date('Y-m-d H:i:s', $expires)
        );

        self::setRememberCookie($config, $selector . ':' . $validator, $expires);
    }

    private static function restoreFromRememberCookie(array $config): void
    {
        $cookie = self::parseRememberCookie();
        if ($cookie === null) {
            return;
        }

        $repository = new AuthRepository();
        $persistent = $repository->findPersistentSession($cookie['selector']);

        if (!$persistent) {
            self::clearRememberCookie($config);
            return;
        }

        $expectedUserAgentHash = self::userAgentHash($config);
        $storedUserAgentHash = $persistent['user_agent_hash'] ?? null;
        $userAgentMatches = $storedUserAgentHash === null
            || $expectedUserAgentHash === null
            || hash_equals(
                (string) $storedUserAgentHash,
                (string) $expectedUserAgentHash
            );

        if (
            !$userAgentMatches
            || !hash_equals(
                (string) $persistent['validator_hash'],
                hash('sha256', $cookie['validator'])
            )
        ) {
            $repository->revokePersistentSession($cookie['selector']);
            self::clearRememberCookie($config);
            return;
        }

        $user = $repository->findActiveUserContext((int) $persistent['user_id']);
        if (!$user) {
            $repository->revokePersistentSession($cookie['selector']);
            self::clearRememberCookie($config);
            return;
        }

        $roles = $repository->rolesForUser((int) $user['id']);
        $doctors = $repository->accessibleDoctors(
            (int) $user['id'],
            (int) $user['clinic_id'],
            $roles
        );
        if ($doctors === []) {
            $repository->revokePersistentSession($cookie['selector']);
            self::clearRememberCookie($config);
            return;
        }

        $allowedIds = array_map(
            static fn(array $doctor): int => (int) $doctor['id'],
            $doctors
        );
        $doctorId = (int) ($persistent['selected_doctor_id'] ?? 0);
        if (!in_array($doctorId, $allowedIds, true)) {
            $doctorId = (int) $doctors[0]['id'];
        }

        session_regenerate_id(true);
        self::establishSession($user, $roles, $doctorId);

        $newValidator = bin2hex(random_bytes(32));
        $repository->rotatePersistentSession(
            (int) $persistent['id'],
            hash('sha256', $newValidator),
            $doctorId
        );
        self::setRememberCookie(
            $config,
            $cookie['selector'] . ':' . $newValidator,
            strtotime((string) $persistent['expires_at'])
        );
    }

    private static function buildCapabilities(
        array $roles,
        string $accessLevel
    ): array {
        $isAdmin = in_array('clinic_admin', $roles, true);
        $isDoctor = in_array('doctor', $roles, true);
        $hasPatients = in_array(
            $accessLevel,
            ['queue_and_patients', 'full'],
            true
        );
        $hasFull = $accessLevel === 'full';

        return [
            'queue.view' => true,
            'queue.manage' => true,
            'patients.view' => $isAdmin || $isDoctor || $hasPatients,
            'patients.manage' => $isAdmin || $isDoctor || $hasPatients,
            'lists.view' => $isAdmin || $isDoctor || $hasPatients,
            'settings.view' => $isAdmin || $isDoctor || $hasFull,
            'settings.manage_doctor' => $isAdmin || $isDoctor,
            'settings.manage_clinic' => $isAdmin,
            'team.manage' => $isAdmin,
        ];
    }

    private static function roleLabel(array $roles): string
    {
        if (in_array('clinic_admin', $roles, true)) {
            return in_array('doctor', $roles, true)
                ? 'Médecin administrateur'
                : 'Administrateur de la structure';
        }

        if (in_array('doctor', $roles, true)) {
            return 'Médecin';
        }

        return 'Secrétariat';
    }

    private static function denyUnauthenticated(
        array $config,
        bool $api,
        string $message = 'Vous devez vous connecter.'
    ): never {
        if ($api) {
            self::jsonError(401, $message, 'AUTH_REQUIRED');
        }

        header('Location: ' . self::baseUrl($config) . '/login.php');
        exit;
    }

    private static function denyForbidden(
        array $config,
        bool $api,
        string $message
    ): never {
        if ($api) {
            self::jsonError(403, $message);
        }

        http_response_code(403);
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        exit;
    }

    private static function parseRememberCookie(): ?array
    {
        $value = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($value === '' || !str_contains($value, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $value, 2);
        if (
            preg_match('/^[a-f0-9]{24}$/', $selector) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $validator) !== 1
        ) {
            return null;
        }

        return [
            'selector' => $selector,
            'validator' => $validator,
        ];
    }

    private static function setRememberCookie(
        array $config,
        string $value,
        int $expires
    ): void {
        $basePath = rtrim((string) ($config['app']['base_path'] ?? '/'), '/');
        $path = $basePath !== '' ? $basePath . '/' : '/';
        $secure = markiRequestIsHttps($config)
            || (bool) ($config['security']['force_https'] ?? false);

        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires' => $expires,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private static function clearRememberCookie(array $config): void
    {
        self::setRememberCookie($config, '', time() - 3600);
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
            (string) ($config['app']['app_key'] ?? 'marki')
        );
    }

    private static function userAgentHash(array $config): ?string
    {
        $agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($agent === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            $agent,
            (string) ($config['app']['app_key'] ?? 'marki')
        );
    }

    private static function assertPasswordStrength(
        array $config,
        string $password
    ): void {
        $minimum = (int) ($config['auth']['password_min_length'] ?? 10);

        if (mb_strlen($password) < $minimum) {
            throw new AuthException(
                "Le mot de passe doit contenir au moins {$minimum} caractères."
            );
        }

        if (
            preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1
        ) {
            throw new AuthException(
                'Le mot de passe doit contenir une majuscule, une minuscule et un chiffre.'
            );
        }
    }

    private static function deliverResetLink(
        array $config,
        string $email,
        string $link
    ): void {
        if (($config['app']['env'] ?? 'local') === 'local') {
            $logDirectory = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDirectory)) {
                mkdir($logDirectory, 0775, true);
            }

            file_put_contents(
                $logDirectory . '/password_reset.log',
                sprintf("[%s] %s %s\n", date('c'), $email, $link),
                FILE_APPEND | LOCK_EX
            );
            return;
        }

        @mail(
            $email,
            'Réinitialisation de votre mot de passe MARKI',
            "Utilisez ce lien pendant 30 minutes :\n\n{$link}",
            "Content-Type: text/plain; charset=UTF-8\r\n"
        );
    }

    private static function throttlePublicAction(
        array $config,
        string $scope,
        string $subject,
        int $maximum,
        int $windowSeconds
    ): void {
        try {
            SecurityRateLimiter::consume(
                $config,
                $scope,
                $subject,
                $maximum,
                $windowSeconds
            );
        } catch (SecurityRateLimitException $exception) {
            throw new AuthRateLimitException($exception->getMessage());
        } catch (PDOException $exception) {
            if ((string) ($config['app']['env'] ?? 'local') === 'production') {
                throw new RuntimeException(
                    'La protection contre les attaques est indisponible.',
                    0,
                    $exception
                );
            }
        }
    }
}
