<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dbConfig = $config['db'];

    if (trim((string) ($dbConfig['username'] ?? '')) === '') {
        throw new RuntimeException(
            'La connexion MySQL n est pas configuree. ' .
            'Renseignez MARKI_DB_USERNAME dans le fichier .env.'
        );
    }

    // Laragon peut utiliser un compte root local sans mot de passe.
    // Une chaîne vide est donc une valeur valide pour MARKI_DB_PASSWORD.
    $dbConfig['password'] = (string) ($dbConfig['password'] ?? '');

    $fallbackTimezone = (string) ($config['app']['timezone'] ?? 'UTC');
    if (!isValidTimezone($fallbackTimezone)) {
        $fallbackTimezone = 'UTC';
    }

    date_default_timezone_set($fallbackTimezone);

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['dbname'],
        $dbConfig['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO(
            $dsn,
            $dbConfig['username'],
            $dbConfig['password'],
            $options
        );

        applyTimezoneToRuntime($pdo, $fallbackTimezone);

        return $pdo;
    } catch (PDOException $exception) {
        if (PHP_SAPI === 'cli') {
            throw $exception;
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'ok' => false,
            'message' => 'Erreur de connexion à la base de données.',
        ];

        if ((bool) ($config['app']['debug'] ?? false)) {
            $response['error'] = $exception->getMessage();
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function configureClinicTimezone(
    PDO $pdo,
    int $clinicId,
    string $fallbackTimezone = 'UTC'
): string {
    $stmt = $pdo->prepare(
        'SELECT timezone FROM clinics WHERE id = :clinic_id LIMIT 1'
    );
    $stmt->execute([':clinic_id' => $clinicId]);

    $row = $stmt->fetch();
    $timezone = isset($row['timezone'])
        ? trim((string) $row['timezone'])
        : '';

    if (!isValidTimezone($timezone)) {
        $timezone = isValidTimezone($fallbackTimezone)
            ? $fallbackTimezone
            : 'UTC';
    }

    return applyTimezoneToRuntime($pdo, $timezone);
}

function applyTimezoneToRuntime(PDO $pdo, string $timezone): string
{
    if (!isValidTimezone($timezone)) {
        $timezone = 'UTC';
    }

    date_default_timezone_set($timezone);

    $timezoneObject = new DateTimeZone($timezone);
    $now = new DateTimeImmutable('now', $timezoneObject);
    $offsetSeconds = $timezoneObject->getOffset($now);
    $sign = $offsetSeconds < 0 ? '-' : '+';
    $absoluteOffset = abs($offsetSeconds);
    $hours = intdiv($absoluteOffset, 3600);
    $minutes = intdiv($absoluteOffset % 3600, 60);
    $mysqlOffset = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

    $pdo->exec('SET time_zone = ' . $pdo->quote($mysqlOffset));

    return $timezone;
}

function isValidTimezone(string $timezone): bool
{
    return $timezone !== ''
        && in_array($timezone, timezone_identifiers_list(), true);
}
