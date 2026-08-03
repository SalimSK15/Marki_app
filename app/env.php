<?php

declare(strict_types=1);

/**
 * Charge le fichier .env situé à la racine du projet.
 *
 * Le parseur est volontairement simple et prévisible :
 * - il ignore les lignes vides et les commentaires ;
 * - il accepte les valeurs avec ou sans guillemets simples/doubles ;
 * - il retire le BOM UTF-8 éventuel ;
 * - le fichier .env local reste la source de vérité à chaque requête.
 */
function loadMarkiEnvironment(?string $path = null): void
{
    static $loadedPaths = [];

    $path ??= dirname(__DIR__) . '/.env';
    $realPath = realpath($path) ?: $path;

    if (isset($loadedPaths[$realPath])) {
        return;
    }

    $loadedPaths[$realPath] = true;

    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Impossible de lire le fichier .env de MARKI.');
    }

    // Retirer le BOM UTF-8 qui peut être ajouté par certains éditeurs Windows.
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
    $lines = preg_split('/\R/', $contents) ?: [];

    foreach ($lines as $lineNumber => $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name)) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Le .env local doit remplacer une ancienne valeur restée dans un processus Apache.
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function markiEnvRaw(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    return trim((string) $value);
}

function markiEnv(string $key, ?string $default = null): ?string
{
    $value = markiEnvRaw($key, $default);

    if ($value === null || $value === '') {
        return $default;
    }

    return $value;
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
