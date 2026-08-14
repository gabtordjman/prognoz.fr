from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Any

import bcrypt
import mysql.connector
from mysql.connector import Error as MySQLError
from mysql.connector.connection import MySQLConnection

from prognoz_crypto import decrypt_sensitive, encryption_key_from_env

PODIUM_BONUS = {1: 5, 2: 3, 3: 1}
PODIUM_LABELS = {1: "Badge Or", 2: "Badge Argent", 3: "Badge Bronze"}

_PARIS_TZ = None


def paris_tz():
    """Europe/Paris — sur Windows il faut le paquet `tzdata` (voir requirements.txt)."""
    global _PARIS_TZ
    if _PARIS_TZ is not None:
        return _PARIS_TZ
    try:
        from zoneinfo import ZoneInfo

        _PARIS_TZ = ZoneInfo("Europe/Paris")
        return _PARIS_TZ
    except Exception:
        # Fallback sans base IANA : UTC+2 en été, UTC+1 en hiver (approx. UE).
        # Préférer : pip install tzdata
        now_utc = datetime.now(timezone.utc)
        year = now_utc.year
        # Dernier dimanche de mars 01:00 UTC → dernier dimanche d'octobre 01:00 UTC
        march = datetime(year, 3, 31, tzinfo=timezone.utc)
        while march.weekday() != 6:
            march -= timedelta(days=1)
        october = datetime(year, 10, 31, tzinfo=timezone.utc)
        while october.weekday() != 6:
            october -= timedelta(days=1)
        offset = timedelta(hours=2) if march <= now_utc < october else timedelta(hours=1)
        _PARIS_TZ = timezone(offset)
        return _PARIS_TZ


@dataclass(frozen=True)
class DbConfig:
    host: str
    name: str
    user: str
    password: str
    encryption_key_b64: str
    port: int = 3306
    app_url: str = ""
    odds_api_key: str = ""
    odds_api_key_source: str = ""
    cron_secret: str = ""
    saison_duree_jours: int = 14

    @property
    def encryption_key(self) -> bytes:
        return encryption_key_from_env(self.encryption_key_b64)


def season_clock_now() -> str:
    """Horloge métier des saisons = Europe/Paris (pas MySQL NOW())."""
    return datetime.now(paris_tz()).strftime("%Y-%m-%d %H:%M:%S")


def next_month_start() -> str:
    now = datetime.now(paris_tz())
    if now.month == 12:
        nxt = now.replace(year=now.year + 1, month=1, day=1, hour=0, minute=0, second=0, microsecond=0)
    else:
        nxt = now.replace(month=now.month + 1, day=1, hour=0, minute=0, second=0, microsecond=0)
    return nxt.strftime("%Y-%m-%d %H:%M:%S")


def connect(cfg: DbConfig) -> MySQLConnection:
    conn = mysql.connector.connect(
        host=cfg.host,
        port=cfg.port,
        database=cfg.name,
        user=cfg.user,
        password=cfg.password,
        charset="utf8mb4",
        use_unicode=True,
        autocommit=False,
    )
    return conn


def connect_with_hint(cfg: DbConfig) -> MySQLConnection:
    try:
        return connect(cfg)
    except MySQLError as exc:
        if exc.errno == 1045:
            raise ConnectionError(
                "Accès MySQL refusé.\n\n"
                f"Tentative : {cfg.user}@{cfg.host}:{cfg.port}/{cfg.name}\n\n"
                "Tunnel SSH :\n"
                "  ssh -L 3307:127.0.0.1:3306 root@213.156.133.126\n"
                "Puis .env.local avec DB_HOST=127.0.0.1 et DB_PORT=3307"
            ) from exc
        if exc.errno == 2003:
            raise ConnectionError(
                f"MySQL injoignable ({cfg.host}:{cfg.port}).\n"
                "Le tunnel SSH est-il ouvert ?"
            ) from exc
        raise


