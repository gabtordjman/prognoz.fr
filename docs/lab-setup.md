# Serveur de test / lab

Monter Prognoz sur une machine de lab (VM, VPS de test) **sans toucher à la prod**.  
Adapte les chemins et l’URL. Retour au [README principal](../README.md).

| Tu peux écrire dans… | Mets le projet ici |
|----------------------|--------------------|
| `/var/www/` (avec sudo) | `/var/www/prognoz-lab` |
| seulement `/var/www/html/` | `/var/www/html/prognoz-lab` |

Les exemples ci-dessous utilisent `/var/www/html/prognoz-lab`.

---

## 0. Prérequis (Debian / Ubuntu)

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server \
  php php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd \
  unzip git curl composer

sudo a2enmod rewrite
sudo systemctl enable --now apache2 mariadb
```

Variante Nginx : `sudo apt install -y nginx php-fpm` (+ mêmes paquets PHP).

---

## 1. Copier le code

```bash
mkdir -p /var/www/html/prognoz-lab
```

Depuis ta machine :

```bash
rsync -avz --exclude '.env' --exclude 'vendor/' --exclude 'var/cache/' \
  --exclude 'public/uploads/avatars/*' \
  ./ user@LAB:/var/www/html/prognoz-lab/
```

Ou : `git clone … /var/www/html/prognoz-lab`

**DocumentRoot** = `/var/www/html/prognoz-lab/public` (pas le dossier parent).

---

## 2. Droits

```bash
cd /var/www/html/prognoz-lab
mkdir -p public/uploads/avatars var/log var/cache

sudo chown -R www-data:www-data /var/www/html/prognoz-lab
sudo find /var/www/html/prognoz-lab -type d -exec chmod 755 {} \;
sudo find /var/www/html/prognoz-lab -type f -exec chmod 644 {} \;
sudo chmod -R 775 public/uploads var

sudo -u www-data touch public/uploads/avatars/.write_test
sudo -u www-data rm public/uploads/avatars/.write_test
```

---

## 3. Base MySQL lab

```sql
CREATE DATABASE IF NOT EXISTS pronosocial_lab
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'prognoz_lab'@'localhost'
  IDENTIFIED BY 'CHOISIS_UN_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON pronosocial_lab.* TO 'prognoz_lab'@'localhost';
FLUSH PRIVILEGES;
```

```bash
sed 's/pronosocial/pronosocial_lab/g' schema.sql | mysql -u prognoz_lab -p
```

Ou migrations : voir [README](../README.md#migrations-base-déjà-existante) (même liste, base `pronosocial_lab`).

---

## 4. `.env` lab

```bash
cp .env.example .env
nano .env
```

Minimal :

```env
DB_HOST=localhost
DB_NAME=pronosocial_lab
DB_USER=prognoz_lab
DB_PASS=…

APP_URL=http://192.168.1.50
ODDS_API_KEY=
APP_ENCRYPTION_KEY=
CRON_SECRET=
APP_BETA=1
APP_CONTACT_EMAIL=toi@exemple.local
MAIL_FROM=noreply@exemple.local
```

```bash
php -r "echo 'APP_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;"
php -r "echo 'CRON_SECRET=' . bin2hex(random_bytes(16)) . PHP_EOL;"
chmod 640 .env
```

Clone d’une BDD prod chiffrée → **même** `APP_ENCRYPTION_KEY` que la prod.  
Ne committe jamais le `.env`.

---

## 5. Apache (DocumentRoot → `public/`)

Créer le dossier **avant** de changer le DocumentRoot.

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
sudo apache2ctl configtest && sudo systemctl reload apache2
```

Vhost dédié : même `Directory` + `ServerName lab.exemple.local` + `a2ensite`.

---

## 6. Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name lab.exemple.local;
    root /var/www/html/prognoz-lab/public;
    index index.php;

    location / {
        try_files $uri $uri/ $uri.php?$query_string /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. { deny all; }
}
```

Uploads : `upload_max_filesize = 5M`, `post_max_size = 8M`.

---

## 7. Composer + admin + tests

```bash
composer install --no-interaction --no-dev --optimize-autoloader
php tools/generate_vapid.php
php tools/generate_admin_credentials.php
```

- Admin web : `http://IP/admin/?s=SLUG`
- Sync : `curl -s "http://IP/api/sync?cron=1&key=…"`
- Diagnostic : `php tools/diagnose_results.php`

Cron lab optionnel :

```cron
*/30 * * * * curl -s "http://127.0.0.1/api/sync?cron=1&key=VOTRE_CRON_SECRET" >/dev/null 2>&1
```
