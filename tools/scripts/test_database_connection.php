<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/env.php';
$config = require dirname(__DIR__, 2) . '/app/config.php';
require_once dirname(__DIR__, 2) . '/app/db.php';

fwrite(STDOUT, "MARKI - Diagnostic de la base de donnees\n");
fwrite(STDOUT, str_repeat('=', 45) . "\n");
fwrite(STDOUT, 'Hote       : ' . (string) $config['db']['host'] . "\n");
fwrite(STDOUT, 'Port       : ' . (string) $config['db']['port'] . "\n");
fwrite(STDOUT, 'Base       : ' . (string) $config['db']['dbname'] . "\n");
fwrite(STDOUT, 'Utilisateur: ' . (string) $config['db']['username'] . "\n\n");

try {
    $pdo = db();
    $row = $pdo->query(
        'SELECT DATABASE() AS database_name, CURRENT_USER() AS current_user, VERSION() AS server_version'
    )->fetch();

    $requiredTables = [
        'clinics',
        'users',
        'queue_entries',
        'public_links',
        'platform_admins',
    ];

    $missing = [];
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = :schema_name AND table_name = :table_name'
    );

    foreach ($requiredTables as $table) {
        $stmt->execute([
            ':schema_name' => (string) $config['db']['dbname'],
            ':table_name' => $table,
        ]);

        if ((int) $stmt->fetchColumn() !== 1) {
            $missing[] = $table;
        }
    }

    fwrite(STDOUT, "CONNEXION REUSSIE\n");
    fwrite(STDOUT, 'Compte MySQL reel : ' . (string) ($row['current_user'] ?? '') . "\n");
    fwrite(STDOUT, 'Serveur MySQL      : ' . (string) ($row['server_version'] ?? '') . "\n");

    if ($missing === []) {
        fwrite(STDOUT, "Tables principales : OK\n");
        exit(0);
    }

    fwrite(STDOUT, 'Tables manquantes  : ' . implode(', ', $missing) . "\n");
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, "CONNEXION ECHOUEE\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    fwrite(STDERR, "\nLe mot de passe du compte MySQL et MARKI_DB_PASSWORD doivent etre strictement identiques.\n");
    exit(1);
}