def fetch_stats(conn: MySQLConnection) -> dict[str, Any]:
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT COUNT(*) AS n FROM users WHERE actif = 1")
    users = int(cur.fetchone()["n"])
    cur.execute("SELECT COUNT(*) AS n FROM communities")
    communities = int(cur.fetchone()["n"])
    cur.execute("SELECT COUNT(*) AS n FROM community_messages WHERE supprime = 0")
    messages = int(cur.fetchone()["n"])
    cur.execute(
        "SELECT COUNT(*) AS n FROM community_messages "
        "WHERE supprime = 0 AND created_at >= NOW() - INTERVAL 24 HOUR"
    )
    messages_24h = int(cur.fetchone()["n"])
    pending = fetch_pending_stats(conn)
    season = fetch_active_season(conn)
    cur.close()
    return {
        "users": users,
        "communities": communities,
        "messages": messages,
        "messages_24h": messages_24h,
        "pending": pending,
        "season": season,
    }


def fetch_pending_stats(conn: MySQLConnection) -> dict[str, int]:
    cur = conn.cursor(dictionary=True)
    cur.execute(
        """
        SELECT
            COUNT(*) AS pending,
            SUM(CASE WHEN m.date_match < UTC_TIMESTAMP() THEN 1 ELSE 0 END) AS stuck,
            COUNT(DISTINCT p.user_id) AS users
        FROM predictions p
        INNER JOIN prediction_markets pm ON pm.id = p.market_id
        INNER JOIN matches m ON m.id = pm.match_id
        WHERE p.statut = 'en_attente'
        """
    )
    row = cur.fetchone() or {}
    cur.close()
    return {
        "pending": int(row.get("pending") or 0),
        "stuck": int(row.get("stuck") or 0),
        "users": int(row.get("users") or 0),
    }


def fetch_pending_by_sport(conn: MySQLConnection, ready_minutes: int = 150) -> list[dict[str, Any]]:
    cur = conn.cursor(dictionary=True)
    cur.execute(
        f"""
        SELECT m.sport,
               COUNT(DISTINCT m.id) AS matchs,
               COUNT(p.id) AS pronos,
               MIN(m.date_match) AS plus_ancien,
               MAX(m.date_match) AS plus_recent
        FROM matches m
        INNER JOIN prediction_markets pm ON pm.match_id = m.id
        INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
        WHERE m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {int(ready_minutes)} MINUTE)
          AND m.statut <> 'annule'
          AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
        GROUP BY m.sport
        HAVING pronos > 0
        ORDER BY pronos DESC
        """
    )
    rows = cur.fetchall()
    cur.close()
    return rows


def fetch_stuck_matches(conn: MySQLConnection, limit: int = 40, ready_minutes: int = 150) -> list[dict[str, Any]]:
    cur = conn.cursor(dictionary=True)
    cur.execute(
        f"""
        SELECT m.id, m.sport, m.competition, m.equipe_home, m.equipe_away, m.date_match,
               COUNT(p.id) AS pending_count
        FROM matches m
        INNER JOIN prediction_markets pm ON pm.match_id = m.id
        INNER JOIN predictions p ON p.market_id = pm.id AND p.statut = 'en_attente'
        WHERE m.date_match < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {int(ready_minutes)} MINUTE)
          AND m.statut <> 'annule'
          AND (m.resultat_1x2 IS NULL OR m.resultat_1x2 = '')
        GROUP BY m.id
        ORDER BY m.date_match ASC
        LIMIT %s
        """,
        (max(1, limit),),
    )
    rows = cur.fetchall()
    cur.close()
    return rows


