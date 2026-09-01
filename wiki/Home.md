# Accueil

**Prognoz** est un site de pronostics sportifs entre amis (football, basket, tennis).  
Pas de paris d’argent : uniquement des **points**, des **classements** et des **communautés**.

| | |
|---|---|
| Production | [prognoz.fr](https://www.prognoz.fr) |
| Code | dépôt Gitea `tordjman/prognoz` |
| Version actuelle | voir le `README.md` du dépôt |

---

## Par où commencer ?

| Tu veux… | Page |
|----------|------|
| Installer le projet chez toi ou sur un lab | [Installation](Installation) |
| Comprendre le fichier `.env` | [Configuration](Configuration) |
| Faire tourner les scores / le cron | [Exploitation](Exploitation) |
| Utiliser le panel admin | [Administration](Administration) |
| Modifier le code | [Developpement](Developpement) |
| Un problème courant | [FAQ](FAQ) |

---

## En deux mots (débutant)

1. Tu crées un compte et tu rejoins la communauté **Générale** (et éventuellement des potes).
2. Tu ouvres les matchs, tu remplis un ticket, tu valides.
3. Quand le match est fini, le site récupère le score (API + cron) et calcule tes points.
4. Une saison dure ~14 jours : classement, badges podium, reset.

### Barème des points

| Marché | Si gagné | Si raté |
|--------|----------|---------|
| Vainqueur (1x2) | +1 (+ série / événement) | 0 pt (série à zéro) |
| Score exact | +3 | 0 (bonus) |
| Buteur | +2 | 0 (bonus) |
| Équipe préférée | +4 | 0 (bonus) |

Aucun marché ne retire de points : score exact / buteur / fav sont des **bonus**. Seule la série tombe à zéro sur un 1x2 perdu. Total et saison ne descendent jamais sous **0**.  
Détail aussi dans l’aide « ? » points sur le site et *Comment ça marche*.

## En deux mots (technique)

- PHP + MySQL, document root = `public/` uniquement.
- Import / scores via The Odds API + `tools/resolve_results.php` ou `/api/sync`.
- Chat / noms de communautés : chiffrement AES (`APP_ENCRYPTION_KEY`).
- i18n : fichiers `lang/fr.php` et `lang/en.php`.
- Événements site : table + admin `/admin/events.php` (multiplicateur de points, thème, bannière).

---

## Règle d’or déploiement

À chaque mise à jour qui touche l’affichage ou de nouvelles chaînes : déployer **le PHP et** les fichiers `lang/*.php` (+ CSS/JS).  
Sinon tu verras des clés brutes du type `dash.bio` sur le site.
