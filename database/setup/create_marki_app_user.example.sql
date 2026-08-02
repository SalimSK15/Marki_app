-- =========================================================
-- OPTION PRODUCTION — Compte MySQL dédié à MARKI
-- =========================================================
-- 1. Remplacez CHANGE_THIS_STRONG_PASSWORD.
-- 2. Exécutez ce fichier avec un compte MySQL administrateur.
-- 3. Reportez le nom et le mot de passe dans le fichier .env.
--
-- Ce compte technique n'est PAS un médecin ou une secrétaire.
-- Les comptes de connexion à MARKI sont enregistrés dans la table users.
-- =========================================================

CREATE USER IF NOT EXISTS 'marki_app'@'localhost'
IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';

CREATE USER IF NOT EXISTS 'marki_app'@'127.0.0.1'
IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE
ON markii_db.*
TO 'marki_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON markii_db.*
TO 'marki_app'@'127.0.0.1';

FLUSH PRIVILEGES;
