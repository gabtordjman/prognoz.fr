## Admin web Prognoz

### Menu (colonne gauche)

| Catégorie | Sous-menu | À faire |
|-----------|-----------|---------|
| Accueil | Vue d’ensemble | Chiffres + listes des matchs à traiter |
| Accueil | Rétention | Visites, réguliers, retour après match |
| Accueil | Rapports e-mail | Données indisponibles + rapport du mois |
| Accueil | Annonces | Micro / news joueurs |
| Matchs & résultats | Résultats & scores manuels | Saisir score, TAB, corriger void, annuler |
| Matchs & résultats | Sync API & crédits | Import matchs, cron, sonde quota |
| Communauté | Modération chat | Masquer / restaurer / effacer |
| Communauté | Joueurs & points | Points, désactiver, reset MDP, photos |
| Communauté | Pronostics & réussite | Stats, classement, dossier joueur |
| Compétition | Saisons | Clôturer / planifier |
| Compétition | Événements | ×points, thèmes, push |

### Sync API

- **Maintenance** : bouton dans Ops qui écrit `APP_MAINTENANCE=0|1` dans le `.env`
- **Live football** : sync forcée API-Football (affichage score/minute)

### Résultats & scores — 4 cas

1. **Score déjà en base** → « Donner les points » (0 crédit)
2. **Pas de score API** → saisir score (noms d’équipes + option tirs au but) ou « Vraiment annulé »
3. **Données indisponibles** → saisir le vrai score → recalcule les points joueur
4. **Recherche libre** → taper les noms d’équipes

### Alertes e-mail

- Automatique : quand des pronos passent en « données indisponibles » → `ADMIN_NOTIFY_EMAIL` (défaut `admin@prognoz.fr`)
- Manuel : boutons dans **Rapports e-mail**

### Accès

```bash
php tools/generate_admin_credentials.php
```

URL : `https://…/admin/?s=SLUG`
