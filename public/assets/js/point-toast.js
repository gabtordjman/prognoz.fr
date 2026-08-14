(function () {
    'use strict';

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


    var stack = document.getElementById('pointToastStack');
    if (!stack) return;

    var apiBase = window.PRONO_API || '/api/';
    var dashboardUrl = window.PRONO_DASHBOARD_URL || '/account/dashboard';
    var CONFETTI_MIN_PTS = 3;
    var CONFETTI_KEY = 'prognoz_confetti_date';

    function apiUrl(path) {
        path = String(path || '').replace(/^\//, '');
        path = path.replace(/\.php(?=\?|$)/i, '');
        return apiBase + path;
    }

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function removeToast(el, delay) {
        setTimeout(function () {
            el.classList.add('point-toast--out');
            setTimeout(function () { el.remove(); }, 280);
        }, delay);
    }

    function todayKey() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function launchConfetti() {
        var canvas = document.createElement('canvas');
        canvas.className = 'confetti-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        var w = canvas.width = window.innerWidth;
        var h = canvas.height = window.innerHeight;
        var colors = ['#2d6b48', '#4a9468', '#c4a035', '#e8dcc0', '#8a3020'];
        var pieces = [];
        var count = Math.min(80, Math.floor(w / 14));
        for (var i = 0; i < count; i++) {
            pieces.push({
                x: Math.random() * w,
                y: -Math.random() * h * 0.3,
                r: 3 + Math.random() * 4,
                vx: -2 + Math.random() * 4,
                vy: 2 + Math.random() * 5,
                rot: Math.random() * 360,
                vr: -6 + Math.random() * 12,
                color: colors[Math.floor(Math.random() * colors.length)]
            });
        }
        var start = Date.now();
        var duration = 2200;

        function frame() {
            var elapsed = Date.now() - start;
            ctx.clearRect(0, 0, w, h);
            pieces.forEach(function (p) {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.08;
                p.rot += p.vr;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot * Math.PI / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.r, -p.r * 0.5, p.r * 2, p.r);
                ctx.restore();
            });
            if (elapsed < duration) {
                requestAnimationFrame(frame);
            } else {
                canvas.remove();
            }
        }
        requestAnimationFrame(frame);
    }

    function maybeConfetti(totalPoints) {
        if (totalPoints < CONFETTI_MIN_PTS) return;
        try {
            if (localStorage.getItem(CONFETTI_KEY) === todayKey()) return;
            localStorage.setItem(CONFETTI_KEY, todayKey());
        } catch (e) {
            return;
        }
        launchConfetti();
    }

    function showToast(item, totalForConfetti) {
        var el = document.createElement('div');
        el.className = 'point-toast point-toast--pop';
        el.innerHTML =
            '<div class="point-toast-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></div>' +
            '<div class="point-toast-body">' +
                '<p class="point-toast-title">+' + item.points + ' ' + (item.points > 1 ? i18n('js.pts') : i18n('js.pt')) + ' — ' + i18n('js.good_pick') + '</p>' +
                '<p class="point-toast-match">' + escapeHtml(item.match) + '</p>' +
                '<p class="point-toast-detail">' + escapeHtml(item.label) + ' : ' + escapeHtml(item.pick) + ' · ' + i18n('js.result_label') + ' ' + escapeHtml(item.result) + '</p>' +
            '</div>' +
            '<button type="button" class="point-toast-close" aria-label="' + escapeHtml(i18n('js.close')) + '">&times;</button>';
        stack.appendChild(el);

        var closeBtn = el.querySelector('.point-toast-close');
        function dismiss() { removeToast(el, 0); }
        if (closeBtn) closeBtn.addEventListener('click', dismiss);
        removeToast(el, 7000);
        maybeConfetti(totalForConfetti);
    }

    function showBatchSummary(total, count, sample) {
        var el = document.createElement('div');
        el.className = 'point-toast point-toast--summary point-toast--pop';
        var detail = count > 1
            ? i18n('js.batch_wins_other', { n: count, pts: total })
            : i18n('js.batch_wins_one', { n: count, pts: total });
        if (sample && sample.match) {
            detail += ' · ' + sample.match;
            if (count > 1) {
                var others = count - 1;
                detail += ' ' + (others > 1 ? i18n('js.and_others_other', { n: others }) : i18n('js.and_others_one', { n: others }));
            }
        }
        el.innerHTML =
            '<div class="point-toast-icon" aria-hidden="true"><i class="fa-solid fa-trophy"></i></div>' +
            '<div class="point-toast-body">' +
                '<p class="point-toast-title">' + i18n('js.bravo', { n: total }) + '</p>' +
                '<p class="point-toast-detail">' + escapeHtml(detail) + '</p>' +
                '<p class="point-toast-link"><a href="' + escapeHtml(dashboardUrl) + '">' + escapeHtml(i18n('js.see_results')) + '</a></p>' +
            '</div>' +
            '<button type="button" class="point-toast-close" aria-label="' + escapeHtml(i18n('js.close')) + '">&times;</button>';
        stack.appendChild(el);

        var closeBtn = el.querySelector('.point-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () { removeToast(el, 0); });
        }
        removeToast(el, 9000);
        maybeConfetti(total);
    }

    window.PrognozPointToast = {
        showToast: showToast,
        showBatchSummary: showBatchSummary
    };
})();
