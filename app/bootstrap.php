<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bootstrap interne de MARKI
|--------------------------------------------------------------------------
| Prépare :
|
| - la session ;
| - la configuration ;
| - la connexion PDO ;
| - le cabinet courant ;
| - le médecin sélectionné ;
| - l'utilisateur connecté ;
| - le fuseau horaire du cabinet ;
| - la date locale du cabinet.
|--------------------------------------------------------------------------
*/

if (
    PHP_SAPI !== 'cli'
    && session_status() === PHP_SESSION_NONE
) {
    session_start();
}

$config =
    require __DIR__ . '/config.php';

require_once __DIR__ . '/db.php';

/*
|--------------------------------------------------------------------------
| Contexte réel lorsqu'une session existe
|--------------------------------------------------------------------------
*/
$sessionClinicId =
    isset($_SESSION['clinic_id'])
        ? (int) $_SESSION['clinic_id']
        : 0;

$sessionDoctorId =
    isset($_SESSION['selected_doctor_id'])
        ? (int) $_SESSION['selected_doctor_id']
        : 0;

$sessionUserId =
    isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : 0;

/*
|--------------------------------------------------------------------------
| Contexte temporaire de développement
|--------------------------------------------------------------------------
*/
$clinicId =
    $sessionClinicId > 0
        ? $sessionClinicId
        : (int) (
            $config['dev_context']['clinic_id']
            ?? 0
        );

$doctorId =
    $sessionDoctorId > 0
        ? $sessionDoctorId
        : (int) (
            $config['dev_context']['doctor_id']
            ?? 0
        );

$userId =
    $sessionUserId > 0
        ? $sessionUserId
        : (int) (
            $config['dev_context']['user_id']
            ?? 0
        );

if (
    $clinicId <= 0
    || $doctorId <= 0
    || $userId <= 0
) {
    throw new RuntimeException(
        'Le contexte utilisateur est incomplet.'
    );
}

/*
|--------------------------------------------------------------------------
| Connexion et fuseau horaire du cabinet
|--------------------------------------------------------------------------
*/
$pdo = db();

$timezone =
    configureClinicTimezone(
        $pdo,
        $clinicId,
        (string) (
            $config['app']['timezone']
            ?? 'UTC'
        )
    );

$clinicNow =
    new DateTimeImmutable(
        'now',
        new DateTimeZone($timezone)
    );

return [
    'config' => $config,
    'pdo' => $pdo,

    'clinic_id' => $clinicId,
    'doctor_id' => $doctorId,
    'user_id' => $userId,

    'timezone' => $timezone,

    /*
    |--------------------------------------------------------------------------
    | Date et heure locales du cabinet
    |--------------------------------------------------------------------------
    */
    'today' =>
        $clinicNow->format('Y-m-d'),

    'now' =>
        $clinicNow->format(
            'Y-m-d H:i:s'
        ),
];