<?php

declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$context = require __DIR__ . '/../app/web_bootstrap.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$capabilities = $context['capabilities'];
$doctor = $context['doctor'];
$doctors = $context['doctors'];
$basePath = rtrim((string) $context['config']['app']['base_path'], '/');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/marki-app-192.png" type="image/png">
    <meta name="csrf-token" content="<?= e($context['csrf_token']) ?>">
    <meta name="marki-base-path" content="<?= e($basePath) ?>">
    <meta name="marki-timezone" content="<?= e($context['timezone']) ?>">
    <script nonce="<?= e($context['csp_nonce']) ?>">
        window.MARKI_CONTEXT = <?= json_encode([
            'capabilities' => $capabilities,
            'role_label' => $context['role_label'],
            'user_id' => (int) $context['user_id'],
            'doctor_id' => (int) $context['doctor_id'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <title>MARKI — Gestion des patients</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/styles.css?v=20260829-brand1">
    <link rel="stylesheet" href="assets/css/v1-tabs.css?v=20260829-fix6">
    <link rel="stylesheet" href="assets/css/session-ui.css?v=20260829-fix6">
    <link rel="stylesheet" href="assets/css/password-toggle.css?v=20260829-fix6">
    <link rel="stylesheet" href="assets/css/public-registration-admin.css?v=20260829-fix6">
    <link rel="stylesheet" href="assets/css/desktop-density.css?v=20260829-fix6">

    <script src="assets/js/auth-client.js?v=20260829-fix6" defer></script>
    <script src="assets/js/phone-input.js?v=20260829-fix6" defer></script>
    <script src="assets/js/password-toggle.js?v=20260829-fix6" defer></script>
    <script src="assets/js/algeria-locations.js?v=20260829-fix6" defer></script>
    <script src="assets/js/app.js?v=20260829-fix6" defer></script>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
        integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
        defer
    ></script>
    <script src="assets/js/v1-tabs.js?v=20260829-fix6" defer></script>
    <script src="assets/js/public-registration-admin.js?v=20260829-fix6" defer></script>
    <script src="assets/js/team.js?v=20260829-fix6" defer></script>
    <script src="assets/js/header.js?v=20260829-fix6" defer></script>
    <script src="assets/js/marki-motion.js?v=20260829-fix6" defer></script>
    <link rel="stylesheet" href="assets/design-system/marki-theme.css?v=20260829-fix6">
</head>

<body>
    <?php require __DIR__ . '/../app/partials/icons_sprite.php'; ?>

    <div class="app">
        <header class="header" data-timezone="<?= e($context['timezone']) ?>">
            <div class="header__profile">
                <div class="avatar">
                    <img src="assets/icons/avatar_docteur01.svg" alt="">
                </div>

                <div class="profile-info">
                    <h1 id="header-doctor-name"><?= e($doctor['display_name']) ?></h1>
                    <p id="header-doctor-specialty"><?= e($doctor['specialty'] ?: 'Médecin') ?></p>

                    <?php if (count($doctors) > 1): ?>
                        <label class="header-doctor-switcher" for="header-doctor-select">
                            <span class="sr-only">Changer de médecin</span>
                            <select id="header-doctor-select">
                                <?php foreach ($doctors as $availableDoctor): ?>
                                    <option
                                        value="<?= (int) $availableDoctor['id'] ?>"
                                        <?= (int) $availableDoctor['id'] === (int) $context['doctor_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($availableDoctor['display_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="header__right">
                <div class="header__datetime" aria-live="polite">
                    <p class="date" id="header-current-date">—</p>
                    <p class="time" id="header-current-time">—</p>
                </div>

                <details class="account-menu">
                    <summary>
                        <span class="account-menu__name"><?= e($context['user']['full_name']) ?></span>
                        <span class="account-menu__role"><?= e($context['role_label']) ?></span>
                    </summary>
                    <div class="account-menu__panel">
                        <p><?= e($context['clinic']['name']) ?></p>
                        <a href="change-password.php">Changer le mot de passe</a>
                        <form action="api/auth_logout.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($context['csrf_token']) ?>">
                            <button type="submit">Se déconnecter</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <div class="layout">
            <nav class="sidebar" id="app-sidebar" aria-label="Navigation principale">
                <div class="sidebar__inner">
                    <ul class="sidebar__menu">
                        <?php if ($capabilities['queue.view'] ?? false): ?>
                            <li class="sidebar__item active" data-page="dashboard" tabindex="0" role="button" title="Liste du jour">
                                <i class="icon">
                                    <svg class="mk-icon mk-icon--lg" aria-hidden="true"><use href="#mk-queue"></use></svg>
                                </i>
                                <span>Liste du jour</span>
                            </li>
                        <?php endif; ?>

                        <?php if ($capabilities['patients.view'] ?? false): ?>
                            <li class="sidebar__item" data-page="patients" tabindex="0" role="button" title="Mes Patients">
                                <i class="icon">
                                    <svg class="mk-icon mk-icon--lg" aria-hidden="true"><use href="#mk-patients"></use></svg>
                                </i>
                                <span>Mes Patients</span>
                            </li>
                        <?php endif; ?>

                        <?php if ($capabilities['lists.view'] ?? false): ?>
                            <li class="sidebar__item" data-page="lists" tabindex="0" role="button" title="Toutes les listes">
                                <i class="icon">
                                    <svg class="mk-icon mk-icon--lg" aria-hidden="true"><use href="#mk-archive"></use></svg>
                                </i>
                                <span>Toutes les listes</span>
                            </li>
                        <?php endif; ?>

                        <?php if ($capabilities['settings.view'] ?? false): ?>
                            <li class="sidebar__item" data-page="settings" tabindex="0" role="button" title="Paramètres">
                                <i class="icon">
                                    <svg class="mk-icon mk-icon--lg" aria-hidden="true"><use href="#mk-settings"></use></svg>
                                </i>
                                <span>Paramètres</span>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <div class="sidebar__footer">
                        <button
                            type="button"
                            class="sidebar__toggle"
                            id="sidebarToggleBtn"
                            aria-label="Réduire ou agrandir la barre latérale"
                            title="Réduire le menu"
                        >
                            <span class="sidebar__toggle-icon">
                                <svg class="mk-icon" aria-hidden="true"><use href="#mk-chevron-left"></use></svg>
                            </span>
                            <span class="sidebar__toggle-text">Réduire le menu</span>
                        </button>
                    </div>
                </div>
            </nav>

            <main class="main-content" id="main-content">
                <!-- Les pages internes sont chargées ici. -->
            </main>
        </div>
    </div>
    <div
        class="marki-toast-container"
        id="marki-toast-container"
        aria-live="polite"
        aria-atomic="true"
    ></div>

</body>

</html>
