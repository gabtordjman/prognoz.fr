# Prognoz — v1.0 (stable)

Jeu de pronostics social **100 % gratuit** (foot, basket, tennis) : pas de bookmaker,
pas d'argent réel. Cumulez des points, créez des communautés privées et défiez vos potes.

**Version : 1.0.1** — base autonome pour la prod (et future app mobile).

Site : [prognoz.fr](https://www.prognoz.fr)

## Fonctionnalités

- **Comptes** — inscription, connexion, reset mot de passe par e-mail
- **Matchs** — import The Odds API (1/N/2, score exact et buteur sur le foot)
- **Ticket** — brouillon local puis validation batch, mobile-friendly
- **Points & séries** — barème 1 / 2 / 3 pts, badges classement, historique des résultats
- **Saisons** — classements remis à zéro tous les 14 jours, podium + badges Or/Argent/Bronze
- **Communautés** — privées + Générale, invitations, chat chiffré (AES-256-GCM), admin (description, expulsion)
- **Photos de profil** — upload JPEG/PNG/WebP (max 5 Mo), compression serveur (~256 px)
- **Notifications** — Web Push (messages communauté, gains, fin de saison, rappels matchs)
- **Amis** — demandes par pseudo, liste et comparaison de points
- **RGPD** — politique de confidentialité, CGU, suppression de compte

## URLs (v1.0)

Les URLs publiques sont **sans `.php`** (fichiers PHP inchangés sur disque) :

| Canonique | Ancienne (301 GET) |
|-----------|-------------------|
| `/` | `/index.php` |
| `/auth/login` | `/auth/login.php` |
| `/account/dashboard` | `/account/dashboard.php` |
| `/api/validate_ticket` | `/api/validate_ticket.php` |
| `/legal/cgu` | `/legal/cgu.php` |

Apache : `mod_rewrite` + [`public/.htaccess`](public/.htaccess) (`AllowOverride All`).  
Les stubs racine (`/login.php`, `/dashboard.php`, …) redirigent encore vers les bons chemins.

## Installation

1. Document root = dossier `public/` (le dossier `app/` ne doit pas être exposé).
2. Copier `.env.example` → `.env` et renseigner les valeurs.
3. Importer `schema.sql` (base `pronosocial`) **ou** appliquer les migrations (voir ci-dessous).
4. Extensions PHP : `pdo_mysql`, `mbstring`, `openssl`, `gd` (photos de profil), `fileinfo`.
5. Droits d’écriture sur `public/uploads/` et `var/` (voir [Serveur de test / lab](#serveur-de-test--lab)).
6. Prod v1.0 : `APP_BETA=0` (retire bandeau / modale bêta).

### Migration base existante

Si la BDD existe déjà, exécuter les migrations dans l'ordre (dossier **`db/migrations/`** uniquement) :

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

(Les colonnes sont aussi créées automatiquement au boot quand c'est possible.  
Les scripts ponctuels historiques sont dans `sql/` — voir `sql/README.md`.)

### Web Push (notifications)

Sur le serveur (SSH), à la racine du projet :

```bash
composer install --no-interaction
php tools/generate_vapid.php
```

Copier les lignes `VAPID_*` dans `.env`. Puis **Paramètres → Autoriser → Tester** sur le site.

## Configuration `.env` (production)

| Variable | Description |
|----------|-------------|
| `APP_URL` | URL canonique (https://www.prognoz.fr) |
| `ODDS_API_KEY` | Clé The Odds API |
| `APP_ENCRYPTION_KEY` | Clé 32 octets base64 — chat & noms communautés |
| `CRON_SECRET` | Protège `/api/sync` (cron + sync manuelle curl) |
| `SYNC_ADMIN_USER_IDS` | IDs CSV autorisés à forcer la sync depuis Paramètres (sinon e-mail = `APP_CONTACT_EMAIL`) |
| `VAPID_PUBLIC_KEY` | Clé publique Web Push (voir `tools/generate_vapid.php`) |
| `VAPID_PRIVATE_KEY` | Clé privée Web Push |
| `VAPID_SUBJECT` | `mailto:admin@prognoz.fr` |
| `APP_CONTACT_EMAIL` | contact@prognoz.fr |
| `MAIL_FROM` | noreply@prognoz.fr (expéditeur) |
| `MAIL_SMTP_HOST` | Serveur SMTP (obligatoire en prod si `mail()` ne marche pas) |
| `MAIL_SMTP_PORT` | 587 (tls) ou 465 (ssl) |
| `MAIL_SMTP_USER` | Identifiant SMTP (souvent = adresse mail) |
| `MAIL_SMTP_PASS` | Mot de passe de la boîte mail |
| `MAIL_SMTP_SECURE` | `tls`, `ssl` ou `none` |
| `APP_BETA` | **`0` en prod v1.0** (désactive bandeau/modale bêta) |

Générer une clé de chiffrement :

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

## Cron (fortement recommandé en prod)

```bash
# Toutes les 15–30 min — scores, points, cache, cycle de vie des matchs
curl -s "https://www.prognoz.fr/api/sync?cron=1&key=VOTRE_CRON_SECRET"
```

Équivalent en CLI, sans clé ni HTTP (à privilégier dans un `crontab` sur le VPS) :

```bash
*/15 * * * * cd /var/www/prognoz && php tools/resolve_results.php >> var/log/resolve.log 2>&1
```

Sans cron, les pages du site déclenchent la résolution en tâche de fond (appel asynchrone
`/api/sync?mode=light`, throttlé côté serveur). Le cron reste préférable :
les résultats tombent même quand personne n'est connecté.

Un match sans résultat côté API après `RESULT_MAX_WAIT_DAYS` (4 jours) est passé
automatiquement en **reporté** : les joueurs voient « Match reporté » (0 pt),
la file admin se vide. Un e-mail admin résume les cas. Tu peux encore saisir un
score plus tard dans **Admin → Reportés**.

### Diagnostic des pronos bloqués

```bash
php tools/diagnose_results.php
```

Lecture seule (aucun appel API). Affiche le quota API restant, la dernière erreur HTTP
rencontrée, le nombre de pronos en attente et les sports dont les matchs joués n'ont
toujours pas de résultat. C'est le premier réflexe quand des points ne tombent pas.

Le quota API est également visible dans **Admin → Sync API** et Paramètres (compte admin).

Sync complète (import matchs, **en arrière-plan** — ne bloque pas le site) :

```powershell
curl.exe -s "https://www.prognoz.fr/api/sync?force=1&key=VOTRE_CRON_SECRET"
```

Réponse `"queued": true` = OK, rechargez les matchs après 1–2 min. Forcer l'exécution synchrone (debug) : ajoutez `&wait=1`. Rafraîchir le cache API : `&refresh=1`.

La clé `VOTRE_CRON_SECRET` est la valeur exacte de `CRON_SECRET` dans le `.env` **du serveur** (pas un placeholder).

**Sans curl :** connectez-vous avec le compte admin (`APP_CONTACT_EMAIL`) → **Paramètres** → **Importer les matchs**.

### Modes `/api/sync`

| URL | Auth | Effet |
|-----|------|--------|
| `?cron=1&key=...` | Clé cron | Scores, points, cache (léger) |
| `?mode=light` | Session connectée | Résolution résultats + points (throttlé) |
| `?mode=odds&force=1&key=...` | Clé cron **ou** admin | Rafraîchir les cotes |
| `?force=1&key=...` | Clé cron **ou** admin connecté | Import matchs complet |

### Cotes manquantes (certaines ligues)

Si des matchs (ex. Pologne, Eredivisie) n’affichent jamais de %, voir
[`docs/odds-missing-leagues.md`](docs/odds-missing-leagues.md) — **réparable**, pas un oubli de clé API.

## Structure

```
app/              Logique PHP (bootstrap, auth, matches, scoring…) — hors web
public/           Document root
  index.php       Page matchs (URL : /)
  account/        Mon espace, amis, paramètres, profil
  auth/           Login, register, reset MDP
  api/            Chat, sync, notifications push
  admin/          Panel admin web
  uploads/        Photos de profil (inscriptible)
  legal/          CGU, confidentialité, comment ça marche
var/cache/        Cache runtime (APP_CACHE_DIR)
docs/             Notes ops (ex. cotes manquantes)
schema.sql        Schéma complet
db/migrations/    Migrations incrémentales (001 → 007)
sql/              Scripts SQL ponctuels / historiques
vendor/           Composer (Web Push)
tools/            Scripts CLI (sync, vapid, diagnose…)
tools/admin_console/  Console admin Python
```

Stubs legacy à la racine de `public/` (`login.php`, `dashboard.php`, …) : redirections 301 seulement.

## Serveur de test / lab

Parcours complet pour monter Prognoz sur une machine de lab (VM, VPS de test, serveur
interne) **sans toucher à la prod**. Adapte les chemins et l’URL.

| Tu peux écrire dans… | Mets le projet ici |
|----------------------|--------------------|
| `/var/www/` (avec sudo) | `/var/www/prognoz-lab` |
| seulement `/var/www/html/` (souvent le cas) | `/var/www/html/prognoz-lab` |

Les exemples ci-dessous utilisent `/var/www/html/prognoz-lab` (défaut Apache).
Remplace par `/var/www/prognoz-lab` si tu as les droits sur `/var/www`.

Vérifier le user PHP si besoin :

```bash
ps aux | egrep 'php-fpm|apache|nginx' | head
ls -ld /var/www /var/www/html
```
### 0. Prérequis (Debian / Ubuntu)

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server \
  php php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd \
  unzip git curl composer

sudo a2enmod rewrite
sudo systemctl enable --now apache2 mariadb
```

Variante Nginx : `sudo apt install -y nginx php-fpm` (+ mêmes paquets PHP) à la place d’Apache.

### 1. Copier le code

Sur le lab (sans droit sur `/var/www`, OK dans `html`) :

```bash
mkdir -p /var/www/html/prognoz-lab
```

Depuis ta machine, à la racine du projet :

```bash
rsync -avz --exclude '.env' --exclude 'vendor/' --exclude 'var/cache/' \
  --exclude 'public/uploads/avatars/*' \
  ./ user@LAB:/var/www/html/prognoz-lab/
```

Ou sur le lab :

```bash
git clone VOTRE_REPO /var/www/html/prognoz-lab
# avec sudo si besoin : sudo git clone … && sudo chown -R "$USER":www-data …
```

**DocumentRoot Apache** = `/var/www/html/prognoz-lab/public`  
(pas `/var/www/html/prognoz-lab` ni `/var/www/html` : sinon `app/` serait exposé).
### 2. Droits + dossiers d’écriture

```bash
cd /var/www/html/prognoz-lab

mkdir -p public/uploads/avatars var/log var/cache

# Si tu as sudo : propriétaire = PHP
sudo chown -R www-data:www-data /var/www/html/prognoz-lab
sudo find /var/www/html/prognoz-lab -type d -exec chmod 755 {} \;
sudo find /var/www/html/prognoz-lab -type f -exec chmod 644 {} \;
sudo chmod -R 775 public/uploads var

# Sans sudo : ton user + groupe www-data (ou chmod large en lab)
# chgrp -R www-data public/uploads var
# chmod -R 775 public/uploads var
```

Test d’écriture (doit réussir, sinon photos / cache en échec) :

```bash
sudo -u www-data touch /var/www/html/prognoz-lab/public/uploads/avatars/.write_test
sudo -u www-data rm /var/www/html/prognoz-lab/public/uploads/avatars/.write_test
```
### 3. Base MySQL lab (séparée de la prod)

Via **phpMyAdmin** (optionnel) : créer la base `pronosocial_lab` utf8mb4, puis Importer
`schema.sql` (ou le contenu renommé `pronosocial` → `pronosocial_lab`).  
Sinon en CLI :

```bash
sudo mysql
```
```sql
CREATE DATABASE IF NOT EXISTS pronosocial_lab
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'prognoz_lab'@'localhost'
  IDENTIFIED BY 'CHOISIS_UN_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON pronosocial_lab.* TO 'prognoz_lab'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Import schéma (si `schema.sql` force `USE pronosocial`, on renomme à la volée) :

```bash
cd /var/www/html/prognoz-lab
sed 's/pronosocial/pronosocial_lab/g' schema.sql | mysql -u prognoz_lab -p
```

Ou base déjà existante / partielle :

```bash
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/001_match_probabilities.sql
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/002_encryption.sql
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/003_features.sql
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/003_sport_column.sql
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/004_profile_changes.sql
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/005_seasons.sql
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/006_push_subscriptions.sql
mysql -u prognoz_lab -p pronosocial_lab < db/migrations/007_avatars.sql
```

### 4. Fichier `.env` lab

```bash
cd /var/www/html/prognoz-lab
cp .env.example .env
nano .env
```
Exemple minimal lab :

```env
DB_HOST=localhost
DB_NAME=pronosocial_lab
DB_USER=prognoz_lab
DB_PASS=CHOISIS_UN_MOT_DE_PASSE_FORT

APP_URL=http://192.168.1.50
# ou https://lab.exemple.local — doit matcher l’URL du navigateur

ODDS_API_KEY=ta_cle
# Idéal : autre clé que la prod (même plafond 500 crédits/mois)

APP_ENCRYPTION_KEY=
CRON_SECRET=

APP_BETA=1
APP_MAINTENANCE=0
APP_CONTACT_EMAIL=toi@exemple.local

MAIL_FROM=noreply@exemple.local
MAIL_SMTP_HOST=
# SMTP optionnel en lab (sinon reset MDP peut échouer)
```

Générer les secrets et sécuriser `.env` :

```bash
cd /var/www/html/prognoz-lab
php -r "echo 'APP_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;"
php -r "echo 'CRON_SECRET=' . bin2hex(random_bytes(16)) . PHP_EOL;"

chmod 640 .env
# si sudo : sudo chown www-data:www-data .env
```
**Clone d’une BDD prod déjà chiffrée** : réutilise la **même** `APP_ENCRYPTION_KEY`
que la prod, sinon chat / noms de communautés illisibles. Sur une BDD vide lab → nouvelle clé.

Ne committe jamais le `.env` lab. Ne copie pas le `.env` prod tel quel.

### 5. Virtual host Apache (DocumentRoot → `public/`)

**Important :** crée d’abord le dossier (et déploie le code) **avant** de changer
`DocumentRoot`, sinon Apache refuse de démarrer (`is not a directory`).

```bash
# Le chemin doit déjà exister et être lisible
ls -la /var/www/html/prognoz-lab/public
```

**Option simple (lab / IP)** — modifier le site par défaut :

```bash
sudo nano /etc/apache2/sites-available/000-default.conf
```

Contenu minimal qui marche (une fois `public/` déployé) :

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/prognoz-lab/public

    <Directory /var/www/html/prognoz-lab/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Le site répond alors sur `http://IP_DU_LAB/` (racine = `public/`).

Si Apache est cassé parce que le dossier n’existait pas encore :

```bash
sudo mkdir -p /var/www/html/prognoz-lab/public
# temporaire : DocumentRoot /var/www/html  puis reload,
# déploie le code, remets DocumentRoot sur …/prognoz-lab/public
sudo apache2ctl configtest && sudo systemctl start apache2
```
**Option vhost dédié** :

```bash
sudo nano /etc/apache2/sites-available/prognoz-lab.conf
```

```apache
<VirtualHost *:80>
    ServerName lab.exemple.local
    DocumentRoot /var/www/html/prognoz-lab/public

    <Directory /var/www/html/prognoz-lab/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/prognoz-lab-error.log
    CustomLog ${APACHE_LOG_DIR}/prognoz-lab-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite prognoz-lab.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```
### 6. Variante Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name lab.exemple.local;
    root /var/www/html/prognoz-lab/public;
    index index.php;

    # URLs sans .php (v1.0) : fichier → dossier → .php → front controller
    location / {
        try_files $uri $uri/ $uri.php?$query_string /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;  # adapte la version (php -v)
    }

    location ~ /\. {
        deny all;
    }
}
```

```bash
sudo ln -sf /etc/nginx/sites-available/prognoz-lab /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Limites upload (photos jusqu’à 5 Mo) — pool PHP-FPM ou `php.ini` :

```ini
upload_max_filesize = 5M
post_max_size = 8M
```

```bash
sudo systemctl reload php8.3-fpm
# ou : sudo systemctl reload apache2
php -r 'var_export(gd_info());'   # WebP Support => true
```

(`public/.user.ini` et `public/.htaccess` aident aussi selon l’hébergeur.)

### 7. Composer + Web Push (optionnel en lab)

```bash
cd /var/www/html/prognoz-lab
composer install --no-interaction --no-dev --optimize-autoloader
# ou : sudo -u www-data composer install --no-interaction --no-dev --optimize-autoloader
php tools/generate_vapid.php
# coller VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY / VAPID_SUBJECT dans .env
```

### 8. Panel admin (web + terminal SSH)

```bash
cd /var/www/html/prognoz-lab
php tools/generate_admin_credentials.php
# ou : php tools/generate_admin_credentials.php monUser 'MotDePasseTresLong!!'
```
Coller `ADMIN_PANEL_SLUG`, `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH` dans `.env`.

- Web : `http://IP_OU_HOST/admin/?s=SLUG` (mauvais slug → 404)
- SSH : `php tools/ops_terminal.php` (mêmes identifiants)

Détails : [`public/admin/README.md`](public/admin/README.md).

### 9. Premiers tests

```bash
# Remplace IP_OU_HOST et CRON_SECRET
curl -s "http://IP_OU_HOST/api/sync?force=1&wait=1&key=VOTRE_CRON_SECRET"
curl -s "http://IP_OU_HOST/api/sync?cron=1&key=VOTRE_CRON_SECRET"

cd /var/www/html/prognoz-lab
php tools/diagnose_results.php
```

Navigateur : inscription → prono → photo de profil → panel admin / Paramètres pour sync.
Vérifier aussi : `/auth/login` (sans `.php`) et qu’un ancien `/auth/login.php` redirige en 301.

Cron optionnel lab (toutes les 30 min) :

```bash
crontab -e
```

```cron
*/30 * * * * curl -s "http://127.0.0.1/api/sync?cron=1&key=VOTRE_CRON_SECRET" >/dev/null 2>&1
# ou :
# */30 * * * * cd /var/www/html/prognoz-lab && php tools/resolve_results.php >> var/log/resolve.log 2>&1
```

### 10. Lab vs prod

| Point | Lab | Prod |
|-------|-----|------|
| Chemin | `/var/www/html/prognoz-lab` | `/var/www/prognoz` |
| `APP_URL` | IP / hostname lab | `https://www.prognoz.fr` |
| Base | `pronosocial_lab` | `pronosocial` |
| `ODDS_API_KEY` | Idéalement autre clé | Clé prod |
| `APP_BETA` | `1` | **`0` (v1.0)** |
| SMTP | Optionnel | Requis (reset MDP) |
| HTTPS | Facultatif en LAN | Obligatoire |
| Cron | Optionnel | Fortement recommandé |

Mise à jour du code lab :

```bash
# depuis ta machine
rsync -avz --exclude '.env' --exclude 'vendor/' --exclude 'var/cache/' \
  --exclude 'public/uploads/avatars/*' \
  ./ user@LAB:/var/www/html/prognoz-lab/

# sur le lab
cd /var/www/html/prognoz-lab
composer install --no-interaction --no-dev --optimize-autoloader
# + migrations SQL si nouvelles tables
```

## Déploiement production (rappel)

Même enchaînement que le lab, avec `/var/www/prognoz` (ou ton chemin prod), BDD prod, `APP_URL=https://www.prognoz.fr`,
SMTP + SPF/DKIM, cron toutes les 15–30 min, et checklist :

1. `composer install` + `vendor/`
2. `.env` prod (`APP_URL`, BDD, clés, SMTP, VAPID, `ADMIN_*`)
3. Droits `public/uploads` + `var/` (test `touch` ci-dessus)
4. Migrations SQL si besoin (`005`, `006`, `007_avatars`, …)
5. Tester : pari → résultat → push/toast + historique
6. Tester : message communauté → push

## Console admin

- **Web / terminal SSH** — [`public/admin/README.md`](public/admin/README.md)
- **Python (local + tunnel MySQL)** — [`tools/admin_console/README.md`](tools/admin_console/README.md)
