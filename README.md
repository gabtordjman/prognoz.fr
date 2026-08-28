# Prognoz

Site de pronostics entre potes (foot, basket, tennis).  
Gratuit, sans argent réel : on joue pour les points et le classement.

| | |
|---|---|
| Site | [prognoz.fr](https://www.prognoz.fr) |
| Version | **1.2.0** |
| Doc détaillée | [Wiki Gitea](http://192.168.1.65:3000/tordjman/prognoz/wiki) (sources dans `wiki/`) |

Le serveur web ne doit servir que le dossier **`public/`**. Le reste (`app/`, `.env`, etc.) reste hors web.

---

## Contenu

- Compte, photo, bio, sport favori
- Matchs (The Odds API) : 1/N/2, score exact, buteur
- Ticket de pronos + points / séries / saisons
- Communautés, amis, chat chiffré
- Événements site (×points, thème, bannière)
- Série 1x2 : ×1.5 / ×2 / ×2.5 selon la longueur
- Annonces admin (micro + pastille)
- Notifications push
- Admin (scores, sync, événements, annonces…)

---

## Installer en local (XAMPP / lab)

1. Vhost ou alias pointé sur `public/`
2. Copier `.env.example` → `.env` (BDD, `APP_URL`, clés)
3. Importer `schema.sql` (install neuve) **ou** les fichiers de `db/migrations/` dans l’ordre
4. PHP : `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`
5. Dossiers en écriture : `public/uploads/`, `var/`
6. `composer install` (push)
7. En prod : `APP_BETA=0`

Générer la clé de chiffrement :

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

Variables : voir [`.env.example`](.env.example).  
Pas à pas lab : [`docs/lab-setup.md`](docs/lab-setup.md) ou le wiki **Installation**.

---

## Cron & sync

Toutes les 15–30 min (prod) :

```bash
curl -s "https://www.prognoz.fr/api/sync?cron=1&key=VOTRE_CRON_SECRET"
# ou
*/15 * * * * cd /var/www/prognoz && php tools/resolve_results.php >> var/log/resolve.log 2>&1
```

Sans cron, une sync légère tourne quand même côté site (throttlée).

Détail des modes : wiki **Exploitation**.

---

## Admin

```bash
php tools/generate_admin_credentials.php
# coller ADMIN_PANEL_SLUG, ADMIN_USERNAME, ADMIN_PASSWORD_HASH dans .env
```

Accès : `/admin/?s=SLUG` — doc dans [`public/admin/README.md`](public/admin/README.md).

---

## Déployer

1. Code sur le serveur, DocumentRoot = `public/`
2. `composer install --no-dev`
3. `.env` prod à jour
4. Migrations si besoin (`008_site_events.sql`, etc.)
5. Droits `uploads/` + `var/`
6. Cron actif
7. Test rapide : un prono → un score → une notif

```bash
rsync -avz --exclude '.env' --exclude 'vendor/' --exclude 'var/cache/' \
  --exclude 'public/uploads/avatars/*' \
  ./ user@SERVEUR:/var/www/prognoz/
```

Ou `git pull` depuis Gitea.

---

## Arborescence utile

```
app/            PHP métier
public/         Racine web
db/migrations/  SQL incrémental
wiki/           Pages pour le Wiki Gitea
docs/           Notes lab / API
lang/           FR / EN
tools/          CLI (sync, vapid, diagnose…)
db/migrations/  SQL (jusqu’à 009_site_announcements)
```

Ne pas versionner : `.env`, caches, uploads utilisateurs.

---

## Wiki

Les fichiers de `wiki/` sont prêts à coller dans le Wiki Gitea du dépôt :

```bash
git clone http://192.168.1.65:3000/tordjman/prognoz.wiki.git
# copier le contenu de wiki/ dedans, commit, push
```

Sur Gitea : dépôt → **Wiki** → activer si besoin, puis pousser ces pages.
