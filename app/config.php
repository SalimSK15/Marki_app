<?php

return [
    'app' => [
        'name' => 'MARKI',
        'env' => 'local',
        'debug' => true,
        'timezone' => 'Africa/Algiers',
        'base_path' => '/Marki_app/Partie_medecin/public',
        'app_key' => 'marki-v1-change-this-key-before-production-2026',
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3307,
        'dbname' => 'markii_db',
        'charset' => 'utf8mb4',
        'username' => 'root',
        'password' => '',
    ],

    'auth' => [
        'session_name' => 'marki_session',
        'idle_timeout_seconds' => 43200,
        'remember_days' => 30,
        'max_failed_attempts' => 5,
        'lock_minutes' => 15,
        'password_min_length' => 10,
    ],

    // Accès interne V1 utilisé uniquement pour générer le premier lien
    // d’activation d’un nouveau cabinet ou d’une nouvelle clinique.
    // Remplace cette valeur avant toute mise en ligne.
    'platform' => [
        'setup_key' => 'marki-local-change-this-platform-key-2026',
        'invitation_expiry_hours' => 72,
    ],
];
