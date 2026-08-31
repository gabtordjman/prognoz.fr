<?php
if (!defined('APP_BOOT')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}

/**
 * Mutations admin partagées (panel web, CLI).
 *
 * @return array{ok:bool,type:string,message:string,payload:array}
 */
function adminActionResult(bool $ok, string $message, array $payload = [], string $type = ''): array
{
    return [
        'ok'      => $ok,
        'type'    => $type !== '' ? $type : ($ok ? 'success' : 'error'),
        'message' => $message,
        'payload' => $payload,
    ];
}

/**
 * @param array<string,mixed> $p
 * @return array{ok:bool,type:string,message:string,payload:array}
 */
function adminRunAction(PDO $pdo, string $action, array $p = []): array
{
    try {
        return match ($action) {
            'grant_points' => adminActionGrantPoints($pdo, $p),
            'set_active' => adminActionSetActive($pdo, $p),
            'set_mail_opt_out' => adminActionSetMailOptOut($pdo, $p),
            'reset_password' => adminActionResetPassword($pdo, $p),
            'remove_avatar' => adminActionRemoveAvatar($pdo, $p),
            'manual_score' => adminActionManualScore($pdo, $p),
            'clear_match_score' => adminActionClearMatchScore($pdo, $p),
            'cancel_match' => adminActionCancelMatch($pdo, $p),
            'postpone_match' => adminActionPostponeMatch($pdo, $p),
            'postpone_set_date' => adminActionPostponeSetDate($pdo, $p),
            'postpone_reactivate' => adminActionPostponeReactivate($pdo, $p),
            'score_local' => adminActionScoreLocal($pdo, $p),
            'catchup_scores' => adminActionCatchupScores($pdo),
            'recover_postponed_scores' => adminActionRecoverPostponed($pdo),
            'dismiss_empty_postponed' => adminActionDismissEmptyPostponed($pdo),
            'reactivate_future_postponed' => adminActionReactivateFuturePostponed($pdo),
            'probe_quota' => adminActionProbeQuota($p),
            'prune' => adminActionPrune($pdo),
            'clear_lock' => adminActionClearLock(),
            'cron' => adminActionCron($pdo),
            'matches' => adminActionMatchesSync($pdo),
            'odds' => adminActionOddsSync($pdo),
            'soft_delete' => adminActionMessageSoftDelete($pdo, $p),
            'restore' => adminActionMessageRestore($pdo, $p),
            'hard_delete' => adminActionMessageHardDelete($pdo, $p),
            'close_now' => adminActionSeasonCloseNow($pdo),
            'schedule_month' => adminActionSeasonScheduleMonth($pdo),
            'schedule_custom' => adminActionSeasonScheduleCustom($pdo, $p),
            'event_save' => adminActionEventSave($pdo, $p),
            'event_toggle' => adminActionEventToggle($pdo, $p),
            'event_publish' => adminActionEventPublish($pdo, $p),
            'event_notify' => adminActionEventNotify($pdo, $p),
            'event_delete' => adminActionEventDelete($pdo, $p),
            'ann_save' => adminActionAnnSave($pdo, $p),
            'ann_publish' => adminActionAnnPublish($pdo, $p),
            'ann_delete' => adminActionAnnDelete($pdo, $p),
            'report_unavailable' => adminActionReportUnavailable($pdo),
            'report_month' => adminActionReportMonth($pdo),
            default => adminActionResult(false, 'Action inconnue.'),
        };
    } catch (InvalidArgumentException $e) {
        return adminActionResult(false, $e->getMessage());
    } catch (Throwable $e) {
        return adminActionResult(false, 'Erreur : ' . $e->getMessage());
    }
}

function adminFindUserByPseudo(PDO $pdo, string $pseudo): ?array
{
    $pseudo = trim($pseudo);
    if ($pseudo === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, pseudo, points_totaux, actif FROM users WHERE pseudo = ? LIMIT 1'
    );
    $stmt->execute([$pseudo]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function adminRequireUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, pseudo FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException('Joueur introuvable.');
    }

    return $row;
}

