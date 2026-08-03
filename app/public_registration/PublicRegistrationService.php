<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/PatientDataNormalizer.php';
require_once __DIR__ . '/PublicRegistrationRepository.php';

final class PublicRegistrationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly ?string $errorCode = null,
        private readonly array $errors = [],
        private readonly array $data = []
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function data(): array
    {
        return $this->data;
    }
}

final class PublicRegistrationService
{
    private PublicRegistrationRepository $repository;
    private PDO $pdo;

    public function __construct()
    {
        $this->repository = new PublicRegistrationRepository();
        $this->pdo = $this->repository->pdo();
    }

    public function publicContext(
        string $publicId,
        string $token,
        array $config,
        bool $logScan = true
    ): array {
        $link = $this->validatedLink($publicId, $token, $config);
        $timezone = configureClinicTimezone(
            $this->pdo,
            (int) $link['clinic_id'],
            (string) ($config['app']['timezone'] ?? 'UTC')
        );
        $today = (new DateTimeImmutable(
            'now',
            new DateTimeZone($timezone)
        ))->format('Y-m-d');

        $this->repository->ensureDoctorConfiguration(
            (int) $link['clinic_id'],
            (int) $link['doctor_id']
        );
        $settings = $this->repository->settingsForDoctor(
            (int) $link['clinic_id'],
            (int) $link['doctor_id']
        );
        $messages = $this->repository->messagesForDoctor(
            (int) $link['clinic_id'],
            (int) $link['doctor_id']
        );
        $queue = $this->findTodayQueue(
            (int) $link['clinic_id'],
            (int) $link['doctor_id'],
            $today
        );
        $availability = $this->availability(
            $link,
            $settings,
            $messages,
            $queue,
            $today
        );

        if ($logScan) {
            $ipHash = PublicRegistrationSecurity::clientIpHash($config);
            $userAgent = PublicRegistrationSecurity::userAgent();

            $this->repository->touchLastScanned((int) $link['id']);
            $this->repository->logPublicEvent(
                (int) $link['id'],
                'scan',
                $availability['can_register'] ? 'accepted' : $availability['code'],
                $ipHash,
                $userAgent,
                $queue !== null ? (int) $queue['id'] : null,
                null,
                [
                    'doctor_id' => (int) $link['doctor_id'],
                    'availability_code' => $availability['code'],
                ]
            );
        }

        return [
            'clinic' => [
                'name' => (string) $link['clinic_name'],
                'type' => (string) $link['clinic_type'],
                'address' => $link['clinic_address'],
                'city' => $link['clinic_city'],
                'wilaya' => $link['clinic_wilaya'],
                'phone' => $link['clinic_phone'],
            ],
            'doctor' => [
                'name' => (string) $link['doctor_name'],
                'specialty' => $link['doctor_specialty'],
            ],
            'availability' => $availability,
            'requirements' => [
                'phone_required' => true,
                'birth_date_required' =>
                    (bool) $settings['birth_date_required'],
                'privacy_consent_required' => true,
            ],
            'today' => $today,
            'timezone' => $timezone,
        ];
    }

