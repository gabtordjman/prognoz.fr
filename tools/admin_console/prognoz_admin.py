#!/usr/bin/env python3

from __future__ import annotations

import hashlib
import json
import os
import sys
import tkinter as tk
from pathlib import Path
from tkinter import messagebox, scrolledtext, ttk

from dotenv import dotenv_values

from prognoz_db import (
    DbConfig,
    apply_manual_match_score,
    cancel_match,
    close_expired_seasons,
    connect_with_hint,
    fetch_communities,
    fetch_messages,
    fetch_pending_by_sport,
    fetch_pending_stats,
    fetch_seasons,
    fetch_stats,
    fetch_stuck_matches,
    force_close_active_season,
    grant_user_points,
    hard_delete_message,
    next_month_start,
    reset_user_password,
    restore_message,
    schedule_season_end,
    search_users,
    season_clock_now,
    set_user_active,
    soft_delete_message,
)
from prognoz_ops import call_prognoz_sync, mask_api_key, probe_odds_quota

ROOT_DIR = Path(__file__).resolve().parents[2]
ENV_PATH = ROOT_DIR / ".env"
LOCAL_ENV_PATH = Path(__file__).resolve().parent / ".env.local"
APP_TITLE = "Prognoz Admin"


def load_env_file(path: Path) -> dict[str, str | None]:
    last_error: UnicodeDecodeError | None = None
    for encoding in ("utf-8", "utf-8-sig", "cp1252", "latin-1"):
        try:
            return dotenv_values(path, encoding=encoding)
        except UnicodeDecodeError as exc:
            last_error = exc
    raise ValueError(f"Lecture impossible : {path}" + (f" ({last_error})" if last_error else ""))


def load_merged_env() -> dict[str, str | None]:
    if not ENV_PATH.is_file() and not LOCAL_ENV_PATH.is_file():
        raise FileNotFoundError(f".env introuvable. Voir config.example.env → .env.local")

    env: dict[str, str | None] = {}
    if ENV_PATH.is_file():
        env.update(load_env_file(ENV_PATH))
    if LOCAL_ENV_PATH.is_file():
        env.update({k: v for k, v in load_env_file(LOCAL_ENV_PATH).items() if v is not None})
    return env


def odds_key_source() -> str:
    """D'où vient ODDS_API_KEY (local surcharge la racine)."""
    local_key = ""
    root_key = ""
    if LOCAL_ENV_PATH.is_file():
        local_key = (load_env_file(LOCAL_ENV_PATH).get("ODDS_API_KEY") or "").strip()
    if ENV_PATH.is_file():
        root_key = (load_env_file(ENV_PATH).get("ODDS_API_KEY") or "").strip()
    if local_key:
        return str(LOCAL_ENV_PATH)
    if root_key:
        return str(ENV_PATH)
    return "(aucune)"


def load_config() -> DbConfig:
    env = load_merged_env()
    missing = [k for k in ("DB_HOST", "DB_NAME", "DB_USER", "APP_ENCRYPTION_KEY") if not env.get(k)]
    if missing:
        raise ValueError(f"Variables manquantes : {', '.join(missing)}")

    return DbConfig(
        host=env["DB_HOST"] or "localhost",
        name=env["DB_NAME"] or "pronosocial",
        user=env["DB_USER"] or "root",
        password=env.get("DB_PASS") or "",
        encryption_key_b64=env["APP_ENCRYPTION_KEY"] or "",
        port=int(env.get("DB_PORT") or 3306),
        app_url=(env.get("APP_URL") or "").rstrip("/"),
        odds_api_key=(env.get("ODDS_API_KEY") or "").strip(),
        odds_api_key_source=odds_key_source(),
        cron_secret=(env.get("CRON_SECRET") or "").strip(),
        saison_duree_jours=int(env.get("SAISON_DUREE_JOURS") or 14),
    )


def pin_hash(pin: str) -> str:
    return hashlib.sha256(pin.encode("utf-8")).hexdigest()


def verify_admin_pin(env: dict[str, str | None]) -> bool:
    plain_pin = (env.get("ADMIN_CONSOLE_PIN") or "").strip()
    pin_hash_env = (env.get("ADMIN_CONSOLE_PIN_HASH") or "").strip()
    if not plain_pin and not pin_hash_env:
        return True

    dialog = tk.Tk()
    dialog.title("Accès")
    dialog.resizable(False, False)
    dialog.geometry("340x130")

    frame = ttk.Frame(dialog, padding=16)
    frame.pack(fill="both", expand=True)

    ttk.Label(frame, text="Code d'accès").pack(anchor="w")
    pin_var = tk.StringVar()
    entry = ttk.Entry(frame, textvariable=pin_var, show="•", width=30)
    entry.pack(fill="x", pady=(6, 10))
    entry.focus_set()

    ok = {"value": False}

    def submit(_event=None) -> None:
        entered = pin_var.get().strip()
        if not entered:
            return
        if (plain_pin and entered == plain_pin) or (pin_hash_env and pin_hash(entered) == pin_hash_env):
            ok["value"] = True
            dialog.destroy()
        else:
            messagebox.showerror("Refusé", "Code incorrect.", parent=dialog)

    entry.bind("<Return>", submit)
    ttk.Button(frame, text="OK", command=submit).pack(anchor="e")
    dialog.mainloop()
    return ok["value"]


