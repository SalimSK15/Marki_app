<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$public = require __DIR__ . '/../../app/public_bootstrap.php';
$config = $public['config'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : $_POST;
    Auth::validateCsrf((string) ($input['csrf_token'] ?? ''));

    Auth::requestPasswordReset(
        $config,
        (string) ($input['clinic_slug'] ?? ''),
        (string) ($input['email'] ?? '')
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Si le compte existe, un lien de réinitialisation a été préparé.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible de traiter la demande.');
}
