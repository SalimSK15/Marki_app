<?php
/**
 * Construit la feuille de style du design system.
 *
 * Pourquoi : marki-theme.css enchainait neuf @import. Le navigateur
 * doit d'abord telecharger marki-theme.css, y decouvrir les imports,
 * puis les telecharger a leur tour — deux allers-retours en serie
 * avant le premier pixel affiche.
 *
 * Ce script assemble les sources en un seul fichier. Les fichiers
 * sources restent separes et lisibles ; seul le resultat est servi.
 *
 * Utilisation :
 *     tools/bin/php tools/scripts/build_design_system.php
 *
 * A relancer apres toute modification d'un fichier de
 * public/assets/design-system/.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$dir = $root . '/public/assets/design-system';
$output = $dir . '/marki-theme.css';

// L'ordre est significatif : les jetons d'abord, les adaptations
// d'ecran en dernier.
$sources = [
    $root . '/public/assets/fonts/inter.css',
    $dir . '/tokens.css',
    $dir . '/foundations.css',
    $dir . '/layout.css',
    $dir . '/icons.css',
    $dir . '/components.css',
    $dir . '/pages.css',
    $dir . '/motion.css',
    $dir . '/responsive.css',
];

$parts = [
    "/* =========================================================\n" .
    "   MARKI — DESIGN SYSTEM (fichier genere)\n" .
    "   NE PAS MODIFIER DIRECTEMENT.\n" .
    "\n" .
    "   Ce fichier est l'assemblage des sources situees dans\n" .
    "   public/assets/design-system/. Pour changer l'apparence,\n" .
    "   modifier la source concernee — en general tokens.css —\n" .
    "   puis relancer :\n" .
    "\n" .
    "       tools/bin/php tools/scripts/build_design_system.php\n" .
    "\n" .
    "   Genere le " . date('d/m/Y à H:i') . "\n" .
    "   ========================================================= */\n",
];

foreach ($sources as $source) {
    if (!is_readable($source)) {
        fwrite(STDERR, "Source introuvable : {$source}\n");
        exit(1);
    }

    $css = (string) file_get_contents($source);

    // inter.css vit dans assets/fonts/ et pointe ses fichiers en
    // relatif. Une fois assemble dans assets/design-system/, le
    // chemin doit remonter d'un cran.
    if (str_ends_with($source, 'inter.css')) {
        $css = str_replace('url(./inter-', 'url(../fonts/inter-', $css);
    }

    $parts[] = "\n/* ----- source : " . basename($source) . " ----- */\n" . $css;
}

file_put_contents($output, implode("\n", $parts));

$size = round(filesize($output) / 1024, 1);
echo "marki-theme.css genere ({$size} Ko, " . count($sources) . " sources assemblees)\n";