class AdminApp(tk.Tk):
    def __init__(self, cfg: DbConfig) -> None:
        super().__init__()
        self.cfg = cfg
        self.conn = connect_with_hint(cfg)
        self.title(APP_TITLE)
        self.geometry("1000x680")
        self.minsize(860, 560)

        header = ttk.Frame(self, padding=(12, 8))
        header.pack(fill="x")
        ttk.Label(header, text="Prognoz — console locale", font=("Segoe UI", 11, "bold")).pack(anchor="w")
        ttk.Label(
            header,
            text=f"{cfg.user}@{cfg.host}:{cfg.port}/{cfg.name}",
            foreground="#666",
        ).pack(anchor="w")

        notebook = ttk.Notebook(self)
        notebook.pack(fill="both", expand=True, padx=12, pady=(0, 12))

        self.stats_tab = StatsTab(notebook, self)
        self.ops_tab = OpsTab(notebook, self)
        self.seasons_tab = SeasonsTab(notebook, self)
        self.scores_tab = ScoresTab(notebook, self)
        self.messages_tab = MessagesTab(notebook, self)
        self.users_tab = UsersTab(notebook, self)

        notebook.add(self.stats_tab, text="Vue d'ensemble")
        notebook.add(self.ops_tab, text="API & Sync")
        notebook.add(self.seasons_tab, text="Saisons")
        notebook.add(self.scores_tab, text="Scores manuels")
        notebook.add(self.messages_tab, text="Messages")
        notebook.add(self.users_tab, text="Utilisateurs")

        self.protocol("WM_DELETE_WINDOW", self.on_close)

    def on_close(self) -> None:
        try:
            self.conn.close()
        except Exception:
            pass
        self.destroy()


class StatsTab(ttk.Frame):
    def __init__(self, parent: ttk.Notebook, app: AdminApp) -> None:
        super().__init__(parent, padding=16)
        self.app = app

        ttk.Button(self, text="Actualiser", command=self.refresh).pack(anchor="w")
        self.label = ttk.Label(self, font=("Segoe UI", 10), justify="left")
        self.label.pack(anchor="w", pady=(12, 0))
        self.refresh()

    def refresh(self) -> None:
        try:
            s = fetch_stats(self.app.conn)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return

        season = s.get("season") or {}
        pending = s.get("pending") or {}
        if season:
            season_txt = (
                f"#{season['id']}  {season['debut']} → {season['fin']}"
            )
        else:
            season_txt = "aucune active"

        self.label.config(
            text=(
                f"Utilisateurs actifs : {s['users']}\n"
                f"Communautés : {s['communities']}\n"
                f"Messages visibles : {s['messages']}\n"
                f"Messages (24 h) : {s['messages_24h']}\n"
                f"\nSaison active : {season_txt}\n"
                f"Horloge saisons (Paris) : {season_clock_now()}\n"
                f"\nPronos en attente : {pending.get('pending', 0)}\n"
                f"Dont matchs déjà joués : {pending.get('stuck', 0)}\n"
                f"Joueurs concernés : {pending.get('users', 0)}"
            )
        )


