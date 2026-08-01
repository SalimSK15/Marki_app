-- =========================================================
-- MARKI V1 — Invitations d'activation d'une nouvelle structure
-- À exécuter une seule fois après la migration d'authentification V1.
-- =========================================================

CREATE TABLE structure_activation_invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    recipient_label VARCHAR(191) NULL,
    recipient_email VARCHAR(191) NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_clinic_id BIGINT UNSIGNED NULL,
    created_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_structure_invitation_selector (selector),
    KEY ix_structure_invitation_state (used_at, revoked_at, expires_at),
    KEY ix_structure_invitation_clinic (created_clinic_id),
    KEY ix_structure_invitation_user (created_user_id),
    CONSTRAINT fk_structure_invitation_clinic
        FOREIGN KEY (created_clinic_id)
        REFERENCES clinics (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_structure_invitation_user
        FOREIGN KEY (created_user_id)
        REFERENCES users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