/** @param array<string,mixed> $p */
function adminActionGrantPoints(PDO $pdo, array $p): array
{
    $userId = (int) ($p['user_id'] ?? 0);
    if ($userId < 1) {
        $target = adminFindUserByPseudo($pdo, (string) ($p['pseudo'] ?? ''));
        if (!$target) {
            throw new InvalidArgumentException('Pseudo introuvable.');
        }
        $userId = (int) $target['id'];
    }
    $r = grantUserPoints(
        $pdo,
        $userId,
        (int) ($p['delta'] ?? 0),
        !array_key_exists('to_season', $p) || !empty($p['to_season'])
    );
    $sign = $r['delta'] > 0 ? '+' . $r['delta'] : (string) $r['delta'];

    return adminActionResult(
        true,
        $r['pseudo'] . ' : ' . $sign . ' pt(s)'
        . ($r['season'] ? ' (saison + total)' : ' (total)')
        . ' → ' . $r['points_totaux'] . ' pts totaux.',
        $r
    );
}

/** @param array<string,mixed> $p */
function adminActionSetActive(PDO $pdo, array $p): array
{
    $target = adminRequireUser($pdo, (int) ($p['user_id'] ?? 0));
    $active = !empty($p['actif']) ? 1 : 0;
    $pdo->prepare('UPDATE users SET actif = ? WHERE id = ?')->execute([$active, (int) $target['id']]);

    return adminActionResult(
        true,
        $target['pseudo'] . ($active ? ' réactivé.' : ' désactivé (ne peut plus se connecter).')
    );
}

/** @param array<string,mixed> $p */
function adminActionSetMailOptOut(PDO $pdo, array $p): array
{
    ensureMailPrefsSchema($pdo);
    $target = adminRequireUser($pdo, (int) ($p['user_id'] ?? 0));
    $optOut = !empty($p['mail_opt_out']);
    setUserMailOptOut($pdo, (int) $target['id'], $optOut);

    return adminActionResult(
        true,
        $target['pseudo'] . ($optOut
            ? ' désinscrit des e-mails (rappels / bilans).'
            : ' réinscrit aux e-mails.')
    );
}

/** @param array<string,mixed> $p */
function adminActionResetPassword(PDO $pdo, array $p): array
{
    $pass = (string) ($p['new_password'] ?? '');
    if (strlen($pass) < 8) {
        throw new InvalidArgumentException('Mot de passe : 8 caractères minimum.');
    }
    $target = adminRequireUser($pdo, (int) ($p['user_id'] ?? 0));
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([$hash, (int) $target['id']]);

    return adminActionResult(true, 'Mot de passe réinitialisé pour ' . $target['pseudo'] . '.');
}

