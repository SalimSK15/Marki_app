-- ==========================================================================
-- MARKI - Stabilite QR et unicite patient/file
-- Date : 2026-08-03
-- A executer seulement sur une base qui ne contient pas de doubles lignes
-- (queue_id, patient_id). Le reset global fourni nettoie d'abord les donnees.
-- ==========================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS marki_add_queue_patient_unique;

DELIMITER $$
CREATE PROCEDURE marki_add_queue_patient_unique()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM queue_entries
        WHERE patient_id IS NOT NULL
        GROUP BY queue_id, patient_id
        HAVING COUNT(*) > 1
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Des inscriptions patient dupliquees existent. Executez le reset global ou nettoyez-les avant cette migration.';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'queue_entries'
          AND index_name = 'ux_qe_queue_patient'
    ) THEN
        ALTER TABLE queue_entries
            ADD UNIQUE KEY ux_qe_queue_patient (queue_id, patient_id);
    END IF;
END$$
DELIMITER ;

CALL marki_add_queue_patient_unique();
DROP PROCEDURE marki_add_queue_patient_unique;