def apply_manual_match_score(
    conn: MySQLConnection, match_id: int, score_home: int, score_away: int
) -> tuple[bool, str]:
    if score_home < 0 or score_away < 0 or score_home > 99 or score_away > 99:
        return False, "Score invalide."

    cur = conn.cursor(dictionary=True)
    try:
        cur.execute("SELECT id, sport, resultat_1x2 FROM matches WHERE id = %s", (match_id,))
        match = cur.fetchone()
        if not match:
            return False, "Match introuvable."
        if match.get("resultat_1x2") not in (None, ""):
            return False, "Ce match a déjà un résultat."
        if match.get("statut") == "annule":
            return False, "Ce match est déjà marqué annulé."

        sport = str(match.get("sport") or "")
        has_draw = sport.startswith("soccer_")
        if score_home > score_away:
            result = "1"
        elif score_home < score_away:
            result = "2"
        elif has_draw:
            result = "N"
        else:
            return False, (
                "Score nul impossible pour ce sport (tennis/basket). "
                "Utilisez « Annuler le match » si la rencontre n’a pas eu lieu."
            )

        cur.execute(
            """
            UPDATE matches
            SET statut = 'termine', resultat_1x2 = %s, score_home = %s, score_away = %s
            WHERE id = %s AND (resultat_1x2 IS NULL OR resultat_1x2 = '') AND statut <> 'annule'
            """,
            (result, score_home, score_away, match_id),
        )
        if cur.rowcount < 1:
            conn.rollback()
            return False, "Résultat déjà enregistré entre-temps."
        conn.commit()
        return True, f"Score {score_home}-{score_away} ({result}) enregistré — lancez « Attribuer points (0 crédit) »."
    except MySQLError as exc:
        conn.rollback()
        return False, str(exc)
    finally:
        cur.close()


def cancel_match(conn: MySQLConnection, match_id: int) -> tuple[bool, str]:
    """Marque le match annulé et passe tous les pronos en attente à « annule » (0 pt)."""
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute("SELECT id, statut, resultat_1x2, equipe_home, equipe_away FROM matches WHERE id = %s", (match_id,))
        match = cur.fetchone()
        if not match:
            return False, "Match introuvable."
        if match.get("resultat_1x2") not in (None, ""):
            return False, "Ce match a déjà un score — impossible d’annuler."
        if match.get("statut") == "annule":
            # Idempotent : void leftover pending if any
            pass

        cur.execute(
            """
            UPDATE matches
            SET statut = 'annule', score_home = NULL, score_away = NULL
            WHERE id = %s AND (resultat_1x2 IS NULL OR resultat_1x2 = '')
            """,
            (match_id,),
        )
        cur.execute(
            """
            UPDATE predictions p
            INNER JOIN prediction_markets pm ON pm.id = p.market_id
            SET p.statut = 'annule', p.points_gagnes = 0, p.resolved_at = UTC_TIMESTAMP()
            WHERE pm.match_id = %s AND p.statut = 'en_attente'
            """,
            (match_id,),
        )
        voided = cur.rowcount
        conn.commit()
        label = f"{match.get('equipe_home')} – {match.get('equipe_away')}"
        return True, f"Match annulé : {label} · {voided} prono(s) à 0 pt."
    except MySQLError as exc:
        conn.rollback()
        return False, str(exc)
    finally:
        cur.close()


def fetch_seasons(conn: MySQLConnection, limit: int = 12) -> list[dict[str, Any]]:
    cur = conn.cursor(dictionary=True)
    cur.execute(
        "SELECT id, debut, fin, cloturee FROM seasons ORDER BY id DESC LIMIT %s",
        (max(1, limit),),
    )
    rows = cur.fetchall()
    cur.close()
    return rows


def fetch_active_season(conn: MySQLConnection) -> dict[str, Any] | None:
    now = season_clock_now()
    cur = conn.cursor(dictionary=True)
    cur.execute(
        """
        SELECT id, debut, fin, cloturee FROM seasons
        WHERE cloturee = 0 AND fin > %s
        ORDER BY debut DESC LIMIT 1
        """,
        (now,),
    )
    row = cur.fetchone()
    cur.close()
    return row


