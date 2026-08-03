<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/env.php';
$config = require $root . '/app/config.php';
require_once $root . '/app/db.php';
require_once $root . '/app/platform/PlatformAdminRepository.php';

$email = mb_strtolower(trim((string) getenv('MARKI_NEW_PLATFORM_ADMIN_EMAIL')), 'UTF-8');
$fullName = trim((string) getenv('MARKI_NEW_PLATFORM_ADMIN_NAME'));
$password = (string) getenv('MARKI_NEW_PLATFORM_ADMIN_PASSWORD');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Adresse courriel invalide.\n");
    exit(1);
}

if ($fullName === '') {
    fwrite(STDERR, "Le nom complet est obligatoire.\n");
    exit(1);
}

if (mb_strlen($password, 'UTF-8') < 12) {
    fwrite(STDERR, "Le mot de passe doit contenir au moins 12 caracteres.\n");
    exit(1);
}

try {
    $repository = new PlatformAdminRepository(db());
    $adminId = $repository->upsertAdmin(
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $fullName
    );
    $repository->log(
        $adminId,
        'PLATFORM_ADMIN_CREATED_OR_PASSWORD_RESET',
        ['email' => $email, 'source' => 'local_cli']
    );

    fwrite(STDOUT, "Compte administrateur MARKI pret.\n");
    fwrite(STDOUT, "ID       : {$adminId}\n");
    fwrite(STDOUT, "Nom      : {$fullName}\n");
    fwrite(STDOUT, "Courriel : {$email}\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Impossible de creer le compte administrateur.\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
