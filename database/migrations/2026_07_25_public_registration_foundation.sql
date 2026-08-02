-- ============================================================================
-- MARKI - Fondation de l'inscription publique par QR code
-- Version : 1.0
-- Cible   : MySQL 8.4+
--
-- IMPORTANT
-- - Sélectionner la base MARKI dans phpMyAdmin avant d'exécuter ce fichier.
-- - Faire une sauvegarde complète avant l'exécution.
-- - Ce script ne modifie pas la logique actuelle de la page "Liste du jour".
-- - La table `queues` n'est volontairement pas modifiée.
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 1. Vérifications préalables
-- ============================================================================

DROP PROCEDURE IF EXISTS marki_assert_public_registration_foundation;

DELIMITER $$

CREATE PROCEDURE marki_assert_public_registration_foundation()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM public_links
        GROUP BY token_hash
        HAVING COUNT(*) > 1
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Migration arrêtée : des token_hash dupliqués existent dans public_links.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM queue_entries
        WHERE position_number IS NOT NULL
        GROUP BY queue_id, position_number
        HAVING COUNT(*) > 1
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Migration arrêtée : des positions dupliquées existent dans une file.';
    END IF;
END$$

DELIMITER ;

CALL marki_assert_public_registration_foundation();
DROP PROCEDURE marki_assert_public_registration_foundation;

-- ============================================================================
-- 2. Renforcer les liens publics et les QR permanents
-- ============================================================================

ALTER TABLE public_links
    ADD COLUMN public_id CHAR(32) NULL AFTER id,
    ADD COLUMN token_version SMALLINT UNSIGNED NOT NULL DEFAULT 1
        AFTER token_hash,
    ADD COLUMN activated_at DATETIME NULL AFTER is_active,
    ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL
        AFTER activated_at,
    ADD COLUMN last_scanned_at DATETIME NULL
        AFTER deactivated_by_user_id,
    ADD COLUMN revoked_at DATETIME NULL
        AFTER last_scanned_at,
    ADD COLUMN revoked_by_user_id BIGINT UNSIGNED NULL
        AFTER revoked_at;

UPDATE public_links
SET public_id = LOWER(REPLACE(UUID(), '-', ''))
WHERE public_id IS NULL;

UPDATE public_links
SET activated_at = created_at
WHERE is_active = 1
  AND activated_at IS NULL;

ALTER TABLE public_links
    MODIFY COLUMN public_id CHAR(32) NOT NULL,
    ADD UNIQUE KEY ux_pl_public_id (public_id),
    ADD UNIQUE KEY ux_pl_token_hash (token_hash),
    ADD KEY ix_pl_doctor_active (doctor_id, is_active),
    ADD KEY fk_pl_created_by (created_by_user_id),
    ADD KEY fk_pl_revoked_by (revoked_by_user_id),
    ADD CONSTRAINT fk_pl_created_by
        FOREIGN KEY (created_by_user_id)
        REFERENCES users (id)
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_pl_revoked_by
        FOREIGN KEY (revoked_by_user_id)
        REFERENCES users (id)
        ON DELETE SET NULL;

-- ============================================================================
-- 3. Relier une inscription au QR ou au lien public réellement utilisé
-- ============================================================================

ALTER TABLE queue_entries
    ADD COLUMN public_link_id BIGINT UNSIGNED NULL AFTER source,
    ADD UNIQUE KEY ux_qe_queue_position (queue_id, position_number),
    ADD KEY ix_qe_public_link (public_link_id),
    ADD CONSTRAINT fk_qe_public_link
        FOREIGN KEY (public_link_id)
        REFERENCES public_links (id)
        ON DELETE SET NULL;

-- ============================================================================
-- 4. Enrichir la journalisation des liens publics
-- ============================================================================

