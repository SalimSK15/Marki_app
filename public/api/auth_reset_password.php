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

    $password = (string) ($input['password'] ?? '');
    $confirmation = (string) ($input['password_confirmation'] ?? '');

    if ($password !== $confirmation) {
        throw new AuthException('Les mots de passe ne correspondent pas.');
    }

    Auth::resetPassword(
        $config,
        (string) ($input['selector'] ?? ''),
        (string) ($input['token'] ?? ''),
        $password
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Mot de passe modifié. Vous pouvez vous connecter.',
        'data' => [
            'redirect' => Auth::baseUrl($config) . '/login.php',
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (AuthException $exception) {
    Auth::jsonError(422, $exception->getMessage());
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible de modifier le mot de passe.');
}
