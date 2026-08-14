"""Appels Ops distants : quota Odds API (gratuit) + sync HTTP Prognoz."""

from __future__ import annotations

import json
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any


ODDS_API_BASE = "https://api.the-odds-api.com"


def mask_api_key(api_key: str) -> str:
    key = (api_key or "").strip()
    if not key:
        return "(vide)"
    if len(key) <= 8:
        return key[:2] + "…" + key[-2:]
    return key[:4] + "…" + key[-4:] + f" ({len(key)} car.)"


def probe_odds_quota(api_key: str, timeout: float = 12.0, label: str = "") -> dict[str, Any]:
    """
    GET /v4/sports — ne consomme PAS de crédit (doc Odds API).
    Lit x-requests-remaining / used / last dans les en-têtes.
    """
    key = (api_key or "").strip()
    meta = {
        "key_mask": mask_api_key(key),
        "label": label or "clé",
    }
    if not key:
        return {"ok": False, "error": "ODDS_API_KEY manquante", **meta}

    # Cache-buster pour éviter un éventuel proxy local
    qs = urllib.parse.urlencode({"apiKey": key, "_": str(int(time.time()))})
    url = f"{ODDS_API_BASE}/v4/sports?{qs}"
    req = urllib.request.Request(
        url,
        headers={
            "Accept": "application/json",
            "User-Agent": "PrognozAdmin/1.0",
            "Cache-Control": "no-cache",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            headers = {k.lower(): v for k, v in resp.headers.items()}
            body = resp.read()
            sports = json.loads(body.decode("utf-8")) if body else []
            return {
                "ok": True,
                "remaining": _header_int(headers, "x-requests-remaining"),
                "used": _header_int(headers, "x-requests-used"),
                "last_cost": _header_int(headers, "x-requests-last"),
                "sports_count": len(sports) if isinstance(sports, list) else 0,
                "http_status": getattr(resp, "status", 200),
                "live": True,
                **meta,
            }
    except urllib.error.HTTPError as exc:
        err_body = ""
        try:
            err_body = exc.read().decode("utf-8", errors="replace")[:300]
        except Exception:
            pass
        headers = {k.lower(): v for k, v in (exc.headers.items() if exc.headers else [])}
        return {
            "ok": False,
            "error": f"HTTP {exc.code} — {err_body or exc.reason}",
            "remaining": _header_int(headers, "x-requests-remaining"),
            "used": _header_int(headers, "x-requests-used"),
            "last_cost": _header_int(headers, "x-requests-last"),
            "http_status": exc.code,
            "live": True,
            **meta,
        }
    except Exception as exc:
        return {"ok": False, "error": str(exc), "live": False, **meta}


def _header_int(headers: dict[str, str], name: str) -> int | None:
    raw = headers.get(name)
    if raw is None or raw == "":
        return None
    try:
        return int(raw)
    except ValueError:
        return None


def call_prognoz_sync(
    app_url: str,
    cron_secret: str,
    *,
    mode: str = "score_local",
    timeout: float | None = None,
) -> dict[str, Any]:
    """
    Appelle public/api/sync.php avec la clé cron.

    Modes :
      score_local     → 0 crédit (points BDD)
      cron            → scores API selon budget serveur (jusqu'à 2 crédits)
      matches         → import calendrier /events (0 crédit) force+refresh+wait
      odds            → cotes manquantes seulement (max ~3 ligues, payant)
      clear_sync_lock → libère un verrou orphelin (0 crédit)
      prune_db        → purge options / matchs orphelins (0 crédit)
    """
    base = (app_url or "").rstrip("/")
    if not base:
        return {"ok": False, "error": "APP_URL manquante"}
    if not cron_secret:
        return {"ok": False, "error": "CRON_SECRET manquant"}

    if mode == "cron":
        params = {"cron": "1", "key": cron_secret}
        timeout = timeout if timeout is not None else 90.0
    elif mode == "matches":
        # Import matchs : /sports + /events = 0 crédit (doc Odds API).
        # wait=1 pour réponse synchrone (sinon file d'attente serveur).
        params = {"key": cron_secret, "force": "1", "refresh": "1", "wait": "1"}
        timeout = timeout if timeout is not None else 150.0
    elif mode == "odds":
        params = {"mode": "odds", "force": "1", "key": cron_secret}
        timeout = timeout if timeout is not None else 60.0
    elif mode == "clear_sync_lock":
        params = {"mode": "clear_sync_lock", "key": cron_secret}
        timeout = timeout if timeout is not None else 30.0
    elif mode == "prune_db":
        params = {"mode": "prune_db", "key": cron_secret}
        timeout = timeout if timeout is not None else 60.0
    else:
        params = {"mode": mode, "key": cron_secret}
        timeout = timeout if timeout is not None else 90.0

    url = f"{base}/api/sync.php?{urllib.parse.urlencode(params)}"
    req = urllib.request.Request(url, headers={"Accept": "application/json", "User-Agent": "PrognozAdmin/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            data = json.loads(raw) if raw else {}
            if not isinstance(data, dict):
                return {"ok": False, "error": f"Réponse invalide: {raw[:200]}"}
            data.setdefault("ok", True)
            data["http_status"] = getattr(resp, "status", 200)
            return data
    except urllib.error.HTTPError as exc:
        body = ""
        try:
            body = exc.read().decode("utf-8", errors="replace")[:400]
        except Exception:
            pass
        return {"ok": False, "error": f"HTTP {exc.code}: {body or exc.reason}", "http_status": exc.code}
    except Exception as exc:
        return {"ok": False, "error": str(exc)}
