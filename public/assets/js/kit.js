/**
 * Cabine d'essayage — habille le mannequin (maillot / short) et sauvegarde
 * le choix en base. Rendu initial déjà correct côté serveur (app/kit.php) ;
 * ce script gère seulement l'ouverture du dialog, le clic sur les vignettes
 * et l'enregistrement.
 */
(function () {
    var openBtn = document.getElementById('kitOpenBtn');
    var dialog = document.getElementById('kitDialog');
    if (!openBtn || !dialog) return;

    var closeBtn = document.getElementById('kitDialogClose');
    var backdrop = document.getElementById('kitDialogBackdrop');
    var torso = document.getElementById('kitTorsoGroup');
    var shortsShape = document.getElementById('kitShortsGroup');
    var collar = document.getElementById('kitCollarShape');
    var jerseyGroup = document.getElementById('kitJerseySwatches');
    var shortsGroup = document.getElementById('kitShortsSwatches');
    var propStage = document.getElementById('kitPropStage');
    var propGroup = document.getElementById('kitPropSwatches');
    var saveNote = document.getElementById('kitSaveNote');

    function activeIdOf(group) {
        var active = group && group.querySelector('.kit-swatch.is-active');
        return active ? (active.getAttribute('data-kit-id') || '') : '';
    }

    var state = {
        jersey: activeIdOf(jerseyGroup),
        shorts: activeIdOf(shortsGroup),
        prop: activeIdOf(propGroup)
    };

    var saveTimer = null;
    var lastFocused = null;

    function openDialog() {
        lastFocused = document.activeElement;
        dialog.hidden = false;
        document.body.classList.add('kit-dialog-open');
        if (closeBtn) closeBtn.focus();
        document.addEventListener('keydown', onKeydown);
    }

    function closeDialog() {
        dialog.hidden = true;
        document.body.classList.remove('kit-dialog-open');
        document.removeEventListener('keydown', onKeydown);
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            closeDialog();
        }
    }

    function setActive(group, btn) {
        var all = group.querySelectorAll('.kit-swatch');
        for (var i = 0; i < all.length; i++) {
            all[i].classList.remove('is-active');
            all[i].setAttribute('aria-pressed', 'false');
        }
        btn.classList.add('is-active');
        btn.setAttribute('aria-pressed', 'true');
    }

    function applyJerseyFill(btn) {
        var fill = btn.getAttribute('data-kit-fill');
        var hasTrim = btn.getAttribute('data-kit-trim') === '1';
        if (torso) torso.style.fill = fill;
        if (collar) {
            collar.style.display = hasTrim ? '' : 'none';
            if (hasTrim) {
                collar.style.fill = btn.getAttribute('data-kit-trim-color') || '';
            }
        }
    }

    function applyShortsFill(btn) {
        var fill = btn.getAttribute('data-kit-fill');
        if (shortsShape) shortsShape.style.fill = fill;
    }

    function applyProp(btn) {
        if (!propStage) return;
        var id = btn.getAttribute('data-kit-id') || '';
        var looks = propStage.querySelectorAll('.kit-prop-look');
        for (var i = 0; i < looks.length; i++) {
            looks[i].style.display = (looks[i].getAttribute('data-kit-prop-id') === id) ? '' : 'none';
        }
    }

    function scheduleSave() {
        if (saveNote) saveNote.textContent = saveNote.getAttribute('data-msg-saving') || '';
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 350);
    }

    function save() {
        var api = window.PRONO_API || 'api/';
        fetch(api + 'kit_save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                jersey: state.jersey || '',
                shorts: state.shorts || '',
                prop: state.prop || '',
                csrf_token: window.PRONO_CSRF || ''
            })
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (!saveNote) return;
            saveNote.textContent = (json && json.ok) ?
                (saveNote.getAttribute('data-msg-saved') || '') :
                (saveNote.getAttribute('data-msg-error') || '');
        }).catch(function () {
            if (saveNote) saveNote.textContent = saveNote.getAttribute('data-msg-error') || '';
        });
    }

    openBtn.addEventListener('click', openDialog);
    if (closeBtn) closeBtn.addEventListener('click', closeDialog);
    if (backdrop) backdrop.addEventListener('click', closeDialog);

    if (jerseyGroup) {
        jerseyGroup.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.kit-swatch') : null;
            if (!btn) return;
            state.jersey = btn.getAttribute('data-kit-id') || '';
            setActive(jerseyGroup, btn);
            applyJerseyFill(btn);
            scheduleSave();
        });
    }

    if (shortsGroup) {
        shortsGroup.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.kit-swatch') : null;
            if (!btn) return;
            state.shorts = btn.getAttribute('data-kit-id') || '';
            setActive(shortsGroup, btn);
            applyShortsFill(btn);
            scheduleSave();
        });
    }

    if (propGroup) {
        propGroup.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.kit-swatch') : null;
            if (!btn) return;
            state.prop = btn.getAttribute('data-kit-id') || '';
            setActive(propGroup, btn);
            applyProp(btn);
            scheduleSave();
        });
    }
})();
