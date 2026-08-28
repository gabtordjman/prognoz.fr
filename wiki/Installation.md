# Installation

Guide pour monter Prognoz. Trois niveaux : **local (XAMPP)**, **lab**, **production**.

---

## Prérequis

- PHP 8+ avec : `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`, `curl`
- MySQL / MariaDB
- Apache (`mod_rewrite`) ou Nginx
- Composer (pour les notifications push)

Le serveur web doit pointer sur le dossier **`public/`**, jamais sur la racine du repo.

---

## 1. Récupérer le code

```bash
git clone http://192.168.1.65:3000/tordjman/prognoz.git
cd prognoz
```

Sous Windows / XAMPP : placer le projet dans `C:\xampp\htdocs\prognoz` (ou un vhost dédié).

---

## 2. Base de données

**Install neuve** — importer le schéma complet :

```bash
mysql -u root -p -e "CREATE DATABASE pronosocial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p pronosocial < schema.sql
```

**Base déjà existante** — jouer les migrations dans l’ordre :

```bash
mysql -u root -p pronosocial < db/migrations/001_match_probabilities.sql
# … jusqu’à
mysql -u root -p pronosocial < db/migrations/008_site_events.sql
```

Beaucoup de colonnes sont aussi créées automatiquement au démarrage PHP si possible.

---

## 3. Fichier `.env`

```bash
cp .env.example .env
```

Renseigner au minimum :

- `APP_URL` — ex. `http://localhost/prognoz/public` (local) ou `https://www.prognoz.fr`
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_ENCRYPTION_KEY` — 32 octets en base64
- `ODDS_API_KEY` — clé The Odds API
- `CRON_SECRET` — mot de passe pour les appels cron

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

Détail de chaque variable : [Configuration](Configuration).

---

## 4. Dépendances & droits

```bash
composer install
```

Dossiers accessibles en écriture par PHP :

- `public/uploads/` (avatars)
- `var/` (cache, logs)

---

## 5. Apache (exemple)

DocumentRoot → `…/prognoz/public`  
`AllowOverride All` pour que `public/.htaccess` active les URLs sans `.php`.

Sous XAMPP, un alias du type `/prognoz` → `htdocs/prognoz/public` fonctionne aussi.

---

## 6. Premier démarrage

1. Ouvrir le site, créer un compte
2. Générer l’admin : `php tools/generate_admin_credentials.php` → coller dans `.env`
3. Admin → sync / import matchs (ou attendre le cron)
4. Prod : `APP_BETA=0`

Lab détaillé (Debian, Nginx, etc.) : fichier `docs/lab-setup.md` dans le dépôt.

---

## Checklist prod (rapide)

- [ ] DocumentRoot = `public/`
- [ ] `.env` prod (HTTPS, SMTP, VAPID, `ADMIN_*`)
- [ ] `composer install --no-dev`
- [ ] Migrations à jour
- [ ] Cron 15–30 min
- [ ] Test : prono → score → points