class OpsTab(ttk.Frame):
    """Quota Odds API (appel gratuit /sports) + sync HTTP distant."""

    def __init__(self, parent: ttk.Notebook, app: AdminApp) -> None:
        super().__init__(parent, padding=12)
        self.app = app

        info = ttk.LabelFrame(self, text="Quota The Odds API", padding=10)
        info.pack(fill="x")
        ttk.Label(
            info,
            text="Sonde live GET /v4/sports (0 crédit). Les % affichés sur le site viennent de la BDD "
            "(cache) : les voir ne prouve pas que l’API répond encore.",
            wraplength=920,
            justify="left",
        ).pack(anchor="w")

        self.key_info = ttk.Label(info, foreground="#444", wraplength=920, justify="left")
        self.key_info.pack(anchor="w", pady=(6, 0))
        self.refresh_key_info()

        row = ttk.Frame(info)
        row.pack(fill="x", pady=(8, 0))
        ttk.Button(row, text="Vérifier quota (0 crédit)", command=self.probe_quota).pack(side="left")
        ttk.Button(row, text="Recharger .env", command=self.reload_env).pack(side="left", padx=(8, 0))
        self.quota_label = ttk.Label(row, text="—", foreground="#333")
        self.quota_label.pack(side="left", padx=(12, 0))

        cmp = ttk.Frame(info)
        cmp.pack(fill="x", pady=(8, 0))
        ttk.Label(cmp, text="Comparer une autre clé :").pack(side="left")
        self.alt_key_var = tk.StringVar()
        ttk.Entry(cmp, textvariable=self.alt_key_var, width=42, show="•").pack(side="left", padx=(6, 8))
        ttk.Button(cmp, text="Sonder cette clé", command=self.probe_alt_key).pack(side="left")

        sync_box = ttk.LabelFrame(self, text="Sync serveur (HTTP + CRON_SECRET)", padding=10)
        sync_box.pack(fill="x", pady=(12, 0))
        self.app_url_label = ttk.Label(
            sync_box,
            text=f"APP_URL = {app.cfg.app_url or '(manquant dans .env.local)'}",
            foreground="#666",
        )
        self.app_url_label.pack(anchor="w")

        ttk.Label(
            sync_box,
            text="« Rafraîchir matchs » = calendrier /events (0 crédit) → foot + basket + tennis. "
            "Les cotes (%) sont optionnelles et coûtent un peu.",
            wraplength=920,
            justify="left",
            foreground="#555",
        ).pack(anchor="w", pady=(4, 0))

        brow = ttk.Frame(sync_box)
        brow.pack(fill="x", pady=(8, 0))
        ttk.Button(
            brow,
            text="Rafraîchir matchs (0 crédit)",
            command=lambda: self.run_sync("matches"),
        ).pack(side="left")
        ttk.Button(
            brow,
            text="Attribuer points (0 crédit)",
            command=lambda: self.run_sync("score_local"),
        ).pack(side="left", padx=(8, 0))
        ttk.Button(
            brow,
            text="Cotes manquantes (peu)",
            command=lambda: self.run_sync("odds"),
        ).pack(side="left", padx=(8, 0))
        ttk.Button(
            brow,
            text="Cron scores (budget API)",
            command=lambda: self.run_sync("cron"),
        ).pack(side="left", padx=(8, 0))
        ttk.Button(
            brow,
            text="Libérer verrou sync",
            command=lambda: self.run_sync("clear_sync_lock"),
        ).pack(side="left", padx=(8, 0))
        ttk.Button(
            brow,
            text="Purger BDD",
            command=lambda: self.run_sync("prune_db"),
        ).pack(side="left", padx=(8, 0))

        diag = ttk.LabelFrame(self, text="Diagnostic", padding=10)
        diag.pack(fill="both", expand=True, pady=(12, 0))
        ttk.Button(diag, text="Diagnostic BDD (pronos bloqués)", command=self.refresh_diag).pack(anchor="w")
        self.diag = scrolledtext.ScrolledText(diag, height=14, wrap="word", state="disabled")
        self.diag.pack(fill="both", expand=True, pady=(8, 0))
        self.refresh_diag()

    def refresh_key_info(self) -> None:
        cfg = self.app.cfg
        self.key_info.config(
            text=(
                f"Clé chargée : {mask_api_key(cfg.odds_api_key)}  ·  source : {cfg.odds_api_key_source}\n"
                f"(.env.local surcharge le .env racine — change la clé puis « Recharger .env », "
                f"sinon la console garde l’ancienne en mémoire.)"
            )
        )

    def reload_env(self) -> None:
        try:
            self.app.cfg = load_config()
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        self.refresh_key_info()
        self.app_url_label.config(text=f"APP_URL = {self.app.cfg.app_url or '(manquant)'}")
        messagebox.showinfo("OK", f"Env rechargé.\nClé : {mask_api_key(self.app.cfg.odds_api_key)}")

    def set_diag(self, text: str) -> None:
        self.diag.config(state="normal")
        self.diag.delete("1.0", "end")
        self.diag.insert("1.0", text)
        self.diag.config(state="disabled")

    def _format_probe(self, result: dict, title: str) -> str:
        rem = result.get("remaining")
        used = result.get("used")
        last = result.get("last_cost")
        lines = [
            title,
            f"  Clé sondée : {result.get('key_mask')}",
            f"  Live API : {'oui' if result.get('live') else 'non'}",
            f"  HTTP : {result.get('http_status', '?')}",
            f"  Restants : {rem}",
            f"  Utilisés (depuis reset mensuel) : {used}",
            f"  Coût dernier appel : {last}",
            f"  Sports listés : {result.get('sports_count', '—')}",
        ]
        if not result.get("ok"):
            lines.append(f"  Erreur : {result.get('error')}")
        lines.extend(
            [
                "",
                "Interprétation :",
                "  • 500 restants / 0 utiliséés après le 1er du mois = normal (reset FAQ),",
                "    même pour l’ancienne clé si elle a bien été renouvelée.",
                "  • Même chiffre sur 2 clés ≠ bug si les 2 ont 500 crédits frais.",
                "  • Pour prouver que ce sont 2 clés différentes : colle la nouvelle",
                "    dans « Comparer une autre clé » et sonde — les masques doivent différer.",
                "  • Les cotes visibles sur le site = BDD (prob_*), pas un appel live.",
            ]
        )
        return "\n".join(lines)

    def probe_quota(self) -> None:
        self.quota_label.config(text="Appel…")
        self.update_idletasks()
        result = probe_odds_quota(self.app.cfg.odds_api_key, label="env")
        self.set_diag(self._format_probe(result, "Quota Odds API (sonde /sports, 0 crédit)"))
        if result.get("ok"):
            self.quota_label.config(
                text=(
                    f"Restants : {result.get('remaining')}  ·  Utilisés : {result.get('used')}  ·  "
                    f"clé {result.get('key_mask')}"
                )
            )
        else:
            self.quota_label.config(text=f"Échec : {result.get('error', '?')}")

    def probe_alt_key(self) -> None:
        alt = self.alt_key_var.get().strip()
        if not alt:
            messagebox.showwarning("", "Colle une clé API à comparer.")
            return
        self.quota_label.config(text="Comparaison…")
        self.update_idletasks()
        a = probe_odds_quota(self.app.cfg.odds_api_key, label="env")
        b = probe_odds_quota(alt, label="collée")
        same_key = self.app.cfg.odds_api_key.strip() == alt
        text = (
            self._format_probe(a, "A — clé de l’env")
            + "\n\n"
            + self._format_probe(b, "B — clé collée")
            + "\n\n"
            + (
                "Verdict : c’est LA MÊME clé (copier-coller identique)."
                if same_key
                else (
                    "Verdict : deux clés différentes. "
                    f"A={a.get('remaining')}/{a.get('used')}  vs  B={b.get('remaining')}/{b.get('used')}."
                )
            )
        )
        self.set_diag(text)
        self.quota_label.config(
            text=f"A {a.get('remaining')}/{a.get('used')}  ·  B {b.get('remaining')}/{b.get('used')}"
        )

    def run_sync(self, mode: str) -> None:
        labels = {
            "score_local": "points locaux",
            "cron": "cron scores",
            "matches": "import matchs",
            "odds": "cotes manquantes",
            "clear_sync_lock": "libération verrou",
            "prune_db": "purge BDD",
        }
        label = labels.get(mode, mode)
        if mode == "cron" and not messagebox.askyesno(
            "Cron API",
            "Lance le cron distant (peut coûter jusqu'à 2 crédits si des pronos sont bloqués).\nContinuer ?",
        ):
            return
        if mode == "matches" and not messagebox.askyesno(
            "Import matchs",
            "Relance l’import calendrier sur le serveur (foot / basket / tennis).\n"
            "Coût Odds API : 0 crédit (/events).\n"
            "Ça peut prendre ~1–2 min. Continuer ?",
        ):
            return
        if mode == "odds" and not messagebox.askyesno(
            "Cotes",
            "Remplit les % manquants (max 3 ligues sans cotes).\n"
            "Coût typique : quelques crédits seulement.\nContinuer ?",
        ):
            return
        if mode == "prune_db" and not messagebox.askyesno(
            "Purge BDD",
            "Supprime les options score_exact en BDD (désormais en PHP),\n"
            "les buteurs des vieux matchs, et les matchs terminés sans prono.\n"
            "L’historique des pronos n’est pas touché. Continuer ?",
        ):
            return
        self.set_diag(f"Sync {label}… (patience si import matchs)")
        self.update_idletasks()
        result = call_prognoz_sync(self.app.cfg.app_url, self.app.cfg.cron_secret, mode=mode)

        # 409 sync_busy : souvent un verrou fantôme — tenter clear puis 1 retry.
        if mode == "matches" and (
            result.get("http_status") == 409
            or "sync_busy" in str(result.get("error", ""))
        ):
            self.set_diag("409 sync_busy → libération verrou puis nouvel essai…")
            self.update_idletasks()
            cleared = call_prognoz_sync(
                self.app.cfg.app_url, self.app.cfg.cron_secret, mode="clear_sync_lock"
            )
            if cleared.get("ok") and not cleared.get("busy"):
                result = call_prognoz_sync(
                    self.app.cfg.app_url, self.app.cfg.cron_secret, mode="matches"
                )
            else:
                result = {
                    "ok": False,
                    "error": "sync_busy",
                    "hint": cleared.get("hint")
                    or "Sync encore active sur le serveur. Réessaie dans 1–2 min.",
                    "clear_attempt": cleared,
                }

        self.set_diag(self._format_sync_result(mode, result))
        if result.get("ok"):
            messagebox.showinfo("OK", f"Sync {label} terminée.")
        else:
            messagebox.showerror("Erreur", str(result.get("error") or result.get("hint") or result))

    def _format_sync_result(self, mode: str, result: dict) -> str:
        if mode == "odds" and result.get("ok"):
            lines = [
                "Sync cotes",
                f"  Matchs mis à jour : {result.get('match_updates', 0)}",
                f"  Couverture affichée : {result.get('coverage_with')}/{result.get('coverage_total')}",
                f"  Quota restant : {result.get('quota_remaining')}",
                f"  Ligues appelées : {', '.join(result.get('sports') or []) or '—'}",
            ]
            if result.get("nothing_to_do"):
                lines.append("  Rien à faire : les matchs affichés ont déjà des %.")
            if result.get("skipped_quota"):
                lines.append("  Stoppé : quota / réserve trop bas.")
            if result.get("throttled"):
                lines.append("  Throttle : attends un peu ou force déjà utilisé.")
            lines.append("")
            lines.append("Astuce : Ctrl+F5 sur le site si les % ne bougent pas tout de suite.")
            return "\n".join(lines)
        if mode == "prune_db" and result.get("ok"):
            pruned = result.get("pruned") or {}
            return (
                "Purge BDD terminée\n"
                f"  Options score_exact supprimées : {pruned.get('score_options', 0)}\n"
                f"  Options buteur purgées : {pruned.get('buteur_options', 0)}\n"
                f"  Matchs orphelins (sans prono) : {pruned.get('orphan_matches', 0)}\n"
                f"\n{result.get('hint', '')}"
            )
        if mode != "matches" or not result.get("ok"):
            return json.dumps(result, ensure_ascii=False, indent=2, default=str)
        lines = [
            "Import matchs terminé (0 crédit /events)",
            f"  Ligues sondées : {result.get('sports_checked')}",
            f"  Tennis actifs API : {result.get('active_tennis')}  ·  "
            f"Basket : {result.get('active_basketball')}  ·  Foot : {result.get('active_soccer')}",
            f"  Événements récupérés : {result.get('events_fetched')}  ·  "
            f"importés/MAJ : {result.get('events_imported')}",
            "",
            "En BDD (à venir) :",
            f"  Tennis : {result.get('db_tennis')}  ·  Basket : {result.get('db_basketball')}  ·  "
            f"Foot : {result.get('db_soccer')}",
            "Affichés (rotation) :",
            f"  Tennis : {result.get('shown_tennis')}  ·  Basket : {result.get('shown_basketball')}  ·  "
            f"Foot : {result.get('shown_soccer')}  ·  Total : {result.get('shown_total')}",
        ]
        if result.get("queued"):
            lines.append("")
            lines.append("Note : sync mise en file (queued) — recharge le site dans 1–2 min.")
        skips = result.get("import_skips") or {}
        if skips:
            lines.append("")
            lines.append("Détail import : " + ", ".join(f"{k}={v}" for k, v in skips.items()))
        by_sport = result.get("fetched_by_sport") or {}
        if by_sport:
            lines.append("")
            lines.append("Événements par ligue (extrait) :")
            for key, n in list(by_sport.items())[:20]:
                lines.append(f"  {key}: {n}")
            if len(by_sport) > 20:
                lines.append(f"  … +{len(by_sport) - 20} ligues")
        return "\n".join(lines)

    def refresh_diag(self) -> None:
        try:
            pending = fetch_pending_stats(self.app.conn)
            by_sport = fetch_pending_by_sport(self.app.conn)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        lines = [
            f"Pronos en attente : {pending['pending']}",
            f"Dont matchs joués : {pending['stuck']}",
            f"Joueurs : {pending['users']}",
            "",
            "Par sport (matchs prêts, sans résultat) :",
        ]
        if not by_sport:
            lines.append("  Aucun.")
        for row in by_sport:
            lines.append(
                f"  {row['sport']:40}  {int(row['matchs']):3} match(s)  "
                f"{int(row['pronos']):3} prono(s)  "
                f"{row['plus_ancien']} → {row['plus_recent']}"
            )
        self.set_diag("\n".join(lines))


