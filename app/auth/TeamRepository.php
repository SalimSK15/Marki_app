<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/PatientDataNormalizer.php';
require_once __DIR__ . '/AuthRepository.php';

final class TeamValidationException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        private array $errors = []
    ) {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

final class TeamRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function index(int $clinicId, int $currentUserId): array
    {
        /*
        |------------------------------------------------------------------
        | Charger les comptes sans agrégation SQL fragile
        |------------------------------------------------------------------
        | L'ancienne requête regroupait les rôles avec GROUP_CONCAT puis
        | réutilisait cet alias dans ORDER BY. Selon la version/configuration
        | MySQL ou MariaDB, cela pouvait faire échouer uniquement la section
        | « Équipe et accès ». Les comptes, rôles et accès sont maintenant
        | chargés par requêtes simples et assemblés en PHP.
        |------------------------------------------------------------------
        */
        $membersStmt = $this->pdo->prepare(
            "SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.status,
                u.must_change_password,
                u.last_login_at,
                u.created_at,
                sp.id AS staff_profile_id,
                sp.job_title,
                dp.id AS doctor_profile_id,
                dp.display_name AS doctor_display_name,
                dp.specialty AS doctor_specialty,
                dp.license_number AS doctor_license_number
             FROM users u
             LEFT JOIN staff_profiles sp
               ON sp.user_id = u.id
              AND sp.clinic_id = u.clinic_id
             LEFT JOIN doctor_profiles dp
               ON dp.user_id = u.id
              AND dp.clinic_id = u.clinic_id
             WHERE u.clinic_id = :clinic_id
             ORDER BY u.full_name ASC, u.id ASC"
        );
        $membersStmt->execute([':clinic_id' => $clinicId]);
        $rows = $membersStmt->fetchAll();

        $rolesByUser = [];
        $rolesStmt = $this->pdo->prepare(
            "SELECT ur.user_id, r.code
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             INNER JOIN users u ON u.id = ur.user_id
             WHERE u.clinic_id = :clinic_id
             ORDER BY ur.user_id ASC, r.id ASC"
        );
        $rolesStmt->execute([':clinic_id' => $clinicId]);

        foreach ($rolesStmt->fetchAll() as $roleRow) {
            $rolesByUser[(int) $roleRow['user_id']][] =
                (string) $roleRow['code'];
        }

        $accessesByStaff = [];
        $accessStmt = $this->pdo->prepare(
            "SELECT
                sda.staff_profile_id,
                sda.doctor_id,
                sda.access_level
             FROM staff_doctor_access sda
             INNER JOIN staff_profiles sp
               ON sp.id = sda.staff_profile_id
             WHERE sp.clinic_id = :clinic_id
             ORDER BY sda.staff_profile_id ASC, sda.doctor_id ASC"
        );
        $accessStmt->execute([':clinic_id' => $clinicId]);

        foreach ($accessStmt->fetchAll() as $accessRow) {
            $accessesByStaff[(int) $accessRow['staff_profile_id']][] = [
                'doctor_id' => (int) $accessRow['doctor_id'],
                'access_level' => (string) $accessRow['access_level'],
            ];
        }

        $members = [];

        foreach ($rows as $row) {
            $userId = (int) $row['id'];
            $roles = array_values(array_unique($rolesByUser[$userId] ?? []));
            $staffProfileId = $row['staff_profile_id'] !== null
                ? (int) $row['staff_profile_id']
                : null;
            $accountType = in_array('doctor', $roles, true)
                ? 'doctor'
                : 'secretary';

            $members[] = [
                'id' => $userId,
                'full_name' => (string) $row['full_name'],
                'email' => $row['email'],
                'phone' => PatientDataNormalizer::formatPhoneForDisplay(
                    $row['phone'] !== null ? (string) $row['phone'] : null
                ),
                'status' => (string) $row['status'],
                'must_change_password' => (bool) $row['must_change_password'],
                'last_login_at' => $row['last_login_at'],
                'created_at' => $row['created_at'],
                'roles' => $roles,
                'account_type' => $accountType,
                'job_title' => $row['job_title'],
                'doctor_profile_id' => $row['doctor_profile_id'] !== null
                    ? (int) $row['doctor_profile_id']
                    : null,
                'doctor_display_name' => $row['doctor_display_name'],
                'doctor_specialty' => $row['doctor_specialty'],
                'doctor_license_number' => $row['doctor_license_number'],
                'doctor_accesses' => $staffProfileId !== null
                    ? ($accessesByStaff[$staffProfileId] ?? [])
                    : [],
                'is_current_user' => $userId === $currentUserId,
                'is_protected_admin' => in_array('clinic_admin', $roles, true),
            ];
        }

        usort(
            $members,
            static function (array $left, array $right): int {
                $leftAdmin = in_array('clinic_admin', $left['roles'], true);
                $rightAdmin = in_array('clinic_admin', $right['roles'], true);

                if ($leftAdmin !== $rightAdmin) {
                    return $leftAdmin ? -1 : 1;
                }

                return strcasecmp(
                    (string) $left['full_name'],
                    (string) $right['full_name']
                );
            }
        );

        $doctorsStmt = $this->pdo->prepare(
            "SELECT id, display_name, specialty, is_active
             FROM doctor_profiles
             WHERE clinic_id = :clinic_id
             ORDER BY display_name ASC, id ASC"
        );
        $doctorsStmt->execute([':clinic_id' => $clinicId]);

        $doctors = array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
                'specialty' => $row['specialty'],
                'is_active' => (bool) $row['is_active'],
            ],
            $doctorsStmt->fetchAll()
        );

        return [
            'members' => $members,
            'doctors' => $doctors,
        ];
    }

    public function save(
        int $clinicId,
        int $actorUserId,
        array $input,
        int $minimumPasswordLength
    ): array {
        $userId = (int) ($input['user_id'] ?? 0);
        $fullName = PatientDataNormalizer::normalizeName(
            (string) ($input['full_name'] ?? '')
        );
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')), 'UTF-8');
        $rawPhone = trim((string) ($input['phone'] ?? ''));
        $phone = $rawPhone !== ''
            ? PatientDataNormalizer::normalizePhone($rawPhone)
            : null;
        $accountType = trim((string) ($input['account_type'] ?? 'secretary'));
        $temporaryPassword = (string) ($input['temporary_password'] ?? '');
        $jobTitle = trim((string) ($input['job_title'] ?? ''));
        $specialty = trim((string) ($input['specialty'] ?? ''));
        $licenseNumber = trim((string) ($input['license_number'] ?? ''));
        $accessLevel = trim((string) ($input['access_level'] ?? 'queue_only'));
        $doctorIds = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($input['doctor_ids'] ?? null)
                ? $input['doctor_ids']
                : []
        ), static fn(int $id): bool => $id > 0)));

        if ($fullName === '') {
            throw new TeamValidationException(
                'Le nom complet est obligatoire.',
                ['full_name' => 'Le nom complet est obligatoire.']
            );
        }

        if ($email === '' && $phone === null) {
            throw new TeamValidationException(
                'Un courriel ou un numéro de téléphone est obligatoire.',
                [
                    'email' => 'Saisissez un courriel ou un téléphone.',
                    'phone' => 'Saisissez un courriel ou un téléphone.',
                ]
            );
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new TeamValidationException(
                'Adresse courriel invalide.',
                ['email' => 'Adresse courriel invalide.']
            );
        }

        if ($phone !== null && !PatientDataNormalizer::isValidPhone($phone)) {
            throw new TeamValidationException(
                PatientDataNormalizer::phoneValidationMessage(),
                ['phone' => PatientDataNormalizer::phoneValidationMessage()]
            );
        }

        if (!in_array($accountType, ['doctor', 'secretary'], true)) {
            throw new TeamValidationException(
                'Type de compte invalide.',
                ['account_type' => 'Type de compte invalide.']
            );
        }

        if (!in_array(
            $accessLevel,
            ['queue_only', 'queue_and_patients', 'full'],
            true
        )) {
            throw new TeamValidationException(
                'Niveau d’accès invalide.',
                ['access_level' => 'Niveau d’accès invalide.']
            );
        }

        if ($userId === 0) {
            $this->assertTemporaryPasswordStrength(
                $temporaryPassword,
                $minimumPasswordLength
            );
        } elseif ($temporaryPassword !== '') {
            $this->assertTemporaryPasswordStrength(
                $temporaryPassword,
                $minimumPasswordLength
            );
        }

        if ($accountType === 'secretary' && $doctorIds === []) {
            throw new TeamValidationException(
                'Attribuez au moins un médecin à ce compte.',
                ['doctor_ids' => 'Attribuez au moins un médecin à ce compte.']
            );
        }

        $email = $email !== '' ? $email : null;
        $jobTitle = $jobTitle !== '' ? $jobTitle : null;
        $specialty = $specialty !== '' ? $specialty : null;
        $licenseNumber = $licenseNumber !== '' ? $licenseNumber : null;

        $this->assertUniqueIdentifiers($clinicId, $email, $phone, $userId);
        $this->assertDoctorsBelongToClinic($clinicId, $doctorIds);

        $this->pdo->beginTransaction();

        try {
            if ($userId === 0) {
                $userId = $this->createUser(
                    $clinicId,
                    $fullName,
                    $email,
                    $phone,
                    $temporaryPassword
                );
                $this->assignRole($userId, $accountType);

                if ($accountType === 'doctor') {
                    $doctorId = $this->createDoctorProfile(
                        $clinicId,
                        $userId,
                        $fullName,
                        $specialty,
                        $licenseNumber
                    );
                    $entityId = $doctorId;
                } else {
                    $staffProfileId = $this->createStaffProfile(
                        $clinicId,
                        $userId,
                        $jobTitle
                    );
                    $this->replaceStaffAccesses(
                        $staffProfileId,
                        $doctorIds,
                        $accessLevel
                    );
                    $entityId = $staffProfileId;
                }

                $action = 'USER_CREATED';
            } else {
                $existing = $this->findEditableUser($clinicId, $userId);
                $existingType = in_array('doctor', $existing['roles'], true)
                    ? 'doctor'
                    : 'secretary';

                if ($userId === $actorUserId && $temporaryPassword !== '') {
                    throw new TeamValidationException(
                        'Utilisez le menu de votre compte pour changer votre mot de passe.',
                        [
                            'temporary_password' =>
                                'Utilisez « Changer le mot de passe » dans le menu de votre compte.',
                        ]
                    );
                }

                if ($existingType !== $accountType) {
                    throw new InvalidArgumentException(
                        'Le type de compte ne peut pas être changé.'
                    );
                }

                $sql = "
                    UPDATE users
                    SET full_name = :full_name,
                        email = :email,
                        phone = :phone,
                        updated_at = NOW()
                ";
                $params = [
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':user_id' => $userId,
                    ':clinic_id' => $clinicId,
                ];

                if ($temporaryPassword !== '') {
                    $sql .= ",
                        password_hash = :password_hash,
                        must_change_password = 1,
                        password_changed_at = NOW(),
                        failed_login_attempts = 0,
                        locked_until = NULL
                    ";
                    $params[':password_hash'] = password_hash(
                        $temporaryPassword,
                        PASSWORD_DEFAULT
                    );
                }

                $sql .= "
                    WHERE id = :user_id
                      AND clinic_id = :clinic_id
                    LIMIT 1
                ";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);

                if ($accountType === 'doctor') {
                    $doctorStmt = $this->pdo->prepare(
                        "UPDATE doctor_profiles
                         SET display_name = :display_name,
                             specialty = :specialty,
                             license_number = :license_number,
                             updated_at = NOW()
                         WHERE user_id = :user_id
                           AND clinic_id = :clinic_id
                         LIMIT 1"
                    );
                    $doctorStmt->execute([
                        ':display_name' => $fullName,
                        ':specialty' => $specialty,
                        ':license_number' => $licenseNumber,
                        ':user_id' => $userId,
                        ':clinic_id' => $clinicId,
                    ]);
                    $entityId = (int) $existing['doctor_profile_id'];
                } else {
                    $staffStmt = $this->pdo->prepare(
                        "UPDATE staff_profiles
                         SET job_title = :job_title,
                             updated_at = NOW()
                         WHERE user_id = :user_id
                           AND clinic_id = :clinic_id
                         LIMIT 1"
                    );
                    $staffStmt->execute([
                        ':job_title' => $jobTitle,
                        ':user_id' => $userId,
                        ':clinic_id' => $clinicId,
                    ]);
                    $staffProfileId = (int) $existing['staff_profile_id'];
                    $this->replaceStaffAccesses(
                        $staffProfileId,
                        $doctorIds,
                        $accessLevel
                    );
                    $entityId = $staffProfileId;
                }

                if ($temporaryPassword !== '') {
                    $this->pdo->prepare(
                        "UPDATE user_sessions
                         SET revoked_at = NOW()
                         WHERE user_id = :user_id
                           AND revoked_at IS NULL"
                    )->execute([':user_id' => $userId]);
                }

                $action = 'USER_UPDATED';
            }

            (new AuthRepository())->log(
                $clinicId,
                $actorUserId,
                $action,
                'user',
                $userId,
                [
                    'account_type' => $accountType,
                    'profile_entity_id' => $entityId,
                    'doctor_ids' => $doctorIds,
                    'access_level' => $accountType === 'secretary'
                        ? $accessLevel
                        : 'full',
                    'password_reset' => $temporaryPassword !== '',
                ]
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $this->index($clinicId, $actorUserId);
    }

    public function toggleStatus(
        int $clinicId,
        int $actorUserId,
        int $userId
    ): array {
        if ($userId === $actorUserId) {
            throw new InvalidArgumentException(
                'Vous ne pouvez pas désactiver votre propre compte.'
            );
        }

        $user = $this->findEditableUser($clinicId, $userId);
        if (in_array('clinic_admin', $user['roles'], true)) {
            throw new InvalidArgumentException(
                'Le compte administrateur principal est protégé.'
            );
        }

        $newStatus = $user['status'] === 'active' ? 'disabled' : 'active';

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE users
                 SET status = :status,
                     updated_at = NOW()
                 WHERE id = :user_id
                   AND clinic_id = :clinic_id
                 LIMIT 1"
            );
            $stmt->execute([
                ':status' => $newStatus,
                ':user_id' => $userId,
                ':clinic_id' => $clinicId,
            ]);

            if ($newStatus === 'disabled') {
                $this->pdo->prepare(
                    "UPDATE user_sessions
                     SET revoked_at = NOW()
                     WHERE user_id = :user_id
                       AND revoked_at IS NULL"
                )->execute([':user_id' => $userId]);
            }

            (new AuthRepository())->log(
                $clinicId,
                $actorUserId,
                $newStatus === 'active'
                    ? 'USER_ENABLED'
                    : 'USER_DISABLED',
                'user',
                $userId
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $this->index($clinicId, $actorUserId);
    }

    private function createUser(
        int $clinicId,
        string $fullName,
        ?string $email,
        ?string $phone,
        string $temporaryPassword
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (
                clinic_id,
                email,
                phone,
                password_hash,
                full_name,
                status,
                last_login_at,
                must_change_password,
                password_changed_at,
                failed_login_attempts,
                locked_until,
                created_at,
                updated_at
             ) VALUES (
                :clinic_id,
                :email,
                :phone,
                :password_hash,
                :full_name,
                'active',
                NULL,
                1,
                NOW(),
                0,
                NULL,
                NOW(),
                NOW()
             )"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':email' => $email,
            ':phone' => $phone,
            ':password_hash' => password_hash(
                $temporaryPassword,
                PASSWORD_DEFAULT
            ),
            ':full_name' => $fullName,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assignRole(int $userId, string $accountType): void
    {
        $roleCode = $accountType === 'doctor' ? 'doctor' : 'secretary';
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_roles (user_id, role_id)
             SELECT :user_id, id
             FROM roles
             WHERE code = :role_code
             LIMIT 1"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':role_code' => $roleCode,
        ]);
    }

    private function createDoctorProfile(
        int $clinicId,
        int $userId,
        string $fullName,
        ?string $specialty,
        ?string $licenseNumber
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO doctor_profiles (
                clinic_id,
                user_id,
                display_name,
                specialty,
                license_number,
                address,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :clinic_id,
                :user_id,
                :display_name,
                :specialty,
                :license_number,
                NULL,
                1,
                NOW(),
                NOW()
             )"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':user_id' => $userId,
            ':display_name' => $fullName,
            ':specialty' => $specialty,
            ':license_number' => $licenseNumber,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createStaffProfile(
        int $clinicId,
        int $userId,
        ?string $jobTitle
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO staff_profiles (
                clinic_id,
                user_id,
                job_title,
                created_at,
                updated_at
             ) VALUES (
                :clinic_id,
                :user_id,
                :job_title,
                NOW(),
                NOW()
             )"
        );
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':user_id' => $userId,
            ':job_title' => $jobTitle,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function replaceStaffAccesses(
        int $staffProfileId,
        array $doctorIds,
        string $accessLevel
    ): void {
        $this->pdo->prepare(
            'DELETE FROM staff_doctor_access WHERE staff_profile_id = :staff_profile_id'
        )->execute([':staff_profile_id' => $staffProfileId]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO staff_doctor_access (
                staff_profile_id,
                doctor_id,
                access_level
             ) VALUES (
                :staff_profile_id,
                :doctor_id,
                :access_level
             )"
        );

        foreach ($doctorIds as $doctorId) {
            $stmt->execute([
                ':staff_profile_id' => $staffProfileId,
                ':doctor_id' => $doctorId,
                ':access_level' => $accessLevel,
            ]);
        }
    }

    private function findEditableUser(int $clinicId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id,
                u.status,
                sp.id AS staff_profile_id,
                dp.id AS doctor_profile_id,
                GROUP_CONCAT(DISTINCT r.code SEPARATOR ',') AS role_codes
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             LEFT JOIN staff_profiles sp ON sp.user_id = u.id
             LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
             WHERE u.id = :user_id
               AND u.clinic_id = :clinic_id
             GROUP BY u.id, u.status, sp.id, dp.id
             LIMIT 1"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':clinic_id' => $clinicId,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Compte introuvable.');
        }

        $row['roles'] = array_values(array_filter(
            explode(',', (string) ($row['role_codes'] ?? ''))
        ));

        return $row;
    }

    private function assertUniqueIdentifiers(
        int $clinicId,
        ?string $email,
        ?string $phone,
        int $excludedUserId
    ): void {
        if ($email !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE clinic_id = :clinic_id
                   AND LOWER(email) = :email
                   AND id <> :excluded_user_id
                 LIMIT 1"
            );
            $stmt->execute([
                ':clinic_id' => $clinicId,
                ':email' => $email,
                ':excluded_user_id' => $excludedUserId,
            ]);
            if ($stmt->fetch()) {
                throw new TeamValidationException(
                    'Ce courriel est déjà utilisé dans cette structure.',
                    ['email' => 'Ce courriel est déjà utilisé dans cette structure.']
                );
            }
        }

        if ($phone !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE clinic_id = :clinic_id
                   AND phone = :phone
                   AND id <> :excluded_user_id
                 LIMIT 1"
            );
            $stmt->execute([
                ':clinic_id' => $clinicId,
                ':phone' => $phone,
                ':excluded_user_id' => $excludedUserId,
            ]);
            if ($stmt->fetch()) {
                throw new TeamValidationException(
                    'Ce téléphone est déjà utilisé dans cette structure.',
                    ['phone' => 'Ce téléphone est déjà utilisé dans cette structure.']
                );
            }
        }
    }

    private function assertDoctorsBelongToClinic(
        int $clinicId,
        array $doctorIds
    ): void {
        if ($doctorIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM doctor_profiles
             WHERE clinic_id = ?
               AND id IN ({$placeholders})"
        );
        $stmt->execute([$clinicId, ...$doctorIds]);
        $total = (int) ($stmt->fetch()['total'] ?? 0);

        if ($total !== count($doctorIds)) {
            throw new InvalidArgumentException('Sélection de médecins invalide.');
        }
    }
    private function assertTemporaryPasswordStrength(
        string $password,
        int $minimumLength
    ): void {
        if (mb_strlen($password) < $minimumLength) {
            $message =
                "Le mot de passe temporaire doit contenir au moins {$minimumLength} caractères.";

            throw new TeamValidationException(
                $message,
                ['temporary_password' => $message]
            );
        }

        if (
            preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1
        ) {
            $message =
                'Le mot de passe temporaire doit contenir une majuscule, une minuscule et un chiffre.';

            throw new TeamValidationException(
                $message,
                ['temporary_password' => $message]
            );
        }
    }

}
