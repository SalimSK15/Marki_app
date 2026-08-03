<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$selector = trim((string) ($_GET['selector'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe — MARKI</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20260801-preqr1">
    <link rel="stylesheet" href="assets/css/password-toggle.css?v=20260801-preqr1">
    <link rel="stylesheet" href="assets/design-system/marki-theme.css?v=20260803-design-ready1">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand"><span class="auth-brand__mark">M</span><h1>MARKI</h1></div>
        <header class="auth-card__header">
            <h2>Nouveau mot de passe</h2>
            <p>Choisissez un mot de passe sécurisé.</p>
        </header>

        <form id="reset-password-form" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($public['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="selector" value="<?= htmlspecialchars($selector, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                <span>Nouveau mot de passe</span>
                <input type="password" name="password" autocomplete="new-password" required>
            </label>
            <label>
                <span>Confirmer le mot de passe</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>
            <small>10 caractères minimum, avec majuscule, minuscule et chiffre.</small>
            <div id="auth-message" class="auth-message" role="alert" aria-live="polite"></div>
            <button type="submit" class="auth-button">Modifier le mot de passe</button>
        </form>
    </main>
    <script src="assets/js/login.js?v=20260802-login2" defer></script>
    <script src="assets/js/password-toggle.js?v=20260801-preqr1" defer></script>
</body>
</html>
