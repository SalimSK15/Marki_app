<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$config = $public['config'];

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . Auth::baseUrl($config) . '/index.php');
    exit;
}

require_once __DIR__ . '/../app/auth/AuthRepository.php';

$clinicSlug = trim((string) ($_GET['clinic'] ?? ''));
$activationCompleted = (string) ($_GET['activated'] ?? '') === '1';
$clinic = null;
$clinicLookupError = '';

if ($clinicSlug !== '') {
    $clinic = (new AuthRepository())->findClinicBySlug($clinicSlug);

    if ($clinic === null) {
        $clinicLookupError = 'Aucune structure ne correspond à ce code.';
    } elseif (($clinic['status'] ?? '') !== 'active') {
        $clinicLookupError = 'Cette structure est actuellement indisponible.';
        $clinic = null;
    }
}

$hasRequestedClinic = $clinicSlug !== '';
$hasValidClinic = is_array($clinic);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/marki-app-192.png" type="image/png">
    <title>Connexion — MARKI</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20260802-login2">
    <link rel="stylesheet" href="assets/css/password-toggle.css?v=20260802-login1">
</head>
<body
    class="auth-page"
    data-clinic-requested="<?= $hasRequestedClinic ? '1' : '0' ?>"
    data-clinic-valid="<?= $hasValidClinic ? '1' : '0' ?>"
>
    <main class="auth-card">
        <?php if ($hasValidClinic): ?>
            <a
                class="auth-shortcut-button"
                href="download-shortcut.php?type=clinic&amp;clinic=<?= rawurlencode($clinicSlug) ?>"
                aria-label="Télécharger le raccourci MARKI de cette structure"
                title="Télécharger le raccourci bureau"
                download
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3v11m0 0 4-4m-4 4-4-4"></path>
                    <path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"></path>
                </svg>
            </a>
        <?php endif; ?>
        <div class="auth-brand">
            <span class="auth-brand__mark">M</span>
            <div>
                <h1>MARKI</h1>
                <p>Gestion des patients</p>
            </div>
        </div>

        <?php if ($hasValidClinic): ?>
            <header class="auth-card__header">
                <h2>Connexion</h2>
                <p>Accédez à votre espace professionnel.</p>
            </header>

            <div class="auth-structure" aria-label="Structure sélectionnée">
                <span class="auth-structure__label">Espace de connexion</span>
                <strong><?= htmlspecialchars((string) $clinic['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <?php if ($activationCompleted): ?>
                <div class="auth-message is-success auth-message--visible" role="status">
                    Votre espace MARKI a été créé. Connectez-vous avec le compte que vous venez de choisir.
                </div>
            <?php endif; ?>

            <form id="login-form" class="auth-form" novalidate>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($public['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

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

            <div class="auth-links">
                <a class="auth-link" href="forgot-password.php?clinic=<?= rawurlencode($clinicSlug) ?>">
                    Mot de passe oublié ?
                </a>
                <a class="auth-link auth-link--secondary" href="login.php?change=1">
                    Utiliser une autre structure
                </a>
            </div>
        <?php else: ?>
            <header class="auth-card__header">
                <h2>Retrouver votre structure</h2>
                <p>Entrez le code fourni par votre cabinet ou votre clinique.</p>
            </header>

            <?php if ($clinicLookupError !== ''): ?>
                <div class="auth-message is-error auth-message--visible" role="alert">
                    <?= htmlspecialchars($clinicLookupError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form id="clinic-lookup-form" class="auth-form" novalidate>
                <label>
                    <span>Code de la structure</span>
                    <input
                        type="text"
                        name="clinic_code"
                        value="<?= htmlspecialchars($clinicSlug, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ex. clinique-el-amal"
                        autocomplete="organization"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                    >
                    <small>
                        Ce code figure dans le lien de connexion transmis par votre structure.
                    </small>
                </label>

                <div id="auth-message" class="auth-message" role="alert" aria-live="polite"></div>

                <button type="submit" class="auth-button">Continuer</button>
            </form>
        <?php endif; ?>
    </main>

    <script src="assets/js/login.js?v=20260802-login2" defer></script>
    <script src="assets/js/password-toggle.js?v=20260802-login1" defer></script>
</body>
</html>
