(function () {
    'use strict';

    var STORAGE_KEY = 'prognoz_seen_closed_matches';

    function getSeen() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function saveSeen(seen) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(seen));
        } catch (e) { /* ignore */ }
    }

    function flashCard(card) {
        card.classList.add('is-result-flash');
        setTimeout(function () {
            card.classList.remove('is-result-flash');
        }, 1400);
    }

    document.querySelectorAll('.match-card.is-picks-closed').forEach(function (card) {
        var id = card.getAttribute('data-match-id');
        if (!id) return;
        var seen = getSeen();
        if (seen[id]) return;
        seen[id] = Date.now();
        saveSeen(seen);
        flashCard(card);
    });

    document.querySelectorAll('.history-item.is-result-flash').forEach(function (item, index) {
        setTimeout(function () {
            item.classList.add('is-flashing');
            setTimeout(function () {
                item.classList.remove('is-flashing', 'is-result-flash');
            }, 1500);
        }, index * 120);
    });
})();
