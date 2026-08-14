# Cotes manquantes (Pologne / Eredivisie) — diagnostic v1.0

**Verdict : réparable.** Ce n’est pas « The Odds API ignore ces ligues ».

## Constats

- Clés configurées dans `app/config.php` :
  - `soccer_poland_ekstraklasa`
  - `soccer_netherlands_eredivisie`
- Les caches `var/cache/odds_soccer_*` contiennent déjà des événements **avec bookmakers h2h**.
- Les marqueurs `odds_avail_*` peuvent indiquer `available: true` alors que certains matchs affichés n’ont toujours pas de `prob_1` / `prob_n` / `prob_2` en BDD.

Donc l’échec « pas de cotes à l’écran » vient surtout du **pipeline d’attache BDD**, pas de l’absence de marché API.

## Causes probables (par ordre)

1. **Budget sync** — `syncDisplayedMatchOdds()` ne traite que **2** ligues (3 en force admin) parmi celles qui ont des matchs sans probas. PL / NL peuvent perdre face à d’autres ligues plus nombreuses sans cotes.
2. **Cache 6 h sans bypass** — même un rafraîchissement admin ne force pas `bypassCache` ; un fetch vide ou partiel peut rester collé (`ODDS_CACHE_TTL_ODDS`).
3. **Matching strict** — `syncSportOdds()` met à jour seulement si `external_id` ou noms d’équipes **exactement** égaux. Un écart de graphie = match sans cotes alors que l’API a les cotes.
4. **Quota** — sous `ODDS_QUOTA_RESERVE_ODDS` (60), plus d’appel cotes payant.

## Pistes de réparation (hors v1.0)

- Prioriser les ligues à **0 %** de couverture affichée.
- `bypassCache` sur une action admin « forcer cotes » ciblée.
- Outil de diagnostic : events odds vs lignes BDD non matchées.
- Assouplir le matching des noms (normalisation accents / alias).

## Que faire en attendant

- Vérifier le quota dans **Admin → Sync API**.
- Lancer une sync cotes (`/api/sync?mode=odds&force=1&key=…`) quand le crédit le permet.
- Saisir manuellement un score n’ajoute pas les cotes ; les cotes ne servent qu’à l’affichage des %.
- Les paris 1/N/2 restent possibles **sans** barres de probas.
