<?php

declare(strict_types=1);

$context = require __DIR__ . '/../app/web_bootstrap.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($context['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <meta name="marki-base-path" content="<?= htmlspecialchars(Auth::baseUrl($context['config']), ENT_QUOTES, 'UTF-8') ?>">
    <title>Changer le mot de passe — MARKI</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20260801-preqr1">
    <link rel="stylesheet" href="assets/css/password-toggle.css?v=20260801-preqr1">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand"><span class="auth-brand__mark">M</span><h1>MARKI</h1></div>
        <header class="auth-card__header">
            <h2>Changer le mot de passe</h2>
            <p><?= $context['user']['must_change_password'] ? 'Vous devez remplacer le mot de passe temporaire.' : 'Mettez à jour votre mot de passe.' ?></p>
        </header>

        <form id="change-password-form" class="auth-form" novalidate>
            <label>
                <span>Mot de passe actuel</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label>
                <span>Nouveau mot de passe</span>
                <input type="password" name="new_password" autocomplete="new-password" required>
            </label>
            <label>
                <span>Confirmer le nouveau mot de passe</span>
                <input type="password" name="new_password_confirmation" autocomplete="new-password" required>
            </label>
            <small>10 caractères minimum, avec majuscule, minuscule et chiffre.</small>
            <div id="auth-message" class="auth-message" role="alert" aria-live="polite"></div>
            <button type="submit" class="auth-button">Enregistrer</button>
        </form>

        <?php if (!$context['user']['must_change_password']): ?>
            <a class="auth-link" href="index.php">Retour à l’application</a>
        <?php endif; ?>
    </main>
    <script src="assets/js/auth-client.js?v=20260801-preqr1" defer></script>
    <script src="assets/js/login.js?v=20260802-login2" defer></script>
    <script src="assets/js/password-toggle.js?v=20260801-preqr1" defer></script>
</body>
</html>
