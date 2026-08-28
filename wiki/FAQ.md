# FAQ

## Je vois `dash.bio` / `dash.personalize` sur le site

Le PHP a été mis à jour **sans** les fichiers `lang/fr.php` et `lang/en.php`.  
Redéploie les deux. Vide le cache navigateur si besoin.

---

## Les points ne tombent pas après un match

1. `php tools/diagnose_results.php`
2. Vérifier le cron / `/api/sync`
3. Admin → Scores : le match est-il fini, reporté, annulé ?
4. Quota The Odds API (Admin → Sync)

---

## Un match est « reporté » alors qu’il s’est joué

Souvent : score API arrivé trop tard, ou mauvais match API.  
Admin → Scores : saisir le score, ou annuler si doublon / erreur d’identité d’équipe.

---

## Le formulaire Mon profil a un grand vide

Vérifier que `style.css` **et** `account/dashboard.php` sont à jour (formulaire pleine largeur).  
Hard refresh (`Ctrl+F5`).

---

## L’événement apparaît deux fois

La bannière du haut suffit. Le doublon dans Mon espace a été retiré ; si tu le vois encore, ton `dashboard.php` n’est pas à jour.

---

## Comment activer le Wiki Gitea ?

1. Dépôt → **Wiki** → activer
2. Suivre `wiki/_Comment-publier.md` (clone `prognoz.wiki.git`, copier les pages, push)

---

## Reset MDP ne marche pas

SMTP mal configuré ou absent en prod. Voir [Configuration](Configuration) → Mail.

---

## Où est la doc lab Debian / Nginx ?

Fichier `docs/lab-setup.md` dans le dépôt git (complément de [Installation](Installation)).