ALTER TABLE public_link_events
    MODIFY COLUMN event_type VARCHAR(50)
        COLLATE utf8mb4_unicode_ci NOT NULL,
    ADD COLUMN queue_id BIGINT UNSIGNED NULL AFTER public_link_id,
    ADD COLUMN queue_entry_id BIGINT UNSIGNED NULL AFTER queue_id,
    ADD COLUMN result_code VARCHAR(50)
        COLLATE utf8mb4_unicode_ci NULL AFTER event_type,
    ADD COLUMN metadata_json JSON NULL AFTER result_code,
    ADD KEY ix_ple_link_ip_time (
        public_link_id,
        ip_hash,
        created_at
    ),
    ADD KEY ix_ple_result_time (
        result_code,
        created_at
    ),
    ADD KEY fk_ple_queue (queue_id),
    ADD KEY fk_ple_queue_entry (queue_entry_id),
    ADD CONSTRAINT fk_ple_queue
        FOREIGN KEY (queue_id)
        REFERENCES queues (id)
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_ple_queue_entry
        FOREIGN KEY (queue_entry_id)
        REFERENCES queue_entries (id)
        ON DELETE SET NULL;

-- ============================================================================
-- 5. Renforcer les sessions publiques temporaires des patients
-- ============================================================================

ALTER TABLE patient_public_sessions
    ADD COLUMN last_used_at DATETIME NULL AFTER expires_at,
    ADD COLUMN revoked_at DATETIME NULL AFTER last_used_at,
    ADD COLUMN created_ip_hash CHAR(64)
        COLLATE utf8mb4_unicode_ci NULL AFTER revoked_at,
    ADD COLUMN user_agent VARCHAR(255)
        COLLATE utf8mb4_unicode_ci NULL AFTER created_ip_hash,
    ADD COLUMN updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at,
    ADD KEY ix_pps_expiration (
        expires_at,
        revoked_at
    );

-- ============================================================================
-- 6. Paramètres généraux de l'inscription publique par médecin
-- ============================================================================

