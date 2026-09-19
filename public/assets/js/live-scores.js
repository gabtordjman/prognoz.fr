/**
 * Poll léger des scores live football (cache serveur).
 * Ne s’active que s’il y a des cartes [data-live-track].
 */
(function () {
    'use strict';

    var POLL_MS = 20000;
    var POLL_HIDDEN_MS = 60000;
    var timer = null;

    function apiUrl(path) {
        var base = window.PRONO_API || '/api/';
        path = String(path || '').replace(/^\//, '').replace(/\.php(?=\?|$)/i, '');
        return base + path;
    }

    function cards() {
        return document.querySelectorAll('.match-card[data-live-track="1"]');
    }

    function applySnap(card, snap) {
        if (!card || !snap) return;

        var scoreEl = card.querySelector('[data-live-score]');
        var vsEl = card.querySelector('[data-live-vs]');
        var badge = card.querySelector('[data-live-badge]');
        var clockEl = card.querySelector('[data-live-clock]');

        var hasScore = snap.home !== null && snap.home !== undefined
            && snap.away !== null && snap.away !== undefined
            && !snap.pending;

        if (hasScore && scoreEl) {
            var next = String(snap.home) + '–' + String(snap.away);
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
        if (clockEl && clock) {
            clockEl.textContent = clock;
        }
        if (badge) {
            if (clock || hasScore) {
                badge.hidden = false;
                badge.classList.toggle('is-waiting', !hasScore);
                badge.classList.toggle('is-finished', !!snap.finished);
            }
        }
    }

    function poll() {
        if (!cards().length) return Promise.resolve();

        return fetch(apiUrl('live_scores'), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.matches) return;
                cards().forEach(function (card) {
                    var id = card.getAttribute('data-match-id');
                    if (!id || !data.matches[id]) return;
                    applySnap(card, data.matches[id]);
                });
            })
            .catch(function () {});
    }

    function schedule() {
        if (timer) window.clearTimeout(timer);
        if (!cards().length) return;
        var delay = document.hidden ? POLL_HIDDEN_MS : POLL_MS;
        timer = window.setTimeout(function () {
            poll().finally(schedule);
        }, delay);
    }

    if (!cards().length) return;

    poll().finally(schedule);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
        schedule();
    });
})();
