<?php

declare(strict_types=1);

$context = require __DIR__ . '/../app/web_bootstrap.php';
$config = $context['config'];

if (
    !($config['app']['debug'] ?? false)
    || !($context['capabilities']['team.manage'] ?? false)
) {
    http_response_code(404);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/../app/repositories/SettingsRepository.php';
require_once __DIR__ . '/../app/auth/TeamRepository.php';
require_once __DIR__ . '/../app/public_registration/PublicRegistrationRepository.php';

function diagnosticTest(string $label, callable $test): array
{
    try {
        $value = $test();
        return [
            'label' => $label,
            'ok' => true,
            'message' => is_string($value) ? $value : 'OK',
        ];
    } catch (Throwable $exception) {
        return [
            'label' => $label,
            'ok' => false,
            'message' => get_class($exception) . ': ' . $exception->getMessage(),
        ];
    }
}

$tests = [];
$tests[] = diagnosticTest('Connexion MySQL Web', static function () use ($context): string {
    $row = $context['pdo']->query(
        'SELECT DATABASE() AS db_name, CURRENT_USER() AS mysql_account'
    )->fetch();

    return (string) ($row['db_name'] ?? '')
        . ' / '
        . (string) ($row['mysql_account'] ?? '');
});

$tests[] = diagnosticTest('Contexte utilisateur', static function () use ($context): string {
    return (string) $context['user']['full_name']
        . ' — '
        . implode(', ', $context['roles']);
});

$tests[] = diagnosticTest('Paramètres', static function () use ($context): string {
    $data = (new SettingsRepository())->get(
        (int) $context['clinic_id'],
        (int) $context['doctor_id']
    );

    return (string) $data['clinic']['name']
        . ' / '
        . (string) $data['doctor']['display_name'];
});

$tests[] = diagnosticTest('Équipe et accès', static function () use ($context): string {
    $data = (new TeamRepository())->index(
        (int) $context['clinic_id'],
        (int) $context['user_id']
    );

    return count($data['members']) . ' comptes, '
        . count($data['doctors']) . ' médecins';
});

$tests[] = diagnosticTest('Configuration QR', static function () use ($context): string {
    $data = (new PublicRegistrationRepository())->adminOverview(
        (int) $context['clinic_id'],
        (int) $context['doctor_id'],
        (int) $context['user_id'],
        $context['config'],
        (string) $context['today']
    );

    return !empty($data['link']['id']) ? 'Lien QR disponible' : 'Lien absent';
});

$allOk = !in_array(false, array_column($tests, 'ok'), true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic MARKI</title>
    <style nonce="<?= htmlspecialchars($context['csp_nonce'], ENT_QUOTES, 'UTF-8') ?>">
        body { margin: 0; padding: 32px; font-family: Arial, sans-serif; background: #f7f6fb; color: #19152e; }
        main { max-width: 900px; margin: 0 auto; background: white; border: 1px solid #e7e2f2; border-radius: 20px; padding: 28px; }
        h1 { margin-top: 0; }
        .summary { padding: 14px 16px; border-radius: 12px; font-weight: 700; background: <?= $allOk ? '#e9f9f1' : '#fff0f1' ?>; color: <?= $allOk ? '#087647' : '#b4232f' ?>; }
        .test { margin-top: 12px; padding: 14px 16px; border: 1px solid #ece8f4; border-radius: 12px; }
        .ok { color: #087647; }
        .error { color: #b4232f; }
        code { word-break: break-word; }
    </style>
</head>
<body>
<main>
    <h1>Diagnostic Web MARKI</h1>
    <p class="summary"><?= $allOk ? 'Tous les services Web sont opérationnels.' : 'Au moins un service Web a échoué.' ?></p>
    <?php foreach ($tests as $test): ?>
        <div class="test">
            <strong class="<?= $test['ok'] ? 'ok' : 'error' ?>"><?= $test['ok'] ? 'OK' : 'ERREUR' ?> — <?= htmlspecialchars($test['label'], ENT_QUOTES, 'UTF-8') ?></strong>
            <p><code><?= htmlspecialchars($test['message'], ENT_QUOTES, 'UTF-8') ?></code></p>
        </div>
    <?php endforeach; ?>
</main>
</body>
</html>
