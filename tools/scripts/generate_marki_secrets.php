<?php

declare(strict_types=1);

function secret(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

echo "Copiez ces trois lignes dans votre fichier .env :\n\n";
echo 'MARKI_APP_KEY=' . secret() . PHP_EOL;
echo 'MARKI_QR_HMAC_SECRET=' . secret() . PHP_EOL;
echo 'MARKI_PLATFORM_SETUP_KEY=' . secret() . PHP_EOL;
echo "\nNe partagez pas le fichier .env et ne l'ajoutez pas a Git.\n";
