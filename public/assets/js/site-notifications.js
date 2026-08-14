(function () {
    'use strict';

    var LS_CURSORS = 'prognoz_chat_cursors';
    var LS_LAST_RESOLVE = 'prognoz_last_resolve';
    var POLL_VISIBLE_MS = 20000;
    var POLL_HIDDEN_MS = 8000;
    var BURST_INTERVALS = [0, 2000, 5000, 10000];
    var RESOLVE_MIN_MS = 600000;

    var apiBase = window.PRONO_API || '/api/';
    var communitiesBase = window.PRONO_COMMUNITIES_URL || '/communities/view?id=';
    var pollTimer = null;
    var burstTimers = [];

    function apiUrl(path) {
        path = String(path || '').replace(/^\//, '');
        path = path.replace(/\.php(?=\?|$)/i, '');
        return apiBase + path;
    }

    function readCursors() {
        try {
            return JSON.parse(localStorage.getItem(LS_CURSORS) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function writeCursors(cursors) {
        try {
            localStorage.setItem(LS_CURSORS, JSON.stringify(cursors || {}));
        } catch (e) { /* ignore */ }
    }

    function mergeMissingCursors(serverCursors) {
        if (!serverCursors) return;
        var local = readCursors();
        var changed = false;
        Object.keys(serverCursors).forEach(function (key) {
            if (local[key] === undefined) {
                local[key] = parseInt(serverCursors[key], 10) || 0;
                changed = true;
            }
        });
        if (changed) writeCursors(local);
    }

    function truncate(str, max) {
        str = String(str || '').trim();
        if (str.length <= max) return str;
        return str.substring(0, max - 1) + '…';
    }

    /**
     * Demande au serveur de résoudre les matchs terminés (points en attente).
     * Asynchrone et throttlé : ne retarde jamais l'affichage de la page.
     */
    function requestResultsResolution() {
        var last = 0;
        try {
            last = parseInt(localStorage.getItem(LS_LAST_RESOLVE), 10) || 0;
        } catch (e) { /* ignore */ }

        if (Date.now() - last < RESOLVE_MIN_MS) return Promise.resolve();

        try {
            localStorage.setItem(LS_LAST_RESOLVE, String(Date.now()));
        } catch (e) { /* ignore */ }

        return fetch(apiUrl('sync?mode=light'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // De nouveaux points viennent d'être attribués : les remonter tout de suite.
                if (data && data.ok && (data.resolved > 0 || data.scored > 0)) {
                    pollPointNotifications();
                }
            })
            .catch(function () {});
    }

    function bootstrapChatCursors() {
        return fetch(apiUrl('chat_notifications?bootstrap=1'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.cursors) return;
                mergeMissingCursors(data.cursors);
            })
            .catch(function () {});
    }

    function pollChatNotifications() {
        if (!window.PrognozNotify || !PrognozNotify.isEnabled() || !PrognozNotify.chatEnabled()) {
            return Promise.resolve();
        }

        var cursors = readCursors();
        if (!Object.keys(cursors).length) {
            return bootstrapChatCursors();
        }

        var exclude = window.COMMUNITY_ID ? String(window.COMMUNITY_ID) : '';
        var url = apiUrl('chat_notifications?cursors=' + encodeURIComponent(JSON.stringify(cursors)));
        if (exclude) {
            url += '&exclude_community_id=' + encodeURIComponent(exclude);
        }

        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;

                mergeMissingCursors(data.cursors);

                if (!data.messages || !data.messages.length) return;

                var updated = readCursors();
                var shows = [];

                data.messages.forEach(function (msg) {
                    var title = msg.community_name || 'Communauté';
                    var body = (msg.pseudo || '?') + ' : ' + truncate(msg.contenu, 120);
                    var link = communitiesBase + msg.community_id;
                    var key = String(msg.community_id);

                    if (document.hidden) {
                        shows.push(PrognozNotify.show(title, body, {
                            tag: 'prognoz-chat-' + msg.id,
                            url: link
                        }));
                    }

                    updated[key] = Math.max(parseInt(updated[key], 10) || 0, parseInt(msg.id, 10));
                });

                writeCursors(updated);
                return Promise.all(shows);
            })
            .catch(function () {});
    }

    function ackPointNotifications(ids) {
        if (!ids || !ids.length) return Promise.resolve();
        return fetch(apiUrl('point_notifications'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ ids: ids, csrf_token: window.PRONO_CSRF || '' })
        }).catch(function () {});
    }

    function pollPointNotifications() {
        return fetch(apiUrl('point_notifications'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.items || !data.items.length) return;

                var items = data.items;
                var total = data.total_points || 0;
                var ids = items.map(function (item) { return item.id; });
                var useBrowser = window.PrognozNotify
                    && PrognozNotify.isEnabled()
                    && PrognozNotify.winsEnabled()
                    && document.hidden;

                if (useBrowser) {
                    var showPromise;
                    if (items.length >= 2) {
                        showPromise = PrognozNotify.show(
                            'Bravo ! +' + total + ' pts',
                            items.length + ' pronos gagnés',
                            {
                                tag: 'prognoz-wins-batch',
                                url: window.PRONO_DASHBOARD_URL || '/account/dashboard'
                            }
                        );
                    } else {
                        var item = items[0];
                        showPromise = PrognozNotify.show(
                            '+' + item.points + ' pt' + (item.points > 1 ? 's' : '') + ' — Bon prono !',
                            item.match + ' · ' + item.label,
                            {
                                tag: 'prognoz-win-' + item.id,
                                url: window.PRONO_DASHBOARD_URL || '/account/dashboard'
                            }
                        );
                    }
                    return Promise.resolve(showPromise).then(function () {
                        ackPointNotifications(ids);
                    });
                }

                if (window.PrognozPointToast) {
                    if (items.length >= 2) {
                        PrognozPointToast.showBatchSummary(total, items.length, items[0]);
                    } else {
                        PrognozPointToast.showToast(items[0], total);
                    }
                    ackPointNotifications(ids);
                }
            })
            .catch(function () {});
    }

    function pollAll() {
        pollPointNotifications();
        pollChatNotifications();
    }

    function clearBurst() {
        burstTimers.forEach(function (t) { clearTimeout(t); });
        burstTimers = [];
    }

    function burstPollWhenHidden() {
        clearBurst();
        if (!document.hidden) return;
        BURST_INTERVALS.forEach(function (delay) {
            burstTimers.push(setTimeout(pollAll, delay));
        });
    }

    function schedulePoll() {
        if (pollTimer) clearInterval(pollTimer);
        var ms = document.hidden ? POLL_HIDDEN_MS : POLL_VISIBLE_MS;
        pollTimer = setInterval(pollAll, ms);
    }

    function start() {
        var boot = window.PrognozNotify && PrognozNotify.init
            ? PrognozNotify.init()
            : Promise.resolve();

        boot.then(function () {
            if (window.PrognozNotify && PrognozNotify.isEnabled && PrognozNotify.isEnabled()) {
                return PrognozNotify.subscribePush();
            }
        }).finally(function () {
            bootstrapChatCursors().finally(function () {
                pollAll();
                schedulePoll();
                requestResultsResolution();
            });
        });
    }

    try {
        start();
    } catch (e) { /* IE / push : ne pas bloquer le reste de la page */ }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            pollAll();
            burstPollWhenHidden();
        } else {
            clearBurst();
            pollAll();
        }
        schedulePoll();
    });
})();