/** @param array<string,mixed> $p */
function adminActionRemoveAvatar(PDO $pdo, array $p): array
{
    $targetId = (int) ($p['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, pseudo, avatar_url FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        throw new InvalidArgumentException('Joueur introuvable.');
    }
    if (empty($target['avatar_url'])) {
        throw new InvalidArgumentException('Pas de photo sur ce compte.');
    }
    removeUserAvatar($pdo, $targetId);

    return adminActionResult(true, 'Photo retirée pour ' . $target['pseudo'] . '.');
}

/** @param array<string,mixed> $p */
function adminActionManualScore(PDO $pdo, array $p): array
{
    $pens = !empty($p['pens']);
    $pensWinner = null;
    if ($pens) {
        $pensWinner = (string) ($p['pens_winner'] ?? '');
        if ($pensWinner !== '1' && $pensWinner !== '2') {
            throw new InvalidArgumentException('Tirs au but : choisis le vainqueur.');
        }
    }
    $res = applyManualMatchScore(
        $pdo,
        (int) ($p['match_id'] ?? 0),
        (int) ($p['score_home'] ?? -1),
        (int) ($p['score_away'] ?? -1),
        $pensWinner
    );
    $msg = 'Score enregistré — points attribués.';
    if ((int) ($res['rescored'] ?? 0) > 0) {
        $msg = 'Score enregistré — ' . (int) $res['rescored'] . ' prono(s) recalculé(s).';
    }

    return adminActionResult(true, $msg, $res);
}

/** @param array<string,mixed> $p */
function adminActionClearMatchScore(PDO $pdo, array $p): array
{
    $cleared = clearManualMatchScore($pdo, (int) ($p['match_id'] ?? 0));

    return adminActionResult(
        true,
        'Score effacé — '
        . (int) $cleared['reopened'] . ' prono(s) rouvert(s), '
        . (int) $cleared['points_reversed'] . ' pt retirés.',
        $cleared
    );
}

/** @param array<string,mixed> $p */
function adminActionCancelMatch(PDO $pdo, array $p): array
{
    $reason = normalizeMatchCancelReason((string) ($p['cancel_reason'] ?? ''));
    if ($reason === null) {
        throw new InvalidArgumentException('Choisis une raison d’annulation.');
    }
    $n = cancelMatch($pdo, (int) ($p['match_id'] ?? 0), $reason);
    $label = matchCancelReasonOptions()[$reason] ?? $reason;

    return adminActionResult(true, 'Match annulé (' . $label . ') — ' . $n . ' prono(s) à 0 pt.', ['n' => $n]);
}

/** @param array<string,mixed> $p */
function adminActionPostponeMatch(PDO $pdo, array $p): array
{
    $dateRaw = trim((string) ($p['new_date'] ?? ''));
    $newDateUtc = $dateRaw !== '' ? parseAdminMatchDatetime($dateRaw) : null;
    $n = postponeMatch($pdo, (int) ($p['match_id'] ?? 0), $newDateUtc);
    $msg = 'Match reporté — ' . $n . ' prono(s) à 0 pt.';
    if ($newDateUtc) {
        $msg .= ' Nouvelle date enregistrée.';
    }

    return adminActionResult(true, $msg, ['n' => $n]);
}

/** @param array<string,mixed> $p */
function adminActionPostponeSetDate(PDO $pdo, array $p): array
{
    $dateUtc = parseAdminMatchDatetime((string) ($p['new_date'] ?? ''));
    updatePostponedMatchDate($pdo, (int) ($p['match_id'] ?? 0), $dateUtc);

    return adminActionResult(true, 'Date du match reporté mise à jour.');
}

/** @param array<string,mixed> $p */
function adminActionPostponeReactivate(PDO $pdo, array $p): array
{
    $dateRaw = trim((string) ($p['new_date'] ?? ''));
    $newDateUtc = $dateRaw !== '' ? parseAdminMatchDatetime($dateRaw) : null;
    $n = reactivatePostponedMatch($pdo, (int) ($p['match_id'] ?? 0), $newDateUtc);

    return adminActionResult(true, 'Match réactivé (à venir) — ' . $n . ' prono(s) rouvert(s).', ['n' => $n]);
}

/** @param array<string,mixed> $p */
function adminActionScoreLocal(PDO $pdo, array $p): array
{
    $scored = scorePendingFinishedMatches($pdo);
    $close = !empty($p['close_expired']);
    $closed = $close ? closeExpiredMatches($pdo) : 0;
    $msg = 'Points locaux : ' . $scored . ' match(s) traités.';
    if ($close) {
        $msg .= ' · fermés=' . $closed;
    }

    return adminActionResult(true, $msg, ['scored' => $scored, 'closed' => $closed]);
}

function adminActionCatchupScores(PDO $pdo): array
{
    @set_time_limit(240);
    $rec = catchUpMissingScoresFromApi($pdo);
    if (!empty($rec['quota_blocked'])) {
        return adminActionResult(false, 'Rattrapage bloqué : quota API trop bas.', $rec);
    }

    return adminActionResult(
        true,
        'Rattrapage API : '
        . (int) $rec['sports_queried'] . ' ligue(s) (~'
        . (int) $rec['credits_est'] . ' crédits) · '
        . (int) $rec['resolved'] . ' score(s) · '
        . 'reste ' . (int) $rec['still_stuck']
        . ' (API=' . (int) $rec['still_api']
        . ', trop vieux=' . (int) $rec['too_old'] . ')',
        $rec,
        (int) $rec['resolved'] > 0 ? 'success' : 'info'
    );
}

function adminActionRecoverPostponed(PDO $pdo): array
{
    $rec = recoverPostponedScoresFromApi($pdo, 3);
    $msg = 'Récupération API reportés : '
        . (int) $rec['recovered'] . ' score(s)'
        . ' · ' . (int) $rec['checked'] . ' dans la fenêtre 3 j'
        . ' · ' . (int) $rec['skipped_old'] . ' trop vieux'
        . (!empty($rec['quota_blocked']) ? ' · quota bloqué' : '');

    return adminActionResult(true, $msg, $rec, (int) $rec['recovered'] > 0 ? 'success' : 'info');
}

function adminActionDismissEmptyPostponed(PDO $pdo): array
{
    $n = dismissPostponedMatchesWithoutPredictions($pdo);

    return adminActionResult(
        true,
        $n > 0
            ? $n . ' reporté(s) sans prono retiré(s) de la liste.'
            : 'Aucun reporté sans prono à nettoyer.',
        ['n' => $n],
        $n > 0 ? 'success' : 'info'
    );
}

function adminActionReactivateFuturePostponed(PDO $pdo): array
{
    $n = reactivateFuturePostponedMatches($pdo);

    return adminActionResult(
        true,
        $n > 0
            ? $n . ' reporté(s) à date future réactivé(s).'
            : 'Aucun reporté futur à réactiver.',
        ['n' => $n],
        $n > 0 ? 'success' : 'info'
    );
}

/** @param array<string,mixed> $p */
function adminActionProbeQuota(array $p): array
{
    $alt = trim((string) ($p['alt_key'] ?? ''));
    $probe = probeOddsApiQuota($alt !== '' ? $alt : null);
    if (empty($probe['ok'])) {
        return adminActionResult(false, (string) ($probe['error'] ?? 'Sonde échouée.'), $probe);
    }

    return adminActionResult(
        true,
        'Sonde live (' . $probe['key_mask'] . ') : restants='
        . ($probe['remaining'] ?? '?')
        . ', utilisés=' . ($probe['used'] ?? '?')
        . ', sports=' . (int) $probe['sports_count']
        . ' — 0 crédit.',
        $probe
    );
}

function adminActionPrune(PDO $pdo): array
{
    $pruned = pruneStaleMatchData($pdo);
    if (ensureAppCacheDir()) {
        @file_put_contents(pruneLastRunPath(), (string) time());
    }

    return adminActionResult(
        true,
        'Purge : matchs mois préc.=' . (int) ($pruned['old_matches'] ?? 0)
        . ' (erreurs gardées=' . (int) ($pruned['kept_errors'] ?? 0) . ')'
        . ', junk terminés=' . (int) ($pruned['junk_finished'] ?? 0)
        . ', scores=' . (int) $pruned['score_options']
        . ', buteurs=' . (int) $pruned['buteur_options']
        . ', marchés vides=' . (int) ($pruned['empty_markets'] ?? 0),
        $pruned
    );
}

function adminActionClearLock(): array
{
    $lock = clearIdleSyncLock();

    return adminActionResult(
        empty($lock['busy']),
        !empty($lock['busy'])
            ? 'Sync encore active — attendez 1–2 min.'
            : 'Verrou sync libéré (ou déjà libre).',
        $lock
    );
}

function adminActionCron(PDO $pdo): array
{
    @set_time_limit(180);
    $lifecycle = maintainMatchLifecycle($pdo, false);
    $reminders = maybeSendDailyMatchReminders($pdo);
    $summary = summarizeStuckScoresQueue($pdo);

    return adminActionResult(
        true,
        'Cron scores : scores_run='
        . (!empty($lifecycle['scores']) ? 'oui' : 'non/throttle')
        . ' · cache=' . (!empty($lifecycle['cache']) ? 'oui' : 'non')
        . ' · fermés=' . (int) ($lifecycle['closed'] ?? 0)
        . ' · rappels_push=' . (int) ($reminders['sent_push'] ?? 0)
        . ' · rappels_mail=' . (int) ($reminders['sent_mail'] ?? 0)
        . ' · encore sans score=' . (int) $summary['total']
        . ' (API≤3j=' . (int) $summary['api_window']
        . ', trop vieux=' . (int) $summary['too_old'] . ')'
        . ' · quota=' . (oddsQuotaRemaining() ?? '?'),
        ['lifecycle' => $lifecycle, 'reminders' => $reminders, 'summary' => $summary]
    );
}

function adminActionMatchesSync(PDO $pdo): array
{
    @set_time_limit(150);
    $syncResult = runMatchImportSync($pdo, true, true);

    return adminActionResult(
        true,
        'Import matchs : ran=' . (!empty($syncResult['ran']) ? 'oui' : 'non')
        . ' · skip=' . (string) ($syncResult['skip_reason'] ?? '—')
        . ' · sports=' . (int) ($syncResult['sports_checked'] ?? 0)
        . ' · events=' . (int) ($syncResult['events_fetched'] ?? 0)
        . ' · importés=' . (int) ($syncResult['events_imported'] ?? 0),
        $syncResult,
        !empty($syncResult['ran']) ? 'success' : 'info'
    );
}

function adminActionOddsSync(PDO $pdo): array
{
    @set_time_limit(90);
    $odds = maybeSyncOdds($pdo, true);
    $coverage = countDisplayedOddsCoverage($pdo);

    return adminActionResult(
        true,
        'Cotes : ran=' . (!empty($odds['ran']) ? 'oui' : 'non')
        . ' · maj=' . (int) ($odds['updated'] ?? 0)
        . ' · couverture=' . (int) ($coverage['with'] ?? 0) . '/' . (int) ($coverage['total'] ?? 0)
        . ' · quota=' . (oddsQuotaRemaining() ?? '?'),
        ['odds' => $odds, 'coverage' => $coverage]
    );
}

/** @param array<string,mixed> $p */
function adminActionMessageSoftDelete(PDO $pdo, array $p): array
{
    $msgId = (int) ($p['message_id'] ?? 0);
    if ($msgId < 1) {
        throw new InvalidArgumentException('Message invalide.');
    }
    $stmt = $pdo->prepare('UPDATE community_messages SET supprime = 1 WHERE id = ?');
    $stmt->execute([$msgId]);
    if ($stmt->rowCount() < 1) {
        throw new InvalidArgumentException('Message introuvable.');
    }

    return adminActionResult(true, 'Message masqué sur le site.');
}

/** @param array<string,mixed> $p */
function adminActionMessageRestore(PDO $pdo, array $p): array
{
    $msgId = (int) ($p['message_id'] ?? 0);
    if ($msgId < 1) {
        throw new InvalidArgumentException('Message invalide.');
    }
    $stmt = $pdo->prepare('UPDATE community_messages SET supprime = 0 WHERE id = ?');
    $stmt->execute([$msgId]);
    if ($stmt->rowCount() < 1) {
        throw new InvalidArgumentException('Message introuvable.');
    }

    return adminActionResult(true, 'Message restauré.');
}

/** @param array<string,mixed> $p */
function adminActionMessageHardDelete(PDO $pdo, array $p): array
{
    $msgId = (int) ($p['message_id'] ?? 0);
    if ($msgId < 1) {
        throw new InvalidArgumentException('Message invalide.');
    }
    $stmt = $pdo->prepare('DELETE FROM community_messages WHERE id = ?');
    $stmt->execute([$msgId]);
    if ($stmt->rowCount() < 1) {
        throw new InvalidArgumentException('Message introuvable ou déjà effacé.');
    }

    return adminActionResult(true, 'Message effacé définitivement.');
}

function adminActionSeasonCloseNow(PDO $pdo): array
{
    $season = forceCloseActiveSeason($pdo);

    return adminActionResult(
        true,
        $season
            ? 'Saison clôturée. Nouvelle active #' . (int) $season['id'] . ' → ' . formatSeasonFin($season['fin'])
            : 'Clôture effectuée (aucune saison active).',
        ['season' => $season]
    );
}

function adminActionSeasonScheduleMonth(PDO $pdo): array
{
    $at = nextMonthStartDatetime();
    $r = scheduleActiveSeasonEnd($pdo, $at);

    return adminActionResult(
        true,
        'Fin planifiée au ' . formatSeasonFin($r['season']['fin'])
        . ' (saison #' . (int) $r['season']['id'] . ').',
        $r
    );
}

/** @param array<string,mixed> $p */
function adminActionSeasonScheduleCustom(PDO $pdo, array $p): array
{
    $raw = trim((string) ($p['fin'] ?? ''));
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }
    $r = scheduleActiveSeasonEnd($pdo, $raw);

    return adminActionResult(
        true,
        'Fin planifiée au ' . formatSeasonFin($r['season']['fin'])
        . ' (saison #' . (int) $r['season']['id'] . ').',
        $r
    );
}

