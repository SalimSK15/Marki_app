<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/auth/TeamRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Auth::jsonError(405, 'Méthode non autorisée.');
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : $_POST;

    $data = (new TeamRepository())->save(
        (int) $context['clinic_id'],
        (int) $context['user_id'],
        $input,
        (int) ($context['config']['auth']['password_min_length'] ?? 10)
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Compte enregistré avec succès.',
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException | RuntimeException $exception) {
    Auth::jsonError(422, $exception->getMessage());
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible d’enregistrer le compte.');
}
