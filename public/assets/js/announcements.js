(function () {
    'use strict';

    var data = window.PRONO_ANNOUNCE || { unread_count: 0, items: [] };
    var panel = document.getElementById('announcePanel');
    var btn = document.getElementById('announceBtn');
    var badge = document.getElementById('announceBadge');
    var listEl = document.getElementById('announceList');
    var emptyEl = document.getElementById('announceEmpty');
    var closeBtn = document.getElementById('announceClose');
    if (!panel || !btn || !listEl) {
        return;
    }

    var apiUrl = (window.PRONO_API || '/api/') + 'announcement_read.php';
    var pollMs = 25000;

    function setDot(n) {
        n = parseInt(n, 10) || 0;
        if (!badge) return;
        if (n <= 0) {
            badge.hidden = true;
            btn.classList.remove('has-unread');
            return;
        }
        badge.hidden = false;
        btn.classList.add('has-unread');
    }

    function renderList() {
        var items = data.items || [];
        listEl.innerHTML = '';
        if (emptyEl) {
            emptyEl.hidden = items.length > 0;
        }
        items.forEach(function (item) {
            var article = document.createElement('article');
            article.className = 'announce-item' + (item.unread ? ' is-unread' : '');
            var date = document.createElement('time');
            date.className = 'announce-item-date';
            date.textContent = item.date || '';
            var title = document.createElement('h3');
            title.className = 'announce-item-title';
            title.textContent = item.title || '';
            var body = document.createElement('p');
            body.className = 'announce-item-body';
            body.textContent = item.body || '';
            article.appendChild(date);
            article.appendChild(title);
            article.appendChild(body);
            listEl.appendChild(article);
        });
    }

    function applyPayload(json) {
        if (!json || !json.ok) return;
        data.unread_count = json.unread_count || 0;
        data.items = json.items || [];
        setDot(data.unread_count);
        if (panel.classList.contains('is-open')) {
            renderList();
        }
    }

    function fetchFeed() {
        return fetch(apiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(applyPayload).catch(function () { /* ignore */ });
    }

    function markAllRead() {
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                mark_all: true,
                csrf_token: window.PRONO_CSRF || ''
            })
        }).then(function (r) { return r.json(); }).then(applyPayload).catch(function () { /* ignore */ });
    }

    function openPanel() {
        renderList();
        panel.hidden = false;
        panel.classList.add('is-open');
        if ((data.unread_count || 0) > 0) {
            markAllRead();
        }
    }

    function closePanel() {
        panel.classList.remove('is-open');
        panel.hidden = true;
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.classList.contains('is-open')) {
            closePanel();
            return;
        }
        openPanel();
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closePanel);
    }

    document.addEventListener('click', function (e) {
        if (!panel.classList.contains('is-open')) return;
        if (panel.contains(e.target) || btn.contains(e.target)) return;
        closePanel();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) {
            closePanel();
        }
    });

    setDot(data.unread_count);

    window.setInterval(function () {
        if (document.hidden) return;
        fetchFeed();
    }, pollMs);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            fetchFeed();
        }
    });
})();
