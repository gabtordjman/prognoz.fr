/**
 * Pronostics - brouillon local + validation explicite en base.
 */
(function () {
    var STORAGE_KEY = 'prognoz_draft_ticket';
    var apiBase = window.PRONO_API || 'api/';
    var markets = window.PRONO_MARKETS || {};
    var validated = window.PRONO_VALIDATED || {};

    function i18n(key, replace) {
        if (typeof window.t === 'function') return window.t(key, replace || {});
        var dict = window.PRONO_I18N || {};
        var text = dict[key] || key;
        if (replace) {
            Object.keys(replace).forEach(function (k) {
                text = text.split('{' + k + '}').join(String(replace[k]));
            });
        }
        return text;
    }

    function apiUrl(name) {
        name = String(name || '').replace(/^\//, '');
        name = name.replace(/\.php(?=\?|$)/i, '');
        return apiBase + name;
    }

    function closestEl(el, sel) {
        if (window.prognozClosest) {
            return window.prognozClosest(el, sel);
        }
        if (!el) {
            return null;
        }
        if (el.nodeType === 3) {
            el = el.parentElement || el.parentNode;
        }
        return el && el.closest ? el.closest(sel) : null;
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pickLabel(meta, reponse) {
        if (meta.type === 'score_exact') return i18n('js.score_prefix') + ' ' + reponse;
        if (meta.type === 'buteur') return reponse;
        if (reponse === '1') return meta.home;
        if (reponse === '2') return meta.away;
        return i18n('js.draw');
    }

    function buildTicketItem(marketId, reponse, isValidated) {
        var meta = markets[String(marketId)];
        if (!meta) return null;
        return {
            market_id: marketId,
            reponse: reponse,
            competition: meta.competition,
            home: meta.home,
            away: meta.away,
            market_type: meta.type,
            market_label: meta.label,
            pick_label: pickLabel(meta, reponse),
            points: meta.points,
            validated: !!isValidated
        };
    }

    function loadDraftPicks() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                raw = localStorage.getItem('prognoz_guest_ticket');
                if (raw) {
                    localStorage.setItem(STORAGE_KEY, raw);
                    localStorage.removeItem('prognoz_guest_ticket');
                }
            }
            if (!raw) return {};
            var data = JSON.parse(raw);
            return data && typeof data === 'object' ? data : {};
        } catch (e) {
            return {};
        }
    }

    function saveDraftPicks(picks) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(picks));
    }

    function clearDraftPicks() {
        localStorage.removeItem(STORAGE_KEY);
    }

    function isValidatedMarket(marketId) {
        return Object.prototype.hasOwnProperty.call(validated, String(marketId));
    }

    function draftPicksToTicket(picks) {
        var ticket = [];
        Object.keys(picks).forEach(function (k) {
            var marketId = parseInt(k, 10);
            if (isValidatedMarket(marketId)) {
                return;
            }
            var item = buildTicketItem(marketId, picks[k].reponse, false);
            if (item) ticket.push(item);
        });
        return ticket;
    }

    function draftTicket() {
        return draftPicksToTicket(loadDraftPicks());
    }

    function ticketGain(ticket) {
        var g = 0;
        ticket.forEach(function (i) { g += i.points || 0; });
        return g;
    }

    function draftCount() {
        var picks = loadDraftPicks();
        var n = 0;
        Object.keys(picks).forEach(function (k) {
            if (!isValidatedMarket(parseInt(k, 10))) n++;
        });
        return n;
    }

    var mobileMq = window.matchMedia('(max-width: 768px)');

    function isMobileTicket() {
        return mobileMq.matches;
    }

    function updateMobileTicketSpace() {
        if (!isMobileTicket()) return;
        var panel = document.getElementById('pronosTicket');
        if (!panel) return;
        var rect = panel.getBoundingClientRect();
        var viewportH = window.innerHeight || document.documentElement.clientHeight;
        var visibleBottom = Math.min(rect.bottom, viewportH);
        var visibleTop = Math.max(rect.top, 0);
        var visibleHeight = Math.max(0, visibleBottom - visibleTop);
        document.documentElement.style.setProperty(
            '--ticket-mobile-space',
            Math.ceil(visibleHeight + 14) + 'px'
        );
    }

    function setTicketCollapsed(collapsed, persist) {
        var panel = document.getElementById('pronosTicket');
        if (!panel || !isMobileTicket()) return;
        panel.classList.toggle('is-collapsed', collapsed);
        var handle = document.getElementById('ticketMobileHandle');
        if (handle) handle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        if (persist !== false) {
            try {
                sessionStorage.setItem('prognoz_ticket_collapsed', collapsed ? '1' : '0');
            } catch (e) { /* ignore */ }
        }
        updateMobileTicketSpace();
    }

    function observeMobileTicketHeight() {
        var panel = document.getElementById('pronosTicket');
        if (!panel || typeof ResizeObserver === 'undefined') return;
        var ro = new ResizeObserver(function () {
            if (isMobileTicket()) {
                updateMobileTicketSpace();
            }
        });
        ro.observe(panel);
    }

    function expandMobileTicket() {
        setTicketCollapsed(false);
    }

    function initMobileTicket() {
        var panel = document.getElementById('pronosTicket');
        var handle = document.getElementById('ticketMobileHandle');
        var head = document.getElementById('ticketHead');
        if (!panel) return;

        function toggleCollapsed() {
            setTicketCollapsed(!panel.classList.contains('is-collapsed'));
        }

        if (handle) {
            handle.addEventListener('click', toggleCollapsed);
        }
        if (head) {
            head.addEventListener('click', function (e) {
                if (closestEl(e.target, '#ticketCount')) return;
                toggleCollapsed();
            });
        }

        panel.addEventListener('transitionend', function (e) {
            if (e.propertyName === 'transform') {
                updateMobileTicketSpace();
            }
        });

        try {
            if (isMobileTicket()) {
                var stored = sessionStorage.getItem('prognoz_ticket_collapsed');
                if (stored === '0') {
                    setTicketCollapsed(false, false);
                } else {
                    setTicketCollapsed(true, false);
                }
            } else {
                panel.classList.remove('is-collapsed', 'is-dragging');
                document.documentElement.style.removeProperty('--ticket-mobile-space');
            }
        } catch (e) { /* ignore */ }

        var touchStartY = 0;
        var touchDelta = 0;
        var dragging = false;

        panel.addEventListener('touchstart', function (e) {
            if (!isMobileTicket()) return;
            touchStartY = e.touches[0].clientY;
            touchDelta = 0;
            dragging = true;
            panel.classList.add('is-dragging');
        }, { passive: true });

        panel.addEventListener('touchmove', function (e) {
            if (!dragging || !isMobileTicket()) return;
            touchDelta = e.touches[0].clientY - touchStartY;
        }, { passive: true });

        panel.addEventListener('touchend', function () {
            if (!dragging) return;
            dragging = false;
            panel.classList.remove('is-dragging');
            if (touchDelta > 50) {
                setTicketCollapsed(true);
            } else if (touchDelta < -50) {
                setTicketCollapsed(false);
            }
            touchDelta = 0;
        });

        if (typeof mobileMq.addEventListener === 'function') {
            mobileMq.addEventListener('change', function () {
                if (!mobileMq.matches) {
                    panel.classList.remove('is-collapsed', 'is-dragging');
                    document.documentElement.style.removeProperty('--ticket-mobile-space');
                } else {
                    updateMobileTicketSpace();
                }
            });
        }

        observeMobileTicketHeight();
    }

    function showFlash(msg, ok) {
        var el = document.getElementById('ticketFlash');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        el.classList.toggle('ticket-flash-ok', !!ok);
        el.classList.toggle('ticket-flash-err', !ok);
    }

    function renderTicket() {
        var ticket = draftTicket();
        var list = document.getElementById('ticketList');
        var empty = document.getElementById('ticketEmpty');
        var footer = document.getElementById('ticketFooter');
        var countEl = document.getElementById('ticketCount');
        var gainEl = document.getElementById('ticketGain');
        var validateBtn = document.getElementById('ticketValidate');
        if (!list) return;

        list.innerHTML = '';
        ticket.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'ticket-item ticket-item-draft';
            li.dataset.marketId = item.market_id;
            li.innerHTML =
                '<div class="ticket-item-top">' +
                    '<span class="ticket-sport">' + esc(item.competition) + '</span>' +
                    '<span class="ticket-pts">+' + item.points + ' pt</span>' +
                '</div>' +
                '<div class="ticket-match">' + esc(item.home) + ' - ' + esc(item.away) + '</div>' +
                '<div class="ticket-pick">' +
                    '<span class="ticket-type">' + esc(item.market_label) + '</span> ' +
                    '<strong>' + esc(item.pick_label) + '</strong>' +
                '</div>' +
                '<button type="button" class="ticket-remove" data-market="' + item.market_id + '" title="' + esc(i18n('js.remove')) + '" aria-label="' + esc(i18n('js.remove_aria')) + '">&times;</button>';
            list.appendChild(li);
        });

        var n = ticket.length;
        var g = ticketGain(ticket);
        if (countEl) countEl.textContent = n;
        if (gainEl) gainEl.textContent = '+' + g + ' ' + (g > 1 ? i18n('js.pts') : i18n('js.pt'));
        if (empty) empty.hidden = n > 0;
        if (footer) footer.hidden = n === 0;
        if (validateBtn) {
            validateBtn.classList.toggle('is-hidden', n === 0);
        }
        var summary = document.getElementById('ticketHeadSummary');
        if (summary) {
            if (n > 0) {
                summary.textContent = i18n('js.to_validate', { n: n, g: g, pts: (g > 1 ? i18n('js.pts') : i18n('js.pt')) });
                summary.hidden = false;
            } else {
                summary.hidden = true;
                summary.textContent = '';
            }
        }
        list.hidden = n === 0;
        updateMobileTicketSpace();
    }

    function openMatchExtraForMarket(marketId) {
        var el = document.querySelector(
            '.score-grid[data-market="' + marketId + '"], .scorer-grid[data-market="' + marketId + '"]'
        );
        if (!el) {
            return;
        }
        var card = closestEl(el, '.match-card');
        if (!card) {
            return;
        }
        var btn = card.querySelector('.match-markets-toggle');
        var extra = card.querySelector('.match-markets-extra');
        card.classList.add('is-markets-open');
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
        if (extra) {
            extra.hidden = false;
        }
    }

    function syncExtraMarketPanels() {
        var picks = loadDraftPicks();
        Object.keys(picks).forEach(function (k) {
            var meta = markets[String(k)];
            if (!meta || meta.type === '1x2') {
                return;
            }
            openMatchExtraForMarket(parseInt(k, 10));
        });
        Object.keys(validated).forEach(function (k) {
            var meta = markets[String(k)];
            if (!meta || meta.type === '1x2') {
                return;
            }
            openMatchExtraForMarket(parseInt(k, 10));
        });
    }

    function initMatchMarketToggles() {
        document.querySelectorAll('.match-markets-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = closestEl(btn, '.match-card');
                var extra = card ? card.querySelector('.match-markets-extra') : null;
                if (!card || !extra) {
                    return;
                }
                var open = !card.classList.contains('is-markets-open');
                card.classList.toggle('is-markets-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                extra.hidden = !open;
            });
        });
    }

    function updateMarketUI(marketId, pick, options) {
        options = options || {};
        var locked = options.locked || isValidatedMarket(marketId);
        var row = document.querySelector('.pick-row[data-market="' + marketId + '"]');
        if (row) {
            row.classList.toggle('pick-row-locked', locked);
            row.querySelectorAll('.pick-btn').forEach(function (b) {
                b.classList.toggle('selected', !!pick && b.dataset.pick === pick);
                b.classList.toggle('pick-locked', locked);
                b.disabled = locked;
            });
        }
        document.querySelectorAll('.score-grid[data-market="' + marketId + '"] .score-btn').forEach(function (b) {
            b.classList.toggle('selected', !!pick && b.dataset.pick === pick);
            b.classList.toggle('pick-locked', locked);
            b.disabled = locked;
        });
        document.querySelectorAll('.scorer-grid[data-market="' + marketId + '"] .scorer-btn').forEach(function (b) {
            b.classList.toggle('selected', !!pick && b.dataset.pick === pick);
            b.classList.toggle('pick-locked', locked);
            b.disabled = locked;
        });
        var sel = document.querySelector('select.pick-select[data-market="' + marketId + '"]');
        if (sel) {
            sel.value = pick || '';
            sel.disabled = locked;
            sel.classList.toggle('pick-locked', locked);
        }
    }

    function pruneDraftPicks() {
        var picks = loadDraftPicks();
        var changed = false;
        Object.keys(picks).forEach(function (k) {
            if (!markets[String(k)]) {
                delete picks[k];
                changed = true;
            }
        });
        if (changed) {
            saveDraftPicks(picks);
        }
        return picks;
    }

    function restoreUI() {
        pruneDraftPicks();
        Object.keys(validated).forEach(function (k) {
            updateMarketUI(parseInt(k, 10), validated[k], { locked: true });
        });
        var picks = loadDraftPicks();
        Object.keys(picks).forEach(function (k) {
            var marketId = parseInt(k, 10);
            if (isValidatedMarket(marketId)) {
                return;
            }
            updateMarketUI(marketId, picks[k].reponse, { locked: false });
        });
        renderTicket();
    }

    function parseJsonResponse(r) {
        return r.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(i18n('js.invalid_response'));
            }
        });
    }

    function removeDraftPick(marketId) {
        if (isValidatedMarket(marketId)) {
            return;
        }
        var picks = loadDraftPicks();
        delete picks[String(marketId)];
        saveDraftPicks(picks);
        updateMarketUI(marketId, null, { locked: false });
        renderTicket();
    }

    function addOrToggleDraftPick(marketId, pick) {
        if (isValidatedMarket(marketId)) {
            showFlash(i18n('js.already_validated'), false);
            return;
        }
        var picks = loadDraftPicks();
        var key = String(marketId);
        if (picks[key] && picks[key].reponse === pick) {
            delete picks[key];
            saveDraftPicks(picks);
            updateMarketUI(marketId, null, { locked: false });
        } else {
            picks[key] = { reponse: pick };
            saveDraftPicks(picks);
            updateMarketUI(marketId, pick, { locked: false });
            if (isMobileTicket()) {
                var panel = document.getElementById('pronosTicket');
                if (panel && panel.classList.contains('is-collapsed')) {
                    panel.classList.add('is-count-pulse');
                    setTimeout(function () {
                        panel.classList.remove('is-count-pulse');
                    }, 520);
                }
            }
            var meta = markets[String(marketId)];
            if (meta && meta.type !== '1x2') {
                openMatchExtraForMarket(marketId);
            }
        }
        renderTicket();
    }

    function handlePick(marketId, pick, isCancel) {
        if (isValidatedMarket(marketId)) {
            showFlash(i18n('js.already_validated'), false);
            return;
        }
        if (isCancel) {
            removeDraftPick(marketId);
        } else {
            addOrToggleDraftPick(marketId, pick);
        }
    }

    function syncValidatedFromServer(ticket) {
        validated = {};
        (ticket || []).forEach(function (item) {
            validated[String(item.market_id)] = item.reponse;
        });
        window.PRONO_VALIDATED = validated;
    }

    function validateTicket() {
        var picks = loadDraftPicks();
        var list = [];
        Object.keys(picks).forEach(function (k) {
            var marketId = parseInt(k, 10);
            if (isValidatedMarket(marketId)) {
                return;
            }
            list.push({ market_id: marketId, reponse: picks[k].reponse });
        });

        if (list.length === 0) {
            showFlash(i18n('js.no_picks'), false);
            return Promise.resolve();
        }

        if (!window.PRONO_USER) {
            window.location.href = window.PRONO_LOGIN_URL || 'auth/login?redirect=index.php';
            return Promise.resolve();
        }

        var validateBtn = document.getElementById('ticketValidate');
        var defaultLabel = i18n('js.validate');
        function restoreValidateBtn() {
            if (!validateBtn) return;
            validateBtn.disabled = false;
            validateBtn.textContent = defaultLabel;
            validateBtn.classList.toggle('is-hidden', draftCount() === 0);
        }

        if (validateBtn) {
            validateBtn.disabled = true;
            validateBtn.textContent = i18n('js.validating');
        }

        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeoutId = null;
        if (ctrl) {
            timeoutId = setTimeout(function () {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }, 20000);
        }

        var fetchOpts = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ picks: list, csrf_token: window.PRONO_CSRF })
        };
        if (ctrl) {
            fetchOpts.signal = ctrl.signal;
        }

        return fetch(apiUrl('validate_ticket'), fetchOpts)
        .then(function (r) { return parseJsonResponse(r); })
        .then(function (data) {
            if (!data || !data.ok) {
                throw new Error((data && data.error) || i18n('js.validate_impossible'));
            }

            syncValidatedFromServer(data.ticket);

            var remaining = loadDraftPicks();
            list.forEach(function (p) {
                delete remaining[String(p.market_id)];
            });
            saveDraftPicks(remaining);

            Object.keys(validated).forEach(function (k) {
                updateMarketUI(parseInt(k, 10), validated[k], { locked: true });
            });

            renderTicket();
            var msg = data.saved > 1 ? i18n('js.saved_other', { n: data.saved }) : i18n('js.saved_one', { n: data.saved });
            showFlash(msg, true);
        })
        .catch(function (err) {
            var msg = (err && err.name === 'AbortError')
                ? i18n('js.network_error')
                : ((err && err.message) || i18n('js.network_error'));
            showFlash(msg, false);
        })
        .then(function () {
            if (timeoutId) clearTimeout(timeoutId);
            restoreValidateBtn();
        }, function () {
            if (timeoutId) clearTimeout(timeoutId);
            restoreValidateBtn();
        });
    }

    document.querySelectorAll('.pick-row[data-market]').forEach(function (row) {
        var marketId = parseInt(row.dataset.market, 10);
        row.querySelectorAll('.pick-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (isValidatedMarket(marketId)) {
                    showFlash(i18n('js.already_validated'), false);
                    return;
                }
                if (btn.classList.contains('selected')) {
                    handlePick(marketId, btn.dataset.pick, true);
                } else {
                    handlePick(marketId, btn.dataset.pick, false);
                }
            });
        });
    });

    document.querySelectorAll('.score-grid[data-market]').forEach(function (grid) {
        var marketId = parseInt(grid.dataset.market, 10);
        grid.querySelectorAll('.score-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (isValidatedMarket(marketId)) {
                    showFlash(i18n('js.already_validated'), false);
                    return;
                }
                if (btn.classList.contains('selected')) {
                    handlePick(marketId, btn.dataset.pick, true);
                } else {
                    handlePick(marketId, btn.dataset.pick, false);
                }
            });
        });
    });

    document.querySelectorAll('.scorer-grid[data-market]').forEach(function (grid) {
        var marketId = parseInt(grid.dataset.market, 10);
        grid.querySelectorAll('.scorer-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (isValidatedMarket(marketId)) {
                    showFlash(i18n('js.already_validated'), false);
                    return;
                }
                if (btn.classList.contains('selected')) {
                    handlePick(marketId, btn.dataset.pick, true);
                } else {
                    handlePick(marketId, btn.dataset.pick, false);
                }
            });
        });
    });

    document.querySelectorAll('select.pick-select[data-market]').forEach(function (sel) {
        var marketId = parseInt(sel.dataset.market, 10);
        sel.addEventListener('change', function () {
            if (isValidatedMarket(marketId)) {
                sel.value = validated[String(marketId)] || '';
                showFlash(i18n('js.already_validated'), false);
                return;
            }
            if (!sel.value) {
                handlePick(marketId, null, true);
            } else {
                handlePick(marketId, sel.value, false);
            }
        });
    });

    var ticketListEl = document.getElementById('ticketList');
    if (ticketListEl) {
        ticketListEl.addEventListener('click', function (e) {
            var btn = closestEl(e.target, '.ticket-remove');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var marketId = parseInt(btn.getAttribute('data-market'), 10);
            if (marketId > 0) {
                removeDraftPick(marketId);
            }
        });
    }

    var validateEl = document.getElementById('ticketValidate');
    if (validateEl && validateEl.tagName === 'BUTTON') {
        validateEl.addEventListener('click', function (e) {
            e.preventDefault();
            try {
                validateTicket();
            } catch (err) {
                validateEl.disabled = false;
                validateEl.textContent = i18n('js.validate');
                showFlash((err && err.message) || i18n('js.validate_impossible'), false);
            }
        });
    }

    initMobileTicket();
    initMatchMarketToggles();
    restoreUI();
    syncExtraMarketPanels();
})();