CREATE TABLE doctor_public_registration_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,

    public_registration_enabled TINYINT(1) NOT NULL DEFAULT 1,
    guest_registration_enabled TINYINT(1) NOT NULL DEFAULT 1,

    phone_required TINYINT(1) NOT NULL DEFAULT 1,
    birth_date_required TINYINT(1) NOT NULL DEFAULT 0,
    privacy_consent_required TINYINT(1) NOT NULL DEFAULT 1,

    automatic_schedule_enabled TINYINT(1) NOT NULL DEFAULT 0,

    public_sessions_enabled TINYINT(1) NOT NULL DEFAULT 1,
    public_session_duration_minutes INT UNSIGNED NOT NULL DEFAULT 720,

    queue_notifications_enabled TINYINT(1) NOT NULL DEFAULT 0,
    max_public_registrations_per_day INT UNSIGNED NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY ux_dprs_doctor (doctor_id),
    KEY ix_dprs_clinic (clinic_id),

    CONSTRAINT fk_dprs_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_dprs_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctor_profiles (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Créer les paramètres par défaut pour les médecins déjà présents.
INSERT INTO doctor_public_registration_settings (
    clinic_id,
    doctor_id
)
SELECT
    clinic_id,
    id
FROM doctor_profiles;

-- ============================================================================
-- 7. Horaires réguliers futurs des inscriptions publiques
--
-- day_of_week :
-- 1 = lundi, 2 = mardi, 3 = mercredi, 4 = jeudi,
-- 5 = vendredi, 6 = samedi, 7 = dimanche.
--
-- automatic_schedule_enabled reste à 0 par défaut.
-- Ces horaires ne modifient donc pas la Liste du jour actuelle.
-- ============================================================================

CREATE TABLE doctor_public_registration_hours (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,

    day_of_week TINYINT UNSIGNED NOT NULL,
    slot_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    registration_open_time TIME NOT NULL,
    registration_close_time TIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY ux_dprh_doctor_day_slot (
        doctor_id,
        day_of_week,
        slot_order
    ),
    KEY ix_dprh_clinic (clinic_id),
    KEY ix_dprh_doctor_active (
        doctor_id,
        is_active
    ),

    CONSTRAINT chk_dprh_day_of_week
        CHECK (day_of_week BETWEEN 1 AND 7),

    CONSTRAINT fk_dprh_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_dprh_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctor_profiles (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. Exceptions futures aux horaires réguliers
-- Exemple : congé, fermeture exceptionnelle ou horaire spécial.
-- Cette table n'est pas utilisée par la V1 déléguée.
-- ============================================================================

CREATE TABLE doctor_public_registration_exceptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,

    exception_date DATE NOT NULL,
    mode VARCHAR(30)
        COLLATE utf8mb4_unicode_ci NOT NULL,
    slot_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    registration_open_time TIME NULL,
    registration_close_time TIME NULL,

    reason_code VARCHAR(50)
        COLLATE utf8mb4_unicode_ci NULL,
    public_message_override VARCHAR(500)
        COLLATE utf8mb4_unicode_ci NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY ux_dpre_doctor_date_slot (
        doctor_id,
        exception_date,
        slot_order
    ),
    KEY ix_dpre_clinic_date (
        clinic_id,
        exception_date
    ),
    KEY ix_dpre_doctor_date (
        doctor_id,
        exception_date,
        is_active
    ),

    CONSTRAINT fk_dpre_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_dpre_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctor_profiles (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. Messages publics standards personnalisables par médecin
--
-- La page publique choisit le message selon l'état réel de `queues`.
-- Aucun motif ni heure estimée n'est demandé dans la Liste du jour actuelle.
-- ============================================================================

CREATE TABLE doctor_public_registration_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,

    message_code VARCHAR(50)
        COLLATE utf8mb4_unicode_ci NOT NULL,
    message_text VARCHAR(1000)
        COLLATE utf8mb4_unicode_ci NOT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY ux_dprm_doctor_code (
        doctor_id,
        message_code
    ),
    KEY ix_dprm_clinic (clinic_id),

    CONSTRAINT fk_dprm_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_dprm_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctor_profiles (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Messages généraux : aucun motif précis ni heure estimée.
INSERT INTO doctor_public_registration_messages (
    clinic_id,
    doctor_id,
    message_code,
    message_text
)
SELECT
    dp.clinic_id,
    dp.id,
    defaults.message_code,
    defaults.message_text
FROM doctor_profiles AS dp
CROSS JOIN (
    SELECT
        'registration_open' AS message_code,
        'Les inscriptions sont ouvertes. Vous pouvez rejoindre la liste d’attente.' AS message_text

    UNION ALL

    SELECT
        'registration_closed',
        'Les nouvelles inscriptions sont temporairement fermées. Veuillez revenir plus tard ou contacter le cabinet.'

    UNION ALL

    SELECT
        'queue_paused',
        'La prise en charge est temporairement en pause. Les nouvelles inscriptions ne sont pas disponibles pour le moment.'

    UNION ALL

    SELECT
        'day_completed',
        'La liste d’attente est terminée pour aujourd’hui. Veuillez revenir lors de la prochaine journée d’ouverture.'

    UNION ALL

    SELECT
        'qr_disabled',
        'L’inscription par QR code est temporairement indisponible pour ce médecin.'

    UNION ALL

    SELECT
        'outside_schedule',
        'Les inscriptions en ligne ne sont pas disponibles à cette heure.'

    UNION ALL

    SELECT
        'registration_success',
        'Votre inscription à la liste d’attente a bien été enregistrée.'
) AS defaults;

-- ============================================================================
-- 10. Consentements liés à une inscription
-- ============================================================================

CREATE TABLE queue_entry_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clinic_id BIGINT UNSIGNED NOT NULL,
    queue_entry_id BIGINT UNSIGNED NOT NULL,

    consent_type VARCHAR(50)
        COLLATE utf8mb4_unicode_ci NOT NULL,
    channel VARCHAR(30)
        COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',

    granted TINYINT(1) NOT NULL,
    policy_version VARCHAR(30)
        COLLATE utf8mb4_unicode_ci NULL,

    created_ip_hash CHAR(64)
        COLLATE utf8mb4_unicode_ci NULL,
    user_agent VARCHAR(255)
        COLLATE utf8mb4_unicode_ci NULL,

    consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY ux_qec_entry_type_channel (
        queue_entry_id,
        consent_type,
        channel
    ),
    KEY ix_qec_clinic_time (
        clinic_id,
        consented_at
    ),

    CONSTRAINT fk_qec_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_qec_queue_entry
        FOREIGN KEY (queue_entry_id)
        REFERENCES queue_entries (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. Contrôles finaux
-- ============================================================================

SELECT
    'Migration terminée' AS result,
    COUNT(*) AS doctor_settings_created
FROM doctor_public_registration_settings;

SELECT
    message_code,
    COUNT(*) AS doctors_configured
FROM doctor_public_registration_messages
GROUP BY message_code
ORDER BY message_code;
