-- ============================================================================
-- REPARER LE COMPTE MYSQL DE MARKI
-- A executer dans phpMyAdmin avec le compte root / administrateur MySQL.
-- ============================================================================
-- IMPORTANT :
-- 1. Remplacez partout CHANGE_THIS_DATABASE_PASSWORD par UN NOUVEAU mot de passe.
-- 2. Executez le fichier complet.
-- 3. Copiez exactement le meme mot de passe dans MARKI_DB_PASSWORD du fichier .env.
-- 4. N'ajoutez jamais ce mot de passe dans .env.example ou dans Git.

DROP USER IF EXISTS 'marki_app'@'localhost';
DROP USER IF EXISTS 'marki_app'@'127.0.0.1';
DROP USER IF EXISTS 'marki_app'@'::1';

CREATE USER 'marki_app'@'localhost'
IDENTIFIED BY ">]a.6JK7Rk2^iG_<8";

CREATE USER 'marki_app'@'127.0.0.1'
IDENTIFIED BY ">]a.6JK7Rk2^iG_<8";

CREATE USER 'marki_app'@'::1'
IDENTIFIED BY ">]a.6JK7Rk2^iG_<8";

GRANT SELECT, INSERT, UPDATE, DELETE
ON `markii_db`.*
TO 'marki_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON `markii_db`.*
TO 'marki_app'@'127.0.0.1';

GRANT SELECT, INSERT, UPDATE, DELETE
ON `markii_db`.*
TO 'marki_app'@'::1';

FLUSH PRIVILEGES;

SELECT User, Host, plugin
FROM mysql.user
WHERE User = 'marki_app'
ORDER BY Host;
