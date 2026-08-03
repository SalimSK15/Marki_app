<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$config = require $root . '/app/config.php';
require_once $root . '/app/db.php';
require_once $root . '/app/auth/Auth.php';

fwrite(STDOUT, "MARKI - Diagnostic contexte et permissions\n");
fwrite(STDOUT, str_repeat('=', 52) . "\n");

try {
    $pdo = db();
    $users = $pdo->query(
        "SELECT id, email, full_name
         FROM users
         WHERE status = 'active'
         ORDER BY id"
    )->fetchAll();

    if ($users === []) {
        throw new RuntimeException('Aucun utilisateur actif.');
    }

    Auth::start($config);

    foreach ($users as $user) {
        $_SESSION = [
            'user_id' => (int) $user['id'],
            'last_activity_at' => time(),
            'csrf_token' => bin2hex(random_bytes(32)),
        ];

        $context = Auth::context($config, true);
        $roles = implode(',', $context['roles']);
        $team = !empty($context['capabilities']['team.manage']) ? 'oui' : 'non';
        $qr = !empty($context['capabilities']['settings.manage_doctor']) ? 'oui' : 'non';
        $settings = !empty($context['capabilities']['settings.view']) ? 'oui' : 'non';

        fwrite(
            STDOUT,
            sprintf(
                "%-28s roles=%-24s parametres=%-3s qr=%-3s equipe=%-3s\n",
                (string) $user['email'],
                $roles,
                $settings,
                $qr,
                $team
            )
        );
    }

    fwrite(STDOUT, "\nCONTEXTE ET PERMISSIONS : OK\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "\nDIAGNOSTIC ECHOUE\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
