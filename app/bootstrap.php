<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$debug = (bool) ($config['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/Auth.php';

$context = Auth::context($config, true);
Auth::authorizeEndpoint($context);

/*
 * Les API Parametres, QR et Equipe sont chargees presque en meme temps.
 * PHP verrouille le fichier de session tant qu'une requete le garde ouvert.
 * A ce stade, le contexte et le CSRF sont deja verifies : on libere donc le
 * verrou avant les requetes SQL metier afin d'eviter les attentes et courses.
 */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

return $context;
