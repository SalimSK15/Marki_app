<?php

declare(strict_types=1);

$public = require __DIR__ . '/../../app/public_bootstrap.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$sessionToken = trim((string) ($_GET['session'] ?? ''));
$basePath = rtrim((string) ($public['config']['app']['base_path'] ?? ''), '/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/icons/marki-app-192.png" type="image/png">
    <meta name="theme-color" content="#6d4aff">
    <meta name="csrf-token" content="<?= e($public['csrf_token']) ?>">
    <meta name="marki-base-path" content="<?= e($basePath) ?>">
    <title>MARKI — Suivi en direct de votre passage</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/design-system/marki-theme.css?v=20260830-ui1">
    <link rel="stylesheet" href="assets/registration.css?v=20260830-ui1">
    <script src="assets/status.js?v=20260830-ui1" defer></script>
</head>
<body>
    <?php require __DIR__ . '/../../app/partials/icons_sprite.php'; ?>

    <main
        class="public-registration-shell"
        id="public-status-app"
        data-session="<?= e($sessionToken) ?>"
    >
        <header class="public-registration-brand">
            <a class="public-registration-brand__logo" href="#" aria-label="MARKI">
                <span class="public-registration-brand__mark">M</span>
                <span>MARKI</span>
            </a>
            <div class="public-registration-brand__secure">
                <span class="public-registration-secure-dot" aria-hidden="true"></span>
                <svg class="mk-icon mk-icon--xs" aria-hidden="true"><use href="#mk-shield-check"></use></svg>
                <span>Suivi privé en direct</span>
            </div>
        </header>

        <section class="public-registration-card public-status-card" aria-labelledby="status-title">
            <div id="status-loading" class="public-registration-loading" role="status">
                <span class="public-registration-spinner" aria-hidden="true"></span>
                <span>Synchronisation de votre inscription en direct…</span>
            </div>

            <div id="status-error" class="public-registration-state" hidden>
                <div class="public-registration-state__icon" aria-hidden="true">
                    <svg class="mk-icon mk-icon--lg"><use href="#mk-alert-circle"></use></svg>
                </div>
                <h1 id="status-error-title">Suivi indisponible</h1>
                <p id="status-error-message"></p>
            </div>

            <div id="status-content" hidden>
                <div class="public-status-header">
                    <p class="public-registration-eyebrow">Votre passage</p>
                    <h1 id="status-title">Bonjour <span id="status-patient-name">—</span></h1>
                    <p id="status-doctor-line">—</p>
                </div>

                <div class="public-status-position-grid">
                    <article class="public-status-number-card public-status-number-card--arrival">
                        <span>Votre N° d’arrivée</span>
                        <strong id="status-position">—</strong>
                        <small id="status-created-at">—</small>
                    </article>

                    <article class="public-status-number-card public-status-number-card--ahead">
                        <span>Patients avant votre tour</span>
                        <strong id="status-ahead">—</strong>
                        <small id="status-ahead-note">La position s'actualise en temps réel.</small>
                    </article>
                </div>

                <div class="public-status-grid public-status-grid--single">
                    <article class="public-status-info">
                        <span>État actuel</span>
                        <strong id="status-label">—</strong>
                    </article>
                </div>

                <div class="public-registration-notice" id="status-guidance"></div>

                <div class="public-status-meta">
                    <div>
                        <span>Cabinet</span>
                        <strong id="status-clinic-name">—</strong>
                    </div>
                    <div>
                        <span>Téléphone associé</span>
                        <strong id="status-phone">—</strong>
                    </div>
                    <div>
                        <span>Dernière actualisation</span>
                        <strong id="status-refreshed-at">—</strong>
                    </div>
                </div>

                <div class="public-status-actions">
                    <button type="button" id="status-refresh" class="public-registration-button public-registration-button--secondary">
                        <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-refresh-cw"></use></svg>
                        <span>Actualiser</span>
                    </button>
                    <button type="button" id="status-cancel" class="public-registration-button public-registration-button--danger" hidden>
                        <svg class="mk-icon mk-icon--sm" aria-hidden="true"><use href="#mk-x"></use></svg>
                        <span>Annuler mon inscription</span>
                    </button>
                </div>

                <div id="status-message" class="public-registration-message" role="status" aria-live="polite"></div>
            </div>
        </section>

        <footer class="public-registration-footer">
            <span>Actualisation automatique en temps réel toutes les 5 secondes.</span>
        </footer>
    </main>

    <div id="status-cancel-modal" class="public-registration-modal" hidden aria-hidden="true">
        <button type="button" class="public-registration-modal__backdrop" data-close-status-modal aria-label="Fermer"></button>
        <div class="public-registration-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="status-cancel-title">
            <h2 id="status-cancel-title">Annuler votre inscription à la liste d’attente ?</h2>
            <p>Votre inscription sera annulée et vous quitterez la file. Pour revenir, vous devrez vous réinscrire via le QR code ou contacter le secrétariat.</p>
            <div class="public-registration-modal__actions">
                <button type="button" class="public-registration-button public-registration-button--secondary" data-close-status-modal>Retour</button>
                <button type="button" class="public-registration-button public-registration-button--danger" id="status-confirm-cancel">Confirmer l’annulation</button>
            </div>
        </div>
    </div>
</body>
</html>
