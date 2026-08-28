(function () {
    'use strict';

    var data = window.PRONO_ANNOUNCE || { unread_count: 0, latest: null };
    var panel = document.getElementById('announcePanel');
    var btn = document.getElementById('announceBtn');
    var badge = document.getElementById('announceBadge');
    var titleEl = document.getElementById('announceTitle');
    var bodyEl = document.getElementById('announceBody');
    var gotIt = document.getElementById('announceGotIt');
    if (!panel || !btn || !titleEl || !bodyEl || !gotIt) {
        return;
    }

    var currentId = 0;
    var autoShownKey = 'prognoz_announce_auto_';

    function setBadge(n) {
        n = parseInt(n, 10) || 0;
        if (!badge) return;
        if (n <= 0) {
            badge.hidden = true;
            badge.textContent = '0';
            btn.classList.remove('has-unread');
            return;
        }
        badge.hidden = false;
        badge.textContent = n > 9 ? '9+' : String(n);
        btn.classList.add('has-unread');
    }

    function fill(ann) {
        if (!ann) return;
        currentId = parseInt(ann.id, 10) || 0;
        titleEl.textContent = ann.title || '';
        bodyEl.textContent = ann.body || '';
    }

    function openPanel() {
        if (!data.latest) return;
        fill(data.latest);
        panel.hidden = false;
        panel.classList.add('is-open');
        try {
            gotIt.focus();
        } catch (e) { /* ignore */ }
    }

    function closePanel() {
        panel.classList.remove('is-open');
        panel.hidden = true;
    }

    function markRead() {
        if (!currentId) {
            closePanel();
            return;
        }
        var api = (window.PRONO_API || '/api/') + 'announcement_read.php';
        fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                id: currentId,
                csrf_token: window.PRONO_CSRF || ''
            })
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (!json || !json.ok) return;
            data.unread_count = json.unread_count || 0;
            data.latest = json.latest || null;
            setBadge(data.unread_count);
            closePanel();
            if (data.latest) {
                // Prochaine non lue dispo au prochain clic / visite
            }
        }).catch(function () {
            closePanel();
        });
    }

    btn.addEventListener('click', function () {
        if (panel.classList.contains('is-open')) {
            closePanel();
            return;
        }
        if (data.latest) {
            openPanel();
        }
    });

    gotIt.addEventListener('click', markRead);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) {
            closePanel();
        }
    });

    setBadge(data.unread_count);

    if (data.latest && data.unread_count > 0) {
        var sid = String(data.latest.id);
        var already = false;
        try {
            already = sessionStorage.getItem(autoShownKey + sid) === '1';
        } catch (e) { /* ignore */ }
        if (!already) {
            var delay = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 200
                : 900;
            window.setTimeout(function () {
                openPanel();
                try {
                    sessionStorage.setItem(autoShownKey + sid, '1');
                } catch (e) { /* ignore */ }
            }, delay);
        }
    }
})();
