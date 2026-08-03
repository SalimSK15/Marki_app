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
    <title>MARKI — Suivi de votre inscription</title>
    <link rel="stylesheet" href="assets/registration.css?v=20260803-final2">
    <script src="assets/status.js?v=20260803-final2" defer></script>
</head>
<body>
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
            <span class="public-registration-brand__secure">Suivi privé</span>
        </header>

        <section class="public-registration-card public-status-card" aria-labelledby="status-title">
            <div id="status-loading" class="public-registration-loading" role="status">
                <span class="public-registration-spinner" aria-hidden="true"></span>
                Chargement de votre inscription…
            </div>

            <div id="status-error" class="public-registration-state" hidden>
                <div class="public-registration-state__icon" aria-hidden="true">!</div>
                <h1 id="status-error-title">Suivi indisponible</h1>
                <p id="status-error-message"></p>
            </div>

            <div id="status-content" hidden>
                <div class="public-status-header">
                    <p class="public-registration-eyebrow">Votre inscription</p>
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
                        <small id="status-ahead-note">La position se met à jour automatiquement.</small>
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
                        Actualiser
                    </button>
                    <button type="button" id="status-cancel" class="public-registration-button public-registration-button--danger" hidden>
                        Annuler mon inscription
                    </button>
                </div>

                <div id="status-message" class="public-registration-message" role="status" aria-live="polite"></div>
            </div>
        </section>

        <footer class="public-registration-footer">
            <span>Mise à jour automatique toutes les 5 secondes.</span>
        </footer>
    </main>

    <div id="status-cancel-modal" class="public-registration-modal" hidden aria-hidden="true">
        <button type="button" class="public-registration-modal__backdrop" data-close-status-modal aria-label="Fermer"></button>
        <div class="public-registration-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="status-cancel-title">
            <h2 id="status-cancel-title">Annuler votre inscription à la liste d’attente ?</h2>
            <p>Votre inscription sera annulée et vous quitterez la liste d’attente. Pour revenir, vous devrez vous inscrire de nouveau avec le QR code ou contacter le secrétariat.</p>
            <div class="public-registration-modal__actions">
                <button type="button" class="public-registration-button public-registration-button--secondary" data-close-status-modal>Retour</button>
                <button type="button" class="public-registration-button public-registration-button--danger" id="status-confirm-cancel">Annuler mon inscription</button>
            </div>
        </div>
    </div>
</body>
</html>