/** @param array<string,mixed> $p */
function adminActionEventSave(PDO $pdo, array $p): array
{
    ensureSiteEventsSchema($pdo);
    $id = (int) ($p['id'] ?? 0);
    $payload = [
        'title'      => $p['title'] ?? '',
        'message'    => $p['message'] ?? '',
        'type'       => $p['type'] ?? '',
        'theme'      => $p['theme'] ?? 'default',
        'starts_at'  => $p['starts_at'] ?? '',
        'ends_at'    => $p['ends_at'] ?? '',
        'enabled'    => !empty($p['enabled']),
        'published'  => !empty($p['published']),
        'multiplier' => $p['multiplier'] ?? '2',
        'sport'      => $p['sport'] ?? '',
    ];
    if ($id > 0) {
        updateSiteEvent($pdo, $id, $payload);
        $msg = 'Événement #' . $id . ' mis à jour.';
        $ev = fetchSiteEvent($pdo, $id);
        if ($ev && !empty($p['notify_push']) && siteEventIsLive($ev)) {
            $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
        }

        return adminActionResult(true, $msg, ['id' => $id]);
    }
    $newId = createSiteEvent($pdo, $payload);
    $msg = 'Événement #' . $newId . ' créé'
        . (empty($payload['published']) ? ' (brouillon)' : '') . '.';
    $ev = fetchSiteEvent($pdo, $newId);
    $wantNotify = !array_key_exists('notify_push', $p) || !empty($p['notify_push']);
    if ($ev && $wantNotify && siteEventIsLive($ev)) {
        $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
    }

    return adminActionResult(true, $msg, ['id' => $newId]);
}

