(function () {
    'use strict';

    var h = new Date().getHours();
    var d = new Date().getDay();
    var root = document.documentElement;
    if (d === 0 || d === 6 || h >= 18 || h < 8) {
        root.classList.add('theme-match-night');
    } else {
        root.classList.add('theme-match-day');
    }
})();
