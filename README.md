# Prognoz

Pronostics sociaux **100 % gratuits** (foot, basket, tennis) — pas de bookmaker, pas d’argent réel.  
Points, communautés, chat chiffré, saisons.

| | |
|---|---|
| **Site** | [prognoz.fr](https://www.prognoz.fr) |
| **Version** | **1.0.1** (stable / petite beta) |
| **Document root** | `public/` uniquement (`app/` jamais exposé) |

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Structure du projet](#structure-du-projet)
3. [Installation rapide](#installation-rapide)
4. [Configuration `.env`](#configuration-env)
5. [URLs](#urls)
6. [Exploitation (cron, sync, reportés)](#exploitation)
7. [Administration](#administration)
8. [Déploiement](#déploiement)
9. [Docs complémentaires](#docs-complémentaires)

---

## Fonctionnalités

| Domaine | Contenu |
|---------|---------|
| Compte | Inscription, connexion, reset MDP e-mail, photo de profil |
| Matchs | Import The Odds API — 1/N/2, score exact, buteur (foot) |
| Ticket | Brouillon local + validation batch (mobile OK) |
| Points | Barème 1 / 2 / 3 pts, séries, historique |
| Saisons | Reset classement tous les 14 jours, badges podium |
| Social | Communautés, invitations, chat AES-256-GCM, amis |
| Notifs | Web Push (chat, gains, saison, rappels matchs) |
| Légal | CGU, confidentialité, comment ça marche |

---

## Structure du projet

```
app/                 Logique PHP (hors web)
public/              Document root Apache/Nginx
  account/ auth/ api/ admin/ legal/ uploads/
var/cache/           Cache runtime
db/migrations/       Migrations SQL (001 → 007)
schema.sql           Schéma complet (install neuve)
tools/               CLI (sync, vapid, diagnose…)
docs/                Guides détaillés
lang/                FR / EN
```

Ne pas committer : `.env`, `var/cache/*`, uploads utilisateurs (voir `.gitignore`).

---

## Installation rapide

1. Pointer le vhost sur **`…/public`**
2. `cp .env.example .env` → renseigner BDD, clés, `APP_URL`
3. Importer `schema.sql` **ou** les fichiers de `db/migrations/` dans l’ordre
4. Extensions PHP : `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`
5. Droits d’écriture : `public/uploads/` et `var/`
6. `composer install` (Web Push)
7. Prod : `APP_BETA=0`

### Migrations (base déjà existante)

```bash
mysql -u root -p pronosocial < db/migrations/001_match_probabilities.sql
mysql -u root -p pronosocial < db/migrations/002_encryption.sql
mysql -u root -p pronosocial < db/migrations/003_features.sql
mysql -u root -p pronosocial < db/migrations/003_sport_column.sql
mysql -u root -p pronosocial < db/migrations/004_profile_changes.sql
mysql -u root -p pronosocial < db/migrations/005_seasons.sql
mysql -u root -p pronosocial < db/migrations/006_push_subscriptions.sql
mysql -u root -p pronosocial < db/migrations/007_avatars.sql
```

(Beaucoup de colonnes sont aussi créées au boot si possible.)

### Web Push

```bash
composer install --no-interaction
php tools/generate_vapid.php   # coller VAPID_* dans .env
```

Puis sur le site : **Paramètres → Autoriser → Tester**.

---

## Configuration `.env`

| Variable | Rôle |
|----------|------|
| `APP_URL` | URL canonique (`https://www.prognoz.fr`) |
| `DB_*` | Connexion MySQL |
| `ODDS_API_KEY` | The Odds API |
| `APP_ENCRYPTION_KEY` | Clé 32 octets base64 (chat / communautés) |
| `CRON_SECRET` | Protège `/api/sync` |
| `SYNC_ADMIN_USER_IDS` | IDs autorisés à forcer la sync (sinon e-mail contact) |
| `VAPID_*` | Web Push |
| `APP_CONTACT_EMAIL` | Contact + sync admin |
| `MAIL_*` / SMTP | Reset MDP (requis en prod) |
| `APP_BETA` | `0` = pas de bandeau bêta |
| `ADMIN_*` | Panel admin (slug, user, hash) — voir admin README |

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"   # APP_ENCRYPTION_KEY
```

Détail des variables : [`.env.example`](.env.example).

---

## URLs

URLs **sans `.php`** (fichiers PHP inchangés ; 301 GET depuis les anciennes).

| Canonique | Ancienne |
|-----------|----------|
| `/` | `/index.php` |
| `/auth/login` | `/auth/login.php` |
| `/account/dashboard` | `/account/dashboard.php` |
| `/api/validate_ticket` | `/api/validate_ticket.php` |
| `/legal/cgu` | `/legal/cgu.php` |

Apache : `mod_rewrite` + [`public/.htaccess`](public/.htaccess) (`AllowOverride All`).  
Stubs racine (`login.php`, `dashboard.php`, …) : redirections de compatibilité.

---

## Exploitation

### Cron (recommandé)

```bash
# HTTP — toutes les 15–30 min
curl -s "https://www.prognoz.fr/api/sync?cron=1&key=VOTRE_CRON_SECRET"

# ou CLI (préférable sur le VPS)
*/15 * * * * cd /var/www/prognoz && php tools/resolve_results.php >> var/log/resolve.log 2>&1
```

Sans cron, le site déclenche quand même une résolution légère (`/api/sync?mode=light`, throttlée).

### Modes `/api/sync`

| Query | Auth | Effet |
|-------|------|--------|
| `?cron=1&key=…` | Clé cron | Scores, points, cache |
| `?mode=light` | Session | Résolution légère |
| `?mode=odds&force=1&key=…` | Cron ou admin | Rafraîchir les cotes |
| `?force=1&key=…` | Cron ou admin | Import matchs (souvent `queued`) |

Import forcé : `&wait=1` (synchrone debug), `&refresh=1` (cache API).  
Sans curl : compte admin → **Paramètres → Importer les matchs**.

### Reportés automatiques

Après `RESULT_MAX_WAIT_DAYS` (4 j) sans score API → match **reporté**, joueurs « Match reporté » (0 pt), e-mail admin.  
Resaisie possible : **Admin → Reportés**.

### Diagnostic

```bash
php tools/diagnose_results.php
```

Quota API, erreurs HTTP, pronos en attente — premier réflexe si les points ne tombent pas.  
Quota aussi dans **Admin → Sync API**.

Cotes absentes sur certaines ligues : [`docs/odds-missing-leagues.md`](docs/odds-missing-leagues.md).

---

## Administration

| Accès | Doc |
|-------|-----|
| Panel web `/admin/?s=SLUG` + terminal SSH | [`public/admin/README.md`](public/admin/README.md) |
| Console Python (local) | [`tools/admin_console/README.md`](tools/admin_console/README.md) |

```bash
php tools/generate_admin_credentials.php
# coller ADMIN_PANEL_SLUG, ADMIN_USERNAME, ADMIN_PASSWORD_HASH dans .env
```

---

## Déploiement

### Production (checklist)

1. Code sur le serveur + DocumentRoot = `public/`
2. `composer install --no-dev`
3. `.env` prod (BDD, `APP_URL`, clés, SMTP, VAPID, `ADMIN_*`, `APP_BETA=0`)
4. Droits `public/uploads` + `var/`
5. Migrations si besoin
6. Cron 15–30 min
7. Tests : prono → résultat → push ; message communauté → push

### Lab / serveur de test

Guide pas à pas (Apache, Nginx, droits, BDD lab) :  
**[`docs/lab-setup.md`](docs/lab-setup.md)**

| | Lab | Prod |
|---|-----|------|
| `APP_BETA` | `1` | `0` |
| SMTP | Optionnel | Requis |
| Cron | Optionnel | Fortement recommandé |
| HTTPS | Facultatif en LAN | Obligatoire |

Mise à jour typique :

```bash
rsync -avz --exclude '.env' --exclude 'vendor/' --exclude 'var/cache/' \
  --exclude 'public/uploads/avatars/*' \
  ./ user@SERVEUR:/var/www/prognoz/
# puis sur le serveur : composer install --no-dev
```

Ou `git pull` depuis ton dépôt Gitea / GitHub.

---

## Docs complémentaires

| Fichier | Contenu |
|---------|---------|
| [`docs/lab-setup.md`](docs/lab-setup.md) | Install lab détaillée |
| [`docs/odds-missing-leagues.md`](docs/odds-missing-leagues.md) | Diagnostic cotes manquantes |
| [`public/admin/README.md`](public/admin/README.md) | Panel admin |
| [`sql/README.md`](sql/README.md) | Scripts SQL ponctuels |
| [`.env.example`](.env.example) | Modèle de config |
