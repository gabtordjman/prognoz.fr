/**
 * Poll adaptatif des scores live (cache serveur).
 * - Lecture front fréquente = gratuit (JSON local)
 * - Appel API-Football : rare (défaut 5 min + plafond ~80/jour)
 * - Horloge locale qui continue de tourner entre deux syncs (sans API)
 * - Se fige à la MT / fin de match quand le statut le dit
 */
(function () {
    'use strict';

    var DEFAULT_POLL_MS = 15000;
    var HIDDEN_MIN_MS = 60000;
    var GOAL_BURST_MS = 10000;
    var GOAL_BURST_COUNT = 2;
    var TICK_MS = 1000;
    var timer = null;
    var tickTimer = null;
    var fingerprint = '';
    var pollMs = DEFAULT_POLL_MS;
    var burstLeft = 0;
    var lastScores = {};
    /** @type {Object.<string,{minute:number,extra:number,at:number,status:string,frozen:boolean,label:?string}>} */
    var clockBase = {};

    var PLAYING = { '1H': 1, '2H': 1, 'ET': 1, 'LIVE': 1, 'P': 1 };
    var FROZEN = { 'HT': 'MT', 'BT': 'Pause', 'FT': 'Fin', 'AET': 'Fin', 'PEN': 'Fin' };

    function apiUrl(path) {
        var base = window.PRONO_API || '/api/';
        path = String(path || '').replace(/^\//, '').replace(/\.php(?=\?|$)/i, '');
        return base + path;
    }

    function cards() {
        return document.querySelectorAll('.match-card[data-live-track="1"]');
    }

    function parseMinute(clock) {
        var m = String(clock || '').match(/^(\d+)(?:\+(\d+))?'$/);
        if (!m) return null;
        return { base: parseInt(m[1], 10), extra: m[2] ? parseInt(m[2], 10) : 0 };
    }

    function formatPlayingClock(status, minute, extra, elapsedMin) {
        var total = minute + elapsedMin;
        var injury = extra;
        if (extra > 0) {
            injury = extra + elapsedMin;
            total = minute;
        }

        if (status === '1H') {
            if (extra > 0 || total >= 45) {
                var over1 = extra > 0 ? injury : (total - 45);
                return '45+' + Math.max(1, over1) + "'";
            }
            return total + "'";
        }
        if (status === '2H') {
            if (extra > 0 || total >= 90) {
                var over2 = extra > 0 ? injury : (total - 90);
                return '90+' + Math.max(1, over2) + "'";
            }
            return total + "'";
        }
        if (status === 'ET') {
            if (extra > 0 || total >= 120) {
                var overE = extra > 0 ? injury : (total - 120);
                return '120+' + Math.max(1, overE) + "'";
            }
            return total + "'";
        }
        if (extra > 0) {
            return minute + '+' + injury + "'";
        }
        return (minute + elapsedMin) + "'";
    }

    function setClockState(id, clock, status, fetchedAtSec) {
        status = String(status || '').toUpperCase();
        if (FROZEN[status]) {
            clockBase[id] = {
                minute: 0,
                extra: 0,
                at: Date.now(),
                status: status,
                frozen: true,
                label: FROZEN[status]
            };
            return;
        }
        if (!PLAYING[status]) {
            delete clockBase[id];
            return;
        }
        var parsed = parseMinute(clock);
        if (!parsed) {
            delete clockBase[id];
            return;
        }
        var atMs = Date.now();
        if (fetchedAtSec && fetchedAtSec > 0) {
            // Cache déjà vieux : l’horloge rattrape le temps écoulé depuis le sync.
            atMs = fetchedAtSec * 1000;
        }
        clockBase[id] = {
            minute: parsed.base,
            extra: parsed.extra,
            at: atMs,
            status: status,
            frozen: false,
            label: null
        };
    }

    function renderClock(card, id) {
        var base = clockBase[id];
        var clockEl = card.querySelector('[data-live-clock]');
        if (!base || !clockEl || card.classList.contains('is-live-goal')) return;
        if (base.frozen) {
            if (base.label) clockEl.textContent = base.label;
            return;
        }
        var elapsed = Math.max(0, Math.floor((Date.now() - base.at) / 60000));
        clockEl.textContent = formatPlayingClock(base.status, base.minute, base.extra, elapsed);
    }

    function showGoalFlash(card) {
        if (!card) return;
        card.classList.add('is-live-goal');
        var clock = card.querySelector('[data-live-clock]');
        if (clock) {
            clock.textContent = (window.PRONO_I18N && window.PRONO_I18N.live_goal) || 'But !';
        }
        var badge = card.querySelector('[data-live-badge]');
        if (badge) {
            badge.hidden = false;
            badge.classList.remove('is-waiting');
        }
        window.setTimeout(function () {
            card.classList.remove('is-live-goal');
            var id = card.getAttribute('data-match-id') || '';
            renderClock(card, id);
        }, 2200);
    }

    function applySnap(card, snap, fetchedAtSec) {
        if (!card || !snap) return false;

        var scoreEl = card.querySelector('[data-live-score]');
        var vsEl = card.querySelector('[data-live-vs]');
        var badge = card.querySelector('[data-live-badge]');
        var id = card.getAttribute('data-match-id') || '';
        var scored = false;
        var status = String(snap.status || '').toUpperCase();

        var hasScore = snap.home !== null && snap.home !== undefined
            && snap.away !== null && snap.away !== undefined
            && !snap.pending;

        if (hasScore && scoreEl) {
            var next = String(snap.home) + '–' + String(snap.away);
            var prev = lastScores[id];
            if (prev && prev !== next) {
                scored = true;
                showGoalFlash(card);
            }
            lastScores[id] = next;
            if (scoreEl.textContent !== next) {
                scoreEl.textContent = next;
                card.classList.add('is-live-pulse');
                window.setTimeout(function () {
                    card.classList.remove('is-live-pulse');
                }, 700);
            }
            scoreEl.hidden = false;
            if (vsEl) vsEl.hidden = true;
            card.classList.add('is-live');
        }

        var clock = String(snap.clock || '').trim();
        if (badge) {
            if (clock || hasScore || FROZEN[status]) {
                badge.hidden = false;
                badge.classList.toggle('is-waiting', !hasScore && !FROZEN[status]);
                badge.classList.toggle('is-finished', !!snap.finished || !!FROZEN[status]);
            }
        }

        card.setAttribute('data-live-status', status);
        if (clock) card.setAttribute('data-live-clock-raw', clock);
        setClockState(id, clock || card.getAttribute('data-live-clock-raw') || '', status, fetchedAtSec || 0);
        renderClock(card, id);

        return scored;
    }

    function tickLocalClocks() {
        cards().forEach(function (card) {
            var id = card.getAttribute('data-match-id') || '';
            renderClock(card, id);
        });
    }

    function seedFromDom() {
        cards().forEach(function (card) {
            var id = card.getAttribute('data-match-id') || '';
            var status = card.getAttribute('data-live-status') || '';
            var clock = card.getAttribute('data-live-clock-raw') || '';
            var fetched = parseInt(card.getAttribute('data-live-fetched-at') || '0', 10) || 0;
            var scoreEl = card.querySelector('[data-live-score]');
            if (scoreEl && !scoreEl.hidden && scoreEl.textContent) {
                lastScores[id] = scoreEl.textContent.trim();
            }
            if (!clock && !status) return;
            setClockState(id, clock, status, fetched);
            renderClock(card, id);
        });
    }

    function poll() {
        if (!cards().length) return Promise.resolve();

        return fetch(apiUrl('live_scores'), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.matches) return;
                if (typeof data.poll_ms === 'number' && data.poll_ms >= 8000) {
                    pollMs = data.poll_ms;
                }
                var fetchedAt = typeof data.fetched_at === 'number' ? data.fetched_at : 0;
                var goal = false;
                cards().forEach(function (card) {
                    var id = card.getAttribute('data-match-id');
                    if (!id || !data.matches[id]) return;
                    if (applySnap(card, data.matches[id], fetchedAt)) {
                        goal = true;
                    }
                });
                if (goal) {
                    burstLeft = GOAL_BURST_COUNT;
                }
                if (data.fingerprint) {
                    fingerprint = data.fingerprint;
                }
            })
            .catch(function () {});
    }

    function nextDelay() {
        if (document.hidden) {
            return Math.max(HIDDEN_MIN_MS, pollMs);
        }
        if (burstLeft > 0) {
            burstLeft -= 1;
            return GOAL_BURST_MS;
        }
        return pollMs;
    }

    function schedule() {
        if (timer) window.clearTimeout(timer);
        if (!cards().length) return;
        timer = window.setTimeout(function () {
            poll().finally(schedule);
        }, nextDelay());
    }

    if (!cards().length) return;

    seedFromDom();
    poll().finally(schedule);
    tickTimer = window.setInterval(tickLocalClocks, TICK_MS);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            tickLocalClocks();
            poll();
        }
        schedule();
    });
})();