class SeasonsTab(ttk.Frame):
    def __init__(self, parent: ttk.Notebook, app: AdminApp) -> None:
        super().__init__(parent, padding=12)
        self.app = app

        top = ttk.Frame(self)
        top.pack(fill="x")
        ttk.Button(top, text="Actualiser", command=self.refresh).pack(side="left")
        ttk.Button(top, text="Clôturer maintenant", command=self.close_now).pack(side="left", padx=(8, 0))
        ttk.Button(
            top,
            text="Planifier fin → 1er du mois",
            command=self.schedule_month,
        ).pack(side="left", padx=(8, 0))

        self.info = ttk.Label(self, justify="left")
        self.info.pack(anchor="w", pady=(10, 6))

        cols = ("id", "debut", "fin", "etat")
        self.tree = ttk.Treeview(self, columns=cols, show="headings", height=14)
        for col, label, w in (
            ("id", "ID", 48),
            ("debut", "Début", 160),
            ("fin", "Fin", 160),
            ("etat", "État", 100),
        ):
            self.tree.heading(col, text=label)
            self.tree.column(col, width=w, stretch=col in ("debut", "fin"))
        self.tree.pack(fill="both", expand=True)
        self.refresh()

    def refresh(self) -> None:
        for item in self.tree.get_children():
            self.tree.delete(item)
        try:
            rows = fetch_seasons(self.app.conn)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        now = season_clock_now()
        active_id = None
        for row in rows:
            etat = "clôturée" if int(row.get("cloturee") or 0) else (
                "ACTIVE" if str(row["fin"]) > now else "à clôturer"
            )
            if etat == "ACTIVE":
                active_id = row["id"]
            self.tree.insert(
                "",
                "end",
                values=(row["id"], row["debut"], row["fin"], etat),
            )
        self.info.config(
            text=(
                f"Horloge Paris : {now}\n"
                f"Prochain 1er du mois : {next_month_start()}\n"
                f"Durée nouvelle saison : {self.app.cfg.saison_duree_jours} jours\n"
                f"Active : {'#' + str(active_id) if active_id else '—'}"
            )
        )

    def close_now(self) -> None:
        if not messagebox.askyesno(
            "Clôturer",
            "Clôturer la saison active maintenant ?\n"
            "Podium + badges, puis ouverture de la suivante.\n"
            "(Push site : au prochain hit web éventuel)",
        ):
            return
        try:
            # Ferme aussi les saisons dont fin est passée ; force la courante si besoin
            result = close_expired_seasons(self.app.conn, self.app.cfg.saison_duree_jours)
            if not result.get("closed_ids"):
                result = force_close_active_season(self.app.conn, self.app.cfg.saison_duree_jours)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        active = result.get("active") or {}
        messagebox.showinfo(
            "OK",
            f"Clôturée(s) : {result.get('closed_ids')}\n"
            f"Active : #{active.get('id')} → {active.get('fin')}",
        )
        self.refresh()

    def schedule_month(self) -> None:
        fin = next_month_start()
        if not messagebox.askyesno("Planifier", f"Mettre la fin de saison à\n{fin} ?"):
            return
        try:
            season = schedule_season_end(self.app.conn, fin)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        messagebox.showinfo("OK", f"Saison #{season.get('id')} → {season.get('fin')}")
        self.refresh()


