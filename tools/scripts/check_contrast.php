<?php
/**
 * Verifie les contrastes des couples couleur/fond du design system.
 *
 * Seuils WCAG 2.1 :
 *   - texte courant  : 4,5:1
 *   - texte >= 18,7px gras ou 24px : 3:1
 *   - bordures et elements d'interface : 3:1
 *
 * Utilisation :
 *     tools/bin/php tools/scripts/check_contrast.php
 */

declare(strict_types=1);

function mkTokens(string $path): array
{
    $css = (string) file_get_contents($path);
    preg_match_all('/(--mk-[\w-]+):\s*(#[0-9a-fA-F]{3,8})\s*;/', $css, $m, PREG_SET_ORDER);

    $tokens = [];
    foreach ($m as $match) {
        $tokens[$match[1]] = $match[2];
    }

    // Certains jetons sont des alias : --mk-color-waiting pointe vers
    // --mk-color-primary-600. On les resout pour pouvoir les mesurer.
    preg_match_all('/(--mk-[\w-]+):\s*var\((--mk-[\w-]+)\)\s*;/', $css, $aliases, PREG_SET_ORDER);

    for ($pass = 0; $pass < 4; $pass++) {
        foreach ($aliases as $alias) {
            if (!isset($tokens[$alias[1]]) && isset($tokens[$alias[2]])) {
                $tokens[$alias[1]] = $tokens[$alias[2]];
            }
        }
    }

    return $tokens;
}

function mkRgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function mkLuminance(array $rgb): float
{
    $channels = array_map(static function (int $value): float {
        $c = $value / 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, $rgb);

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function mkRatio(string $a, string $b): float
{
    $la = mkLuminance(mkRgb($a));
    $lb = mkLuminance(mkRgb($b));
    $light = max($la, $lb);
    $dark = min($la, $lb);

    return round(($light + 0.05) / ($dark + 0.05), 2);
}

$tokens = mkTokens(__DIR__ . '/../../public/assets/design-system/tokens.css');

// Couples reellement utilises dans l'interface : texte -> fond.
$pairs = [
    ['Texte courant sur page',        '--mk-color-neutral-900', '--mk-color-neutral-50',   4.5],
    ['Texte courant sur carte',       '--mk-color-neutral-900', '--mk-color-neutral-0',    4.5],
    ['Texte secondaire sur carte',    '--mk-color-neutral-500', '--mk-color-neutral-0',    4.5],
    ['Texte secondaire sur page',     '--mk-color-neutral-500', '--mk-color-neutral-50',   4.5],
    ['Corps de texte sur carte',      '--mk-color-neutral-700', '--mk-color-neutral-0',    4.5],
    ['Bouton principal',              '--mk-color-neutral-0',   '--mk-color-primary-600',  4.5],
    ['Bouton secondaire',             '--mk-color-primary-700', '--mk-color-primary-50',   4.5],
    ['Lien / accent de marque',       '--mk-color-primary-700', '--mk-color-neutral-0',    4.5],
    ['Pastille « en attente »',       '--mk-color-waiting-text', '--mk-color-waiting-soft', 4.5],
    ['Pastille « termine »',          '--mk-color-success-text', '--mk-color-success-soft', 4.5],
    ['Pastille « absent »',           '--mk-color-danger-text',  '--mk-color-danger-soft',  4.5],
    ['Pastille « en pause »',         '--mk-color-warning-text', '--mk-color-warning-soft', 4.5],
    ['Pastille « annule »',           '--mk-color-muted-text',   '--mk-color-muted-soft',   4.5],
    ['Pastille « information »',      '--mk-color-info-text',    '--mk-color-info-soft',    4.5],
    ['Nav : onglet actif',            '--mk-color-primary-700', '--mk-color-primary-50',   4.5],
    ['Ligne du patient en cours',     '--mk-color-accent-800',  '--mk-color-accent-50',    4.5],
    ['Bordure de champ sur carte',    '--mk-border-control',    '--mk-color-neutral-0',    3.0],
    ['Bordure active',                '--mk-color-primary-600', '--mk-color-neutral-0',    3.0],
];

$failures = 0;
$widest = 0;
foreach ($pairs as $pair) {
    $widest = max($widest, strlen($pair[0]));
}

echo "\nContraste des couples du design system MARKI\n";
echo str_repeat('-', $widest + 30) . "\n";

foreach ($pairs as [$label, $fg, $bg, $min]) {
    if (!isset($tokens[$fg], $tokens[$bg])) {
        printf("  ?  %-{$widest}s  jeton introuvable\n", $label);
        continue;
    }

    $ratio = mkRatio($tokens[$fg], $tokens[$bg]);
    $ok = $ratio >= $min;

    if (!$ok) {
        $failures++;
    }

    printf(
        "  %s  %-{$widest}s  %5.2f:1  (minimum %.1f)\n",
        $ok ? 'ok  ' : 'ECHEC',
        $label,
        $ratio,
        $min
    );
}

echo str_repeat('-', $widest + 30) . "\n";

if ($failures === 0) {
    echo "Tous les couples respectent le niveau AA.\n\n";
    exit(0);
}

echo "{$failures} couple(s) sous le seuil.\n\n";
exit(1);