    public function register(array $payload, array $config): array
    {
        $publicId = trim((string) ($payload['link'] ?? ''));
        $token = trim((string) ($payload['token'] ?? ''));
        $link = $this->validatedLink($publicId, $token, $config);
        $timezone = configureClinicTimezone(
            $this->pdo,
            (int) $link['clinic_id'],
            (string) ($config['app']['timezone'] ?? 'UTC')
        );
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
        $today = $now->format('Y-m-d');
        $ipHash = PublicRegistrationSecurity::clientIpHash($config);
        $userAgent = PublicRegistrationSecurity::userAgent();

        $limit = (int) ($config['qr']['rate_limit_attempts'] ?? 20);
        $windowMinutes = (int) ($config['qr']['rate_limit_minutes'] ?? 15);
        $attempts = $this->repository->rateLimitCount(
            (int) $link['id'],
            $ipHash,
            $windowMinutes
        );

        if ($attempts >= $limit) {
            throw new PublicRegistrationException(
                'Trop de tentatives ont été effectuées. Réessayez dans quelques minutes.',
                429,
                'RATE_LIMITED'
            );
        }

        $this->repository->logPublicEvent(
            (int) $link['id'],
            'registration_attempt',
            'started',
            $ipHash,
            $userAgent
        );

        $fullName = PatientDataNormalizer::normalizeName(
            (string) ($payload['full_name'] ?? '')
        );
        $phone = PatientDataNormalizer::normalizePhone(
            (string) ($payload['phone'] ?? '')
        );
        $birthDate = trim((string) ($payload['birth_date'] ?? ''));
        $privacyConsent = filter_var(
            $payload['privacy_consent'] ?? false,
            FILTER_VALIDATE_BOOL
        );
        $allowSharedPhone = filter_var(
            $payload['allow_shared_phone'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $errors = [];
        if ($fullName === '') {
            $errors['full_name'] = 'Le nom complet est obligatoire.';
        } elseif (mb_strlen($fullName, 'UTF-8') > 191) {
            $errors['full_name'] = 'Le nom complet est trop long.';
        }

        if ($phone === '') {
            $errors['phone'] = 'Le numéro de téléphone est obligatoire.';
        } elseif (!PatientDataNormalizer::isValidPhone($phone)) {
            $errors['phone'] =
                PatientDataNormalizer::phoneValidationMessage();
        }

        if ($birthDate !== '' && !$this->isValidBirthDate($birthDate, $today)) {
            $errors['birth_date'] = 'La date de naissance est invalide.';
        }

        if (!$privacyConsent) {
            $errors['privacy_consent'] =
                'Vous devez accepter l’utilisation de vos informations pour vous inscrire.';
        }

        if ($errors !== []) {
            $this->repository->logPublicEvent(
                (int) $link['id'],
                'registration_validation_failed',
                'invalid_input',
                $ipHash,
                $userAgent,
                null,
                null,
                ['fields' => array_keys($errors)]
            );

            throw new PublicRegistrationException(
                'Vérifiez les informations du formulaire.',
                422,
                'VALIDATION_ERROR',
                $errors
            );
        }

        $this->repository->ensureDoctorConfiguration(
            (int) $link['clinic_id'],
            (int) $link['doctor_id']
        );

        $this->pdo->beginTransaction();

        try {
            $lockedLink = $this->lockAndRevalidatePublicLink(
                (int) $link['id'],
                $publicId,
                $token,
                $config
            );
            $link = array_merge($link, $lockedLink);

            $settings = $this->settingsForDoctorForUpdate(
                (int) $link['clinic_id'],
                (int) $link['doctor_id']
            );
            $messages = $this->repository->messagesForDoctor(
                (int) $link['clinic_id'],
                (int) $link['doctor_id']
            );
            $queue = $this->findTodayQueueForUpdate(
                (int) $link['clinic_id'],
                (int) $link['doctor_id'],
                $today
            );
            $availability = $this->availability(
                $link,
                $settings,
                $messages,
                $queue,
                $today
            );

            if (!$availability['can_register']) {
                throw new PublicRegistrationException(
                    $availability['message'],
                    409,
                    strtoupper($availability['code'])
                );
            }

            if ($queue === null) {
                throw new PublicRegistrationException(
                    'La liste du jour n’est pas encore ouverte.',
                    409,
                    'DAY_NOT_OPEN'
                );
            }

            if (
                (bool) $settings['birth_date_required']
                && $birthDate === ''
            ) {
                throw new PublicRegistrationException(
                    'La date de naissance est obligatoire.',
                    422,
                    'VALIDATION_ERROR',
                    ['birth_date' => 'La date de naissance est obligatoire.']
                );
            }

            /*
            |--------------------------------------------------------------
            | Bloquer les doubles inscriptions par identité dans la file
            |--------------------------------------------------------------
            | On vérifie d'abord la file elle-même. Cette protection reste
            | efficace même si d'anciens tests ont créé deux fiches patient
            | portant le même nom et le même téléphone.
            |--------------------------------------------------------------
            */
            $existingEntry = $this->findQueueEntryByIdentityForUpdate(
                (int) $queue['id'],
                (int) $link['clinic_id'],
                $fullName,
                $phone
            );

            if ($existingEntry !== null) {
                $this->validateAndCompleteExistingIdentity(
                    $existingEntry,
                    (int) $link['clinic_id'],
                    $birthDate
                );

                return $this->finishExistingRegistration(
                    $existingEntry,
                    $settings,
                    $link,
                    $queue,
                    $ipHash,
                    $userAgent,
                    $fullName,
                    $phone,
                    $birthDate
                );
            }

            $patient = $this->findExactPatientForUpdate(
                (int) $link['clinic_id'],
                $fullName,
                $phone
            );

            if ($patient !== null) {
                $existingBirthDate = trim(
                    (string) ($patient['birth_date'] ?? '')
                );

                if (
                    $birthDate !== ''
                    && $existingBirthDate !== ''
                    && $birthDate !== $existingBirthDate
                ) {
                    throw new PublicRegistrationException(
                        'Les informations saisies ne correspondent pas à la fiche existante. Contactez le cabinet.',
                        409,
                        'IDENTITY_MISMATCH',
                        ['birth_date' => 'La date de naissance ne correspond pas à la fiche existante.']
                    );
                }

                if ($existingBirthDate === '' && $birthDate !== '') {
                    $updateBirthDateSql = "
                        UPDATE patients
                        SET
                            birth_date = :birth_date,
                            updated_at = NOW()
                        WHERE id = :patient_id
                          AND clinic_id = :clinic_id
                        LIMIT 1
                    ";
                    $updateStmt = $this->pdo->prepare($updateBirthDateSql);
                    $updateStmt->execute([
                        ':birth_date' => $birthDate,
                        ':patient_id' => (int) $patient['id'],
                        ':clinic_id' => (int) $link['clinic_id'],
                    ]);
                    $patient['birth_date'] = $birthDate;
                }
            } else {
                $otherPatient = $this->findOtherPatientWithPhoneForUpdate(
                    (int) $link['clinic_id'],
                    $phone
                );

                if ($otherPatient !== null && !$allowSharedPhone) {
                    throw new PublicRegistrationException(
                        'Ce téléphone est déjà associé à une autre fiche. Il peut s’agir d’un numéro familial. Confirmez pour continuer avec ce patient.',
                        409,
                        'PHONE_SHARED_CONFIRMATION_REQUIRED',
                        [],
                        ['phone' => PatientDataNormalizer::formatPhoneForDisplay($phone)]
                    );
                }

                $patient = $this->createPatient(
                    (int) $link['clinic_id'],
                    $fullName,
                    $phone,
                    $birthDate !== '' ? $birthDate : null
                );
            }

            $existingEntry = $this->findPatientEntryForUpdate(
                (int) $queue['id'],
                (int) $patient['id']
            );

            if ($existingEntry !== null) {
                return $this->finishExistingRegistration(
                    $existingEntry,
                    $settings,
                    $link,
                    $queue,
                    $ipHash,
                    $userAgent,
                    $fullName,
                    $phone,
                    $birthDate
                );
            }

            $this->assertPublicDailyLimit(
                (int) $queue['id'],
                (int) $link['id'],
                $settings['max_public_registrations_per_day']
            );

            $positionNumber = $this->nextPositionNumber((int) $queue['id']);
            $entryId = $this->createQueueEntry(
                (int) $queue['id'],
                (int) $link['clinic_id'],
                (int) $patient['id'],
                (int) $link['id'],
                $fullName,
                $phone,
                $birthDate !== '' ? $birthDate : ($patient['birth_date'] ?? null),
                $positionNumber
            );

            $this->createConsent(
                (int) $link['clinic_id'],
                $entryId,
                $ipHash,
                $userAgent
            );
            $sessionToken = $this->createOrRotatePublicSession(
                $entryId,
                $settings,
                $ipHash,
                $userAgent
            );

            $this->repository->logPublicEvent(
                (int) $link['id'],
                'registered',
                'success',
                $ipHash,
                $userAgent,
                (int) $queue['id'],
                $entryId,
                [
                    'source' => 'qr',
                    'patient_id' => (int) $patient['id'],
                    'position_number' => $positionNumber,
                ]
            );

            $this->pdo->commit();

            return [
                'message' => $messages['registration_success']
                    ?? 'Votre inscription a bien été enregistrée.',
                'session_token' => $sessionToken,
                'already_registered' => false,
                'entry' => [
                    'id' => $entryId,
                    'position_number' => $positionNumber,
                    'status' => 'waiting',
                ],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($exception instanceof PublicRegistrationException) {
                $this->repository->logPublicEvent(
                    (int) $link['id'],
                    'registration_validation_failed',
                    strtolower($exception->errorCode() ?? 'rejected'),
                    $ipHash,
                    $userAgent,
                    null,
                    null,
                    ['message' => $exception->getMessage()]
                );
            }

            throw $exception;
        }
    }

    public function status(string $sessionToken, array $config): array
    {
        $sessionToken = trim($sessionToken);
        if ($sessionToken === '') {
            throw new PublicRegistrationException(
                'Lien de suivi invalide.',
                404,
                'INVALID_SESSION'
            );
        }

        $session = $this->findPublicSession(
            PublicRegistrationSecurity::tokenHash($sessionToken)
        );

        if ($session === null) {
            throw new PublicRegistrationException(
                'Cette inscription est introuvable ou le lien a expiré.',
                404,
                'INVALID_SESSION'
            );
        }

        configureClinicTimezone(
            $this->pdo,
            (int) $session['clinic_id'],
            (string) ($config['app']['timezone'] ?? 'UTC')
        );

        if (
            $session['revoked_at'] !== null
            || strtotime((string) $session['expires_at']) <= time()
        ) {
            throw new PublicRegistrationException(
                'Le lien de suivi a expiré. Contactez le cabinet si nécessaire.',
                410,
                'SESSION_EXPIRED'
            );
        }

        $this->touchPublicSession((int) $session['session_id']);
        $patientsAhead = $this->patientsAhead(
            (int) $session['queue_id'],
            (int) $session['position_number'],
            (string) $session['status']
        );

        $ipHash = PublicRegistrationSecurity::clientIpHash($config);
        $userAgent = PublicRegistrationSecurity::userAgent();
        $this->repository->logPublicEvent(
            (int) $session['public_link_id'],
            'status_view',
            'success',
            $ipHash,
            $userAgent,
            (int) $session['queue_id'],
            (int) $session['queue_entry_id']
        );

        return [
            'patient' => [
                'name' => (string) $session['display_name'],
                'phone' => PublicRegistrationSecurity::maskedPhone(
                    $session['phone']
                ),
            ],
            'clinic' => [
                'name' => (string) $session['clinic_name'],
                'phone' => $session['clinic_phone'],
            ],
            'doctor' => [
                'name' => (string) $session['doctor_name'],
                'specialty' => $session['doctor_specialty'],
            ],
            'registration' => [
                'queue_date' => (string) $session['queue_date'],
                'position_number' => (int) $session['position_number'],
                'status' => (string) $session['status'],
                'status_label' => $this->statusLabel(
                    (string) $session['status']
                ),
                'patients_ahead' => $patientsAhead,
                'created_at' => (string) $session['entry_created_at'],
                'can_cancel' => in_array(
                    (string) $session['status'],
                    ['waiting', 'called', 'no_show'],
                    true
                ),
            ],
            'queue' => [
                'registration_status' =>
                    (string) $session['registration_status'],
                'day_status' => (string) $session['day_status'],
            ],
            'expires_at' => (string) $session['expires_at'],
        ];
    }

    public function cancel(string $sessionToken, array $config): array
    {
        $sessionHash = PublicRegistrationSecurity::tokenHash(
            trim($sessionToken)
        );
        $ipHash = PublicRegistrationSecurity::clientIpHash($config);
        $userAgent = PublicRegistrationSecurity::userAgent();

        $this->pdo->beginTransaction();

        try {
            $session = $this->findPublicSessionForUpdate($sessionHash);

            if ($session === null) {
                throw new PublicRegistrationException(
                    'Cette inscription est introuvable.',
                    404,
                    'INVALID_SESSION'
                );
            }

            configureClinicTimezone(
                $this->pdo,
                (int) $session['clinic_id'],
                (string) ($config['app']['timezone'] ?? 'UTC')
            );

            if (
                $session['revoked_at'] !== null
                || strtotime((string) $session['expires_at']) <= time()
            ) {
                throw new PublicRegistrationException(
                    'Le lien de suivi a expiré.',
                    410,
                    'SESSION_EXPIRED'
                );
            }

            $status = (string) $session['status'];
            if ($status === 'canceled') {
                $this->pdo->commit();

                return [
                    'message' => 'Votre inscription est déjà annulée.',
                    'status' => 'canceled',
                ];
            }

            if (!in_array($status, ['waiting', 'called', 'no_show'], true)) {
                throw new PublicRegistrationException(
                    'Cette inscription ne peut plus être annulée en ligne.',
                    409,
                    'CANCEL_NOT_ALLOWED'
                );
            }

            $sql = "
                UPDATE queue_entries
                SET
                    status = 'canceled',
                    canceled_at = NOW(),
                    cancellation_reason = 'patient_request',
                    updated_by_user_id = NULL
                WHERE id = :entry_id
                  AND clinic_id = :clinic_id
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':entry_id' => (int) $session['queue_entry_id'],
                ':clinic_id' => (int) $session['clinic_id'],
            ]);

            $this->repository->logPublicEvent(
                (int) $session['public_link_id'],
                'registration_canceled',
                'success',
                $ipHash,
                $userAgent,
                (int) $session['queue_id'],
                (int) $session['queue_entry_id'],
                ['previous_status' => $status]
            );

            $this->pdo->commit();

            return [
                'message' => 'Votre inscription a été annulée.',
                'status' => 'canceled',
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function validatedLink(
        string $publicId,
        string $token,
        array $config
    ): array {
        if (
            preg_match('/^[a-f0-9]{32}$/', strtolower($publicId)) !== 1
            || $token === ''
        ) {
            throw new PublicRegistrationException(
                'Ce lien d’inscription est invalide.',
                404,
                'INVALID_PUBLIC_LINK'
            );
        }

        $link = $this->repository->findValidatedLinkRecord($publicId);
        if (
            $link === null
            || !PublicRegistrationSecurity::validateToken(
                $link,
                $token,
                $config
            )
        ) {
            throw new PublicRegistrationException(
                'Ce lien d’inscription est invalide ou a été remplacé.',
                404,
                'INVALID_PUBLIC_LINK'
            );
        }

        return $link;
    }

    private function lockAndRevalidatePublicLink(
        int $linkId,
        string $publicId,
        string $token,
        array $config
    ): array {
        $sql = "
            SELECT
                id,
                public_id,
                clinic_id,
                doctor_id,
                token_hash,
                token_version,
                is_active,
                revoked_at
            FROM public_links
            WHERE id = :id
              AND public_id = :public_id
              AND type = 'qr'
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $linkId,
            ':public_id' => strtolower(trim($publicId)),
        ]);
        $lockedLink = $stmt->fetch();

        if (
            !$lockedLink
            || !PublicRegistrationSecurity::validateToken(
                $lockedLink,
                $token,
                $config
            )
        ) {
            throw new PublicRegistrationException(
                'Ce lien d’inscription a été remplacé. Scannez le nouveau QR du cabinet.',
                409,
                'PUBLIC_LINK_REGENERATED'
            );
        }

        return $lockedLink;
    }

    private function availability(
        array $link,
        array $settings,
        array $messages,
        ?array $queue,
        string $today
    ): array {
        $code = 'registration_open';
        $canRegister = true;

        if (
            (int) $link['is_active'] !== 1
            || (int) $link['doctor_is_active'] !== 1
            || (string) $link['clinic_status'] !== 'active'
            || !(bool) $settings['public_registration_enabled']
            || !(bool) $settings['guest_registration_enabled']
        ) {
            $code = 'qr_disabled';
            $canRegister = false;
        } elseif ($queue === null) {
            $code = 'day_not_open';
            $canRegister = false;
        } elseif ((string) $queue['day_status'] === 'completed') {
            $code = 'day_completed';
            $canRegister = false;
        } elseif ((string) $queue['day_status'] === 'paused') {
            $code = 'queue_paused';
            $canRegister = false;
        } elseif ((string) $queue['registration_status'] !== 'open') {
            $code = 'registration_closed';
            $canRegister = false;
        } elseif (
            (bool) $settings['automatic_schedule_enabled']
            && !$this->isWithinAutomaticSchedule(
                (int) $link['clinic_id'],
                (int) $link['doctor_id'],
                $today
            )
        ) {
            $code = 'outside_schedule';
            $canRegister = false;
        }

        $fallbacks = [
            'day_not_open' =>
                'La liste du jour n’est pas encore ouverte. Veuillez revenir plus tard ou contacter le cabinet.',
            'registration_open' =>
                'Les inscriptions sont ouvertes. Vous pouvez rejoindre la liste d’attente.',
            'registration_closed' =>
                'Les nouvelles inscriptions sont temporairement fermées.',
            'queue_paused' =>
                'La prise en charge est temporairement en pause.',
            'day_completed' =>
                'La liste d’attente est terminée pour aujourd’hui.',
            'qr_disabled' =>
                'L’inscription par QR code est temporairement indisponible.',
            'outside_schedule' =>
                'Les inscriptions en ligne ne sont pas disponibles à cette heure.',
        ];

        return [
            'can_register' => $canRegister,
            'code' => $code,
            'message' => $messages[$code]
                ?? $fallbacks[$code]
                ?? 'L’inscription en ligne n’est pas disponible.',
            'queue' => $queue !== null
                ? [
                    'id' => (int) $queue['id'],
                    'registration_status' =>
                        (string) $queue['registration_status'],
                    'day_status' => (string) $queue['day_status'],
                ]
                : null,
        ];
    }

    private function findTodayQueue(
        int $clinicId,
        int $doctorId,
        string $today
    ): ?array {
        $sql = "
            SELECT
                id,
                clinic_id,
                doctor_id,
                queue_date,
                registration_status,
                day_status,
                status,
                opened_at,
                created_at
            FROM queues
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
              AND queue_date = :queue_date
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':queue_date' => $today,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findTodayQueueForUpdate(
        int $clinicId,
        int $doctorId,
        string $today
    ): ?array {
        $sql = "
            SELECT
                id,
                clinic_id,
                doctor_id,
                queue_date,
                registration_status,
                day_status,
                status,
                opened_at,
                created_at
            FROM queues
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
              AND queue_date = :queue_date
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':queue_date' => $today,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function settingsForDoctorForUpdate(
        int $clinicId,
        int $doctorId
    ): array {
        $sql = "
            SELECT
                public_registration_enabled,
                guest_registration_enabled,
                phone_required,
                birth_date_required,
                privacy_consent_required,
                automatic_schedule_enabled,
                public_sessions_enabled,
                public_session_duration_minutes,
                queue_notifications_enabled,
                max_public_registrations_per_day
            FROM doctor_public_registration_settings
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException(
                'Paramètres d’inscription publique introuvables.'
            );
        }

        return [
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
        ];
    }

    private function assertPublicDailyLimit(
        int $queueId,
        int $publicLinkId,
        ?int $limit
    ): void {
        if ($limit === null || $limit <= 0) {
            return;
        }

        $sql = "
            SELECT COUNT(*) AS total
            FROM queue_entries
            WHERE queue_id = :queue_id
              AND public_link_id = :public_link_id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':public_link_id' => $publicLinkId,
        ]);
        $total = (int) ($stmt->fetch()['total'] ?? 0);

        if ($total >= $limit) {
            throw new PublicRegistrationException(
                'Le nombre maximal d’inscriptions en ligne est atteint pour aujourd’hui.',
                409,
                'PUBLIC_DAILY_LIMIT_REACHED'
            );
        }
    }

    private function findQueueEntryByIdentityForUpdate(
        int $queueId,
        int $clinicId,
        string $fullName,
        string $phone
    ): ?array {
        $sql = "
            SELECT
                qe.id,
                qe.queue_id,
                qe.patient_id,
                qe.position_number,
                qe.status,
                qe.display_name,
                qe.phone,
                qe.birth_date,
                qe.created_at,
                p.birth_date AS patient_birth_date
            FROM queue_entries qe
            LEFT JOIN patients p
              ON p.id = qe.patient_id
             AND p.clinic_id = qe.clinic_id
            WHERE qe.queue_id = :queue_id
              AND qe.clinic_id = :clinic_id
              AND qe.phone = :phone
              AND (
                    qe.display_name = :full_name
                 OR p.full_name = :patient_full_name
              )
            ORDER BY
                CASE
                    WHEN qe.status IN ('waiting', 'called') THEN 0
                    WHEN qe.status IN ('no_show', 'canceled') THEN 1
                    ELSE 2
                END,
                qe.id ASC
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':clinic_id' => $clinicId,
            ':phone' => $phone,
            ':full_name' => $fullName,
            ':patient_full_name' => $fullName,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function validateAndCompleteExistingIdentity(
        array $entry,
        int $clinicId,
        string $birthDate
    ): void {
        $existingBirthDate = trim((string) (
            $entry['patient_birth_date']
            ?? $entry['birth_date']
            ?? ''
        ));

        if (
            $birthDate !== ''
            && $existingBirthDate !== ''
            && $birthDate !== $existingBirthDate
        ) {
            throw new PublicRegistrationException(
                'Les informations saisies ne correspondent pas à l’inscription existante. Contactez le cabinet.',
                409,
                'IDENTITY_MISMATCH',
                ['birth_date' => 'La date de naissance ne correspond pas à l’inscription existante.']
            );
        }

        if (
            $birthDate !== ''
            && $existingBirthDate === ''
            && !empty($entry['patient_id'])
        ) {
            $stmt = $this->pdo->prepare(
                "UPDATE patients
                 SET birth_date = :birth_date, updated_at = NOW()
                 WHERE id = :patient_id
                   AND clinic_id = :clinic_id
                 LIMIT 1"
            );
            $stmt->execute([
                ':birth_date' => $birthDate,
                ':patient_id' => (int) $entry['patient_id'],
                ':clinic_id' => $clinicId,
            ]);
        }
    }

    private function finishExistingRegistration(
        array $entry,
        array $settings,
        array $link,
        array $queue,
        string $ipHash,
        string $userAgent,
        string $fullName,
        string $phone,
        string $birthDate
    ): array {
        $status = (string) $entry['status'];
        $rejoined = in_array($status, ['no_show', 'canceled'], true);
        $positionNumber = (int) $entry['position_number'];

        if ($rejoined) {
            $positionNumber = $this->nextPositionNumber((int) $queue['id']);
            $sql = "
                UPDATE queue_entries
                SET
                    display_name = :display_name,
                    phone = :phone,
                    birth_date = COALESCE(:birth_date, birth_date),
                    source = 'qr',
                    public_link_id = :public_link_id,
                    status = 'waiting',
                    position_number = :position_number,
                    called_at = NULL,
                    done_at = NULL,
                    canceled_at = NULL,
                    cancellation_reason = NULL,
                    no_show_at = NULL,
                    last_rejoined_at = NOW(),
                    updated_by_user_id = NULL
                WHERE id = :entry_id
                  AND queue_id = :queue_id
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':display_name' => $fullName,
                ':phone' => $phone,
                ':birth_date' => $birthDate !== '' ? $birthDate : null,
                ':public_link_id' => (int) $link['id'],
                ':position_number' => $positionNumber,
                ':entry_id' => (int) $entry['id'],
                ':queue_id' => (int) $queue['id'],
            ]);
            $status = 'waiting';
        }

        $sessionToken = $this->createOrRotatePublicSession(
            (int) $entry['id'],
            $settings,
            $ipHash,
            $userAgent
        );

        $resultCode = $rejoined
            ? 'rejoined_existing'
            : 'already_registered';

        $this->repository->logPublicEvent(
            (int) $link['id'],
            'registered',
            $resultCode,
            $ipHash,
            $userAgent,
            (int) $queue['id'],
            (int) $entry['id'],
            [
                'patient_id' => isset($entry['patient_id'])
                    ? (int) $entry['patient_id']
                    : null,
                'status' => $status,
                'position_number' => $positionNumber,
            ]
        );

        $this->pdo->commit();

        if ($rejoined) {
            $message = 'Votre inscription a été réactivée avec un nouveau numéro d’arrivée.';
        } elseif ($status === 'done') {
            $message = 'Votre passage est déjà enregistré comme terminé aujourd’hui.';
        } else {
            $message = 'Une inscription existe déjà pour aujourd’hui. Vous pouvez consulter son état.';
        }

        return [
            'message' => $message,
            'session_token' => $sessionToken,
            'already_registered' => !$rejoined,
            'rejoined' => $rejoined,
            'entry' => [
                'id' => (int) $entry['id'],
                'position_number' => $positionNumber,
                'status' => $status,
            ],
        ];
    }

