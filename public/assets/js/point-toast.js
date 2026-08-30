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

    var dashboardUrl = window.PRONO_DASHBOARD_URL || '/account/dashboard';
    var CONFETTI_MIN_PTS = 1;

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

    function launchConfetti() {
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        var canvas = document.createElement('canvas');
        canvas.className = 'confetti-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            canvas.remove();
            return;
        }
        var w = canvas.width = window.innerWidth;
        var h = canvas.height = window.innerHeight;
        var colors = ['#2d6b48', '#4a9468', '#6bb88a', '#c4a035', '#e8d078', '#f5e6b8', '#8a3020', '#e4d9c4'];
        var pieces = [];
        var count = Math.min(140, Math.max(64, Math.floor(w / 9)));
        var cx = w * 0.5;
        var i;

        for (i = 0; i < count; i++) {
            var angle = (Math.random() * Math.PI) - (Math.PI / 2);
            var speed = 4 + Math.random() * 9;
            var shapeRoll = Math.random();
            pieces.push({
                x: cx + (Math.random() - 0.5) * w * 0.35,
                y: -8 - Math.random() * 40,
                w: 4 + Math.random() * 6,
                h: 3 + Math.random() * 5,
                vx: Math.cos(angle) * speed * (0.55 + Math.random() * 0.7),
                vy: 2.5 + Math.random() * 5.5,
                rot: Math.random() * 360,
                vr: -10 + Math.random() * 20,
                color: colors[Math.floor(Math.random() * colors.length)],
                shape: shapeRoll < 0.45 ? 'rect' : (shapeRoll < 0.75 ? 'circle' : 'tri'),
                alpha: 1
            });
        }

        var start = Date.now();
        var duration = 2800;

        function drawPiece(p) {
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot * Math.PI / 180);
            ctx.globalAlpha = Math.max(0, p.alpha);
            ctx.fillStyle = p.color;
            if (p.shape === 'circle') {
                ctx.beginPath();
                ctx.arc(0, 0, p.w * 0.55, 0, Math.PI * 2);
                ctx.fill();
            } else if (p.shape === 'tri') {
                ctx.beginPath();
                ctx.moveTo(0, -p.h);
                ctx.lineTo(p.w, p.h);
                ctx.lineTo(-p.w, p.h);
                ctx.closePath();
                ctx.fill();
            } else {
                ctx.fillRect(-p.w, -p.h * 0.45, p.w * 2, p.h);
            }
            ctx.restore();
        }

        function frame() {
            var elapsed = Date.now() - start;
            var fade = elapsed > duration - 700 ? 1 - ((elapsed - (duration - 700)) / 700) : 1;
            ctx.clearRect(0, 0, w, h);
            pieces.forEach(function (p) {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.12;
                p.vx *= 0.995;
                p.rot += p.vr;
                p.alpha = fade;
                drawPiece(p);
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
