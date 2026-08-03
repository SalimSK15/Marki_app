-- ============================================================================
-- MARKI V1 - Comptes administrateurs de la plateforme
-- Remplace l'ancienne cle unique par des comptes email + mot de passe.
-- A executer une seule fois sur une base existante.
-- ============================================================================

USE `markii_db`;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(191) NOT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    password_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_platform_admin_email (email),
    KEY ix_platform_admin_status (status),
    KEY ix_platform_admin_locked_until (locked_until)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_admin_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform_admin_id BIGINT UNSIGNED NOT NULL,
    selector CHAR(24) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_platform_admin_session_selector (selector),
    KEY ix_platform_admin_session_admin (platform_admin_id),
    KEY ix_platform_admin_session_expiry (expires_at, revoked_at),
    CONSTRAINT fk_platform_admin_session_admin
        FOREIGN KEY (platform_admin_id)
        REFERENCES platform_admins (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_admin_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform_admin_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    metadata_json LONGTEXT NULL,
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_platform_admin_log_admin (platform_admin_id),
    KEY ix_platform_admin_log_action (action),
    KEY ix_platform_admin_log_created_at (created_at),
    CONSTRAINT fk_platform_admin_log_admin
        FOREIGN KEY (platform_admin_id)
        REFERENCES platform_admins (id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
