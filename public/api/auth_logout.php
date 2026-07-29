<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth/Auth.php';

Auth::start($config);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    Auth::validateCsrf((string) ($_POST['csrf_token'] ?? ''));
    Auth::logout($config);

    header('Location: ' . Auth::baseUrl($config) . '/login.php');
    exit;
} catch (Throwable $exception) {
    Auth::logout($config, false);
    header('Location: ' . Auth::baseUrl($config) . '/login.php');
    exit;
}
