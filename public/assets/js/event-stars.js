(function () {
    'use strict';

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var root = document.getElementById('eventStarRain');
    if (!root) {
        return;
    }

    var count = window.innerWidth < 640 ? 16 : 26;
    var i;
    for (i = 0; i < count; i++) {
        var star = document.createElement('span');
        star.className = 'event-star';
        star.style.left = (Math.random() * 100).toFixed(2) + '%';
        star.style.setProperty('--drift', ((Math.random() * 48) - 24).toFixed(1) + 'px');
        star.style.animationDelay = (Math.random() * 1.4).toFixed(2) + 's';
        star.style.animationDuration = (2.6 + Math.random() * 2.4).toFixed(2) + 's';
        star.style.fontSize = (7 + Math.random() * 9).toFixed(1) + 'px';
        star.style.opacity = (0.22 + Math.random() * 0.38).toFixed(2);
        root.appendChild(star);
    }

    window.setTimeout(function () {
        if (root && root.parentNode) {
            root.parentNode.removeChild(root);
        }
    }, 6200);
})();
