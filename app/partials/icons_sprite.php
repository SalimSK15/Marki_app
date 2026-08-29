<?php
/**
 * Injecte le jeu d'icones MARKI dans la page.
 *
 * Le sprite est ecrit directement dans le document plutot que
 * reference par <use href="fichier.svg#id"> : aucune requete
 * reseau supplementaire, et l'icone herite de la couleur du
 * texte meme sur les pages chargees dynamiquement.
 *
 * A appeler une seule fois, juste apres l'ouverture de <body>.
 */

declare(strict_types=1);

$markiSpritePath = __DIR__ . '/../../public/assets/icons/marki-sprite.svg';

if (is_readable($markiSpritePath)) {
    $markiSprite = (string) file_get_contents($markiSpritePath);

    // Une classe plutot qu'un style en ligne, pour rester coherent
    // avec le reste de la feuille de style.
    $markiSprite = str_replace(
        'style="display:none"',
        'class="mk-sprite"',
        $markiSprite
    );

    echo $markiSprite;
}
