<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API : queue_today.php
|--------------------------------------------------------------------------
| Rôle :
| - récupérer la liste du jour du médecin
| - si elle n’existe pas, la créer automatiquement
| - retourner les infos de cette liste en JSON
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Debug temporaire
|--------------------------------------------------------------------------
| On laisse ça pendant le développement pour voir les erreurs.
| Plus tard, on désactivera l'affichage direct en production.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Réponse JSON
|--------------------------------------------------------------------------
*/
header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Charger la config et le repository
|--------------------------------------------------------------------------
*/
$context = require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/repositories/QueueRepository.php';

try {
    /*
    |--------------------------------------------------------------------------
    | Récupérer le contexte de développement
    |--------------------------------------------------------------------------
    | Tant qu’on n’a pas encore codé la vraie connexion utilisateur,
    | on travaille avec un cabinet / médecin / user fixes.
    |--------------------------------------------------------------------------
    */
    
    $clinicId = $context['clinic_id'];
    $doctorId = $context['doctor_id'];
    $userId = $context['user_id'];
    // $today = $context['today']; 
    /*
    |--------------------------------------------------------------------------
    | Définir la date du jour
    |--------------------------------------------------------------------------
    | IMPORTANT :
    | on utilise la timezone déjà définie côté app
    |--------------------------------------------------------------------------
    */
    $today = date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | Instancier le repository
    |--------------------------------------------------------------------------
    */
    $queueRepository = new QueueRepository();

    /*
    |--------------------------------------------------------------------------
    | Récupérer ou créer la liste du jour
    |--------------------------------------------------------------------------
    */
    $queue = $queueRepository->getOrCreateTodayQueue(
        $clinicId,
        $doctorId,
        $userId,
        $today
    );

    /*
    |--------------------------------------------------------------------------
    | Retour JSON en succès
    |--------------------------------------------------------------------------
    */
    echo json_encode([
        'ok' => true,
        'message' => 'Liste du jour récupérée avec succès.',
        'data' => [
            'queue' => $queue,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    /*
    |--------------------------------------------------------------------------
    | Gestion d’erreur
    |--------------------------------------------------------------------------
    */
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Impossible de récupérer la liste du jour.',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
