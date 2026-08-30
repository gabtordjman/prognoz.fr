(function () {
    'use strict';

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var root = document.getElementById('eventStarRain');
    if (!root) {
        return;
    }

    var glyphs = ['★', '✦', '✧', '⋆', '·'];
    var narrow = window.innerWidth < 640;
    var count = narrow ? 28 : 48;
    var i;

    for (i = 0; i < count; i++) {
        var star = document.createElement('span');
        var kind = i % 5;
        star.className = 'event-star event-star--' + kind;
        star.textContent = glyphs[kind];
        star.style.left = (Math.random() * 100).toFixed(2) + '%';
        star.style.setProperty('--drift', ((Math.random() * 72) - 36).toFixed(1) + 'px');
        star.style.setProperty('--spin', ((Math.random() * 200) - 100).toFixed(0) + 'deg');
        star.style.setProperty('--twinkle', (1.4 + Math.random() * 1.8).toFixed(2) + 's');
        star.style.animationDelay = (Math.random() * 2.2).toFixed(2) + 's';
        star.style.animationDuration = (3.2 + Math.random() * 3.4).toFixed(2) + 's';
        star.style.fontSize = (kind === 4 ? 4 + Math.random() * 5 : 8 + Math.random() * 14).toFixed(1) + 'px';
        star.style.opacity = (0.28 + Math.random() * 0.52).toFixed(2);
        root.appendChild(star);
    }

    window.setTimeout(function () {
        if (root && root.parentNode) {
            root.parentNode.removeChild(root);
        }
    }, 7800);
})();