class ScoresTab(ttk.Frame):
    def __init__(self, parent: ttk.Notebook, app: AdminApp) -> None:
        super().__init__(parent, padding=12)
        self.app = app
        self.rows_by_id: dict[str, dict] = {}

        bar = ttk.Frame(self)
        bar.pack(fill="x")
        ttk.Button(bar, text="Actualiser", command=self.refresh).pack(side="left")
        ttk.Button(bar, text="Valider score sélectionné", command=self.save_score).pack(side="left", padx=(8, 0))
        ttk.Button(bar, text="Annuler le match", command=self.cancel_selected).pack(side="left", padx=(8, 0))
        ttk.Button(
            bar,
            text="Puis attribuer points (0 crédit)",
            command=self.score_points,
        ).pack(side="left", padx=(8, 0))

        form = ttk.Frame(self)
        form.pack(fill="x", pady=(8, 0))
        ttk.Label(
            form,
            text="Score si joué · sinon « Annuler le match » (0 pt, visible chez le joueur).",
            foreground="#555",
        ).pack(side="left")
        form2 = ttk.Frame(self)
        form2.pack(fill="x", pady=(6, 0))
        ttk.Label(form2, text="Domicile").pack(side="left")
        self.home_var = tk.StringVar(value="0")
        ttk.Entry(form2, textvariable=self.home_var, width=5).pack(side="left", padx=(4, 12))
        ttk.Label(form2, text="Extérieur").pack(side="left")
        self.away_var = tk.StringVar(value="0")
        ttk.Entry(form2, textvariable=self.away_var, width=5).pack(side="left", padx=(4, 0))

        cols = ("id", "quand", "match", "sport", "pronos")
        self.tree = ttk.Treeview(self, columns=cols, show="headings", height=16)
        for col, label, w in (
            ("id", "ID", 48),
            ("quand", "Coup d'envoi", 130),
            ("match", "Match", 320),
            ("sport", "Sport", 220),
            ("pronos", "Pronos", 60),
        ):
            self.tree.heading(col, text=label)
            self.tree.column(col, width=w, stretch=col == "match")
        self.tree.pack(fill="both", expand=True, pady=(8, 0))
        self.refresh()

    def refresh(self) -> None:
        for item in self.tree.get_children():
            self.tree.delete(item)
        self.rows_by_id.clear()
        try:
            rows = fetch_stuck_matches(self.app.conn)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        for row in rows:
            mid = str(row["id"])
            self.rows_by_id[mid] = row
            self.tree.insert(
                "",
                "end",
                iid=mid,
                values=(
                    row["id"],
                    row["date_match"],
                    f"{row['equipe_home']} – {row['equipe_away']}",
                    row.get("competition") or row.get("sport"),
                    row.get("pending_count"),
                ),
            )

    def selected_id(self) -> int | None:
        sel = self.tree.selection()
        return int(sel[0]) if sel else None

    def save_score(self) -> None:
        mid = self.selected_id()
        if mid is None:
            messagebox.showwarning("", "Sélectionnez un match.")
            return
        try:
            home = int(self.home_var.get().strip())
            away = int(self.away_var.get().strip())
        except ValueError:
            messagebox.showerror("", "Scores numériques requis.")
            return
        row = self.rows_by_id.get(str(mid), {})
        if not messagebox.askyesno(
            "Confirmer",
            f"{row.get('equipe_home')} {home} – {away} {row.get('equipe_away')} ?",
        ):
            return
        try:
            ok, msg = apply_manual_match_score(self.app.conn, mid, home, away)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        if ok:
            messagebox.showinfo("OK", msg)
            self.refresh()
        else:
            messagebox.showerror("Erreur", msg)

    def cancel_selected(self) -> None:
        mid = self.selected_id()
        if mid is None:
            messagebox.showwarning("", "Sélectionnez un match.")
            return
        row = self.rows_by_id.get(str(mid), {})
        if not messagebox.askyesno(
            "Annuler le match",
            f"Marquer comme annulé ?\n{row.get('equipe_home')} – {row.get('equipe_away')}\n\n"
            "Tous les pronos en attente passent à 0 pt (« Match annulé » chez le joueur).",
        ):
            return
        try:
            ok, msg = cancel_match(self.app.conn, mid)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        if ok:
            messagebox.showinfo("OK", msg)
            self.refresh()
        else:
            messagebox.showerror("Erreur", msg)

    def score_points(self) -> None:
        result = call_prognoz_sync(
            self.app.cfg.app_url, self.app.cfg.cron_secret, mode="score_local"
        )
        if result.get("ok"):
            messagebox.showinfo("OK", f"Points attribués — scored={result.get('scored')}")
        else:
            messagebox.showerror("Erreur", str(result.get("error") or result))


