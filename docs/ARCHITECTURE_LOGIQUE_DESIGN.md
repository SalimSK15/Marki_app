# Architecture MARKI — séparation logique et design

## 1. Logique métier — ne pas modifier pour une refonte visuelle

- `app/` : connexion à la base, sessions, permissions, dépôts et services.
- `public/api/` : actions HTTP et réponses JSON.
- `public/registration/api/` : inscription et suivi QR.
- `public/assets/js/` : comportement des écrans, chargement des données, modales et formulaires.
- `database/` : tables, migrations, données de test et vérifications.

Une modification dans ces dossiers peut changer le fonctionnement de MARKI.

## 2. Structure HTML/PHP — modifier avec prudence

- `public/index.php`
- `public/pages/`
- `public/login.php`
- `public/registration/`
- `public/platform-invitations.php`

Le collègue peut ajuster l'ordre visuel et ajouter des classes, mais il doit conserver :

- les attributs `id` ;
- les attributs `name` ;
- les attributs `data-*` ;
- les formulaires, `method` et `action` ;
- les champs cachés et les jetons CSRF ;
- les blocs PHP et les conditions de permissions.

## 3. Design — zone officielle de travail

Le design principal est centralisé dans :

`public/assets/design-system/`

Le point d'entrée est :

`public/assets/design-system/marki-theme.css`

La refonte doit commencer par les fichiers suivants :

- `tokens.css`
- `foundations.css`
- `layout.css`
- `components.css`
- `pages.css`
- `responsive.css`

## 4. Anciennes feuilles de style

Les fichiers de `public/assets/css/` restent la base stable de la V1. Ils ne doivent pas être supprimés pendant la refonte. La nouvelle couche Design System est chargée après eux et sert d'override propre.

Cette organisation permet de modifier l'apparence sans risquer de casser les API ou les événements JavaScript.
