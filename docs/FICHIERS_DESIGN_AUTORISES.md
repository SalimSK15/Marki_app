# Fichiers design autorisés

## Modification libre

```text
public/assets/design-system/marki-theme.css
public/assets/design-system/tokens.css
public/assets/design-system/foundations.css
public/assets/design-system/layout.css
public/assets/design-system/components.css
public/assets/design-system/pages.css
public/assets/design-system/responsive.css
public/assets/icons/
```

## Modification limitée

Les modèles HTML/PHP peuvent recevoir de nouvelles classes CSS, mais leurs identifiants et attributs fonctionnels doivent rester identiques.

```text
public/index.php
public/pages/dashboard.html
public/pages/patients.html
public/pages/lists.html
public/pages/settings.php
public/login.php
public/registration/index.php
public/registration/status.php
public/platform-invitations.php
```

## Ne pas modifier

```text
app/
public/api/
public/registration/api/
public/assets/js/
database/
.env
```