def schedule_season_end(conn: MySQLConnection, fin: str) -> dict[str, Any]:
    if len(fin) == 16:
        fin = fin + ":00"
    datetime.strptime(fin, "%Y-%m-%d %H:%M:%S")
    now = season_clock_now()
    if fin <= now:
        raise ValueError("La fin doit être dans le futur.")

    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(
            "SELECT id, debut, fin, cloturee FROM seasons WHERE cloturee = 0 ORDER BY debut DESC LIMIT 1"
        )
        active = cur.fetchone()
        if not active:
            cur.execute(
                "INSERT INTO seasons (debut, fin, cloturee) VALUES (%s, %s, 0)",
                (now, fin),
            )
        else:
            cur.execute(
                "UPDATE seasons SET fin = %s WHERE id = %s AND cloturee = 0",
                (fin, int(active["id"])),
            )
        conn.commit()
        return fetch_active_season(conn) or {}
    except MySQLError:
        conn.rollback()
        raise
    finally:
        cur.close()


def _award_season_podium(conn: MySQLConnection, season_id: int) -> int:
    cur = conn.cursor(dictionary=True)
    awarded = 0
    try:
        cur.execute("SELECT id FROM communities")
        communities = [int(r["id"]) for r in cur.fetchall()]
        for community_id in communities:
            cur.execute(
                """
                SELECT user_id, points FROM season_scores
                WHERE season_id = %s AND community_id = %s
                ORDER BY points DESC, user_id ASC
                LIMIT 3
                """,
                (season_id, community_id),
            )
            rows = cur.fetchall()
            rank = 0
            for row in rows:
                rank += 1
                if rank > 3 or int(row["points"] or 0) <= 0:
                    break
                user_id = int(row["user_id"])
                bonus = int(PODIUM_BONUS.get(rank, 0))
                label = PODIUM_LABELS.get(rank, f"Top {rank}")
                if bonus > 0:
                    cur.execute(
                        """
                        UPDATE season_scores SET points = points + %s
                        WHERE season_id = %s AND community_id = %s AND user_id = %s
                        """,
                        (bonus, season_id, community_id, user_id),
                    )
                cur.execute(
                    """
                    SELECT id FROM season_rewards
                    WHERE season_id = %s AND community_id = %s AND user_id = %s AND classement = %s
                    """,
                    (season_id, community_id, user_id, rank),
                )
                if not cur.fetchone():
                    cur.execute(
                        """
                        INSERT INTO season_rewards
                            (season_id, community_id, user_id, classement, recompense)
                        VALUES (%s, %s, %s, %s, %s)
                        """,
                        (season_id, community_id, user_id, rank, label),
                    )
                    awarded += 1
        return awarded
    finally:
        cur.close()


def close_expired_seasons(conn: MySQLConnection, saison_duree_jours: int = 14) -> dict[str, Any]:
    """
    Équivalent Python de maintainSeasons() — podium + clôture + nouvelle saison.
    (Push Web non envoyée depuis la console ; le site le fera au prochain hit si besoin.)
    """
    now = season_clock_now()
    cur = conn.cursor(dictionary=True)
    closed_ids: list[int] = []
    try:
        cur.execute(
            "SELECT id FROM seasons WHERE cloturee = 0 AND fin <= %s ORDER BY fin ASC",
            (now,),
        )
        for row in cur.fetchall():
            sid = int(row["id"])
            cur.execute("SELECT cloturee FROM seasons WHERE id = %s", (sid,))
            st = cur.fetchone()
            if not st or int(st["cloturee"] or 0) == 1:
                continue
            _award_season_podium(conn, sid)
            cur.execute("UPDATE seasons SET cloturee = 1 WHERE id = %s", (sid,))
            closed_ids.append(sid)

        cur.execute(
            """
            SELECT id, debut, fin, cloturee FROM seasons
            WHERE cloturee = 0 AND fin > %s
            ORDER BY debut DESC LIMIT 1
            """,
            (now,),
        )
        active = cur.fetchone()
        created = False
        if not active:
            fin_dt = datetime.strptime(now, "%Y-%m-%d %H:%M:%S") + timedelta(days=saison_duree_jours)
            fin = fin_dt.strftime("%Y-%m-%d %H:%M:%S")
            cur.execute(
                "INSERT INTO seasons (debut, fin, cloturee) VALUES (%s, %s, 0)",
                (now, fin),
            )
            created = True
            cur.execute(
                """
                SELECT id, debut, fin, cloturee FROM seasons
                WHERE cloturee = 0 AND fin > %s
                ORDER BY debut DESC LIMIT 1
                """,
                (now,),
            )
            active = cur.fetchone()

        conn.commit()
        return {"closed_ids": closed_ids, "active": active, "created": created, "now": now}
    except MySQLError:
        conn.rollback()
        raise
    finally:
        cur.close()


