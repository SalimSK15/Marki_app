<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$clinicSlug = trim((string) ($_GET['clinic'] ?? 'cabinet-el-amal'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — MARKI</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20260728-auth1">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand"><span class="auth-brand__mark">M</span><h1>MARKI</h1></div>
        <header class="auth-card__header">
            <h2>Mot de passe oublié</h2>
            <p>Un lien temporaire sera envoyé au courriel du compte.</p>
        </header>

        <form id="forgot-password-form" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($public['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <label>
                <span>Code de la structure</span>
                <input type="text" name="clinic_slug" value="<?= htmlspecialchars($clinicSlug, ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>
                <span>Courriel</span>
                <input type="email" name="email" autocomplete="email" required>
            </label>
            <div id="auth-message" class="auth-message" role="status" aria-live="polite"></div>
            <button type="submit" class="auth-button">Préparer la réinitialisation</button>
        </form>

        <a class="auth-link" href="login.php">Retour à la connexion</a>
    </main>
    <script src="assets/js/login.js?v=20260728-auth1" defer></script>
</body>
</html>