class MessagesTab(ttk.Frame):
    def __init__(self, parent: ttk.Notebook, app: AdminApp) -> None:
        super().__init__(parent, padding=12)
        self.app = app
        self.communities: list[dict] = []
        self.rows_by_id: dict[str, dict] = {}

        toolbar = ttk.Frame(self)
        toolbar.pack(fill="x")

        ttk.Label(toolbar, text="Communauté").pack(side="left")
        self.community_var = tk.StringVar()
        self.community_combo = ttk.Combobox(
            toolbar, textvariable=self.community_var, state="readonly", width=36
        )
        self.community_combo.pack(side="left", padx=(8, 12))
        self.community_combo.bind("<<ComboboxSelected>>", lambda _e: self.load_messages())

        ttk.Label(toolbar, text="Filtrer").pack(side="left")
        self.search_var = tk.StringVar()
        search_entry = ttk.Entry(toolbar, textvariable=self.search_var, width=22)
        search_entry.pack(side="left", padx=(6, 12))
        search_entry.bind("<Return>", lambda _e: self.load_messages())

        self.include_deleted = tk.BooleanVar(value=False)
        ttk.Checkbutton(
            toolbar,
            text="Supprimés",
            variable=self.include_deleted,
            command=self.load_messages,
        ).pack(side="left")

        ttk.Button(toolbar, text="Actualiser", command=self.refresh_all).pack(side="right")

        actions = ttk.Frame(self)
        actions.pack(fill="x", pady=(8, 0))
        ttk.Button(actions, text="Masquer (site)", command=self.do_hide).pack(side="left")
        ttk.Button(actions, text="Effacer BDD", command=self.do_purge).pack(side="left", padx=(8, 0))
        ttk.Button(actions, text="Restaurer", command=self.do_restore).pack(side="left", padx=(8, 0))

        paned = ttk.Panedwindow(self, orient="vertical")
        paned.pack(fill="both", expand=True, pady=(8, 0))

        tree_frame = ttk.Frame(paned)
        paned.add(tree_frame, weight=3)

        columns = ("id", "date", "pseudo", "contenu", "supprime")
        self.tree = ttk.Treeview(tree_frame, columns=columns, show="headings", height=16)
        self.tree.heading("id", text="ID")
        self.tree.heading("date", text="Date")
        self.tree.heading("pseudo", text="Pseudo")
        self.tree.heading("contenu", text="Message")
        self.tree.heading("supprime", text="Suppr.")
        self.tree.column("id", width=48, stretch=False)
        self.tree.column("date", width=128, stretch=False)
        self.tree.column("pseudo", width=110, stretch=False)
        self.tree.column("contenu", width=480)
        self.tree.column("supprime", width=44, stretch=False, anchor="center")

        scroll = ttk.Scrollbar(tree_frame, orient="vertical", command=self.tree.yview)
        self.tree.configure(yscrollcommand=scroll.set)
        self.tree.grid(row=0, column=0, sticky="nsew")
        scroll.grid(row=0, column=1, sticky="ns")
        tree_frame.columnconfigure(0, weight=1)
        tree_frame.rowconfigure(0, weight=1)
        self.tree.bind("<<TreeviewSelect>>", self.on_select)

        detail_frame = ttk.LabelFrame(paned, text="Message sélectionné", padding=8)
        paned.add(detail_frame, weight=1)
        self.detail = scrolledtext.ScrolledText(detail_frame, height=5, wrap="word", state="disabled")
        self.detail.pack(fill="both", expand=True)

        self.refresh_all()

    def refresh_all(self) -> None:
        try:
            self.communities = fetch_communities(self.app.conn, self.app.cfg.encryption_key)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return

        labels = []
        for c in self.communities:
            star = "★ " if c.get("est_generale") else ""
            labels.append(f"{star}{c['nom_decrypted']} ({c['msg_count']} msg)")

        self.community_combo["values"] = labels
        if labels and self.community_combo.current() < 0:
            self.community_combo.current(0)
        self.load_messages()

    def community_id(self) -> int | None:
        idx = self.community_combo.current()
        if idx < 0 or idx >= len(self.communities):
            return None
        return int(self.communities[idx]["id"])

    def load_messages(self) -> None:
        cid = self.community_id()
        if cid is None:
            return

        for item in self.tree.get_children():
            self.tree.delete(item)
        self.rows_by_id.clear()
        self.set_detail("")

        try:
            rows = fetch_messages(
                self.app.conn,
                self.app.cfg.encryption_key,
                cid,
                include_deleted=self.include_deleted.get(),
                search=self.search_var.get(),
            )
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return

        for row in rows:
            mid = str(row["id"])
            created = row["created_at"].strftime("%d/%m/%Y %H:%M") if row.get("created_at") else ""
            full = row.get("contenu_decrypted") or ""
            preview = full.replace("\n", " ")
            if len(preview) > 120:
                preview = preview[:117] + "..."
            self.rows_by_id[mid] = row
            self.tree.insert(
                "",
                "end",
                iid=mid,
                values=(row["id"], created, row["pseudo"], preview, "oui" if row.get("supprime") else ""),
            )

    def selected_id(self) -> int | None:
        sel = self.tree.selection()
        if not sel:
            return None
        return int(sel[0])

    def on_select(self, _event=None) -> None:
        mid = self.selected_id()
        if mid is None:
            return
        row = self.rows_by_id.get(str(mid))
        if not row:
            return
        created = row["created_at"].strftime("%d/%m/%Y %H:%M") if row.get("created_at") else ""
        text = (
            f"#{row['id']} — {row['pseudo']} — {created}\n"
            f"{'[SUPPRIMÉ] ' if row.get('supprime') else ''}"
            f"{row.get('contenu_decrypted') or ''}"
        )
        self.set_detail(text)

    def set_detail(self, text: str) -> None:
        self.detail.config(state="normal")
        self.detail.delete("1.0", "end")
        self.detail.insert("1.0", text)
        self.detail.config(state="disabled")

    def do_hide(self) -> None:
        mid = self.selected_id()
        if mid is None:
            messagebox.showwarning("", "Sélectionnez un message.")
            return
        row = self.rows_by_id.get(str(mid))
        preview = (row.get("contenu_decrypted") or "")[:200] if row else ""
        if not messagebox.askyesno(
            "Masquer",
            f"Masquer sur le site (reste chiffré en base) ?\n\n{preview}",
        ):
            return
        try:
            ok, msg = soft_delete_message(self.app.conn, mid)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        if not ok:
            messagebox.showerror("Erreur", msg)
            return
        messagebox.showinfo("OK", msg)
        self.load_messages()

    def do_purge(self) -> None:
        mid = self.selected_id()
        if mid is None:
            messagebox.showwarning("", "Sélectionnez un message.")
            return
        row = self.rows_by_id.get(str(mid))
        preview = (row.get("contenu_decrypted") or "")[:200] if row else ""
        if not messagebox.askyesno(
            "Effacer définitivement",
            f"SUPPRIMER DÉFINITIVEMENT ce message de la base ?\n\n{preview}",
        ):
            return
        try:
            ok, msg = hard_delete_message(self.app.conn, mid)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        if not ok:
            messagebox.showerror("Erreur", msg)
            return
        messagebox.showinfo("OK", msg)
        self.load_messages()

    def do_delete(self) -> None:
        self.do_hide()

    def do_restore(self) -> None:
        mid = self.selected_id()
        if mid is None:
            messagebox.showwarning("", "Sélectionnez un message.")
            return
        if not messagebox.askyesno("Restaurer", "Réafficher ce message sur le site ?"):
            return
        try:
            ok, msg = restore_message(self.app.conn, mid)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        if not ok:
            messagebox.showerror("Erreur", msg)
            return
        messagebox.showinfo("OK", msg)
        self.load_messages()


