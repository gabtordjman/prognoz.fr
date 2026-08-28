# Configuration

Tout passe par le fichier **`.env`** à la racine du projet (jamais committé).  
Modèle : `.env.example`.

---

## Variables essentielles

| Variable | Rôle | Débutant |
|----------|------|----------|
| `APP_URL` | URL publique du site (sans slash final inutile) | Obligatoire |
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | MySQL | Obligatoire |
| `ODDS_API_KEY` | Import matchs & scores (The Odds API) | Obligatoire pour les matchs |
| `APP_ENCRYPTION_KEY` | Chiffre chat & données communautés | Obligatoire |
| `CRON_SECRET` | Protège `/api/sync?cron=1&key=…` | Obligatoire en prod |
| `APP_CONTACT_EMAIL` | Contact + destinataire admin sync | Recommandé |
| `APP_BETA` | `1` = bandeau bêta, `0` = prod | `0` en prod |

---

## Mail (reset mot de passe)

En production, configurer SMTP (`MAIL_*` selon `.env.example`).  
Sans SMTP valide, la réinitialisation de mot de passe échouera.

---

## Web Push

```bash
composer install
php tools/generate_vapid.php
```

Coller `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` dans `.env`.  
Sur le site : **Paramètres → Autoriser → Tester**.

---

## Admin panel

```bash
php tools/generate_admin_credentials.php
```

À coller dans `.env` :

- `ADMIN_PANEL_SLUG`
- `ADMIN_USERNAME`
- `ADMIN_PASSWORD_HASH`

URL : `/admin/?s=VOTRE_SLUG`

---

## Sync forcée / admins

`SYNC_ADMIN_USER_IDS` — liste d’IDs utilisateurs autorisés à forcer certaines syncs.  
Sinon, l’e-mail `APP_CONTACT_EMAIL` peut servir de critère selon le code en place.

---

## Astuces

- Après un changement de `.env`, pas besoin de rebuild : recharger une page PHP suffit.
- Ne jamais exposer `.env` via le web (il est hors de `public/`).
- En lab, tu peux laisser SMTP et VAPID vides ; en prod, non.
