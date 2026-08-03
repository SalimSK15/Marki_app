# MARKI Design System

Ce dossier est la couche visuelle officielle de MARKI. Il est chargé après les anciens fichiers CSS afin de permettre une refonte progressive sans toucher au PHP, aux API ou au JavaScript métier.

## Ordre des fichiers

1. `tokens.css` : couleurs, typographie, espacements, rayons, ombres.
2. `foundations.css` : apparence générale du texte et des contrôles.
3. `layout.css` : en-tête, menu, zones principales.
4. `components.css` : boutons, cartes, champs, tableaux, modales et badges.
5. `pages.css` : écrans particuliers.
6. `responsive.css` : adaptations ordinateur, tablette et téléphone.
7. `marki-theme.css` : point d'entrée qui importe tous les fichiers précédents.

## Règle de travail

Pour une modification d'apparence, commencer dans ce dossier. Ne pas changer les identifiants HTML, les attributs `data-*`, les noms des champs ou le JavaScript.
