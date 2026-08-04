<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $context = require __DIR__ . '/../../app/bootstrap.php';
    require_once __DIR__ . '/../../app/repositories/QueueHistoryRepository.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'message' => 'Méthode non autorisée.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $queueId = (int) ($_GET['queue_id'] ?? 0);
    if ($queueId <= 0) {
        throw new InvalidArgumentException('Liste invalide.');
    }

    $repository = new QueueHistoryRepository();
    $details = $repository->findDetails(
        $queueId,
        (int) $context['clinic_id'],
        (int) $context['doctor_id']
    );

    echo json_encode([
        'ok' => true,
        'data' => $details,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de charger cette liste.',
    ], JSON_UNESCAPED_UNICODE);
}
