<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth/Auth.php';

try {
    $context = Auth::context($config, true);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    Auth::validateCsrf();
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : $_POST;

    $newPassword = (string) ($input['new_password'] ?? '');
    $confirmation = (string) ($input['new_password_confirmation'] ?? '');

    if ($newPassword !== $confirmation) {
        throw new AuthException('Les mots de passe ne correspondent pas.');
    }

    Auth::changePassword(
        $config,
        $context,
        (string) ($input['current_password'] ?? ''),
        $newPassword
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Mot de passe modifié avec succès.',
        'data' => [
            'redirect' => Auth::baseUrl($config) . '/index.php',
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (AuthException $exception) {
    Auth::jsonError(422, $exception->getMessage());
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible de modifier le mot de passe.');
}
