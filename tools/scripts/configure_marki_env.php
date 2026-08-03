<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit etre execute dans un terminal.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__, 2);
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$examplePath = $projectRoot . DIRECTORY_SEPARATOR . '.env.example';

function readEnvValues(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);

    return is_array($values) ? array_map('strval', $values) : [];
}

function ask(string $label, string $default = ''): string
{
    $suffix = $default !== '' ? " [$default]" : '';
    $answer = readline($label . $suffix . ' : ');
    $answer = trim((string) $answer);

    return $answer === '' ? $default : $answer;
}

function envSecretMissing(?string $value): bool
{
    $value = trim((string) $value);

    return $value === ''
        || str_contains($value, 'GENERATE_LOCALLY')
        || str_contains($value, 'change-this')
        || str_contains($value, 'replace-with');
}

function generateSecret(): string
{
    return bin2hex(random_bytes(32));
}

function envLine(string $key, string $value): string
{
    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

    return $key . '="' . $escaped . '"';
}

$current = readEnvValues($envPath);

fwrite(STDOUT, "\n=== Configuration locale MARKI ===\n");
fwrite(STDOUT, "Les vraies informations seront ecrites uniquement dans .env.\n");
fwrite(STDOUT, ".env.example restera un modele sans secret.\n\n");

$dbHost = ask('Hote MySQL', $current['MARKI_DB_HOST'] ?? '127.0.0.1');
$dbPort = ask('Port MySQL', $current['MARKI_DB_PORT'] ?? '3306');
$dbName = ask('Nom de la base', $current['MARKI_DB_NAME'] ?? 'markii_db');
$dbUser = ask('Utilisateur MySQL dedie', $current['MARKI_DB_USERNAME'] ?? 'marki_app');

$existingPassword = (string) ($current['MARKI_DB_PASSWORD'] ?? '');
$passwordPrompt = $existingPassword !== ''
    ? 'Mot de passe MySQL (Entree = conserver le mot de passe actuel)'
    : 'Mot de passe MySQL';
$dbPasswordAnswer = readline($passwordPrompt . ' : ');
$dbPassword = trim((string) $dbPasswordAnswer);
if ($dbPassword === '' && $existingPassword !== '') {
    $dbPassword = $existingPassword;
}

if ($dbUser === '' || $dbPassword === '') {
    fwrite(STDERR, "\nL utilisateur et le mot de passe MySQL sont obligatoires.\n");
    exit(1);
}

$publicOrigin = ask(
    'Origine QR pour le telephone (laisser vide hors test)',
    $current['MARKI_QR_PUBLIC_ORIGIN'] ?? ''
);

$appKey = envSecretMissing($current['MARKI_APP_KEY'] ?? null)
    ? generateSecret()
    : (string) $current['MARKI_APP_KEY'];
$qrSecret = envSecretMissing($current['MARKI_QR_HMAC_SECRET'] ?? null)
    ? generateSecret()
    : (string) $current['MARKI_QR_HMAC_SECRET'];