/** @param array<string,mixed> $p */
function adminActionEventToggle(PDO $pdo, array $p): array
{
    $id = (int) ($p['id'] ?? 0);
    $ev = fetchSiteEvent($pdo, $id);
    if (!$ev) {
        throw new InvalidArgumentException('Événement introuvable.');
    }
    $enable = empty($ev['enabled']);
    setSiteEventEnabled($pdo, $id, $enable);
    $msg = $enable ? 'Événement activé.' : 'Événement désactivé.';
    if ($enable) {
        $ev = fetchSiteEvent($pdo, $id);
        if ($ev && siteEventIsLive($ev)) {
            $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
        }
    }

    return adminActionResult(true, $msg);
}

/** @param array<string,mixed> $p */
function adminActionEventPublish(PDO $pdo, array $p): array
{
    $id = (int) ($p['id'] ?? 0);
    $ev = fetchSiteEvent($pdo, $id);
    if (!$ev) {
        throw new InvalidArgumentException('Événement introuvable.');
    }
    $publish = empty($ev['published']);
    setSiteEventPublished($pdo, $id, $publish);
    $msg = $publish ? 'Événement publié (visible joueurs).' : 'Remis en brouillon.';
    if ($publish) {
        $ev = fetchSiteEvent($pdo, $id);
        if ($ev && siteEventIsLive($ev) && !empty($p['notify_on_publish'])) {
            $msg .= ' · ' . formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev));
        }
    }

    return adminActionResult(true, $msg);
}

