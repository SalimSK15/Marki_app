-- =========================================================
-- MARKI V1 — Authentification, sessions et comptes utilisateurs
-- À exécuter une seule fois sur la base markii_db actuelle.
-- =========================================================


-- Uniformiser les mobiles algériens déjà présents.
UPDATE patients
SET phone = CONCAT('+213', SUBSTRING(REGEXP_REPLACE(phone, '[^0-9]', ''), 2))
WHERE REGEXP_REPLACE(phone, '[^0-9]', '') REGEXP '^0[567][0-9]{8}$';

UPDATE queue_entries
SET phone = CONCAT('+213', SUBSTRING(REGEXP_REPLACE(phone, '[^0-9]', ''), 2))
WHERE REGEXP_REPLACE(phone, '[^0-9]', '') REGEXP '^0[567][0-9]{8}$';

UPDATE users
SET phone = CONCAT('+213', SUBSTRING(REGEXP_REPLACE(phone, '[^0-9]', ''), 2))
WHERE REGEXP_REPLACE(phone, '[^0-9]', '') REGEXP '^0[567][0-9]{8}$';

ALTER TABLE clinics
    ADD COLUMN slug VARCHAR(120) NULL AFTER name;

UPDATE clinics
SET slug = CASE
    WHEN id = 1 THEN 'cabinet-el-amal'
    ELSE CONCAT('structure-', id)
END
WHERE slug IS NULL OR TRIM(slug) = '';

ALTER TABLE clinics
    MODIFY slug VARCHAR(120) NOT NULL,
    ADD UNIQUE KEY ux_clinics_slug (slug);

ALTER TABLE users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0
        AFTER last_login_at,
    ADD COLUMN password_changed_at DATETIME NULL
        AFTER must_change_password,
    ADD COLUMN failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0
        AFTER password_changed_at,
    ADD COLUMN locked_until DATETIME NULL
        AFTER failed_login_attempts;

UPDATE users
SET password_changed_at = COALESCE(password_changed_at, updated_at, created_at)
WHERE password_changed_at IS NULL;

CREATE TABLE user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    clinic_id BIGINT UNSIGNED NOT NULL,
    selected_doctor_id BIGINT UNSIGNED NULL,
    selector VARCHAR(32) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    user_agent_hash CHAR(64) NULL,
    ip_hash CHAR(64) NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_user_sessions_selector (selector),
    KEY ix_user_sessions_user_active (user_id, revoked_at, expires_at),
    KEY ix_user_sessions_expiration (expires_at, revoked_at),
    KEY ix_user_sessions_clinic (clinic_id),
    KEY ix_user_sessions_doctor (selected_doctor_id),
    CONSTRAINT fk_user_sessions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_sessions_clinic
        FOREIGN KEY (clinic_id) REFERENCES clinics (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_sessions_doctor
        FOREIGN KEY (selected_doctor_id) REFERENCES doctor_profiles (id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    clinic_id BIGINT UNSIGNED NOT NULL,
    selector VARCHAR(32) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_password_reset_selector (selector),
    KEY ix_password_reset_user (user_id, used_at, expires_at),
    KEY ix_password_reset_expiration (expires_at, used_at),
    KEY ix_password_reset_clinic (clinic_id),
    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_password_reset_clinic
        FOREIGN KEY (clinic_id) REFERENCES clinics (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

