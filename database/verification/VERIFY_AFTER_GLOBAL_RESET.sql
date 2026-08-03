-- Verification apres RESET_ALL_AND_SEED_MARKI_V1.sql
USE `markii_db`;

SELECT 'platform_admins' AS element, COUNT(*) AS total, 1 AS attendu FROM platform_admins
UNION ALL SELECT 'clinics', COUNT(*), 1 FROM clinics
UNION ALL SELECT 'users', COUNT(*), 6 FROM users
UNION ALL SELECT 'doctor_profiles', COUNT(*), 2 FROM doctor_profiles
UNION ALL SELECT 'staff_profiles', COUNT(*), 4 FROM staff_profiles
UNION ALL SELECT 'patients', COUNT(*), 24 FROM patients
UNION ALL SELECT 'queues', COUNT(*), 18 FROM queues
UNION ALL SELECT 'queue_entries', COUNT(*), 138 FROM queue_entries
UNION ALL SELECT 'visits', COUNT(*), 79 FROM visits
UNION ALL SELECT 'activity_logs', COUNT(*), 10 FROM activity_logs
UNION ALL SELECT 'public_links', COUNT(*), 1 FROM public_links;

SELECT id, email, full_name, status, failed_login_attempts, locked_until
FROM platform_admins
ORDER BY id;

SELECT id, name, slug, type, city, wilaya, status
FROM clinics
ORDER BY id;

SELECT u.id, u.email, u.full_name, u.status,
       GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ', ') AS roles
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON r.id = ur.role_id
GROUP BY u.id, u.email, u.full_name, u.status
ORDER BY u.id;

-- Aucun patient ne doit etre inscrit deux fois dans la meme file.
SELECT queue_id, patient_id, COUNT(*) AS total
FROM queue_entries
WHERE patient_id IS NOT NULL
GROUP BY queue_id, patient_id
HAVING COUNT(*) > 1;

-- L'index de securite doit exister.
SELECT index_name, non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_indexees
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'queue_entries'
  AND index_name = 'ux_qe_queue_patient'
GROUP BY index_name, non_unique;
