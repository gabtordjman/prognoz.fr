(function () {
    var submitting = false;
    var wantFs = false;
    try {
        wantFs = sessionStorage.getItem("ibmiFs") === "1";
    } catch (e) {}

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function formEl() {
        return document.getElementById("ibmiForm");
    }

    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function syncFsBtn() {
        var btn = document.getElementById("ibmiFs");
        if (btn) {
            btn.classList.toggle("is-on", isFullscreen());
            btn.textContent = isFullscreen() ? "Fenêtré" : "Plein écran";
        }
    }

    function enterFs() {
        var el = document.documentElement;
        var req = el.requestFullscreen || el.webkitRequestFullscreen;
        if (!req || isFullscreen()) {
            return Promise.resolve();
        }
        return Promise.resolve(req.call(el)).catch(function () {});
    }

    function exitFs() {
        var ex = document.exitFullscreen || document.webkitExitFullscreen;
        if (ex && isFullscreen()) {
            return Promise.resolve(ex.call(document)).catch(function () {});
        }
        return Promise.resolve();
    }

    function toggleFs() {
        wantFs = !isFullscreen();
        try {
            sessionStorage.setItem("ibmiFs", wantFs ? "1" : "0");
        } catch (e) {}
        if (wantFs) {
            enterFs().then(syncFsBtn);
        } else {
            exitFs().then(syncFsBtn);
        }
    }

    function fitCrt() {
        var term = document.getElementById("ibmiTerminal");
        var room = document.querySelector(".ibmi-room");
        if (!term || !room) {
            return;
        }
        term.style.transform = "scale(1)";
        var pad = isFullscreen() ? 8 : 12;
        var rw = Math.max(320, room.clientWidth - pad);
        var rh = Math.max(240, room.clientHeight - pad);
        var tw = term.offsetWidth;
        var th = term.offsetHeight;
        if (tw < 1 || th < 1) {
            return;
        }
        var s = Math.min(rw / tw, rh / th, 1.45);
        s = Math.max(0.45, s);
        term.style.transform = "scale(" + s + ")";
    }

    function focusFirst() {
        var form = formEl();
        if (!form) {
            return;
        }
        var first = document.getElementById("username")
            || form.querySelector(".ibmi-fld-opt")
            || document.getElementById("ibmiCmd");
        if (first) {
            first.focus();
            try {
                first.setSelectionRange(0, 0);
            } catch (e) {}
        }
    }

    function applyPage(html, url) {
        var doc = new DOMParser().parseFromString(html, "text/html");
        if (!doc.getElementById("ibmiForm")) {
            window.location.href = url || window.location.href;
            return;
        }
        if (url) {
            history.replaceState({}, "", url);
        }
        document.title = doc.title;
        var nextBody = doc.body.className.replace("ibmi-cold", "ibmi-warm");
        document.body.className = nextBody;
        var room = document.querySelector(".ibmi-room");
        var nextRoom = doc.querySelector(".ibmi-room");
        if (room && nextRoom) {
            room.replaceWith(nextRoom);
        }
        submitting = false;
        bind();
        focusFirst();
        fitCrt();
        if (wantFs && !isFullscreen()) {
            enterFs().then(syncFsBtn);
        } else {
            syncFsBtn();
        }
    }

    function submitFkey(code) {
        var form = formEl();
        if (!form || submitting) {
            return;
        }
        submitting = true;
        var fkey = document.getElementById("ibmiFkey");
        if (fkey) {
            fkey.value = code || "";
        }
        var crt = document.getElementById("ibmiCrt");
        if (crt) {
            crt.classList.add("ibmi-retracing");
        }
        var action = form.getAttribute("action") || window.location.href;
        var fd = new FormData(form);
        window.setTimeout(function () {
            fetch(action, {
                method: "POST",
                body: fd,
                credentials: "same-origin",
                redirect: "follow",
                headers: { "X-Requested-With": "fetch" }
            }).then(function (res) {
                return res.text().then(function (html) {
                    return { res: res, html: html };
                });
            }).then(function (pack) {
                applyPage(pack.html, pack.res.url);
            }).catch(function () {
                form.submit();
            });
        }, 70);
    }

    function onKey(ev) {
        var k = ev.key;
        if (!k) {
            return;
        }
        if (ev.altKey && (k === "Enter" || k === "NumpadEnter")) {
            ev.preventDefault();
            toggleFs();
            return;
        }
        var map = {
            F1: "F1", F2: "F2", F3: "F3", F4: "F4", F5: "F5", F6: "F6",
            F7: "F7", F8: "F8", F9: "F9", F10: "F10", F11: "F11", F12: "F12",
            PageDown: "PAGEDOWN", PageUp: "PAGEUP"
        };
        if (map[k]) {
            ev.preventDefault();
            submitFkey(map[k]);
            return;
        }
        if (k === "Enter" && !ev.ctrlKey && !ev.altKey && !ev.shiftKey) {
            var tgt = ev.target;
            if (tgt && tgt.closest && (tgt.closest("#ibmiForm") || tgt.classList.contains("ibmi-fk-enter"))) {
                ev.preventDefault();
                submitFkey("");
            }
        }
    }

    function bind() {
        var form = formEl();
        if (!form) {
            return;
        }
        form.addEventListener("submit", function (ev) {
            ev.preventDefault();
            submitFkey("");
        });
        document.querySelectorAll(".ibmi-fk[data-fkey]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                submitFkey(btn.getAttribute("data-fkey") || "");
            });
        });
        var fs = document.getElementById("ibmiFs");
        if (fs) {
            fs.addEventListener("click", function (ev) {
                ev.preventDefault();
                toggleFs();
            });
        }
        syncFsBtn();
    }

    document.addEventListener("keydown", onKey);
    document.addEventListener("fullscreenchange", function () {
        syncFsBtn();
        fitCrt();
    });
    document.addEventListener("webkitfullscreenchange", function () {
        syncFsBtn();
        fitCrt();
    });
    window.addEventListener("resize", fitCrt);

    bind();
    focusFirst();
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(fitCrt);
    }
    fitCrt();
    window.setTimeout(fitCrt, 80);

    if (wantFs) {
        var once = function () {
            document.removeEventListener("pointerdown", once, true);
            enterFs().then(syncFsBtn);
        };
        document.addEventListener("pointerdown", once, true);
    }
})();