/** @param array<string,mixed> $p */
function adminActionEventNotify(PDO $pdo, array $p): array
{
    $id = (int) ($p['id'] ?? 0);
    $ev = fetchSiteEvent($pdo, $id);
    if (!$ev) {
        throw new InvalidArgumentException('Événement introuvable.');
    }
    if (!siteEventIsLive($ev)) {
        throw new InvalidArgumentException(
            'L’événement doit être activé et dans sa fenêtre de dates pour notifier.'
        );
    }

    return adminActionResult(true, formatSiteEventNotifyFlash(notifySiteEventPush($pdo, $ev)));
}

/** @param array<string,mixed> $p */
function adminActionEventDelete(PDO $pdo, array $p): array
{
    $id = (int) ($p['id'] ?? 0);
    deleteSiteEvent($pdo, $id);

    return adminActionResult(true, 'Événement supprimé.');
}

/** @param array<string,mixed> $p */
function adminActionAnnSave(PDO $pdo, array $p): array
{
    ensureSiteAnnouncementsSchema($pdo);
    $id = (int) ($p['id'] ?? 0);
    $payload = [
        'title'     => $p['title'] ?? '',
        'body'      => $p['body'] ?? '',
        'published' => !empty($p['published']),
    ];
    if ($id > 0) {
        updateSiteAnnouncement($pdo, $id, $payload);

        return adminActionResult(true, 'Annonce #' . $id . ' mise à jour.', ['id' => $id]);
    }
    $newId = createSiteAnnouncement($pdo, $payload);

    return adminActionResult(
        true,
        'Annonce #' . $newId . ' créée'
        . (empty($payload['published']) ? ' (brouillon)' : ' et publiée') . '.',
        ['id' => $newId]
    );
}

