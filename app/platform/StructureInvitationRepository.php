<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/PatientDataNormalizer.php';

final class StructureActivationValidationException extends InvalidArgumentException
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

final class StructureInvitationRepository
{
    private PDO $pdo;
    private array $config;
    private string $invitationSecret;

    public function __construct(?array $config = null)
    {
        $this->pdo = db();
        $this->config = $config ?? require __DIR__ . '/../config.php';
        $this->invitationSecret = trim(
            (string) ($this->config['app']['app_key'] ?? '')
        );

        if (strlen($this->invitationSecret) < 32) {
            throw new RuntimeException(
                'MARKI_APP_KEY doit contenir au moins 32 caractères.'
            );
        }
    }

    public function createInvitation(
        ?string $recipientLabel,
        ?string $recipientEmail,
        int $expiryHours
    ): array {
        $recipientLabel = trim((string) $recipientLabel);
        $recipientEmail = mb_strtolower(
            trim((string) $recipientEmail),
            'UTF-8'
        );

        $errors = [];

        if ($recipientLabel === '') {
            $errors['recipient_label'] = 'Le nom du destinataire est obligatoire.';
        }

        if ($recipientEmail === '') {
            $errors['recipient_email'] = 'Le courriel du destinataire est obligatoire.';
        } elseif (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['recipient_email'] = 'Adresse courriel invalide.';
        }

        if ($errors !== []) {
            throw new StructureActivationValidationException(
                'Renseignez les informations du destinataire.',
                $errors
            );
        }

        $expiryHours = max(1, min(168, $expiryHours));
        $selector = bin2hex(random_bytes(12));
        $validator = $this->validatorForSelector($selector);
        $tokenHash = hash('sha256', $validator);
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + ($expiryHours * 3600)
        );

