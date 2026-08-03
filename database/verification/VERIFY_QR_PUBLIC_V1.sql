-- =========================================================
USE `markii_db`;
-- MARKI V1 — Vérification du module d'inscription publique QR
-- =========================================================

SELECT 'public_links' AS table_name, COUNT(*) AS total
FROM public_links
UNION ALL
SELECT 'public_link_events', COUNT(*) FROM public_link_events
UNION ALL
SELECT 'patient_public_sessions', COUNT(*) FROM patient_public_sessions
UNION ALL
SELECT 'doctor_public_registration_settings', COUNT(*)
FROM doctor_public_registration_settings
UNION ALL
SELECT 'doctor_public_registration_messages', COUNT(*)
FROM doctor_public_registration_messages
UNION ALL
SELECT 'queue_entry_consents', COUNT(*) FROM queue_entry_consents;

SELECT
    pl.id,
    pl.public_id,
    pl.clinic_id,
    pl.doctor_id,
    pl.type,
    pl.token_version,
    pl.is_active,
    pl.activated_at,
    pl.deactivated_at,
    pl.last_scanned_at,
    pl.revoked_at
FROM public_links pl
ORDER BY pl.id DESC;

SELECT
    qe.id,
    qe.queue_id,
    qe.patient_id,
    qe.display_name,
    qe.phone,
    qe.source,
    qe.public_link_id,
    qe.status,
    qe.position_number,
    qe.created_at
FROM queue_entries qe
WHERE qe.source = 'qr'
ORDER BY qe.id DESC
LIMIT 30;

SELECT
    ple.id,
    ple.public_link_id,
    ple.queue_id,
    ple.queue_entry_id,
    ple.event_type,
    ple.result_code,
    ple.created_at
FROM public_link_events ple
ORDER BY ple.id DESC
LIMIT 50;

SELECT
    pps.id,
    pps.queue_entry_id,
    pps.expires_at,
    pps.last_used_at,
    pps.revoked_at,
    pps.created_at
FROM patient_public_sessions pps
ORDER BY pps.id DESC
LIMIT 30;

SELECT
    qec.id,
    qec.queue_entry_id,
    qec.consent_type,
    qec.granted,
    qec.policy_version,
    qec.consented_at
FROM queue_entry_consents qec
ORDER BY qec.id DESC
LIMIT 30;

SELECT
    al.id,
    al.actor_user_id,
    al.action,
    al.entity_type,
    al.entity_id,
    al.created_at
FROM activity_logs al
WHERE al.action LIKE 'PUBLIC_%'
ORDER BY al.id DESC
LIMIT 50;
