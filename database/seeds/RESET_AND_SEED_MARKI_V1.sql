-- ============================================================================
-- MARKI - Jeu de données réaliste V1
-- ATTENTION : ce fichier vide les données de démonstration puis les recrée.
-- Il exige la structure la plus récente de MARKI.
-- ============================================================================
-- Comptes après import :
-- admin@marki.test / Marki2026!        (médecin administrateur)
-- leila@marki.test / Marki2026!        (médecin)
-- amina@marki.test / Marki2026!        (secrétariat accès étendu, 2 médecins)
-- nadia@marki.test / Marki2026!        (liste, patients et historique)
-- samira@marki.test / Marki2026!       (liste du jour seulement)
-- temporaire@marki.test / Temporaire1! (changement obligatoire)
-- Code de structure : clinique-el-amal
-- ============================================================================

USE `markii_db`;
SET NAMES utf8mb4;
SET time_zone = '+01:00';
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Vérifier que les migrations indispensables sont présentes.
DROP PROCEDURE IF EXISTS marki_assert_latest_demo_schema;
DELIMITER $$
CREATE PROCEDURE marki_assert_latest_demo_schema()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'queue_entries'
          AND column_name = 'last_rejoined_at'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Structure trop ancienne : last_rejoined_at manque dans queue_entries.';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'queue_entries'
          AND column_name = 'public_link_id'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Structure trop ancienne : la fondation QR manque.';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'visits'
          AND column_name = 'completed_by_user_id'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Structure trop ancienne : les colonnes audit de visits manquent.';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'clinics'
          AND column_name = 'slug'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La migration d’authentification V1 doit être exécutée avant ce jeu de données.';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'user_sessions'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La table user_sessions manque. Exécutez la migration d’authentification V1.';
    END IF;


    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'structure_activation_invitations'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La migration des invitations de structure V1 doit être exécutée avant ce jeu de données.';
    END IF;
END$$
DELIMITER ;
CALL marki_assert_latest_demo_schema();
DROP PROCEDURE marki_assert_latest_demo_schema;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE structure_activation_invitations;
TRUNCATE TABLE password_reset_tokens;
TRUNCATE TABLE user_sessions;
TRUNCATE TABLE queue_entry_consents;
TRUNCATE TABLE patient_public_sessions;
TRUNCATE TABLE public_link_events;
TRUNCATE TABLE doctor_public_registration_messages;
TRUNCATE TABLE doctor_public_registration_exceptions;
TRUNCATE TABLE doctor_public_registration_hours;
TRUNCATE TABLE doctor_public_registration_settings;
TRUNCATE TABLE notifications;
TRUNCATE TABLE medical_records;
TRUNCATE TABLE prescription_items;
TRUNCATE TABLE prescriptions;
TRUNCATE TABLE files;
TRUNCATE TABLE billing_events;
TRUNCATE TABLE invoice_items;
TRUNCATE TABLE invoices;
TRUNCATE TABLE billing_accounts;
TRUNCATE TABLE appointments;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE visits;
TRUNCATE TABLE queue_entries;
TRUNCATE TABLE queues;
TRUNCATE TABLE public_links;
TRUNCATE TABLE patient_contacts;
TRUNCATE TABLE staff_doctor_access;
TRUNCATE TABLE staff_profiles;
TRUNCATE TABLE user_roles;
TRUNCATE TABLE doctor_profiles;
TRUNCATE TABLE patients;
TRUNCATE TABLE users;
TRUNCATE TABLE roles;
TRUNCATE TABLE clinics;
SET FOREIGN_KEY_CHECKS = 1;

-- Une même fiche patient ne peut apparaître qu’une seule fois dans une file.
-- L’index est ajouté après le nettoyage des données afin d’éviter les doublons
-- lors d’inscriptions QR ou d’ajouts simultanés par le secrétariat.
SET @marki_has_queue_patient_unique = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'queue_entries'
      AND index_name = 'ux_qe_queue_patient'
);
SET @marki_add_queue_patient_unique = IF(
    @marki_has_queue_patient_unique = 0,
    'ALTER TABLE queue_entries ADD UNIQUE KEY ux_qe_queue_patient (queue_id, patient_id)',
    'SELECT 1'
);
PREPARE marki_unique_stmt FROM @marki_add_queue_patient_unique;
EXECUTE marki_unique_stmt;
DEALLOCATE PREPARE marki_unique_stmt;

START TRANSACTION;

INSERT INTO clinics (
    id, name, slug, type, address, city, wilaya, phone, timezone, status, created_at, updated_at
) VALUES (
    1,
    'Clinique Médicale El Amal',
    'clinique-el-amal',
    'clinic',
    '18, rue Didouche Mourad',
    'Alger Centre',
    'Alger',
    '021234567',
    'Africa/Algiers',
    'active',
    DATE_SUB(NOW(), INTERVAL 1 YEAR),
    NOW()
);

INSERT INTO roles (id, code, label) VALUES
    (1, 'clinic_admin', 'Administrateur du cabinet'),
    (2, 'doctor', 'Médecin'),
    (3, 'secretary', 'Secrétaire');