class UsersTab(ttk.Frame):
    def __init__(self, parent: ttk.Notebook, app: AdminApp) -> None:
        super().__init__(parent, padding=12)
        self.app = app
        self.selected_user_id: int | None = None

        search_row = ttk.Frame(self)
        search_row.pack(fill="x")
        ttk.Label(search_row, text="Recherche").pack(side="left")
        self.search_var = tk.StringVar()
        entry = ttk.Entry(search_row, textvariable=self.search_var, width=36)
        entry.pack(side="left", padx=(8, 8))
        entry.bind("<Return>", lambda _e: self.do_search())
        ttk.Button(search_row, text="Chercher", command=self.do_search).pack(side="left")

        columns = ("id", "pseudo", "email", "actif", "points", "created")
        self.tree = ttk.Treeview(self, columns=columns, show="headings", height=11)
        for col, label, width in (
            ("id", "ID", 48),
            ("pseudo", "Pseudo", 120),
            ("email", "E-mail", 240),
            ("actif", "Actif", 50),
            ("points", "Points", 60),
            ("created", "Inscrit", 100),
        ):
            self.tree.heading(col, text=label)
            self.tree.column(col, width=width, stretch=col == "email")
        self.tree.pack(fill="both", expand=True, pady=(10, 10))
        self.tree.bind("<<TreeviewSelect>>", self.on_select)

        actions = ttk.LabelFrame(self, text="Actions", padding=10)
        actions.pack(fill="x")

        self.user_label = ttk.Label(actions, text="—")
        self.user_label.pack(anchor="w", pady=(0, 8))

        row1 = ttk.Frame(actions)
        row1.pack(fill="x", pady=(0, 8))
        ttk.Button(row1, text="Désactiver le compte", command=lambda: self.toggle_active(False)).pack(side="left")
        ttk.Button(row1, text="Réactiver", command=lambda: self.toggle_active(True)).pack(side="left", padx=(8, 0))

        row2 = ttk.Frame(actions)
        row2.pack(fill="x")
        ttk.Label(row2, text="Nouveau MDP").pack(side="left")
        self.pwd_var = tk.StringVar()
        ttk.Entry(row2, textvariable=self.pwd_var, show="•", width=24).pack(side="left", padx=(8, 8))
        ttk.Label(row2, text="Confirmer").pack(side="left")
        self.pwd2_var = tk.StringVar()
        ttk.Entry(row2, textvariable=self.pwd2_var, show="•", width=24).pack(side="left", padx=(8, 8))
        ttk.Button(row2, text="Réinitialiser", command=self.do_reset).pack(side="left")

        row3 = ttk.Frame(actions)
        row3.pack(fill="x", pady=(10, 0))
        ttk.Label(row3, text="Points (±)").pack(side="left")
        self.points_var = tk.StringVar(value="10")
        ttk.Entry(row3, textvariable=self.points_var, width=8).pack(side="left", padx=(8, 8))
        self.points_season_var = tk.BooleanVar(value=True)
        ttk.Checkbutton(
            row3,
            text="Aussi saison en cours",
            variable=self.points_season_var,
        ).pack(side="left", padx=(0, 8))
        ttk.Button(row3, text="Attribuer / retirer", command=self.do_grant_points).pack(side="left")

    def do_search(self) -> None:
        for item in self.tree.get_children():
            self.tree.delete(item)
        self.selected_user_id = None
        self.user_label.config(text="—")

        try:
            rows = search_users(self.app.conn, self.search_var.get())
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return

        for row in rows:
            created = row["created_at"].strftime("%d/%m/%Y") if row.get("created_at") else ""
            self.tree.insert(
                "",
                "end",
                iid=str(row["id"]),
                values=(
                    row["id"],
                    row["pseudo"],
                    row["email"],
                    "oui" if row.get("actif") else "non",
                    row.get("points_totaux", 0),
                    created,
                ),
            )

    def on_select(self, _event=None) -> None:
        sel = self.tree.selection()
        if not sel:
            return
        self.selected_user_id = int(sel[0])
        v = self.tree.item(sel[0], "values")
        self.user_label.config(text=f"{v[1]} · {v[2]}")

    def toggle_active(self, active: bool) -> None:
        if self.selected_user_id is None:
            messagebox.showwarning("", "Sélectionnez un utilisateur.")
            return
        v = self.tree.item(str(self.selected_user_id), "values")
        verb = "Réactiver" if active else "Désactiver"
        if not messagebox.askyesno(verb, f"{verb} « {v[1]} » ?"):
            return
        try:
            set_user_active(self.app.conn, self.selected_user_id, active)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        self.do_search()

    def do_reset(self) -> None:
        if self.selected_user_id is None:
            messagebox.showwarning("", "Sélectionnez un utilisateur.")
            return
        pwd, pwd2 = self.pwd_var.get(), self.pwd2_var.get()
        if pwd != pwd2:
            messagebox.showerror("", "Les mots de passe diffèrent.")
            return
        if len(pwd) < 8:
            messagebox.showerror("", "Minimum 8 caractères.")
            return

        v = self.tree.item(str(self.selected_user_id), "values")
        if not messagebox.askyesno("Confirmer", f"Nouveau mot de passe pour « {v[1]} » ?"):
            return

        try:
            reset_user_password(self.app.conn, self.selected_user_id, pwd)
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return

        self.pwd_var.set("")
        self.pwd2_var.set("")
        messagebox.showinfo("", "Mot de passe mis à jour.")

    def do_grant_points(self) -> None:
        if self.selected_user_id is None:
            messagebox.showwarning("", "Sélectionnez un utilisateur.")
            return
        try:
            delta = int(self.points_var.get().strip())
        except ValueError:
            messagebox.showerror("", "Nombre entier requis (ex. 10 ou -5).")
            return
        v = self.tree.item(str(self.selected_user_id), "values")
        sign = f"+{delta}" if delta > 0 else str(delta)
        season = "saison + total" if self.points_season_var.get() else "total seulement"
        if not messagebox.askyesno(
            "Points",
            f"{sign} pt(s) pour « {v[1]} » ({season}) ?",
        ):
            return
        try:
            ok, msg = grant_user_points(
                self.app.conn,
                self.selected_user_id,
                delta,
                to_season=self.points_season_var.get(),
            )
        except Exception as exc:
            messagebox.showerror("Erreur", str(exc))
            return
        if ok:
            messagebox.showinfo("OK", msg)
            self.do_search()
        else:
            messagebox.showerror("Erreur", msg)


def main() -> None:
    os.chdir(Path(__file__).resolve().parent)

    try:
        env = load_merged_env()
        if not verify_admin_pin(env):
            sys.exit(1)
        cfg = load_config()
    except Exception as exc:
        root = tk.Tk()
        root.withdraw()
        messagebox.showerror("Configuration", str(exc))
        sys.exit(1)

    try:
        app = AdminApp(cfg)
    except Exception as exc:
        root = tk.Tk()
        root.withdraw()
        messagebox.showerror("MySQL", str(exc))
        sys.exit(1)

    app.mainloop()


if __name__ == "__main__":
    main()
