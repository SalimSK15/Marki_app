<?php

declare(strict_types=1);

/**
 * Charge un fichier .env simple situé à la racine du projet.
 * Les variables déjà définies par le serveur gardent la priorité.
 */
function loadMarkiEnvironment(?string $path = null): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $path ??= dirname(__DIR__) . '/.env';

    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);

    if (!is_array($values)) {
        throw new RuntimeException('Le fichier .env de MARKI est invalide.');
    }

    foreach ($values as $key => $value) {
        $name = trim((string) $key);

        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        $normalized = trim((string) $value);
        putenv($name . '=' . $normalized);
        $_ENV[$name] = $normalized;
        $_SERVER[$name] = $normalized;
    }
}

function markiEnv(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    $value = trim((string) $value);

    return $value === '' ? $default : $value;
}

function markiEnvBool(string $key, bool $default = false): bool
{
    $value = markiEnv($key);

    if ($value === null) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        ?? $default;
}

function markiEnvInt(string $key, int $default): int
{
    $value = markiEnv($key);

    if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return $default;
    }

    return (int) $value;
}

loadMarkiEnvironment();