        $stmt = $this->pdo->prepare(
            "INSERT INTO structure_activation_invitations (
                selector,
                token_hash,
                recipient_label,
                recipient_email,
                expires_at,
                used_at,
                revoked_at,
                created_clinic_id,
                created_user_id,
                created_at
             ) VALUES (
                :selector,
                :token_hash,
                :recipient_label,
                :recipient_email,
                :expires_at,
                NULL,
                NULL,
                NULL,
                NULL,
                NOW()
             )"
        );

        $stmt->execute([
            ':selector' => $selector,
            ':token_hash' => $tokenHash,
            ':recipient_label' => $recipientLabel !== ''
                ? $recipientLabel
                : null,
            ':recipient_email' => $recipientEmail !== ''
                ? $recipientEmail
                : null,
            ':expires_at' => $expiresAt,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'selector' => $selector,
            'validator' => $validator,
            'token' => $selector . '.' . $validator,
            'recipient_label' => $recipientLabel !== ''
                ? $recipientLabel
                : null,
            'recipient_email' => $recipientEmail !== ''
                ? $recipientEmail
                : null,
            'expires_at' => $expiresAt,
        ];
    }

    public function listRecent(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));

        $stmt = $this->pdo->query(
            "SELECT
                sai.id,
                sai.selector,
                sai.token_hash,
                sai.recipient_label,
                sai.recipient_email,
                sai.expires_at,
                sai.used_at,
                sai.revoked_at,
                sai.created_at,
                sai.created_clinic_id,
                sai.created_user_id,
                c.name AS clinic_name,
                c.slug AS clinic_slug,
                u.full_name AS activated_by_name
             FROM structure_activation_invitations sai
             LEFT JOIN clinics c
               ON c.id = sai.created_clinic_id
             LEFT JOIN users u
               ON u.id = sai.created_user_id
             ORDER BY sai.id DESC
             LIMIT {$limit}"
        );

        return array_map(
            function (array $row): array {
                $now = time();
                $expiresAt = strtotime((string) $row['expires_at']);

                if ($row['used_at'] !== null) {
                    $status = 'used';
                } elseif ($row['revoked_at'] !== null) {
                    $status = 'revoked';
                } elseif ($expiresAt !== false && $expiresAt < $now) {
                    $status = 'expired';
                } else {
                    $status = 'active';
                }

                $validator = $this->validatorForSelector(
                    (string) $row['selector']
                );
                $token = hash_equals(
                    (string) $row['token_hash'],
                    hash('sha256', $validator)
                )
                    ? (string) $row['selector'] . '.' . $validator
                    : null;

                return [
                    'id' => (int) $row['id'],
                    'selector' => (string) $row['selector'],
                    'recipient_label' => $row['recipient_label'],
                    'recipient_email' => $row['recipient_email'],
                    'expires_at' => $row['expires_at'],
                    'used_at' => $row['used_at'],
                    'revoked_at' => $row['revoked_at'],
                    'created_at' => $row['created_at'],
                    'created_clinic_id' => $row['created_clinic_id'] !== null
                        ? (int) $row['created_clinic_id']
                        : null,
                    'created_user_id' => $row['created_user_id'] !== null
                        ? (int) $row['created_user_id']
                        : null,
                    'clinic_name' => $row['clinic_name'],
                    'clinic_slug' => $row['clinic_slug'],
                    'activated_by_name' => $row['activated_by_name'],
                    'status' => $status,
                    'token' => $token,
                ];
            },
            $stmt->fetchAll()
        );
    }

    public function findValidInvitation(string $token): ?array
    {
        [$selector, $validator] = $this->splitToken($token);

        if ($selector === null || $validator === null) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                id,
                selector,
                token_hash,
                recipient_label,
                recipient_email,
                expires_at,
                used_at,
                revoked_at,
                created_at
             FROM structure_activation_invitations
             WHERE selector = :selector
             LIMIT 1"
        );
        $stmt->execute([':selector' => $selector]);
        $row = $stmt->fetch();

        if (!$row || !$this->invitationRowIsValid($row, $validator)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'selector' => (string) $row['selector'],
            'recipient_label' => $row['recipient_label'],
            'recipient_email' => $row['recipient_email'],
            'expires_at' => $row['expires_at'],
            'created_at' => $row['created_at'],
        ];
    }

    public function revokeInvitation(int $invitationId): void
    {
        if ($invitationId <= 0) {
            throw new InvalidArgumentException('Invitation invalide.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE structure_activation_invitations
             SET revoked_at = NOW()
             WHERE id = :id
               AND used_at IS NULL
               AND revoked_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':id' => $invitationId]);
    }

    public function activateStructure(
        string $token,
        array $input,
        int $minimumPasswordLength
    ): array {
        $data = $this->validateActivationInput(
            $input,
            $minimumPasswordLength
        );

        [$selector, $validator] = $this->splitToken($token);

        if ($selector === null || $validator === null) {
            throw new StructureActivationValidationException(
                'Ce lien d’activation est invalide ou a expiré.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $invitationStmt = $this->pdo->prepare(
                "SELECT *
                 FROM structure_activation_invitations
                 WHERE selector = :selector
                 LIMIT 1
                 FOR UPDATE"
            );
            $invitationStmt->execute([':selector' => $selector]);
            $invitation = $invitationStmt->fetch();

            if (
                !$invitation
                || !$this->invitationRowIsValid($invitation, $validator)
            ) {
                throw new StructureActivationValidationException(
                    'Ce lien d’activation est invalide, expiré ou déjà utilisé.'
                );
            }

            $slug = $this->generateUniqueSlug($data['clinic_name']);
            $clinicId = $this->createClinic($data, $slug);
            $userId = $this->createFirstAdminUser($clinicId, $data);
            $this->assignRole($userId, 'clinic_admin');
            $this->assignRole($userId, 'doctor');
            $doctorId = $this->createDoctorProfile(
                $clinicId,
                $userId,
                $data
            );

            $updateInvitationStmt = $this->pdo->prepare(
                "UPDATE structure_activation_invitations
                 SET used_at = NOW(),
                     created_clinic_id = :clinic_id,
                     created_user_id = :user_id
                 WHERE id = :id
                 LIMIT 1"
            );
            $updateInvitationStmt->execute([
                ':clinic_id' => $clinicId,
                ':user_id' => $userId,
                ':id' => (int) $invitation['id'],
            ]);

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
                    'STRUCTURE_ACTIVATED',
                    'clinic',
                    :entity_id,
                    :metadata_json,
                    NOW()
                 )"
            );
            $logStmt->execute([
                ':clinic_id' => $clinicId,
                ':actor_user_id' => $userId,
                ':entity_id' => $clinicId,
                ':metadata_json' => json_encode([
                    'clinic_type' => $data['clinic_type'],
                    'clinic_slug' => $slug,
                    'first_admin_user_id' => $userId,
                    'first_doctor_id' => $doctorId,
                    'invitation_id' => (int) $invitation['id'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

            return [
                'clinic_id' => $clinicId,
                'clinic_slug' => $slug,
                'user_id' => $userId,
                'doctor_id' => $doctorId,
                'full_name' => $data['full_name'],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function validateActivationInput(
        array $input,
        int $minimumPasswordLength
    ): array {
        $errors = [];

        $clinicName = trim((string) ($input['clinic_name'] ?? ''));
        $clinicType = trim((string) ($input['clinic_type'] ?? 'solo'));
        $clinicPhone = trim((string) ($input['clinic_phone'] ?? ''));
        $clinicAddress = trim((string) ($input['clinic_address'] ?? ''));
        $clinicCity = trim((string) ($input['clinic_city'] ?? ''));
        $clinicWilaya = trim((string) ($input['clinic_wilaya'] ?? ''));
        $clinicTimezone = trim(
            (string) ($input['clinic_timezone'] ?? 'Africa/Algiers')
        );

        $fullName = PatientDataNormalizer::normalizeName(
            (string) ($input['full_name'] ?? '')
        );
        $email = mb_strtolower(
            trim((string) ($input['email'] ?? '')),
            'UTF-8'
        );
        $rawPhone = trim((string) ($input['phone'] ?? ''));
        $phone = $rawPhone !== ''
            ? PatientDataNormalizer::normalizePhone($rawPhone)
            : null;
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) (
            $input['password_confirmation'] ?? ''
        );
        $doctorDisplayName = PatientDataNormalizer::normalizeName(
            (string) ($input['doctor_display_name'] ?? $fullName)
        );
        $doctorSpecialty = trim(
            (string) ($input['doctor_specialty'] ?? '')
        );
        $doctorLicenseNumber = trim(
            (string) ($input['doctor_license_number'] ?? '')
        );
        $doctorAddress = trim(
            (string) ($input['doctor_address'] ?? '')
        );

        if ($clinicName === '') {
            $errors['clinic_name'] = 'Le nom du cabinet ou de la clinique est obligatoire.';
        }

        if (!in_array($clinicType, ['solo', 'clinic'], true)) {
            $errors['clinic_type'] = 'Type de structure invalide.';
        }

        if (
            $clinicTimezone === ''
            || !in_array(
                $clinicTimezone,
                timezone_identifiers_list(),
                true
            )
        ) {
            $errors['clinic_timezone'] = 'Fuseau horaire invalide.';
        }

        if ($fullName === '') {
            $errors['full_name'] = 'Le nom complet est obligatoire.';
        }

        if ($email === '' && $phone === null) {
            $errors['email'] = 'Saisissez un courriel ou un téléphone.';
            $errors['phone'] = 'Saisissez un courriel ou un téléphone.';
        }

        if (
            $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Adresse courriel invalide.';
        }

        if (
            $phone !== null
            && !PatientDataNormalizer::isValidPhone($phone)
        ) {
            $errors['phone'] = PatientDataNormalizer::phoneValidationMessage();
        }

        if (mb_strlen($password) < $minimumPasswordLength) {
            $errors['password'] =
                "Le mot de passe doit contenir au moins {$minimumPasswordLength} caractères.";
        } elseif (
            preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1
        ) {
            $errors['password'] =
                'Ajoutez au moins une majuscule, une minuscule et un chiffre.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] =
                'Les deux mots de passe ne correspondent pas.';
        }

        if ($doctorDisplayName === '') {
            $errors['doctor_display_name'] =
                'Le nom affiché du médecin est obligatoire.';
        }

        if ($errors !== []) {
            throw new StructureActivationValidationException(
                'Vérifiez les champs du formulaire.',
                $errors
            );
        }

        return [
            'clinic_name' => $clinicName,
            'clinic_type' => $clinicType,
            'clinic_phone' => $clinicPhone !== '' ? $clinicPhone : null,
            'clinic_address' => $clinicAddress !== '' ? $clinicAddress : null,
            'clinic_city' => $clinicCity !== '' ? $clinicCity : null,
            'clinic_wilaya' => $clinicWilaya !== '' ? $clinicWilaya : null,
            'clinic_timezone' => $clinicTimezone,
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'password' => $password,
            'doctor_display_name' => $doctorDisplayName,
            'doctor_specialty' => $doctorSpecialty !== ''
                ? $doctorSpecialty
                : null,
            'doctor_license_number' => $doctorLicenseNumber !== ''
                ? $doctorLicenseNumber
                : null,
            'doctor_address' => $doctorAddress !== ''
                ? $doctorAddress
                : ($clinicAddress !== '' ? $clinicAddress : null),
        ];
    }

    private function createClinic(array $data, string $slug): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO clinics (
                name,
                slug,
                type,
                address,
                city,
                wilaya,
                phone,
                timezone,
                status,
                created_at,
                updated_at
             ) VALUES (
                :name,
                :slug,
                :type,
                :address,
                :city,
                :wilaya,
                :phone,
                :timezone,
                'active',
                NOW(),
                NOW()
             )"
        );

        $stmt->execute([
            ':name' => $data['clinic_name'],
            ':slug' => $slug,
            ':type' => $data['clinic_type'],
            ':address' => $data['clinic_address'],
            ':city' => $data['clinic_city'],
            ':wilaya' => $data['clinic_wilaya'],
            ':phone' => $data['clinic_phone'],
            ':timezone' => $data['clinic_timezone'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createFirstAdminUser(int $clinicId, array $data): int
    {
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
                0,
                NOW(),
                0,
                NULL,
                NOW(),
                NOW()
             )"
        );

        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':password_hash' => password_hash(
                $data['password'],
                PASSWORD_DEFAULT
            ),
            ':full_name' => $data['full_name'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assignRole(int $userId, string $roleCode): void
    {
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

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                "Le rôle {$roleCode} est introuvable."
            );
        }
    }

    private function createDoctorProfile(
        int $clinicId,
        int $userId,
        array $data
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
                :address,
                1,
                NOW(),
                NOW()
             )"
        );

        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':user_id' => $userId,
            ':display_name' => $data['doctor_display_name'],
            ':specialty' => $data['doctor_specialty'],
            ':license_number' => $data['doctor_license_number'],
            ':address' => $data['doctor_address'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function generateUniqueSlug(string $clinicName): string
    {
        $source = $clinicName;

        if (function_exists('iconv')) {
            $converted = iconv(
                'UTF-8',
                'ASCII//TRANSLIT//IGNORE',
                $source
            );

            if ($converted !== false) {
                $source = $converted;
            }
        }

        $base = strtolower($source);
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'structure';
        }

        $base = substr($base, 0, 90);
        $slug = $base;
        $suffix = 2;

        $stmt = $this->pdo->prepare(
            'SELECT id FROM clinics WHERE slug = :slug LIMIT 1'
        );

        while (true) {
            $stmt->execute([':slug' => $slug]);

            if (!$stmt->fetch()) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    private function invitationRowIsValid(
        array $row,
        string $validator
    ): bool {
        if (
            $row['used_at'] !== null
            || $row['revoked_at'] !== null
        ) {
            return false;
        }

        $expiresAt = strtotime((string) $row['expires_at']);

        if ($expiresAt === false || $expiresAt < time()) {
            return false;
        }

        return hash_equals(
            (string) $row['token_hash'],
            hash('sha256', $validator)
        );
    }


    private function validatorForSelector(string $selector): string
    {
        return hash_hmac(
            'sha256',
            'structure-invitation:' . strtolower(trim($selector)),
            $this->invitationSecret
        );
    }

    private function splitToken(string $token): array
    {
        $token = trim($token);

        if (!str_contains($token, '.')) {
            return [null, null];
        }

        [$selector, $validator] = explode('.', $token, 2);

        if (
            preg_match('/^[a-f0-9]{24}$/', $selector) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $validator) !== 1
        ) {
            return [null, null];
        }

        return [$selector, $validator];
    }
}
