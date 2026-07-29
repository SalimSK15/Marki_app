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
    $doctorId = (int) ($input['doctor_id'] ?? 0);

    if ($doctorId <= 0) {
        throw new AuthException('Médecin invalide.');
    }

    $updated = Auth::selectDoctor($config, $doctorId);

    echo json_encode([
        'ok' => true,
        'message' => 'Médecin sélectionné.',
        'data' => [
            'doctor' => $updated['doctor'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (AuthException $exception) {
    Auth::jsonError(422, $exception->getMessage());
} catch (Throwable $exception) {
    Auth::jsonError(500, 'Impossible de changer de médecin.');
}
