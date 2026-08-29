# Design system MARKI

Couche visuelle officielle de l'application. Elle porte l'identite
« medical, net, futuriste » : violet profond pour la marque et les
actions, cyan pour ce qui se passe **maintenant**, neutres bleutees
pour les surfaces, ombres teintees pour la profondeur.

---

## Regle unique

**Aucune couleur ne s'ecrit en dur.** Tout passe par un jeton defini
dans `tokens.css`. Cette regle est verifiable :

```bash
grep -rE '#[0-9a-fA-F]{3,6}|rgba?\([0-9]' public/assets/css/*.css
```

Cette commande doit ne rien retourner. Si elle retourne quelque chose,
une couleur a ete ecrite en dur et echappe donc au design system.

---

## Pour changer l'apparence

1. Modifier `tokens.css` — c'est en general le seul fichier a toucher.
2. Regenerer la feuille servie :

```bash
tools/bin/php tools/scripts/build_design_system.php
```

3. Verifier que les contrastes tiennent toujours :

```bash
tools/bin/php tools/scripts/check_contrast.php
```

Changer `--mk-color-primary-600` suffit a repeindre toute
l'application, connexion et inscription publique comprises.

---

## Les fichiers

| Fichier | Role |
|---|---|
| `tokens.css` | Couleurs, typographie, espaces, rayons, ombres, mouvement. **Point de depart de toute modification.** |
| `foundations.css` | Texte, focus clavier, chiffres alignes, champs de saisie. |
| `layout.css` | En-tete, navigation, zones principales. |
| `icons.css` | Regles du jeu d'icones. |
| `components.css` | Boutons, cartes, champs, tableaux, pastilles, modales. |
| `pages.css` | Ajustements propres a un ecran. |
| `motion.css` | Squelettes de chargement, arrivees, surlignages, signal de vie. |
| `responsive.css` | Adaptation ordinateur, tablette, telephone, impression. |
| `marki-theme.css` | **Genere.** Assemblage des precedents. Ne pas modifier. |

L'ordre ci-dessus est significatif : les jetons d'abord, les
adaptations d'ecran en dernier.

---

## Les icones

Un seul jeu : `public/assets/icons/marki-sprite.svg`
(grille 24, trait 1.75, extremites arrondies).

```html
<svg class="mk-icon" aria-hidden="true"><use href="#mk-check"></use></svg>
```

Le sprite est injecte en debut de `<body>` par
`app/partials/icons_sprite.php` : aucune requete reseau, et l'icone
prend automatiquement la couleur du texte qui l'entoure.

Tailles : `mk-icon--xs` a `mk-icon--2xl`.
Teintes : `mk-icon--brand`, `--accent`, `--success`, `--danger`,
`--warning`, `--muted`.
Pastille : `mk-icon-badge` (+ les memes suffixes de teinte).

**Ne jamais** utiliser un caractere typographique (`X`, `✓`, `⊘`)
comme icone : son dessin change d'un appareil a l'autre et casse
l'alignement.

---

## Le mouvement

Le mouvement porte une information, jamais une decoration.

| Classe | Sens |
|---|---|
| `mk-skeleton` | Le contenu arrive : on montre sa forme. |
| `mk-enter` + `mk-stagger` | Premiere arrivee d'une liste. |
| `mk-just-changed` | Cette donnee vient de changer. |
| `mk-live-dot` | C'est en cours en ce moment. |
| `mk-count` | Ce nombre defile vers sa nouvelle valeur. |
| `mk-is-busy` | Cette commande travaille. |

Ces classes sont posees automatiquement par
`public/assets/js/marki-motion.js`, qui **observe** le document sans
toucher a la logique metier. Si ce fichier est retire, l'application
fonctionne toujours — simplement sans animation.

Tout est neutralise si le systeme demande moins d'animations
(`prefers-reduced-motion`).

---

## Accessibilite

- Chaque statut expose trois jetons : la couleur pleine, le fond
  `-soft`, et le texte `-text` a poser dessus. **Toujours utiliser
  `-text` sur un fond `-soft`** : l'ancienne combinaison
  `#10B981` sur `#D1FAE5` ne donnait que 2,1:1.
- `--mk-border-control` est la bordure des champs et boutons : elle
  atteint 3:1, comme l'exige WCAG 1.4.11. `--mk-border-color` reste
  reservee aux separateurs decoratifs.
- Le focus clavier est visible sur tous les composants.
- Les statuts sont doubles d'un point colore : ils restent
  distinguables sans percevoir la teinte.

---

## Ce qu'il ne faut pas faire

- Ecrire une couleur en dur.
- Renommer un identifiant HTML, un attribut `data-*` ou un nom de
  champ : le JavaScript metier s'appuie dessus.
- Modifier `marki-theme.css` a la main : il est ecrase a chaque
  generation.
- Utiliser `rgba()` ou `darken()` sur une variable Sass : elles
  contiennent desormais `var(...)`, que Sass ne sait pas manipuler.
  Passer par les jetons `--mk-alpha-*` ou par `color-mix()`.