$values = [
    'MARKI_APP_NAME' => $current['MARKI_APP_NAME'] ?? 'MARKI',
    'MARKI_APP_ENV' => $current['MARKI_APP_ENV'] ?? 'local',
    'MARKI_APP_DEBUG' => $current['MARKI_APP_DEBUG'] ?? 'true',
    'MARKI_APP_TIMEZONE' => $current['MARKI_APP_TIMEZONE'] ?? 'Africa/Algiers',
    'MARKI_APP_BASE_PATH' => $current['MARKI_APP_BASE_PATH'] ?? '/Marki_app/Partie_medecin/public',
    'MARKI_APP_KEY' => $appKey,
    'MARKI_DB_HOST' => $dbHost,
    'MARKI_DB_PORT' => $dbPort,
    'MARKI_DB_NAME' => $dbName,
    'MARKI_DB_CHARSET' => $current['MARKI_DB_CHARSET'] ?? 'utf8mb4',
    'MARKI_DB_USERNAME' => $dbUser,
    'MARKI_DB_PASSWORD' => $dbPassword,
    'MARKI_QR_HMAC_SECRET' => $qrSecret,
    'MARKI_QR_PUBLIC_ORIGIN' => $publicOrigin,
    'MARKI_QR_RATE_LIMIT_ATTEMPTS' => $current['MARKI_QR_RATE_LIMIT_ATTEMPTS'] ?? '20',
    'MARKI_QR_RATE_LIMIT_MINUTES' => $current['MARKI_QR_RATE_LIMIT_MINUTES'] ?? '15',
    'MARKI_SESSION_NAME' => $current['MARKI_SESSION_NAME'] ?? 'marki_session',
    'MARKI_IDLE_TIMEOUT_SECONDS' => $current['MARKI_IDLE_TIMEOUT_SECONDS'] ?? '43200',
    'MARKI_REMEMBER_DAYS' => $current['MARKI_REMEMBER_DAYS'] ?? '30',
    'MARKI_MAX_FAILED_ATTEMPTS' => $current['MARKI_MAX_FAILED_ATTEMPTS'] ?? '5',
    'MARKI_LOCK_MINUTES' => $current['MARKI_LOCK_MINUTES'] ?? '15',
    'MARKI_PASSWORD_MIN_LENGTH' => $current['MARKI_PASSWORD_MIN_LENGTH'] ?? '10',
    'MARKI_INVITATION_EXPIRY_HOURS' => $current['MARKI_INVITATION_EXPIRY_HOURS'] ?? '72',
    'MARKI_PLATFORM_REMEMBER_DAYS' => $current['MARKI_PLATFORM_REMEMBER_DAYS'] ?? '30',
    'MARKI_PLATFORM_MAX_FAILED_ATTEMPTS' => $current['MARKI_PLATFORM_MAX_FAILED_ATTEMPTS'] ?? '5',
    'MARKI_PLATFORM_LOCK_MINUTES' => $current['MARKI_PLATFORM_LOCK_MINUTES'] ?? '15',
    'MARKI_PLATFORM_IDLE_TIMEOUT_SECONDS' => $current['MARKI_PLATFORM_IDLE_TIMEOUT_SECONDS'] ?? '14400',
];

if (is_file($envPath)) {
    $backup = $envPath . '.backup-' . date('Ymd-His');
    if (!copy($envPath, $backup)) {
        fwrite(STDERR, "Impossible de sauvegarder le fichier .env actuel.\n");
        exit(1);
    }
    fwrite(STDOUT, "Sauvegarde creee : $backup\n");
}

$groups = [
    'Application' => [
        'MARKI_APP_NAME', 'MARKI_APP_ENV', 'MARKI_APP_DEBUG',
        'MARKI_APP_TIMEZONE', 'MARKI_APP_BASE_PATH', 'MARKI_APP_KEY',
    ],
    'Base de donnees' => [
        'MARKI_DB_HOST', 'MARKI_DB_PORT', 'MARKI_DB_NAME',
        'MARKI_DB_CHARSET', 'MARKI_DB_USERNAME', 'MARKI_DB_PASSWORD',
    ],
    'QR public' => [
        'MARKI_QR_HMAC_SECRET', 'MARKI_QR_PUBLIC_ORIGIN',
        'MARKI_QR_RATE_LIMIT_ATTEMPTS', 'MARKI_QR_RATE_LIMIT_MINUTES',
    ],
    'Authentification' => [
        'MARKI_SESSION_NAME', 'MARKI_IDLE_TIMEOUT_SECONDS',
        'MARKI_REMEMBER_DAYS', 'MARKI_MAX_FAILED_ATTEMPTS',
        'MARKI_LOCK_MINUTES', 'MARKI_PASSWORD_MIN_LENGTH',
    ],
    'Administration interne' => [
        'MARKI_INVITATION_EXPIRY_HOURS', 'MARKI_PLATFORM_REMEMBER_DAYS',
        'MARKI_PLATFORM_MAX_FAILED_ATTEMPTS', 'MARKI_PLATFORM_LOCK_MINUTES',
        'MARKI_PLATFORM_IDLE_TIMEOUT_SECONDS',
    ],
];

$lines = [
    '# Fichier local prive MARKI',
    '# Ne jamais ajouter ce fichier a Git.',
    '',
];

foreach ($groups as $title => $keys) {
    $lines[] = '# ' . $title;
    foreach ($keys as $key) {
        $lines[] = envLine($key, (string) $values[$key]);
    }
    $lines[] = '';
}

if (file_put_contents($envPath, implode(PHP_EOL, $lines)) === false) {
    fwrite(STDERR, "Impossible d ecrire le fichier .env.\n");
    exit(1);
}

fwrite(STDOUT, "\nConfiguration enregistree dans : $envPath\n");
fwrite(STDOUT, "\nLes administrateurs MARKI utilisent maintenant un courriel et un mot de passe.\n");
fwrite(STDOUT, "Utilisez tools/scripts/create_platform_admin.bat pour creer un compte.\n");
fwrite(STDOUT, "Redemarrez Apache/Laragon apres modification du fichier .env.\n\n");
