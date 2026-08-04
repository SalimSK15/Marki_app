# MARKI — sécurité de production

## Ce qui a été renforcé

- CSP avec nonce pour bloquer les scripts injectés.
- HTTPS forcé, HSTS, anti-clickjacking et politique de permissions.
- Validation stricte du domaine afin de bloquer les attaques `Host`.
- Cookies de session `Secure`, `HttpOnly`, `SameSite=Strict` et mode strict PHP.
- Limitation en base des connexions, réinitialisations et accès administrateur.
- Temps de réponse rapproché pour les comptes inexistants afin de réduire l’énumération.
- Taille maximale des requêtes et blocage de `TRACE`/`TRACK`.
- Erreurs techniques masquées en production.
- Protection des dossiers privés pour Apache et exemple Nginx.
- Liens absolus construits depuis l’origine configurée, jamais depuis un domaine fourni par le visiteur.

## Ordre de déploiement obligatoire

1. Faire pointer la racine du domaine sur `Partie_medecin/public`.
2. Copier `.env.production.example` vers `.env` hors de la racine publique.
3. Remplacer le domaine, les accès MySQL et tous les `CHANGE_ME`.
4. Générer deux secrets différents avec `php tools/scripts/generate_marki_secrets.php`.
5. Exécuter `database/migrations/2026_08_03_security_hardening.sql` avec le compte de migration.
6. Ne jamais importer les fichiers de `database/seeds` en production.
7. Activer un certificat TLS valide puis tester la redirection HTTPS.
8. Vérifier que `.env`, `/app`, `/database`, `/storage` et `/tools` retournent 403/404.
9. Créer les vrais comptes administrateurs et supprimer tout compte de démonstration.
10. Tester une restauration de sauvegarde avant l’ouverture aux cabinets.

## Valeurs importantes

- `MARKI_ALLOWED_HOSTS` : uniquement le ou les domaines exacts de MARKI.
- `MARKI_TRUST_PROXY_HEADERS` : `true` seulement derrière un proxy/CDN configuré et fiable.
- `MARKI_PLATFORM_ALLOWED_IPS` : option très forte si les administrateurs ont des IP fixes.
- `MARKI_APP_DEBUG=false` : obligatoire en production.
- `MARKI_PASSWORD_MIN_LENGTH=12` : minimum recommandé.

## Hébergement et données patients

- Sauvegardes quotidiennes chiffrées avec rétention et restauration testée.
- Chiffrement des disques et des sauvegardes chez l’hébergeur.
- Accès SFTP/SSH individuel, jamais de mot de passe partagé.
- Journaux d’accès protégés et rétention limitée; éviter de journaliser les URL contenant des jetons.
- Mise à jour régulière de PHP, MySQL/MariaDB et du système.
- WAF et protection anti-DDoS devant l’application.
- Procédure d’incident et de révocation des sessions/secrets.

## Vérifications après déploiement

```bash
curl -I https://app.example.dz/login.php
curl -I https://app.example.dz/.env
curl -I https://app.example.dz/../app/config.php
curl -X TRACE -I https://app.example.dz/
```

La première réponse doit contenir CSP, HSTS, `X-Frame-Options: DENY` et `X-Content-Type-Options: nosniff`. Les ressources privées doivent répondre 403 ou 404.
