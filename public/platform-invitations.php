<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$config = $public['config'];

require_once __DIR__ . '/../app/platform/StructureInvitationRepository.php';

function platformE(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function platformAbsoluteBaseUrl(array $config): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $basePath = rtrim(
        (string) ($config['app']['base_path'] ?? ''),
        '/'
    );

    return $scheme . '://' . $host . $basePath;
}

$expectedKey = (string) ($config['platform']['setup_key'] ?? '');
$providedKey = trim((string) ($_GET['key'] ?? ''));

if (
    $expectedKey !== ''
    && $providedKey !== ''
    && hash_equals($expectedKey, $providedKey)
) {
    $_SESSION['marki_platform_setup_authorized'] = true;

    header(
        'Location: '
        . rtrim((string) $config['app']['base_path'], '/')
        . '/platform-invitations.php'
    );
    exit;
}

if (empty($_SESSION['marki_platform_setup_authorized'])) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Page introuvable</title></head><body><h1>Page introuvable</h1></body></html>';
    exit;
}

$repository = new StructureInvitationRepository();
$message = '';
$messageType = '';
$generatedLink = '';
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::validateCsrf(
            (string) ($_POST['csrf_token'] ?? '')
        );

        $action = trim((string) ($_POST['action'] ?? 'create'));

        if ($action === 'logout_platform') {
            unset($_SESSION['marki_platform_setup_authorized']);
            header(
                'Location: '
                . rtrim((string) $config['app']['base_path'], '/')
                . '/platform-invitations.php'
            );
            exit;
        }

        if ($action === 'revoke') {
            $repository->revokeInvitation(
                (int) ($_POST['invitation_id'] ?? 0)
            );
            $message = 'Invitation révoquée.';
            $messageType = 'success';
        } else {
            $invitation = $repository->createInvitation(
                $_POST['recipient_label'] ?? null,
                $_POST['recipient_email'] ?? null,
                (int) (
                    $_POST['expiry_hours']
                    ?? $config['platform']['invitation_expiry_hours']
                    ?? 72
                )
            );

            $generatedLink = platformAbsoluteBaseUrl($config)
                . '/activate-structure.php?token='
                . rawurlencode((string) $invitation['token']);

            $message = 'Invitation créée. Copiez ce lien maintenant : le jeton complet n’est pas conservé en clair.';
            $messageType = 'success';
        }
    } catch (StructureActivationValidationException $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
        $fieldErrors = $exception->errors();
    } catch (AuthException $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    } catch (Throwable $exception) {
        $message = (bool) ($config['app']['debug'] ?? false)
            ? $exception->getMessage()
            : 'Impossible de traiter cette demande.';
        $messageType = 'error';
    }
}