    private function findExactPatientForUpdate(
        int $clinicId,
        string $fullName,
        string $phone
    ): ?array {
        $sql = "
            SELECT
                id,
                clinic_id,
                full_name,
                phone,
                birth_date
            FROM patients
            WHERE clinic_id = :clinic_id
              AND full_name = :full_name
              AND phone = :phone
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':full_name' => $fullName,
            ':phone' => $phone,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findOtherPatientWithPhoneForUpdate(
        int $clinicId,
        string $phone
    ): ?array {
        $sql = "
            SELECT
                id,
                full_name,
                phone
            FROM patients
            WHERE clinic_id = :clinic_id
              AND phone = :phone
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':phone' => $phone,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function createPatient(
        int $clinicId,
        string $fullName,
        string $phone,
        ?string $birthDate
    ): array {
        $sql = "
            INSERT INTO patients (
                clinic_id,
                full_name,
                birth_date,
                phone,
                created_at,
                updated_at
            ) VALUES (
                :clinic_id,
                :full_name,
                :birth_date,
                :phone,
                NOW(),
                NOW()
            )
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':full_name' => $fullName,
            ':birth_date' => $birthDate,
            ':phone' => $phone,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'clinic_id' => $clinicId,
            'full_name' => $fullName,
            'phone' => $phone,
            'birth_date' => $birthDate,
        ];
    }

    private function findPatientEntryForUpdate(
        int $queueId,
        int $patientId
    ): ?array {
        $sql = "
            SELECT
                id,
                queue_id,
                patient_id,
                position_number,
                status,
                created_at
            FROM queue_entries
            WHERE queue_id = :queue_id
              AND patient_id = :patient_id
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':patient_id' => $patientId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function nextPositionNumber(int $queueId): int
    {
        $sql = "
            SELECT COALESCE(MAX(position_number), 0) + 1 AS next_position
            FROM queue_entries
            WHERE queue_id = :queue_id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':queue_id' => $queueId]);

        return (int) ($stmt->fetch()['next_position'] ?? 1);
    }