-- Comptes de démonstration.
-- Mot de passe normal : Marki2026!
-- Mot de passe temporaire : Temporaire1!
INSERT INTO users (
    id, clinic_id, email, phone, password_hash, full_name, status,
    last_login_at, must_change_password, password_changed_at,
    failed_login_attempts, locked_until, created_at, updated_at
) VALUES
    (1, 1, 'admin@marki.test', '+213550000001', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Dr Karim Benali', 'active', DATE_SUB(NOW(), INTERVAL 2 HOUR), 0, DATE_SUB(NOW(), INTERVAL 1 YEAR), 0, NULL, DATE_SUB(NOW(), INTERVAL 1 YEAR), NOW()),
    (2, 1, 'amina@marki.test', '+213550000002', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Amina Bensaid', 'active', DATE_SUB(NOW(), INTERVAL 30 MINUTE), 0, DATE_SUB(NOW(), INTERVAL 10 MONTH), 0, NULL, DATE_SUB(NOW(), INTERVAL 10 MONTH), NOW()),
    (3, 1, 'nadia@marki.test', '+213550000003', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Nadia Cherif', 'active', DATE_SUB(NOW(), INTERVAL 1 DAY), 0, DATE_SUB(NOW(), INTERVAL 8 MONTH), 0, NULL, DATE_SUB(NOW(), INTERVAL 8 MONTH), NOW()),
    (4, 1, 'samira@marki.test', '+213550000004', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Samira Kaci', 'active', DATE_SUB(NOW(), INTERVAL 3 DAY), 0, DATE_SUB(NOW(), INTERVAL 6 MONTH), 0, NULL, DATE_SUB(NOW(), INTERVAL 6 MONTH), NOW()),
    (5, 1, 'leila@marki.test', '+213550000005', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Dr Leila Mansouri', 'active', DATE_SUB(NOW(), INTERVAL 4 HOUR), 0, DATE_SUB(NOW(), INTERVAL 9 MONTH), 0, NULL, DATE_SUB(NOW(), INTERVAL 9 MONTH), NOW()),
    (6, 1, 'temporaire@marki.test', '+213550000006', '$2y$12$ldKYxFaIpbiPSJVuJwO6F.4jRu.yPxN4iZUEDug4hoM5pNRFfSRrG', 'Yacine Boudjemaa', 'active', NULL, 1, NOW(), 0, NULL, NOW(), NOW());

INSERT INTO user_roles (user_id, role_id) VALUES
    (1, 1), (1, 2),
    (2, 3),
    (3, 3),
    (4, 3),
    (5, 2),
    (6, 3);

INSERT INTO doctor_profiles (
    id, clinic_id, user_id, display_name, specialty, license_number,
    address, is_active, created_at, updated_at
) VALUES
    (1, 1, 1, 'Dr Karim Benali', 'Médecine générale', 'ALG-MG-24871',
     '18, rue Didouche Mourad, Alger Centre', 1,
     DATE_SUB(NOW(), INTERVAL 1 YEAR), NOW()),
    (2, 1, 5, 'Dr Leila Mansouri', 'Pédiatrie', 'ALG-PED-31904',
     '18, rue Didouche Mourad, Alger Centre', 1,
     DATE_SUB(NOW(), INTERVAL 9 MONTH), NOW());

INSERT INTO staff_profiles (
    id, clinic_id, user_id, job_title, created_at, updated_at
) VALUES
    (1, 1, 2, 'Secrétaire médicale', DATE_SUB(NOW(), INTERVAL 10 MONTH), NOW()),
    (2, 1, 3, 'Agente d’accueil', DATE_SUB(NOW(), INTERVAL 8 MONTH), NOW()),
    (3, 1, 4, 'Secrétaire de consultation', DATE_SUB(NOW(), INTERVAL 6 MONTH), NOW()),
    (4, 1, 6, 'Secrétaire en formation', NOW(), NOW());

INSERT INTO staff_doctor_access (staff_profile_id, doctor_id, access_level) VALUES
    (1, 1, 'full'),
    (1, 2, 'full'),
    (2, 1, 'queue_and_patients'),
    (3, 1, 'queue_only'),
    (4, 2, 'queue_only');

INSERT INTO billing_accounts (id, clinic_id, pricing_model, currency, created_at)
VALUES (1, 1, 'subscription', 'DZD', DATE_SUB(NOW(), INTERVAL 1 YEAR));

INSERT INTO patients (
    id, clinic_id, external_ref, full_name, birth_date, gender, phone, email, address, notes_non_medical, created_at, updated_at
) VALUES
    (1, 1, 'PAT-0001', 'Amine Benali', '1991-03-12', 'M', '+213551000001', 'amine.benali@example.test', 'Bab Ezzouar, Alger', 'Préfère être contacté par téléphone.', DATE_SUB(NOW(), INTERVAL 31 DAY), NOW()),
    (2, 1, 'PAT-0002', 'Sara Khelifi', '1988-07-25', 'F', '+213551000002', 'sara.khelifi@example.test', 'Bir Mourad Raïs, Alger', 'Disponible surtout le matin.', DATE_SUB(NOW(), INTERVAL 32 DAY), NOW()),
    (3, 1, 'PAT-0003', 'Nadia Alloune', '1994-01-18', 'F', '+213551000003', NULL, 'Blida', 'Téléphone partagé avec un membre de la famille.', DATE_SUB(NOW(), INTERVAL 33 DAY), NOW()),
    (4, 1, 'PAT-0004', 'Karim Touati', '1985-11-03', 'M', '+213551000004', 'karim.touati@example.test', 'El Harrach, Alger', 'Aucun commentaire administratif.', DATE_SUB(NOW(), INTERVAL 34 DAY), NOW()),
    (5, 1, 'PAT-0005', 'Yasmine Bensaid', '1997-05-09', 'F', '+213551000005', NULL, 'Sétif', 'Première visite enregistrée via la secrétaire.', DATE_SUB(NOW(), INTERVAL 35 DAY), NOW()),
    (6, 1, 'PAT-0006', 'Walid Merabet', '1990-08-21', 'M', '+213551000006', 'walid.merabet@example.test', 'Constantine', 'Appeler avant toute modification de rendez-vous.', DATE_SUB(NOW(), INTERVAL 36 DAY), NOW()),
    (7, 1, 'PAT-0007', 'Lina Rahmani', '1996-12-14', 'F', '+213551000007', NULL, 'Tipaza', 'Patiente régulière.', DATE_SUB(NOW(), INTERVAL 37 DAY), NOW()),
    (8, 1, 'PAT-0008', 'Riad Cherif', '1983-04-30', 'M', '+213551000008', 'riad.cherif@example.test', 'Hydra, Alger', 'Se déplace depuis une autre commune.', DATE_SUB(NOW(), INTERVAL 38 DAY), NOW()),
    (9, 1, 'PAT-0009', 'Meriem Hamdi', '1992-10-06', 'F', '+213551000009', NULL, 'Boumerdès', 'Contact téléphonique confirmé.', DATE_SUB(NOW(), INTERVAL 39 DAY), NOW()),
    (10, 1, 'PAT-0010', 'Sofiane Meziane', '1989-02-27', 'M', '+213551000010', 'sofiane.meziane@example.test', 'Tizi Ouzou', 'Préfère les passages en fin de matinée.', DATE_SUB(NOW(), INTERVAL 40 DAY), NOW()),
    (11, 1, 'PAT-0011', 'Samira Bouzid', '1978-06-16', 'F', '+213661000011', NULL, 'Kouba, Alger', 'Accompagnée habituellement par sa fille.', DATE_SUB(NOW(), INTERVAL 41 DAY), NOW()),
    (12, 1, 'PAT-0012', 'Mourad Saadi', '1969-09-04', 'M', '+213661000012', NULL, 'Birkhadem, Alger', 'Numéro vérifié lors du dernier passage.', DATE_SUB(NOW(), INTERVAL 42 DAY), NOW()),
    (13, 1, 'PAT-0013', 'Ines Belkacem', '2001-11-22', 'F', '+213661000013', 'ines.belkacem@example.test', 'Dely Ibrahim, Alger', 'Inscription publique testée.', DATE_SUB(NOW(), INTERVAL 43 DAY), NOW()),
    (14, 1, 'PAT-0014', 'Abdelkader Boudiaf', '1958-01-30', 'M', '+213661000014', NULL, 'Béjaïa', 'Contact de la famille disponible sur demande.', DATE_SUB(NOW(), INTERVAL 44 DAY), NOW()),
    (15, 1, 'PAT-0015', 'Lila Mansouri', '1981-04-08', 'F', '+213661000015', NULL, 'Draria, Alger', 'Patiente suivie régulièrement.', DATE_SUB(NOW(), INTERVAL 45 DAY), NOW()),
    (16, 1, 'PAT-0016', 'Nabil Ait Ali', '1975-03-19', 'M', '+213661000016', 'nabil.aitali@example.test', 'Bouzareah, Alger', 'Adresse confirmée.', DATE_SUB(NOW(), INTERVAL 46 DAY), NOW()),
    (17, 1, 'PAT-0017', 'Chahrazad Drikeche', '1993-08-11', 'F', '+213771000017', NULL, 'Rouiba, Alger', 'Téléphone principal uniquement.', DATE_SUB(NOW(), INTERVAL 47 DAY), NOW()),
    (18, 1, 'PAT-0018', 'Hocine Lamri', '1987-02-05', 'M', '+213771000018', 'hocine.lamri@example.test', 'Réghaïa, Alger', 'Préfère recevoir les informations par courriel.', DATE_SUB(NOW(), INTERVAL 48 DAY), NOW()),
    (19, 1, 'PAT-0019', 'Baya Ziani', '1965-12-02', 'F', '+213771000019', NULL, 'Cheraga, Alger', 'Venue plusieurs fois au cabinet.', DATE_SUB(NOW(), INTERVAL 49 DAY), NOW()),
    (20, 1, 'PAT-0020', 'Samir Hadj', '1999-07-27', 'M', '+213771000020', 'samir.hadj@example.test', 'Bordj El Kiffan, Alger', 'Dossier administratif complet.', DATE_SUB(NOW(), INTERVAL 50 DAY), NOW()),
    (21, 1, 'PAT-0021', 'Amina Ouali', '1986-10-13', 'F', '+213551000021', NULL, 'El Biar, Alger', 'Peut être ajoutée rapidement à la liste du jour.', DATE_SUB(NOW(), INTERVAL 51 DAY), NOW()),
    (22, 1, 'PAT-0022', 'Farid Meziane', '1972-05-15', 'M', '+213551000022', NULL, 'Hussein Dey, Alger', 'Patient régulier.', DATE_SUB(NOW(), INTERVAL 52 DAY), NOW()),
    (23, 1, 'PAT-0023', 'Kahina Ait Ahmed', '1995-09-09', 'F', '+213661000023', 'kahina.aitahmed@example.test', 'Aïn Benian, Alger', 'Données administratives mises à jour.', DATE_SUB(NOW(), INTERVAL 53 DAY), NOW()),
    (24, 1, 'PAT-0024', 'Youcef Brahimi', '1980-01-21', 'M', '+213771000024', NULL, 'Dar El Beïda, Alger', 'Nouveau patient du mois.', DATE_SUB(NOW(), INTERVAL 54 DAY), NOW());


INSERT INTO public_links (
    id, public_id, clinic_id, doctor_id, type, token_hash, token_version,
    is_active, activated_at, created_by_user_id, created_at, updated_at,
    deactivated_at, deactivated_by_user_id, last_scanned_at, revoked_at, revoked_by_user_id
) VALUES (
    1,
    'a1b2c3d4e5f6478899aabbccddeeff00',
    1,
    1,
    'qr',
    '6f1ed002ab5595859014ebf0951522d9f1f6baf9c6f2d54c53a7b59f6f66a7c1',
    1,
    1,
    DATE_SUB(NOW(), INTERVAL 6 MONTH),
    1,
    DATE_SUB(NOW(), INTERVAL 6 MONTH),
    NOW(),
    NULL,
    NULL,
    DATE_SUB(NOW(), INTERVAL 20 MINUTE),
    NULL,
    NULL
);

INSERT INTO doctor_public_registration_settings (
    id, clinic_id, doctor_id, public_registration_enabled,
    guest_registration_enabled, phone_required, birth_date_required,
    privacy_consent_required, automatic_schedule_enabled,
    public_sessions_enabled, public_session_duration_minutes,
    queue_notifications_enabled, max_public_registrations_per_day,
    created_at, updated_at
) VALUES (
    1, 1, 1, 1, 1, 1, 0, 1, 0, 1, 720, 0, 40,
    DATE_SUB(NOW(), INTERVAL 6 MONTH), NOW()
), (
    2, 1, 2, 1, 1, 1, 0, 1, 0, 1, 720, 0, 30,
    DATE_SUB(NOW(), INTERVAL 6 MONTH), NOW()
);

INSERT INTO doctor_public_registration_hours (
    id, clinic_id, doctor_id, day_of_week, slot_order,
    registration_open_time, registration_close_time, is_active,
    created_at, updated_at
) VALUES
    (1, 1, 1, 1, 1, '08:00:00', '12:00:00', 1, NOW(), NOW()),
    (2, 1, 1, 2, 1, '08:00:00', '12:00:00', 1, NOW(), NOW()),
    (3, 1, 1, 3, 1, '08:00:00', '12:00:00', 1, NOW(), NOW()),
    (4, 1, 1, 4, 1, '08:00:00', '12:00:00', 1, NOW(), NOW()),
    (5, 1, 1, 6, 1, '08:00:00', '12:00:00', 1, NOW(), NOW());

INSERT INTO doctor_public_registration_messages (
    id, clinic_id, doctor_id, message_code, message_text, is_active, created_at, updated_at
) VALUES
    (1, 1, 1, 'registration_open', 'Les inscriptions sont ouvertes. Vous pouvez rejoindre la liste d’attente.', 1, NOW(), NOW()),
    (2, 1, 1, 'registration_closed', 'Les nouvelles inscriptions sont temporairement fermées. Veuillez contacter le cabinet.', 1, NOW(), NOW()),
    (3, 1, 1, 'queue_paused', 'La prise en charge est temporairement en pause.', 1, NOW(), NOW()),
    (4, 1, 1, 'day_completed', 'La liste d’attente est terminée pour aujourd’hui.', 1, NOW(), NOW()),
    (5, 1, 1, 'qr_disabled', 'L’inscription par QR code est temporairement indisponible.', 1, NOW(), NOW()),
    (6, 1, 1, 'outside_schedule', 'Les inscriptions en ligne ne sont pas disponibles à cette heure.', 1, NOW(), NOW()),
    (7, 1, 1, 'registration_success', 'Votre inscription à la liste d’attente a bien été enregistrée.', 1, NOW(), NOW());

-- Liste du jour courante.
INSERT INTO queues (
    id, clinic_id, doctor_id, queue_date,
    registration_status, day_status,
    registration_status_before_completion, day_status_before_completion,
    status, opened_at, closed_at, paused_at, resumed_at, completed_at,
    opened_by_user_id, closed_by_user_id, paused_by_user_id, completed_by_user_id,
    created_at, updated_at
) VALUES (
    1, 1, 1, CURDATE(),
    'open', 'active', NULL, NULL,
    'open', TIMESTAMP(CURDATE(), '08:00:00'), NULL, NULL, NULL, NULL,
    2, NULL, NULL, NULL,
    TIMESTAMP(CURDATE(), '07:55:00'), NOW()
);

INSERT INTO queues (
    id, clinic_id, doctor_id, queue_date, registration_status, day_status,
    registration_status_before_completion, day_status_before_completion, status,
    opened_at, closed_at, paused_at, resumed_at, completed_at,
    opened_by_user_id, closed_by_user_id, paused_by_user_id, completed_by_user_id,
    created_at, updated_at
) VALUES
    (2, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '13:00:00'), 2, 2, NULL, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '13:00:00')),
    (3, 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '13:00:00'), 2, 3, NULL, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '13:00:00')),
    (4, 1, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:00:00'), 2, 2, NULL, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:00:00')),
    (5, 1, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '13:00:00'), 2, 3, NULL, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '13:00:00')),
    (6, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '12:30:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '10:10:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '13:00:00'), 2, 2, 2, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '13:00:00')),
    (7, 1, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '13:00:00'), 2, 3, NULL, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '13:00:00')),
    (8, 1, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'closed', 'paused', NULL, NULL, 'open', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '10:15:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '10:20:00'), NULL, NULL, 2, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '10:20:00')),
    (9, 1, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '13:00:00'), 2, 3, NULL, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '13:00:00')),
    (10, 1, 1, DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '13:00:00'), 2, 2, NULL, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '13:00:00')),
    (11, 1, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '12:30:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '10:10:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '13:00:00'), 2, 3, 2, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '13:00:00')),
    (12, 1, 1, DATE_SUB(CURDATE(), INTERVAL 11 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '13:00:00'), 2, 2, NULL, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '13:00:00')),
    (13, 1, 1, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '13:00:00'), 2, 3, NULL, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '13:00:00')),
    (14, 1, 1, DATE_SUB(CURDATE(), INTERVAL 13 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '13:00:00'), 2, 2, NULL, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '13:00:00')),
    (15, 1, 1, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '12:30:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '13:00:00'), 2, 3, NULL, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '13:00:00')),
    (16, 1, 1, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'closed', 'completed', 'closed', 'active', 'closed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '12:30:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '10:10:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '13:00:00'), 2, 2, 2, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '07:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '13:00:00'));

