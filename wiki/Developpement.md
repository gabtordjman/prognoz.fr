# Développement

Pour modifier Prognoz sans casser la prod.

---

## Structure

```
app/                 Logique métier (PHP, hors web)
public/              Seul dossier servi par Apache/Nginx
  account/ auth/ api/ admin/ legal/ assets/ uploads/
db/migrations/       SQL versionné
lang/fr.php en.php   Traductions
wiki/                Sources du Wiki Gitea
tools/               Scripts CLI
```

Bootstrap : `app/bootstrap.php` charge config, i18n, helpers, events, etc.

---

## i18n

- Clés du type `dash.bio`, `com.general`
- Toujours ajouter **FR et EN** dans `lang/`
- Affichage : `t('cle')` ou `tn('cle', $n)` pour le pluriel
- Si une clé manque en prod, le site peut afficher la clé brute → **déployer les lang** avec le PHP

Fallbacks critiques récents : voir `i18nCriticalFallbacks()` dans `app/i18n.php` (filet de sécurité, pas un remplacement des fichiers lang).

---

## Front

- CSS principal : `public/assets/css/style.css`
- Thème rétro : `retro.css` (navigateurs anciens / mode rétro)
- JS : `public/assets/js/` (cache-bust via `assetUrl()` = `?v=filemtime`)
- Design : papier / feutre / bois / laiton — éviter les looks « dashboard violet »

---

## Points & événements

- Barème dans la config / `app/scoring.php`
- Multiplicateur d’événement branché au scoring quand un événement **publié** est actif
- Bouclier de série, etc. : même fichier + table événements

---

## Contributions / commits

- Messages courts, en français OK, centrés sur le *pourquoi*
- Ne pas committer `.env`, uploads, `var/cache`
- Tester Mon espace + une page match après un changement UI
- Si tu ajoutes une string visible : lang FR + EN dans le même commit

---

## Environnements

| | Lab | Prod |
|---|-----|------|
| `APP_BETA` | `1` | `0` |
| SMTP / VAPID | optionnel | requis |
| Cron | optionnel | oui |
| HTTPS | LAN OK | obligatoire |
