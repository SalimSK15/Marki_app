<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$config = require $root . '/app/config.php';
require_once $root . '/app/db.php';
require_once $root . '/app/repositories/SettingsRepository.php';

fwrite(STDOUT, "MARKI - Diagnostic Parametres\n");
fwrite(STDOUT, str_repeat('=', 42) . "\n");

try {
    $pdo = db();
    $rows = $pdo->query(
        "SELECT c.id AS clinic_id, d.id AS doctor_id, d.display_name
         FROM clinics c
         INNER JOIN doctor_profiles d ON d.clinic_id = c.id
         WHERE c.status = 'active' AND d.is_active = 1
         ORDER BY c.id, d.id"
    )->fetchAll();

    if ($rows === []) {
        throw new RuntimeException('Aucun medecin actif.');
    }

    $repository = new SettingsRepository();
    foreach ($rows as $row) {
        $settings = $repository->get(
            (int) $row['clinic_id'],
            (int) $row['doctor_id']
        );

        fwrite(
            STDOUT,
            sprintf(
                "OK - %s / %s\n",
                (string) ($settings['clinic']['name'] ?? ''),
                (string) ($settings['doctor']['display_name'] ?? '')
            )
        );
    }

    fwrite(STDOUT, "\nLECTURE PARAMETRES REUSSIE\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "\nLECTURE PARAMETRES ECHOUEE\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
