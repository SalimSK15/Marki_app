<?php

declare(strict_types=1);

$config = require __DIR__ . '/../app/config.php';

if ((string) ($config['app']['env'] ?? '') !== 'local') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$clientIp = htmlspecialchars(
    (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'),
    ENT_QUOTES,
    'UTF-8'
);
$host = htmlspecialchars(
    (string) ($_SERVER['HTTP_HOST'] ?? 'inconnu'),
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <link rel="icon" href="assets/icons/marki-app-192.png" type="image/png">
    <title>Test réseau — MARKI</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f7f4fd; color: #211a31; font-family: system-ui, sans-serif; }
        main { width: min(100%, 520px); padding: 28px; border: 1px solid #ded6ed; border-radius: 22px; background: #fff; box-shadow: 0 18px 50px rgba(54, 35, 83, .1); }
        .mark { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px; background: #6f42d9; color: #fff; font-weight: 900; }
        h1 { margin: 18px 0 8px; font-size: 28px; }
        p { color: #665e73; line-height: 1.6; }
        .success { margin-top: 20px; padding: 16px; border: 1px solid #a9dfc5; border-radius: 14px; background: #ecfbf4; color: #08764d; font-weight: 750; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 10px 16px; margin-top: 22px; }
        dt { color: #70677e; } dd { margin: 0; font-weight: 700; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <div class="mark">M</div>
    <h1>MARKI est accessible</h1>
    <p>Cette page confirme que le téléphone peut joindre le serveur Laragon de l’ordinateur.</p>
    <div class="success">Connexion locale réussie.</div>
    <dl>
        <dt>Adresse utilisée</dt><dd><?= $host ?></dd>
        <dt>Appareil reçu</dt><dd><?= $clientIp ?></dd>
    </dl>
</main>
</body>
</html>
