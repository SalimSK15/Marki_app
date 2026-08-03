<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$envPath = $root . '/.env';
$config = require $root . '/app/config.php';

fwrite(STDOUT, "MARKI - Verification du fichier .env\n");
fwrite(STDOUT, str_repeat('=', 42) . "\n");
fwrite(STDOUT, 'Fichier .env : ' . (is_readable($envPath) ? 'OK' : 'INTROUVABLE') . "\n");
fwrite(STDOUT, 'Hote        : ' . (string) $config['db']['host'] . "\n");
fwrite(STDOUT, 'Port        : ' . (string) $config['db']['port'] . "\n");
fwrite(STDOUT, 'Base        : ' . (string) $config['db']['dbname'] . "\n");
fwrite(STDOUT, 'Utilisateur : ' . (string) $config['db']['username'] . "\n");
fwrite(
    STDOUT,
    'Mot de passe: ' . ((string) $config['db']['password'] === '' ? 'VIDE (autorise en local)' : 'CONFIGURE') . "\n"
);

$username = (string) $config['db']['username'];
if ($username === '') {
    fwrite(STDERR, "\nERREUR : MARKI_DB_USERNAME est vide.\n");
    exit(1);
}

if (preg_match('/^[\'\"]|[\'\"]$/', $username) === 1) {
    fwrite(STDERR, "\nERREUR : le nom utilisateur contient encore des guillemets.\n");
    exit(2);
}

fwrite(STDOUT, "\nCONFIGURATION ENV LUE CORRECTEMENT\n");
exit(0);
