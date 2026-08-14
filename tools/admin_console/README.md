# Console admin Prognoz (Python)

Interface locale unique : utilisateurs, messages, **saisons**, **quota API**, **scores manuels**, sync.

## Lancer

```bash
# Tunnel BDD (exemple)
ssh -L 3307:127.0.0.1:3306 root@VOTRE_VPS

cd tools/admin_console
python -m venv .venv
.venv\Scripts\activate          # Windows
pip install -r requirements.txt
copy config.example.env .env.local   # compléter les secrets
python prognoz_admin.py
```

Le `.env` racine du site est aussi lu ; `.env.local` le surcharge (idéal pour `DB_PORT=3307`).

## Onglets

| Onglet | Rôle | Crédits API |
|--------|------|-------------|
| Vue d'ensemble | Stats + saison + pronos bloqués | 0 |
| **API & Sync** | Quota, **rafraîchir matchs** (`/events`), cotes manquantes, cron scores | Matchs / quota = **0** ; cotes = peu ; cron scores ≤ 2 |
| **Saisons** | Lister, clôturer, planifier fin au 1er du mois | 0 |
| **Scores manuels** | Saisir score + attribuer points | 0 (points via `mode=score_local`) |
| Messages / Utilisateurs | Modération, **attribuer / retirer des points** | 0 |

## Vérifier le quota (1er du mois)

Onglet **API & Sync** → **Vérifier quota (0 crédit)**.

Appelle `GET /v4/sports` (gratuit selon la doc Odds API) et affiche `x-requests-remaining`.

## Remplace quoi ?

Ces scripts PHP restent utiles en SSH d’urgence, mais la console couvre le quotidien :

- `tools/diagnose_results.php` → onglet API & Sync
- `tools/prepare_season_reset.php` → onglet Saisons
- saisie manuelle Paramètres web → onglet Scores manuels