    private function createQueueEntry(
        int $queueId,
        int $clinicId,
        int $patientId,
        int $publicLinkId,
        string $fullName,
        string $phone,
        ?string $birthDate,
        int $positionNumber
    ): int {
        $sql = "
            INSERT INTO queue_entries (
                queue_id,
                clinic_id,
                patient_id,
                display_name,
                phone,
                birth_date,
                source,
                public_link_id,
                status,
                position_number,
                created_by_user_id,
                updated_by_user_id,
                created_at
            ) VALUES (
                :queue_id,
                :clinic_id,
                :patient_id,
                :display_name,
                :phone,
                :birth_date,
                'qr',
                :public_link_id,
                'waiting',
                :position_number,
                NULL,
                NULL,
                NOW()
            )
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':clinic_id' => $clinicId,
            ':patient_id' => $patientId,
            ':display_name' => $fullName,
            ':phone' => $phone,
            ':birth_date' => $birthDate,
            ':public_link_id' => $publicLinkId,
            ':position_number' => $positionNumber,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createConsent(
        int $clinicId,
        int $entryId,
        string $ipHash,
        string $userAgent
    ): void {
        $sql = "
            INSERT INTO queue_entry_consents (
                clinic_id,
                queue_entry_id,
                consent_type,
                channel,
                granted,
                policy_version,
                created_ip_hash,
                user_agent,
                consented_at
            ) VALUES (
                :clinic_id,
                :queue_entry_id,
                'privacy',
                'none',
                1,
                'v1.0',
                :created_ip_hash,
                :user_agent,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                granted = 1,
                policy_version = VALUES(policy_version),
                created_ip_hash = VALUES(created_ip_hash),
                user_agent = VALUES(user_agent),
                consented_at = NOW(),
                revoked_at = NULL
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':queue_entry_id' => $entryId,
            ':created_ip_hash' => $ipHash,
            ':user_agent' => $userAgent,
        ]);
    }

