<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/env.php';

$key = markiEnv('MARKI_PLATFORM_SETUP_KEY', '');

if ($key === null || trim($key) === '') {
    fwrite(STDERR, "La cle MARKI_PLATFORM_SETUP_KEY est absente du fichier .env.\n");
    fwrite(STDERR, "Lancez tools\\scripts\\configure_marki_env.bat.\n");
    exit(1);
}

fwrite(STDOUT, "\nCLE D ADMINISTRATION INTERNE MARKI :\n");
fwrite(STDOUT, $key . "\n\n");
fwrite(STDOUT, "Ne partagez pas cette cle avec les cliniques.\n");