/** @param array<string,mixed> $p */
function adminActionAnnPublish(PDO $pdo, array $p): array
{
    $id = (int) ($p['id'] ?? 0);
    $ev = fetchSiteAnnouncement($pdo, $id);
    if (!$ev) {
        throw new InvalidArgumentException('Annonce introuvable.');
    }
    $publish = empty($ev['published']);
    setSiteAnnouncementPublished($pdo, $id, $publish);

    return adminActionResult(true, $publish ? 'Annonce publiée.' : 'Annonce dépubliée.');
}

/** @param array<string,mixed> $p */
function adminActionAnnDelete(PDO $pdo, array $p): array
{
    $id = (int) ($p['id'] ?? 0);
    deleteSiteAnnouncement($pdo, $id);

    return adminActionResult(true, 'Annonce #' . $id . ' supprimée.');
}

function adminActionReportUnavailable(PDO $pdo): array
{
    $ok = sendUnavailableDataReportMail($pdo);
    if (!$ok) {
        return adminActionResult(false, 'Échec envoi mail : ' . (lastMailError() ?: 'inconnu'));
    }

    return adminActionResult(true, 'Diagnostic envoyé à ' . adminNotifyEmail() . '.');
}

function adminActionReportMonth(PDO $pdo): array
{
    $ok = sendMonthlySiteReportMail($pdo);
    if (!$ok) {
        return adminActionResult(false, 'Échec envoi mail : ' . (lastMailError() ?: 'inconnu'));
    }

    return adminActionResult(true, 'Rapport du mois envoyé à ' . adminNotifyEmail() . '.');
}