def force_close_active_season(conn: MySQLConnection, saison_duree_jours: int = 14) -> dict[str, Any]:
    """Met fin=maintenant sur la saison ouverte puis lance close_expired_seasons."""
    now = season_clock_now()
    cur = conn.cursor()
    try:
        cur.execute(
            "UPDATE seasons SET fin = %s WHERE cloturee = 0 AND fin > %s",
            (now, now),
        )
        conn.commit()
    except MySQLError:
        conn.rollback()
        raise
    finally:
        cur.close()
    return close_expired_seasons(conn, saison_duree_jours)


def fetch_communities(conn: MySQLConnection, key: bytes) -> list[dict[str, Any]]:
    cur = conn.cursor(dictionary=True)
    cur.execute(
        """
        SELECT c.id, c.nom, c.est_generale, c.created_at,
               (SELECT COUNT(*) FROM community_messages m
                WHERE m.community_id = c.id AND m.supprime = 0) AS msg_count
        FROM communities c
        ORDER BY c.est_generale DESC, c.id ASC
        """
    )
    rows = cur.fetchall()
    cur.close()

    for row in rows:
        row["nom_decrypted"] = decrypt_sensitive(row["nom"], key) or f"#{row['id']}"
    return rows


def fetch_messages(
    conn: MySQLConnection,
    key: bytes,
    community_id: int,
    *,
    limit: int = 200,
    include_deleted: bool = False,
    search: str = "",
) -> list[dict[str, Any]]:
    sql = """
        SELECT m.id, m.community_id, m.user_id, m.contenu, m.supprime, m.created_at, u.pseudo
        FROM community_messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.community_id = %s
    """
    params: list[Any] = [community_id]
    if not include_deleted:
        sql += " AND m.supprime = 0"
    sql += " ORDER BY m.created_at DESC LIMIT %s"
    params.append(limit)

    cur = conn.cursor(dictionary=True)
    cur.execute(sql, params)
    rows = cur.fetchall()
    cur.close()

    search_l = search.strip().lower()
    out: list[dict[str, Any]] = []
    for row in rows:
        row["contenu_decrypted"] = decrypt_sensitive(row["contenu"], key)
        if search_l:
            hay = f"{row['pseudo']} {row['contenu_decrypted']}".lower()
            if search_l not in hay:
                continue
        out.append(row)
    return out


def soft_delete_message(conn: MySQLConnection, message_id: int) -> tuple[bool, str]:
    cur = conn.cursor()
    try:
        cur.execute(
            "UPDATE community_messages SET supprime = 1 WHERE id = %s",
            (message_id,),
        )
        conn.commit()
        cur.execute("SELECT supprime FROM community_messages WHERE id = %s", (message_id,))
        row = cur.fetchone()
        if row is None:
            return False, "Message introuvable (vérifiez la connexion à la base prod)."
        if int(row[0]) != 1:
            return False, "La colonne supprime n'a pas été mise à jour."
        return True, "Masqué sur le site (données chiffrées conservées en base)."
    except MySQLError as exc:
        conn.rollback()
        return False, str(exc)
    finally:
        cur.close()


def hard_delete_message(conn: MySQLConnection, message_id: int) -> tuple[bool, str]:
    cur = conn.cursor()
    try:
        cur.execute("DELETE FROM community_messages WHERE id = %s", (message_id,))
        conn.commit()
        if cur.rowcount < 1:
            return False, "Message introuvable ou déjà effacé."
        return True, "Message effacé définitivement de la base."
    except MySQLError as exc:
        conn.rollback()
        return False, str(exc)
    finally:
        cur.close()