    private function createOrRotatePublicSession(
        int $entryId,
        array $settings,
        string $ipHash,
        string $userAgent
    ): string {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = PublicRegistrationSecurity::tokenHash($rawToken);
        $durationMinutes = max(
            30,
            min(
                10080,
                (int) ($settings['public_session_duration_minutes'] ?? 720)
            )
        );
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + ($durationMinutes * 60)
        );

        $sql = "
            INSERT INTO patient_public_sessions (
                queue_entry_id,
                session_token_hash,
                expires_at,
                last_used_at,
                revoked_at,
                created_ip_hash,
                user_agent,
                created_at,
                updated_at
            ) VALUES (
                :queue_entry_id,
                :session_token_hash,
                :expires_at,
                NOW(),
                NULL,
                :created_ip_hash,
                :user_agent,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                session_token_hash = VALUES(session_token_hash),
                expires_at = VALUES(expires_at),
                last_used_at = NOW(),
                revoked_at = NULL,
                created_ip_hash = VALUES(created_ip_hash),
                user_agent = VALUES(user_agent),
                updated_at = NOW()
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_entry_id' => $entryId,
            ':session_token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':created_ip_hash' => $ipHash,
            ':user_agent' => $userAgent,
        ]);

        return $rawToken;
    }

    private function findPublicSession(string $tokenHash): ?array
    {
        $sql = $this->publicSessionSelect() . "
            WHERE pps.session_token_hash = :session_token_hash
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':session_token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findPublicSessionForUpdate(string $tokenHash): ?array
    {
        $sql = $this->publicSessionSelect() . "
            WHERE pps.session_token_hash = :session_token_hash
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':session_token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function publicSessionSelect(): string
    {
        return "
            SELECT
                pps.id AS session_id,
                pps.expires_at,
                pps.last_used_at,
                pps.revoked_at,
                qe.id AS queue_entry_id,
                qe.queue_id,
                qe.clinic_id,
                qe.patient_id,
                qe.public_link_id,
                qe.display_name,
                qe.phone,
                qe.position_number,
                qe.status,
                qe.created_at AS entry_created_at,
                q.queue_date,
                q.registration_status,
                q.day_status,
                c.name AS clinic_name,
                c.phone AS clinic_phone,
                dp.display_name AS doctor_name,
                dp.specialty AS doctor_specialty
            FROM patient_public_sessions pps
            INNER JOIN queue_entries qe
                ON qe.id = pps.queue_entry_id
            INNER JOIN queues q
                ON q.id = qe.queue_id
            INNER JOIN clinics c
                ON c.id = qe.clinic_id
            INNER JOIN doctor_profiles dp
                ON dp.id = q.doctor_id
        ";
    }

    private function touchPublicSession(int $sessionId): void
    {
        $sql = "
            UPDATE patient_public_sessions
            SET
                last_used_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $sessionId]);
    }

    private function patientsAhead(
        int $queueId,
        int $positionNumber,
        string $status
    ): int {
        if (!in_array($status, ['waiting', 'called'], true)) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*) AS total
            FROM queue_entries
            WHERE queue_id = :queue_id
              AND status IN ('waiting', 'called')
              AND position_number < :position_number
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':queue_id' => $queueId,
            ':position_number' => $positionNumber,
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    private function isWithinAutomaticSchedule(
        int $clinicId,
        int $doctorId,
        string $today
    ): bool {
        $now = new DateTimeImmutable('now');
        $dayOfWeek = (int) $now->format('N');
        $currentTime = $now->format('H:i:s');

        $exceptionSql = "
            SELECT
                mode,
                registration_open_time,
                registration_close_time,
                public_message_override
            FROM doctor_public_registration_exceptions
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
              AND exception_date = :exception_date
              AND is_active = 1
            ORDER BY slot_order ASC
        ";
        $exceptionStmt = $this->pdo->prepare($exceptionSql);
        $exceptionStmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':exception_date' => $today,
        ]);
        $exceptions = $exceptionStmt->fetchAll();

        if ($exceptions !== []) {
            foreach ($exceptions as $exception) {
                $mode = strtolower((string) $exception['mode']);
                if (in_array($mode, ['closed', 'unavailable'], true)) {
                    continue;
                }

                if (
                    $exception['registration_open_time'] !== null
                    && $exception['registration_close_time'] !== null
                    && $currentTime >= $exception['registration_open_time']
                    && $currentTime <= $exception['registration_close_time']
                ) {
                    return true;
                }
            }

            return false;
        }

        $hoursSql = "
            SELECT
                registration_open_time,
                registration_close_time
            FROM doctor_public_registration_hours
            WHERE clinic_id = :clinic_id
              AND doctor_id = :doctor_id
              AND day_of_week = :day_of_week
              AND is_active = 1
            ORDER BY slot_order ASC
        ";
        $hoursStmt = $this->pdo->prepare($hoursSql);
        $hoursStmt->execute([
            ':clinic_id' => $clinicId,
            ':doctor_id' => $doctorId,
            ':day_of_week' => $dayOfWeek,
        ]);

        foreach ($hoursStmt->fetchAll() as $slot) {
            if (
                $currentTime >= $slot['registration_open_time']
                && $currentTime <= $slot['registration_close_time']
            ) {
                return true;
            }
        }

        return false;
    }

    private function isValidBirthDate(
        string $birthDate,
        string $today
    ): bool {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);

        if (!$date || $date->format('Y-m-d') !== $birthDate) {
            return false;
        }

        $todayDate = new DateTimeImmutable($today);
        $minimum = $todayDate->modify('-120 years');

        return $date <= $todayDate && $date >= $minimum;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'waiting' => 'En attente',
            'called' => 'Appelé',
            'done' => 'Terminé',
            'no_show' => 'Absent',
            'canceled' => 'Annulé',
            default => 'Inscription enregistrée',
        };
    }
}