$invitations = $repository->listRecent();
$csrfToken = Auth::csrfToken();
$defaultExpiry = (int) (
    $config['platform']['invitation_expiry_hours']
    ?? 72
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitations de structure — MARKI</title>
    <link rel="stylesheet" href="assets/css/auth.css?v=20260731-setup1">
    <link rel="stylesheet" href="assets/css/platform-setup.css?v=20260731-setup1">
</head>
<body class="platform-page">
    <main class="platform-shell">
        <header class="platform-header">
            <div>
                <p class="platform-eyebrow">Administration interne MARKI — V1</p>
                <h1>Invitations de nouvelle structure</h1>
                <p>Générez un lien unique pour le médecin responsable d’un cabinet ou d’une clinique.</p>
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                <input type="hidden" name="action" value="logout_platform">
                <button class="platform-button platform-button--ghost" type="submit">Fermer l’accès interne</button>
            </form>
        </header>

        <?php if ($message !== ''): ?>
            <div class="platform-message is-<?= platformE($messageType) ?>" role="status">
                <?= platformE($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($generatedLink !== ''): ?>
            <section class="platform-card platform-link-card" aria-labelledby="generated-link-title">
                <div>
                    <p class="platform-eyebrow">Lien créé</p>
                    <h2 id="generated-link-title">Invitation à transmettre</h2>
                </div>
                <div class="platform-copy-row">
                    <input id="generated-invitation-link" type="text" readonly value="<?= platformE($generatedLink) ?>">
                    <button type="button" class="platform-button" id="copy-invitation-link">Copier le lien</button>
                </div>
                <p class="platform-help">Toute personne qui possède ce lien peut créer une seule structure et son premier compte médecin administrateur avant son expiration.</p>
            </section>
        <?php endif; ?>

        <div class="platform-grid">
            <section class="platform-card" aria-labelledby="new-invitation-title">
                <p class="platform-eyebrow">Nouvelle invitation</p>
                <h2 id="new-invitation-title">Préparer l’accès du responsable</h2>

                <form method="post" class="platform-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">

                    <label>
                        <span>Nom du destinataire</span>
                        <input type="text" name="recipient_label" value="<?= platformE($_POST['recipient_label'] ?? '') ?>" placeholder="Ex. Dr Samir Haddad">
                    </label>

                    <label>
                        <span>Courriel du destinataire</span>
                        <input type="email" name="recipient_email" value="<?= platformE($_POST['recipient_email'] ?? '') ?>" placeholder="docteur@cabinet.dz">
                        <?php if (isset($fieldErrors['recipient_email'])): ?>
                            <small class="platform-field-error"><?= platformE((string) $fieldErrors['recipient_email']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label>
                        <span>Validité du lien</span>
                        <select name="expiry_hours">
                            <?php foreach ([24, 48, 72, 120, 168] as $hours): ?>
                                <option value="<?= $hours ?>" <?= $hours === $defaultExpiry ? 'selected' : '' ?>>
                                    <?= $hours ?> heures
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit" class="platform-button">Générer l’invitation</button>
                </form>
            </section>

            <section class="platform-card platform-card--explanation" aria-labelledby="logic-title">
                <p class="platform-eyebrow">Logique V1</p>
                <h2 id="logic-title">Ce que fera le médecin invité</h2>
                <ol class="platform-steps">
                    <li>Il ouvre le lien personnel.</li>
                    <li>Il renseigne son cabinet ou sa clinique.</li>
                    <li>Il crée son propre mot de passe.</li>
                    <li>MARKI crée son compte <strong>médecin administrateur</strong>.</li>
                    <li>Il se connecte ensuite et crée ses médecins et secrétaires dans <strong>Équipe et accès</strong>.</li>
                </ol>
            </section>
        </div>

        <section class="platform-card" aria-labelledby="recent-invitations-title">
            <div class="platform-card-header">
                <div>
                    <p class="platform-eyebrow">Suivi minimal</p>
                    <h2 id="recent-invitations-title">Invitations récentes</h2>
                </div>
            </div>

            <div class="platform-table-wrap">
                <table class="platform-table">
                    <thead>
                        <tr>
                            <th>Destinataire</th>
                            <th>Créée</th>
                            <th>Expiration</th>
                            <th>État</th>
                            <th>Structure créée</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($invitations === []): ?>
                            <tr><td colspan="6">Aucune invitation.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invitations as $invitation): ?>
                                <?php
                                $statusLabels = [
                                    'active' => 'Active',
                                    'used' => 'Utilisée',
                                    'expired' => 'Expirée',
                                    'revoked' => 'Révoquée',
                                ];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= platformE($invitation['recipient_label'] ?: 'Non précisé') ?></strong>
                                        <small><?= platformE($invitation['recipient_email'] ?: '') ?></small>
                                    </td>
                                    <td><?= platformE(date('d/m/Y H:i', strtotime((string) $invitation['created_at']))) ?></td>
                                    <td><?= platformE(date('d/m/Y H:i', strtotime((string) $invitation['expires_at']))) ?></td>
                                    <td><span class="platform-status is-<?= platformE($invitation['status']) ?>"><?= platformE($statusLabels[$invitation['status']] ?? $invitation['status']) ?></span></td>
                                    <td>
                                        <?php if ($invitation['clinic_name']): ?>
                                            <strong><?= platformE($invitation['clinic_name']) ?></strong>
                                            <small><?= platformE($invitation['activated_by_name'] ?: '') ?></small>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($invitation['status'] === 'active'): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= platformE($csrfToken) ?>">
                                                <input type="hidden" name="action" value="revoke">
                                                <input type="hidden" name="invitation_id" value="<?= (int) $invitation['id'] ?>">
                                                <button type="submit" class="platform-button platform-button--danger">Révoquer</button>
                                            </form>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        document.getElementById('copy-invitation-link')?.addEventListener('click', async () => {
            const input = document.getElementById('generated-invitation-link');
            if (!input) return;

            try {
                await navigator.clipboard.writeText(input.value);
            } catch (error) {
                input.select();
                document.execCommand('copy');
            }

            const button = document.getElementById('copy-invitation-link');
            if (button) {
                const previous = button.textContent;
                button.textContent = 'Copié';
                window.setTimeout(() => {
                    button.textContent = previous;
                }, 1600);
            }
        });
    </script>
</body>
</html>
