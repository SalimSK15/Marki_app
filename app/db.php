<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Connexion PDO centralisée
|--------------------------------------------------------------------------
| Responsabilités :
|
| - créer une seule connexion PDO par requête ;
| - charger la configuration de la base ;
| - appliquer le fuseau horaire du cabinet à PHP ;
| - appliquer le même fuseau à la connexion MySQL ;
| - utiliser la session lorsque l'authentification sera disponible ;
| - utiliser temporairement dev_context pendant le développement.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Obtenir la connexion PDO
|--------------------------------------------------------------------------
*/
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dbConfig = $config['db'];

    /*
    |--------------------------------------------------------------------------
    | Fuseau de secours
    |--------------------------------------------------------------------------
    | Cette valeur est utilisée uniquement lorsqu'aucun cabinet valide
    | ne peut être retrouvé.
    |--------------------------------------------------------------------------
    */
    $fallbackTimezone =
        (string) ($config['app']['timezone'] ?? 'UTC');

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
        PDO::ATTR_ERRMODE =>
            PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE =>
            PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES =>
            false,
    ];

    try {
        $pdo = new PDO(
            $dsn,
            $dbConfig['username'],
            $dbConfig['password'],
            $options
        );

        /*
        |--------------------------------------------------------------------------
        | Identifier le cabinet courant
        |--------------------------------------------------------------------------
        | La session aura priorité lorsque la connexion sera développée.
        | Pour le moment, dev_context reste utilisé.
        |--------------------------------------------------------------------------
        */
        $clinicId =
            resolveRuntimeClinicId($config);

        if ($clinicId !== null) {
            configureClinicTimezone(
                $pdo,
                $clinicId,
                $fallbackTimezone
            );
        } else {
            applyTimezoneToRuntime(
                $pdo,
                $fallbackTimezone
            );
        }

        return $pdo;
    } catch (PDOException $exception) {
        http_response_code(500);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        $isDebug =
            (bool) ($config['app']['debug'] ?? false);

        $response = [
            'ok' => false,
            'message' =>
                'Erreur de connexion à la base de données.',
        ];

        if ($isDebug) {
            $response['error'] =
                $exception->getMessage();
        }

        echo json_encode(
            $response,
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Retrouver le cabinet utilisé pendant la requête
|--------------------------------------------------------------------------
*/
function resolveRuntimeClinicId(array $config): ?int
{
    /*
    |--------------------------------------------------------------------------
    | Priorité à la future session réelle
    |--------------------------------------------------------------------------
    */
    if (
        session_status() === PHP_SESSION_ACTIVE
        && isset($_SESSION['clinic_id'])
    ) {
        $sessionClinicId =
            (int) $_SESSION['clinic_id'];

        if ($sessionClinicId > 0) {
            return $sessionClinicId;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Secours temporaire pour le développement
    |--------------------------------------------------------------------------
    */
    $devClinicId =
        (int) (
            $config['dev_context']['clinic_id']
            ?? 0
        );

    return $devClinicId > 0
        ? $devClinicId
        : null;
}

/*
|--------------------------------------------------------------------------
| Appliquer le fuseau horaire d'un cabinet
|--------------------------------------------------------------------------
*/
function configureClinicTimezone(
    PDO $pdo,
    int $clinicId,
    string $fallbackTimezone = 'UTC'
): string {
    $sql = "
        SELECT
            timezone
        FROM clinics
        WHERE id = :clinic_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':clinic_id' => $clinicId,
    ]);

    $row = $stmt->fetch();

    $timezone =
        isset($row['timezone'])
            ? trim((string) $row['timezone'])
            : '';

    if (!isValidTimezone($timezone)) {
        $timezone = isValidTimezone(
            $fallbackTimezone
        )
            ? $fallbackTimezone
            : 'UTC';
    }

    return applyTimezoneToRuntime(
        $pdo,
        $timezone
    );
}

/*
|--------------------------------------------------------------------------
| Appliquer le même fuseau à PHP et MySQL
|--------------------------------------------------------------------------
*/
function applyTimezoneToRuntime(
    PDO $pdo,
    string $timezone
): string {
    if (!isValidTimezone($timezone)) {
        $timezone = 'UTC';
    }

    /*
    |--------------------------------------------------------------------------
    | Horloge PHP
    |--------------------------------------------------------------------------
    */
    date_default_timezone_set($timezone);

    $timezoneObject =
        new DateTimeZone($timezone);

    $now =
        new DateTimeImmutable(
            'now',
            $timezoneObject
        );

    /*
    |--------------------------------------------------------------------------
    | Calculer le décalage actuel pour MySQL
    |--------------------------------------------------------------------------
    | Exemple :
    |
    | Africa/Algiers   → +01:00
    | America/Toronto  → -04:00 ou -05:00 selon la saison
    |--------------------------------------------------------------------------
    */
    $offsetSeconds =
        $timezoneObject->getOffset($now);

    $sign =
        $offsetSeconds < 0
            ? '-'
            : '+';

    $absoluteOffset =
        abs($offsetSeconds);

    $hours =
        intdiv($absoluteOffset, 3600);

    $minutes =
        intdiv(
            $absoluteOffset % 3600,
            60
        );

    $mysqlOffset = sprintf(
        '%s%02d:%02d',
        $sign,
        $hours,
        $minutes
    );

    /*
    |--------------------------------------------------------------------------
    | Horloge de la connexion MySQL
    |--------------------------------------------------------------------------
    | NOW() et CURRENT_TIMESTAMP utiliseront désormais le fuseau du cabinet.
    |--------------------------------------------------------------------------
    */
    $pdo->exec(
        'SET time_zone = '
        . $pdo->quote($mysqlOffset)
    );

    return $timezone;
}

/*
|--------------------------------------------------------------------------
| Vérifier un identifiant IANA
|--------------------------------------------------------------------------
| Exemples valides :
|
| Africa/Algiers
| America/Toronto
|--------------------------------------------------------------------------
*/
function isValidTimezone(string $timezone): bool
{
    if ($timezone === '') {
        return false;
    }

    return in_array(
        $timezone,
        timezone_identifiers_list(),
        true
    );
}