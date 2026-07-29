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

    $context = Auth::attemptLogin(
        $config,
        (string) ($input['clinic_slug'] ?? ''),
        (string) ($input['identifier'] ?? ''),
        (string) ($input['password'] ?? ''),
        filter_var($input['remember'] ?? false, FILTER_VALIDATE_BOOL)
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Connexion réussie.',
        'data' => [
            'redirect' => $context['user']['must_change_password']
                ? Auth::baseUrl($config) . '/change-password.php'
                : Auth::baseUrl($config) . '/index.php',
            'clinic_slug' => $context['clinic']['slug'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (AuthException $exception) {
    Auth::jsonError(422, $exception->getMessage());
} catch (Throwable $exception) {
    $message = (bool) ($config['app']['debug'] ?? false)
        ? $exception->getMessage()
        : 'Impossible de vous connecter.';
    Auth::jsonError(500, $message);
}
