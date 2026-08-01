<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$config = $public['config'];

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . Auth::baseUrl($config) . '/index.php');
    exit;
}

$clinicSlug = trim((string) ($_GET['clinic'] ?? ''));
$activationCompleted = (string) ($_GET['activated'] ?? '') === '1';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — MARKI</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20260731-final1">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand">
            <span class="auth-brand__mark">M</span>
            <div>
                <h1>MARKI</h1>
                <p>Gestion des patients</p>
            </div>
        </div>

        <header class="auth-card__header">
            <h2>Connexion</h2>
            <p>Accédez à votre espace professionnel.</p>
        </header>

        <?php if ($activationCompleted): ?>
            <div class="auth-message is-success auth-message--visible" role="status">
                Votre espace MARKI a été créé. Connectez-vous avec le compte que vous venez de choisir.
            </div>
        <?php endif; ?>

        <form id="login-form" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($public['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <input
                type="hidden"
                name="clinic_slug"
                value="<?= htmlspecialchars($clinicSlug, ENT_QUOTES, 'UTF-8') ?>"
            >

            <label>
                <span>Courriel ou téléphone</span>
                <input
                    type="text"
                    name="identifier"
                    autocomplete="username"
                    required
                >
            </label>

            <label>
                <span>Mot de passe</span>
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </label>

            <label class="auth-checkbox">
                <input type="checkbox" name="remember" value="1">
                <span>Rester connecté sur cet appareil pendant 30 jours</span>
            </label>

            <div id="auth-message" class="auth-message" role="alert" aria-live="polite"></div>

            <button type="submit" class="auth-button">Se connecter</button>
        </form>

        <a class="auth-link" href="forgot-password.php?clinic=<?= rawurlencode($clinicSlug) ?>">Mot de passe oublié ?</a>
    </main>

    <script src="assets/js/login.js?v=20260731-final1" defer></script>
</body>
</html>
