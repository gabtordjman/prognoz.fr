# Prognoz — Wiki

Documentation du projet. Destinée au **Wiki Gitea** du dépôt  
`http://192.168.1.65:3000/tordjman/prognoz`.

## Publier sur Gitea

1. Sur Gitea : dépôt → **Wiki** → activer le wiki si ce n’est pas déjà fait.
2. Cloner le dépôt wiki (séparé du code) :

```bash
git clone http://192.168.1.65:3000/tordjman/prognoz.wiki.git
cd prognoz.wiki
```

3. Copier les `.md` de ce dossier `wiki/` (sauf ce `_Comment-publier.md` si tu préfères) à la racine du clone.
4. `git add . && git commit -m "docs: wiki initial" && git push`

Les noms de fichiers deviennent des pages : `Home.md` → page d’accueil, `Installation.md` → Installation, etc.

## Pages

| Fichier | Pour qui |
|---------|----------|
| [Home](Home.md) | Tout le monde — vue d’ensemble |
| [Installation](Installation.md) | Débuter (local / lab / prod) |
| [Configuration](Configuration.md) | `.env` et secrets |
| [Exploitation](Exploitation.md) | Cron, sync, scores, pannes |
| [Administration](Administration.md) | Panel admin |
| [Developpement](Developpement.md) | Structure code, i18n, contributions |
| [FAQ](FAQ.md) | Questions fréquentes |
