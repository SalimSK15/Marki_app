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
    $userId = (int) ($input['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new InvalidArgumentException('Compte invalide.');
    }

    $data = (new TeamRepository())->toggleStatus(
        (int) $context['clinic_id'],
        (int) $context['user_id'],
        $userId
    );

    echo json_encode([
        'ok' => true,
        'message' => 'État du compte modifié.',
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException | RuntimeException $exception) {
    Auth::jsonError(422, $exception->getMessage());
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible de modifier le compte.');
}
