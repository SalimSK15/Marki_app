# Guide du collègue — refonte visuelle MARKI

## Objectif

Changer uniquement l'apparence de MARKI sans toucher à la logique, aux permissions, aux données ou aux API.

## Branche Git

```bash
git checkout -b design/refonte-v1
```

## Fichiers autorisés

Priorité absolue :

```text
public/assets/design-system/
public/assets/icons/
```

Modification prudente, seulement pour ajouter des classes ou déplacer un bloc visuel :

```text
public/pages/
public/index.php
public/login.php
public/registration/index.php
public/registration/status.php
public/platform-invitations.php
```

## Fichiers interdits

```text
app/
public/api/
public/registration/api/
public/assets/js/
database/
.env
```

## Méthode recommandée

1. Modifier d'abord `tokens.css` pour les couleurs, les tailles, les espacements et les rayons.
2. Modifier `components.css` pour les boutons, cartes, tableaux, champs, badges et modales.
3. Modifier `layout.css` pour l'en-tête, le menu et la structure générale.
4. Modifier `pages.css` uniquement pour un écran précis.
5. Vérifier `responsive.css` sur ordinateur 14 pouces, grand écran, tablette et téléphone.

## Identifiants à ne jamais renommer

- `id="..."`
- `name="..."`
- `data-page="..."`
- tous les attributs `data-*`
- les valeurs `method` et `action`
- les champs `hidden`

## Tests visuels obligatoires

- 1920 × 1080
- 1366 × 768
- 1280 × 800
- 1024 × 768
- 768 × 1024
- 390 × 844

Tester au minimum : Liste du jour, fiche patient, Mes Patients, Toutes les listes, Paramètres, QR, Équipe, connexion et page publique QR.
