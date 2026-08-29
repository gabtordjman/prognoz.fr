# Administration

Panel web réservé aux ops. Doc longue aussi dans `public/admin/README.md`.

---

## Accès

1. Générer les identifiants :

```bash
php tools/generate_admin_credentials.php
```

2. Coller dans `.env` : `ADMIN_PANEL_SLUG`, `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH`
3. Ouvrir : `https://ton-site/admin/?s=TON_SLUG`
4. Se connecter avec le user / mot de passe choisis

Sans le bon slug dans l’URL, le panel ne s’affiche pas (fichier plat).

---

## Poste 5250 (IBM i)

Même identifiants, look terminal vert, saisie clavier.

- Navigateur : `https://ton-site/admin/ibmi/?s=TON_SLUG` (ou lien **Poste 5250** dans le panel felt)
- SSH : `php tools/ops_terminal.php`

Écrans : `MAIN`, `DSPSTS`, `WRKUSR`, `DSPUSR`, `WRKSCR`, `WRKOPS`, `WRKMSG`, `WRKSEA`, `WRKEVT`, `WRKANN`, `WRKRPT`.

Touches (web) : F3=Exit, F5=Refresh, F12=Cancel, Entrée=valider, PageUp/PageDown. Ligne `===>` : `WRKUSR`, `DSPUSR 12`, `GO OPS`, `90`.

CLI : taper `F3` / `F12` / `F5` au clavier (pas les touches physiques), mêmes commandes.

---

## Rubriques utiles

| Section | Usage |
|---------|--------|
| **Scores** | Saisie / correction / effacement, reportés, rattrapage API multi-ligues |
| **Ops / Sync** | Forcer sync, voir état API |
| **Événements** | Campagnes ×points, thèmes, publication, push |
| Autres | Selon version (rapports, etc.) |

La version affichée en haut du panel = `APP_VERSION` dans `app/config.php`.

---

## Bonnes pratiques

- Toujours **prévisualiser** un événement avant de publier.
- Pour un match « fantôme » API : annuler avec raison (ex. doublon), ne pas inventer un score au hasard.
- Après une sync qui échoue : regarder les logs / `diagnose_results.php`, pas relancer 20 fois `force` sans comprendre.
- Ne pas committer le hash admin ni le slug dans un dépôt public.

---

## Console Python (optionnel)

Outil local : `tools/admin_console/` — voir son README. Utile hors navigateur ; le panel felt et le poste 5250 restent les chemins principaux.