INSERT INTO queue_entries (
    id, queue_id, clinic_id, patient_id, display_name, phone, birth_date,
    source, public_link_id, status, status_before_completion, canceled_by_completion,
    position_number, created_at, called_at, done_at, canceled_at, cancellation_reason,
    no_show_at, last_rejoined_at, created_by_user_id, updated_by_user_id
) VALUES
    (1, 1, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'waiting', NULL, 0, 1, TIMESTAMP(CURDATE(), '08:05:00'), NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
    (2, 1, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'secretary', NULL, 'done', NULL, 0, 2, TIMESTAMP(CURDATE(), '08:10:00'), NULL, TIMESTAMP(CURDATE(), '08:45:00'), NULL, NULL, NULL, NULL, 2, 2),
    (3, 1, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'doctor', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(CURDATE(), '08:15:00'), NULL, NULL, NULL, NULL, TIMESTAMP(CURDATE(), '09:00:00'), NULL, 1, 1),
    (4, 1, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'waiting', NULL, 0, 4, TIMESTAMP(CURDATE(), '08:20:00'), NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
    (5, 1, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'secretary', NULL, 'done', NULL, 0, 5, TIMESTAMP(CURDATE(), '08:30:00'), NULL, TIMESTAMP(CURDATE(), '09:15:00'), NULL, NULL, NULL, NULL, 2, 2),
    (6, 1, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'secretary', NULL, 'canceled', NULL, 0, 6, TIMESTAMP(CURDATE(), '08:40:00'), NULL, NULL, TIMESTAMP(CURDATE(), '09:10:00'), 'patient_request', NULL, NULL, 2, 2),
    (7, 1, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'doctor', NULL, 'waiting', NULL, 0, 7, TIMESTAMP(CURDATE(), '08:50:00'), NULL, NULL, NULL, NULL, NULL, NULL, 1, 1),
    (8, 1, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'secretary', NULL, 'waiting', NULL, 0, 8, TIMESTAMP(CURDATE(), '09:00:00'), NULL, NULL, NULL, NULL, NULL, NULL, 3, 3),
    (9, 1, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'secretary', NULL, 'done', NULL, 0, 9, TIMESTAMP(CURDATE(), '09:10:00'), NULL, TIMESTAMP(CURDATE(), '10:00:00'), NULL, NULL, NULL, NULL, 3, 3),
    (10, 1, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'link', 1, 'waiting', NULL, 0, 10, TIMESTAMP(CURDATE(), '09:20:00'), NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
    (11, 1, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'qr', 1, 'waiting', NULL, 0, 11, TIMESTAMP(CURDATE(), '09:30:00'), NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
    (12, 1, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'secretary', NULL, 'waiting', NULL, 0, 12, TIMESTAMP(CURDATE(), '09:40:00'), NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
    (13, 1, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'waiting', NULL, 0, 13, TIMESTAMP(CURDATE(), '08:25:00'), NULL, NULL, NULL, NULL, TIMESTAMP(CURDATE(), '09:05:00'), TIMESTAMP(CURDATE(), '10:30:00'), 2, 2);

INSERT INTO queue_entries (
    id, queue_id, clinic_id, patient_id, display_name, phone, birth_date,
    source, public_link_id, status, status_before_completion, canceled_by_completion,
    position_number, created_at, called_at, done_at, canceled_at, cancellation_reason,
    no_show_at, last_rejoined_at, created_by_user_id, updated_by_user_id
) VALUES
    (14, 2, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (15, 2, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (16, 2, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:54:00'), NULL, 2, 2),
    (17, 2, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (18, 2, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (19, 2, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (20, 2, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (21, 2, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:54:00'), NULL, 1, 3),
    (22, 3, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (23, 3, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (24, 3, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:54:00'), NULL, 2, 2),
    (25, 3, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (26, 3, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:03:00'), 'registration_error', NULL, NULL, 2, 2),
    (27, 3, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (28, 3, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (29, 3, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:54:00'), NULL, 1, 3),
    (30, 4, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (31, 4, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (32, 4, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:54:00'), NULL, 2, 2),
    (33, 4, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (34, 4, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (35, 4, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (36, 4, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (37, 4, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:54:00'), NULL, 1, 3),
    (38, 5, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (39, 5, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (40, 5, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:54:00'), NULL, 2, 2),
    (41, 5, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (42, 5, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:03:00'), 'registration_error', NULL, NULL, 2, 2),
    (43, 5, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (44, 5, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (45, 5, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:54:00'), NULL, 1, 3),
    (46, 6, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (47, 6, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (48, 6, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:54:00'), NULL, 2, 2),
    (49, 6, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (50, 6, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (51, 6, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (52, 6, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (53, 6, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:54:00'), NULL, 1, 3),
    (54, 7, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (55, 7, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (56, 7, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:54:00'), NULL, 2, 2),
    (57, 7, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (58, 7, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:03:00'), 'registration_error', NULL, NULL, 2, 2),
    (59, 7, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (60, 7, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (61, 7, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:54:00'), NULL, 1, 3),
    (62, 8, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (63, 8, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (64, 8, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:54:00'), NULL, 2, 2),
    (65, 8, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (66, 8, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (67, 8, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (68, 8, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'waiting', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:12:00'), NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
    (69, 8, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'waiting', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:24:00'), NULL, NULL, NULL, NULL, NULL, NULL, 1, 3),
    (70, 9, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (71, 9, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (72, 9, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:54:00'), NULL, 2, 2),
    (73, 9, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (74, 9, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:03:00'), 'registration_error', NULL, NULL, 2, 2),
    (75, 9, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (76, 9, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (77, 9, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:54:00'), NULL, 1, 3),
    (78, 10, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (79, 10, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (80, 10, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:54:00'), NULL, 2, 2),
    (81, 10, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (82, 10, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (83, 10, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (84, 10, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (85, 10, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:54:00'), NULL, 1, 3),
    (86, 11, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (87, 11, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (88, 11, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:54:00'), NULL, 2, 2),
    (89, 11, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (90, 11, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:03:00'), 'registration_error', NULL, NULL, 2, 2),
    (91, 11, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (92, 11, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (93, 11, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:54:00'), NULL, 1, 3),
    (94, 12, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (95, 12, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (96, 12, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:54:00'), NULL, 2, 2),
    (97, 12, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (98, 12, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (99, 12, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (100, 12, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (101, 12, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:54:00'), NULL, 1, 3),
    (102, 13, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (103, 13, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (104, 13, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:54:00'), NULL, 2, 2),
    (105, 13, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (106, 13, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:03:00'), 'registration_error', NULL, NULL, 2, 2),
    (107, 13, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (108, 13, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (109, 13, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:54:00'), NULL, 1, 3),
    (110, 14, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (111, 14, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (112, 14, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:54:00'), NULL, 2, 2),
    (113, 14, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (114, 14, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (115, 14, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (116, 14, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (117, 14, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:54:00'), NULL, 1, 3),
    (118, 15, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (119, 15, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (120, 15, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:54:00'), NULL, 2, 2),
    (121, 15, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (122, 15, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:03:00'), 'registration_error', NULL, NULL, 2, 2),
    (123, 15, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (124, 15, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (125, 15, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:54:00'), NULL, 1, 3),
    (126, 16, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:35:00'), NULL, NULL, NULL, NULL, 2, 2),
    (127, 16, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'done', NULL, 0, 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:47:00'), NULL, NULL, NULL, NULL, 1, 3),
    (128, 16, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'secretary', NULL, 'no_show', NULL, 0, 3, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:54:00'), NULL, 2, 2),
    (129, 16, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'qr', 1, 'done', NULL, 0, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:36:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:11:00'), NULL, NULL, NULL, NULL, 3, 3),
    (130, 16, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'secretary', NULL, 'canceled', NULL, 0, 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:48:00'), NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:03:00'), 'patient_request', NULL, NULL, 2, 2),
    (131, 16, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'link', 1, 'done', NULL, 0, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:00:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:35:00'), NULL, NULL, NULL, NULL, 3, 3),
    (132, 16, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'done', NULL, 0, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:12:00'), NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:47:00'), NULL, NULL, NULL, NULL, 2, 2),
    (133, 16, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'no_show', NULL, 0, 8, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:24:00'), NULL, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:54:00'), NULL, 1, 3);

INSERT INTO visits (
    id, clinic_id, doctor_id, patient_id, queue_entry_id, appointment_id,
    started_at, ended_at, status, created_by_user_id, started_by_user_id,
    completed_by_user_id, canceled_by_user_id, created_at, updated_at
) VALUES
    (1, 1, 1, 2, 2, NULL, NULL, TIMESTAMP(CURDATE(), '08:45:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(CURDATE(), '08:45:00'), TIMESTAMP(CURDATE(), '08:45:00')),
    (2, 1, 1, 5, 5, NULL, NULL, TIMESTAMP(CURDATE(), '09:15:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(CURDATE(), '09:15:00'), TIMESTAMP(CURDATE(), '09:15:00')),
    (3, 1, 1, 9, 9, NULL, NULL, TIMESTAMP(CURDATE(), '10:00:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(CURDATE(), '10:00:00'), TIMESTAMP(CURDATE(), '10:00:00')),
    (4, 1, 1, 4, 14, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:35:00')),
    (5, 1, 1, 5, 15, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:47:00')),
    (6, 1, 1, 7, 17, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:11:00')),
    (7, 1, 1, 9, 19, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:35:00')),
    (8, 1, 1, 10, 20, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:47:00')),
    (9, 1, 1, 7, 22, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:35:00')),
    (10, 1, 1, 8, 23, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:47:00')),
    (11, 1, 1, 10, 25, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:11:00')),
    (12, 1, 1, 12, 27, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:35:00')),
    (13, 1, 1, 13, 28, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:47:00')),
    (14, 1, 1, 10, 30, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:35:00')),
    (15, 1, 1, 11, 31, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:47:00')),
    (16, 1, 1, 13, 33, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:11:00')),
    (17, 1, 1, 15, 35, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:35:00')),
    (18, 1, 1, 16, 36, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:47:00')),
    (19, 1, 1, 13, 38, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:35:00')),
    (20, 1, 1, 14, 39, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:47:00')),
    (21, 1, 1, 16, 41, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:11:00')),
    (22, 1, 1, 18, 43, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:35:00')),
    (23, 1, 1, 19, 44, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:47:00')),
    (24, 1, 1, 16, 46, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:35:00')),
    (25, 1, 1, 17, 47, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:47:00')),
    (26, 1, 1, 19, 49, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:11:00')),
    (27, 1, 1, 21, 51, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:35:00')),
    (28, 1, 1, 22, 52, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:47:00')),
    (29, 1, 1, 19, 54, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:35:00')),
    (30, 1, 1, 20, 55, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:47:00')),
    (31, 1, 1, 22, 57, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:11:00')),
    (32, 1, 1, 24, 59, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:35:00')),
    (33, 1, 1, 1, 60, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:47:00')),
    (34, 1, 1, 22, 62, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:35:00')),
    (35, 1, 1, 23, 63, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:47:00')),
    (36, 1, 1, 1, 65, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:11:00')),
    (37, 1, 1, 3, 67, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:35:00')),
    (38, 1, 1, 1, 70, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:35:00')),
    (39, 1, 1, 2, 71, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:47:00')),
    (40, 1, 1, 4, 73, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:11:00')),
    (41, 1, 1, 6, 75, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:35:00')),
    (42, 1, 1, 7, 76, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '09:47:00')),
    (43, 1, 1, 4, 78, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:35:00')),
    (44, 1, 1, 5, 79, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:47:00')),
    (45, 1, 1, 7, 81, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:11:00')),
    (46, 1, 1, 9, 83, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:35:00')),
    (47, 1, 1, 10, 84, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '09:47:00')),
    (48, 1, 1, 7, 86, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:35:00')),
    (49, 1, 1, 8, 87, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:47:00')),
    (50, 1, 1, 10, 89, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:11:00')),
    (51, 1, 1, 12, 91, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:35:00')),
    (52, 1, 1, 13, 92, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:47:00')),
    (53, 1, 1, 10, 94, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:35:00')),
    (54, 1, 1, 11, 95, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:47:00')),
    (55, 1, 1, 13, 97, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:11:00')),
    (56, 1, 1, 15, 99, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:35:00')),
    (57, 1, 1, 16, 100, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '09:47:00')),
    (58, 1, 1, 13, 102, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:35:00')),
    (59, 1, 1, 14, 103, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:47:00')),
    (60, 1, 1, 16, 105, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:11:00')),
    (61, 1, 1, 18, 107, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:35:00')),
    (62, 1, 1, 19, 108, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:47:00')),
    (63, 1, 1, 16, 110, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:35:00')),
    (64, 1, 1, 17, 111, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:47:00')),
    (65, 1, 1, 19, 113, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:11:00')),
    (66, 1, 1, 21, 115, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:35:00')),
    (67, 1, 1, 22, 116, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '09:47:00')),
    (68, 1, 1, 19, 118, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:35:00')),
    (69, 1, 1, 20, 119, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '08:47:00')),
    (70, 1, 1, 22, 121, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:11:00')),
    (71, 1, 1, 24, 123, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:35:00')),
    (72, 1, 1, 1, 124, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:47:00')),
    (73, 1, 1, 22, 126, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:35:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:35:00')),
    (74, 1, 1, 23, 127, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:47:00'), 'done', 1, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '08:47:00')),
    (75, 1, 1, 1, 129, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:11:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:11:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:11:00')),
    (76, 1, 1, 3, 131, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:35:00'), 'done', 3, NULL, 3, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:35:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:35:00')),
    (77, 1, 1, 4, 132, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:47:00'), 'done', 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:47:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:47:00'));



-- Données du deuxième médecin pour tester le sélecteur et l'isolation.
INSERT INTO queues (
    id, clinic_id, doctor_id, queue_date, registration_status, day_status,
    registration_status_before_completion, day_status_before_completion, status,
    opened_at, closed_at, paused_at, resumed_at, completed_at,
    opened_by_user_id, closed_by_user_id, paused_by_user_id, completed_by_user_id,
    created_at, updated_at
) VALUES
    (17, 1, 2, CURDATE(), 'open', 'active', NULL, NULL, 'open',
     TIMESTAMP(CURDATE(), '08:30:00'), NULL, NULL, NULL, NULL,
     2, NULL, NULL, NULL, TIMESTAMP(CURDATE(), '08:25:00'), NOW()),
    (18, 1, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'closed', 'completed',
     'closed', 'active', 'closed',
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:30:00'),
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:00:00'),
     NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:10:00'),
     2, 2, NULL, 5,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:25:00'),
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:10:00'));

INSERT INTO queue_entries (
    id, queue_id, clinic_id, patient_id, display_name, phone, birth_date,
    source, public_link_id, status, status_before_completion, canceled_by_completion,
    position_number, created_at, called_at, done_at, canceled_at, cancellation_reason,
    no_show_at, last_rejoined_at, created_by_user_id, updated_by_user_id
) VALUES
    (134, 17, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13',
     'secretary', NULL, 'waiting', NULL, 0, 1,
     TIMESTAMP(CURDATE(), '08:35:00'), NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
    (135, 17, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15',
     'secretary', NULL, 'done', NULL, 0, 2,
     TIMESTAMP(CURDATE(), '08:45:00'), NULL, TIMESTAMP(CURDATE(), '09:20:00'),
     NULL, NULL, NULL, NULL, 2, 5),
    (136, 17, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09',
     'doctor', NULL, 'waiting', NULL, 0, 3,
     TIMESTAMP(CURDATE(), '08:55:00'), NULL, NULL, NULL, NULL, NULL, NULL, 5, 5),
    (137, 18, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21',
     'secretary', NULL, 'done', NULL, 0, 1,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:40:00'), NULL,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:15:00'),
     NULL, NULL, NULL, NULL, 2, 5),
    (138, 18, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27',
     'doctor', NULL, 'no_show', NULL, 0, 2,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:55:00'), NULL,
     NULL, NULL, NULL,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00'), NULL, 5, 5);

INSERT INTO visits (
    id, clinic_id, doctor_id, patient_id, queue_entry_id, appointment_id,
    started_at, ended_at, status,
    created_by_user_id, started_by_user_id, completed_by_user_id, canceled_by_user_id,
    created_at, updated_at
) VALUES
    (78, 1, 2, 22, 135, NULL, NULL, TIMESTAMP(CURDATE(), '09:20:00'), 'done',
     2, NULL, 5, NULL, TIMESTAMP(CURDATE(), '09:20:00'), TIMESTAMP(CURDATE(), '09:20:00')),
    (79, 1, 2, 24, 137, NULL, NULL,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:15:00'), 'done',
     2, NULL, 5, NULL,
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:15:00'),
     TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:15:00'));

-- Session et consentements de l'inscription QR de Samira Bouzid.
INSERT INTO patient_public_sessions (
    id, queue_entry_id, session_token_hash, expires_at, last_used_at,
    revoked_at, created_ip_hash, user_agent, created_at, updated_at
) VALUES (
    1, 11,
    '98f13708210194c475687be6106a3b84f9b1f8b2f2b3e3b3cf841094c147f2bc',
    DATE_ADD(NOW(), INTERVAL 12 HOUR),
    DATE_SUB(NOW(), INTERVAL 15 MINUTE),
    NULL,
    'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876',
    'Mozilla/5.0 MARKI demo mobile',
    DATE_SUB(NOW(), INTERVAL 20 MINUTE),
    NOW()
);

INSERT INTO queue_entry_consents (
    id, clinic_id, queue_entry_id, consent_type, channel, granted,
    policy_version, created_ip_hash, user_agent, consented_at, revoked_at
) VALUES
    (1, 1, 11, 'privacy', 'none', 1, 'v1.0',
     'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876',
     'Mozilla/5.0 MARKI demo mobile', DATE_SUB(NOW(), INTERVAL 20 MINUTE), NULL),
    (2, 1, 11, 'notifications', 'sms', 0, 'v1.0',
     'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876',
     'Mozilla/5.0 MARKI demo mobile', DATE_SUB(NOW(), INTERVAL 20 MINUTE), NULL);

INSERT INTO public_link_events (
    id, public_link_id, queue_id, queue_entry_id, event_type, result_code,
    metadata_json, ip_hash, user_agent, created_at
) VALUES
    (1, 1, 1, NULL, 'scan', 'accepted', JSON_OBJECT('doctor_id', 1),
     'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876',
     'Mozilla/5.0 MARKI demo mobile', DATE_SUB(NOW(), INTERVAL 22 MINUTE)),
    (2, 1, 1, 11, 'registered', 'success', JSON_OBJECT('patient_id', 11, 'source', 'qr'),
     'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876',
     'Mozilla/5.0 MARKI demo mobile', DATE_SUB(NOW(), INTERVAL 20 MINUTE));

-- Journal d'activité utile pour l'audit V1.
INSERT INTO activity_logs (
    id, clinic_id, actor_user_id, action, entity_type, entity_id, metadata_json, created_at
) VALUES
    (1, 1, 2, 'QUEUE_OPENED', 'queue', 1,
     JSON_OBJECT('queue_date', DATE_FORMAT(CURDATE(), '%Y-%m-%d')), TIMESTAMP(CURDATE(), '08:00:00')),
    (2, 1, 2, 'QUEUE_ENTRY_ADDED', 'queue_entry', 1,
     JSON_OBJECT('patient_id', 1, 'source', 'secretary'), TIMESTAMP(CURDATE(), '08:05:00')),
    (3, 1, 2, 'VISIT_COMPLETED', 'visit', 1,
     JSON_OBJECT('queue_entry_id', 2, 'patient_id', 2, 'doctor_id', 1), TIMESTAMP(CURDATE(), '08:45:00')),
    (4, 1, 2, 'VISIT_COMPLETED', 'visit', 2,
     JSON_OBJECT('queue_entry_id', 5, 'patient_id', 5, 'doctor_id', 1), TIMESTAMP(CURDATE(), '09:15:00')),
    (5, 1, 3, 'VISIT_COMPLETED', 'visit', 3,
     JSON_OBJECT('queue_entry_id', 9, 'patient_id', 9, 'doctor_id', 1), TIMESTAMP(CURDATE(), '10:00:00')),
    (6, 1, 2, 'QUEUE_ENTRY_REJOINED', 'queue_entry', 13,
     JSON_OBJECT('patient_id', 13, 'previous_arrival_number', 6, 'new_arrival_number', 13), TIMESTAMP(CURDATE(), '10:30:00')),
    (7, 1, 1, 'SETTINGS_UPDATED', 'clinic', 1,
     JSON_OBJECT('doctor_id', 1, 'new_timezone', 'Africa/Algiers'), DATE_SUB(NOW(), INTERVAL 5 DAY)),
    (8, 1, 1, 'USER_CREATED', 'user', 5,
     JSON_OBJECT('account_type', 'doctor'), DATE_SUB(NOW(), INTERVAL 9 MONTH)),
    (9, 1, 1, 'USER_CREATED', 'user', 6,
     JSON_OBJECT('account_type', 'secretary', 'access_level', 'queue_only'), NOW()),
    (10, 1, 5, 'VISIT_COMPLETED', 'visit', 78,
     JSON_OBJECT('queue_entry_id', 135, 'doctor_id', 2), TIMESTAMP(CURDATE(), '09:20:00'));

-- Contacts administratifs complémentaires.
INSERT INTO patient_contacts (id, patient_id, type, value, is_primary, created_at) VALUES
    (1, 1, 'phone', '+213551000001', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
    (2, 3, 'emergency', '+213551009903', 0, DATE_SUB(NOW(), INTERVAL 20 DAY)),
    (3, 14, 'emergency', '+213661009914', 0, DATE_SUB(NOW(), INTERVAL 15 DAY));

-- Quelques rendez-vous pour préparer les futures pages sans influencer la liste actuelle.
INSERT INTO appointments (
    id, clinic_id, doctor_id, patient_id, display_name, phone,
    start_at, end_at, status, reason, notes, created_by_user_id, created_at, updated_at
) VALUES
    (1, 1, 1, 21, 'Amina Ouali', '+213551000021',
     TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00'),
     TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:20:00'),
     'confirmed', 'Contrôle administratif', 'Rendez-vous de démonstration.', 2, NOW(), NOW()),
    (2, 1, 1, 24, 'Youcef Brahimi', '+213771000024',
     TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:30:00'),
     TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:50:00'),
     'scheduled', 'Première consultation', NULL, 3, NOW(), NOW());

COMMIT;

-- Ajuster les prochains identifiants.
ALTER TABLE clinics AUTO_INCREMENT = 2;
ALTER TABLE users AUTO_INCREMENT = 7;
ALTER TABLE doctor_profiles AUTO_INCREMENT = 3;
ALTER TABLE staff_profiles AUTO_INCREMENT = 5;
ALTER TABLE patients AUTO_INCREMENT = 25;
ALTER TABLE queues AUTO_INCREMENT = 19;
ALTER TABLE queue_entries AUTO_INCREMENT = 139;
ALTER TABLE visits AUTO_INCREMENT = 100;
ALTER TABLE activity_logs AUTO_INCREMENT = 11;
ALTER TABLE public_links AUTO_INCREMENT = 2;
ALTER TABLE public_link_events AUTO_INCREMENT = 3;
ALTER TABLE patient_public_sessions AUTO_INCREMENT = 2;
ALTER TABLE queue_entry_consents AUTO_INCREMENT = 3;

-- Résumé de contrôle.
SELECT 'patients' AS element, COUNT(*) AS total FROM patients
UNION ALL
SELECT 'queues', COUNT(*) FROM queues
UNION ALL
SELECT 'queue_entries', COUNT(*) FROM queue_entries
UNION ALL
SELECT 'visits', COUNT(*) FROM visits
UNION ALL
SELECT 'activity_logs', COUNT(*) FROM activity_logs;
