<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/env.php';
$config = require $root . '/app/config.php';
require_once $root . '/app/db.php';
require_once $root . '/app/auth/TeamRepository.php';

fwrite(STDOUT, "MARKI - Diagnostic Equipe et acces\n");
fwrite(STDOUT, str_repeat('=', 45) . "\n");
fwrite(STDOUT, 'Hote       : ' . (string) $config['db']['host'] . "\n");
fwrite(STDOUT, 'Port       : ' . (string) $config['db']['port'] . "\n");
fwrite(STDOUT, 'Base       : ' . (string) $config['db']['dbname'] . "\n");
fwrite(STDOUT, 'Utilisateur: ' . (string) $config['db']['username'] . "\n\n");

try {
    $pdo = db();

    $context = $pdo->query(
        "SELECT c.id AS clinic_id, u.id AS user_id, c.name AS clinic_name
         FROM clinics c
         INNER JOIN users u ON u.clinic_id = c.id
         ORDER BY c.id ASC, u.id ASC
         LIMIT 1"
    )->fetch();

    if (!$context) {
        throw new RuntimeException(
            'Aucune structure avec utilisateur n existe dans la base. Executez le reset global.'
        );
    }

    $result = (new TeamRepository())->index(
        (int) $context['clinic_id'],
        (int) $context['user_id']
    );

    fwrite(STDOUT, "LECTURE EQUIPE REUSSIE\n");
    fwrite(STDOUT, 'Structure   : ' . (string) $context['clinic_name'] . "\n");
    fwrite(STDOUT, 'Comptes     : ' . count($result['members'] ?? []) . "\n");
    fwrite(STDOUT, 'Medecins    : ' . count($result['doctors'] ?? []) . "\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "LECTURE EQUIPE ECHOUEE\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    fwrite(STDERR, "\nVerifiez la connexion MySQL, les migrations et le reset global.\n");
    exit(1);
}