def restore_message(conn: MySQLConnection, message_id: int) -> tuple[bool, str]:
    cur = conn.cursor()
    try:
        cur.execute(
            "UPDATE community_messages SET supprime = 0 WHERE id = %s",
            (message_id,),
        )
        conn.commit()
        cur.execute("SELECT supprime FROM community_messages WHERE id = %s", (message_id,))
        row = cur.fetchone()
        if row is None:
            return False, "Message introuvable."
        if int(row[0]) != 0:
            return False, "Restauration échouée."
        return True, "Message réaffiché sur le site."
    except MySQLError as exc:
        conn.rollback()
        return False, str(exc)
    finally:
        cur.close()


def search_users(conn: MySQLConnection, query: str) -> list[dict[str, Any]]:
    q = query.strip()
    if not q:
        return []

    like = f"%{q}%"
    cur = conn.cursor(dictionary=True)
    cur.execute(
        """
        SELECT id, pseudo, email, actif, points_totaux, created_at
        FROM users
        WHERE pseudo LIKE %s OR email LIKE %s
        ORDER BY pseudo ASC
        LIMIT 50
        """,
        (like, like),
    )
    rows = cur.fetchall()
    cur.close()
    return rows


def set_user_active(conn: MySQLConnection, user_id: int, active: bool) -> None:
    cur = conn.cursor()
    cur.execute("UPDATE users SET actif = %s WHERE id = %s", (1 if active else 0, user_id))
    conn.commit()
    cur.close()


def reset_user_password(conn: MySQLConnection, user_id: int, new_password: str) -> None:
    if len(new_password) < 8:
        raise ValueError("Minimum 8 caractères.")

    password_hash = bcrypt.hashpw(new_password.encode("utf-8"), bcrypt.gensalt()).decode("ascii")
    cur = conn.cursor()
    cur.execute("UPDATE users SET password_hash = %s WHERE id = %s", (password_hash, user_id))
    conn.commit()
    cur.close()


def grant_user_points(
    conn: MySQLConnection,
    user_id: int,
    delta: int,
    *,
    to_season: bool = True,
) -> tuple[bool, str]:
    """Ajoute ou retire des points (total + optionnellement saison en cours)."""
    if delta == 0:
        return False, "Indique un nombre de points non nul."
    if abs(delta) > 10000:
        return False, "Maximum ±10 000 points par opération."

    cur = conn.cursor(dictionary=True)
    try:
        cur.execute("SELECT id, pseudo, points_totaux FROM users WHERE id = %s", (user_id,))
        user = cur.fetchone()
        if not user:
            return False, "Joueur introuvable."

        cur.execute(
            "UPDATE users SET points_totaux = GREATEST(0, points_totaux + %s) WHERE id = %s",
            (delta, user_id),
        )

        season_note = ""
        if to_season:
            now = season_clock_now()
            cur.execute(
                """
                SELECT id FROM seasons
                WHERE cloturee = 0 AND fin > %s
                ORDER BY debut DESC LIMIT 1
                """,
                (now,),
            )
            season = cur.fetchone()
            if season:
                season_id = int(season["id"])
                cur.execute(
                    "SELECT community_id FROM community_members WHERE user_id = %s",
                    (user_id,),
                )
                communities = [int(r["community_id"]) for r in cur.fetchall()]
                for community_id in communities:
                    cur.execute(
                        """
                        INSERT INTO season_scores (season_id, community_id, user_id, points)
                        VALUES (%s, %s, %s, GREATEST(0, %s))
                        ON DUPLICATE KEY UPDATE points = GREATEST(0, points + %s)
                        """,
                        (season_id, community_id, user_id, delta, delta),
                    )
                season_note = " + saison"
            else:
                season_note = " (pas de saison active)"

        cur.execute("SELECT points_totaux FROM users WHERE id = %s", (user_id,))
        after = cur.fetchone()
        conn.commit()
        total = int((after or {}).get("points_totaux") or 0)
        sign = f"+{delta}" if delta > 0 else str(delta)
        return True, f"{user['pseudo']} : {sign} pt(s){season_note} → total {total}."
    except MySQLError as exc:
        conn.rollback()
        return False, str(exc)
    finally:
        cur.close()
